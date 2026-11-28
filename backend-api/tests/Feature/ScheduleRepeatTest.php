<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A repeating service can have a set number of services, and the series can be stopped at any point. */
class ScheduleRepeatTest extends TestCase
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
        $category = VehicleCategory::create(['category_name' => 'Truck', 'description' => 't']);
        $this->vehicle = Vehicle::create(['vehicle_name' => 'Truck', 'plate_number' => 'RPT 1', 'category_id' => $category->category_id, 'brand' => 'x', 'model' => 'y', 'year_model' => 2020, 'capacity' => '1', 'vehicle_color' => 'w', 'current_location' => 'Depot']);
    }

    private function schedule(array $over = []): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create($over + [
            'vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now()->addDays(3)->toDateString(),
            'status' => 'Scheduled', 'created_by' => $this->custodian->id, 'assigned_to' => $this->mechanic->id, 'recurrence_months' => 3,
        ]);
    }

    private function body(array $over = []): array
    {
        return $over + ['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now()->addDays(3)->toDateString()];
    }

    #[Test]
    public function a_series_of_three_stops_after_the_third_service(): void
    {
        $first = $this->schedule(['recurrence_total' => 3]);
        $second = $first->seedNextRecurrence(now()->toDateString(), $this->custodian->id);
        $this->assertSame([2, 3], [$second->recurrence_number, $second->recurrence_total]);
        $third = $second->seedNextRecurrence(now()->addMonths(3)->toDateString(), $this->custodian->id);
        $this->assertSame(3, $third->recurrence_number);
        $this->assertNull($third->seedNextRecurrence(now()->addMonths(6)->toDateString(), $this->custodian->id), 'the third was the last');
        $this->assertSame(3, VehicleMaintenanceSchedule::where('vehicle_id', $this->vehicle->vehicle_id)->count());
    }

    #[Test]
    public function without_a_count_it_repeats_until_stopped(): void
    {
        $first = $this->schedule();
        $next = $first->seedNextRecurrence(now()->toDateString(), $this->custodian->id);
        $this->assertNull($next->recurrence_total);
        $this->assertNotNull($next->seedNextRecurrence(now()->addMonths(3)->toDateString(), $this->custodian->id));
    }

    #[Test]
    public function the_count_needs_a_repeat_and_at_least_two_services(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson('/api/maintenance-schedules', $this->body(['recurrence_total' => 3]))->assertUnprocessable();
        $this->postJson('/api/maintenance-schedules', $this->body(['recurrence_months' => 3, 'recurrence_total' => 1]))->assertUnprocessable();
        $this->postJson('/api/maintenance-schedules', $this->body(['recurrence_months' => 3, 'recurrence_total' => 61]))->assertUnprocessable();
        $res = $this->postJson('/api/maintenance-schedules', $this->body(['recurrence_months' => 3, 'recurrence_total' => 4]))->assertCreated()->json();
        $this->assertSame([3, 4, 1], [$res['recurrence_months'], $res['recurrence_total'], $res['recurrence_number']]);
    }

    #[Test]
    public function stopping_the_repeat_ends_the_series_for_the_custodian_and_the_admin(): void
    {
        foreach ([$this->custodian, $this->admin] as $who) {
            $s = $this->schedule(['recurrence_total' => 3]);
            Sanctum::actingAs($who, ['*']);
            $res = $this->putJson("/api/maintenance-schedules/{$s->schedule_id}/stop-repeat")->assertOk()->json();
            $this->assertNull($res['recurrence_months']);
            $this->assertNull($res['recurrence_total']);
            $this->assertNull($s->fresh()->seedNextRecurrence(now()->toDateString(), $this->custodian->id));
            $this->assertSame('Scheduled', $s->fresh()->status, 'the service itself is untouched');
        }
    }

    #[Test]
    public function stopping_works_even_while_the_service_is_a_job_but_not_for_the_wrong_people_or_cases(): void
    {
        $ticket = \App\Models\MaintenanceTicket::create(['vehicle_id' => $this->vehicle->vehicle_id, 'created_by' => $this->custodian->id, 'ticket_title' => 'T', 'ticket_description' => 'x', 'priority' => 'Low', 'status' => 'Active', 'assigned_custodian_id' => $this->custodian->id]);
        $job = $this->schedule(['resulting_ticket_id' => $ticket->ticket_id]);

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/maintenance-schedules/{$job->schedule_id}/stop-repeat")->assertForbidden();

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$job->schedule_id}/stop-repeat")->assertOk();
        $this->putJson("/api/maintenance-schedules/{$job->schedule_id}/stop-repeat")->assertUnprocessable();   // no longer repeats

        $done = $this->schedule(['status' => 'Completed']);
        $this->putJson("/api/maintenance-schedules/{$done->schedule_id}/stop-repeat")->assertUnprocessable();
    }
}
