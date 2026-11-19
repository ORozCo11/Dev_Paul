<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A schedule is only a plan. Its approval is the job's approval: on the due date it becomes an ACTIVE job, already
 * assigned to the approved mechanic (no second approval), the vehicle's condition is untouched, and the job is verified
 * and closed like any other (which completes the schedule and seeds the next one).
 */
class ScheduleAsJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $this->custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]);
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Plan Truck', 'plate_number' => 'PLN 1001', 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
    }

    private function dueSchedule(array $over = []): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create($over + [
            'vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now('Asia/Manila')->toDateString(),
            'status' => 'Scheduled', 'created_by' => $this->custodian->id, 'assigned_to' => $this->mechanic->id, 'recurrence_months' => 3,
        ]);
    }

    private function jobOf(VehicleMaintenanceSchedule $schedule): MaintenanceTicket
    {
        return MaintenanceTicket::with('subIssues')->findOrFail($schedule->fresh()->resulting_ticket_id);
    }

    #[Test]
    public function a_due_approved_schedule_becomes_an_active_job_for_its_mechanic_with_no_second_approval(): void
    {
        $schedule = $this->dueSchedule();
        $this->artisan('schedules:convert-due-to-tickets')->assertExitCode(0);

        $job = $this->jobOf($schedule);
        $this->assertSame('Active', $job->status);
        $this->assertSame($this->mechanic->id, (int) $job->assigned_mechanic_id);
        $line = $job->subIssues->first();
        $this->assertSame('Under Repair', $line->status);
        $this->assertSame($this->mechanic->id, (int) $line->assigned_mechanic_id);
        $this->assertSame($this->admin->id, (int) $line->mechanic_assigned_by);
        $this->assertNotEmpty($job->decision_history);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->mechanic->id, 'ticket_id' => $job->ticket_id, 'type' => 'mechanic_assigned']);

        // It shows up in the mechanic's own list at once.
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->assertContains($job->ticket_id, collect($this->getJson('/api/tickets')->assertOk()->json())->pluck('ticket_id')->all());
    }

    #[Test]
    public function a_routine_service_never_makes_the_vehicle_needs_repair(): void
    {
        $schedule = $this->dueSchedule();
        $this->artisan('schedules:convert-due-to-tickets')->assertExitCode(0);

        $vehicle = $this->vehicle->fresh();
        $this->assertSame('Under Maintenance', $vehicle->status);
        $this->assertSame('Good', $vehicle->condition);
        $this->assertSame(0, VehicleIssueReport::where('vehicle_id', $vehicle->vehicle_id)->count(), 'no hidden issue report');
        $this->assertNotNull($schedule->fresh()->resulting_ticket_id);
    }

    #[Test]
    public function a_schedule_without_a_mechanic_still_waits_for_the_admin_to_assign_one(): void
    {
        $schedule = $this->dueSchedule(['assigned_to' => null]);
        $this->artisan('schedules:convert-due-to-tickets')->assertExitCode(0);

        $job = $this->jobOf($schedule);
        $this->assertSame('Pending Approval', $job->status);
        $this->assertNull($job->assigned_mechanic_id);
        $this->assertSame('Available', $this->vehicle->fresh()->status);
    }

    #[Test]
    public function the_old_direct_complete_route_is_gone(): void
    {
        $schedule = $this->dueSchedule();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/complete", [])->assertNotFound();
    }

    #[Test]
    public function closing_the_job_completes_the_schedule_writes_the_record_and_plans_the_next_one(): void
    {
        $schedule = $this->dueSchedule();
        $this->artisan('schedules:convert-due-to-tickets')->assertExitCode(0);
        $job = $this->jobOf($schedule);
        $base = "/api/tickets/{$job->ticket_id}/sub-issues/{$job->subIssues->first()->sub_issue_id}";

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Changed the oil and filter; engine runs smoothly.'])->assertOk();
        $this->assertSame('For Verification', $job->fresh()->status);

        // One verification, by the Custodian, closes it: no Admin confirmation step.
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/tickets/{$job->ticket_id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();

        $this->assertSame('Closed', $job->fresh()->status);
        $this->assertSame('Completed', $schedule->fresh()->status);
        $this->assertSame(1, VehicleMaintenanceRecord::where('vehicle_id', $this->vehicle->vehicle_id)->where('progress_status', 'Completed')->count());
        $this->assertSame(0, VehicleMaintenanceRecord::where('progress_status', 'For Verification')->count());
        $next = VehicleMaintenanceSchedule::where('vehicle_id', $this->vehicle->vehicle_id)->where('status', '!=', 'Completed')->first();
        $this->assertNotNull($next, 'the recurring plan is seeded once the job closes');
        $this->assertSame('Available', $this->vehicle->fresh()->status);
        $this->assertSame('Good', $this->vehicle->fresh()->condition);
    }
}
