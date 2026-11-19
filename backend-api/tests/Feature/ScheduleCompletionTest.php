<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gap 2 — recurring preventive maintenance + closing the loop between a
 * schedule and the record that proves it was done.
 */
class ScheduleCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        // schedule.create/edit are Custodian-only now (config/permissions.php,
        // streamlined-workflow spec 2026-10-12).
        $this->custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]);
    }

    private function vehicle(): Vehicle
    {
        $category = VehicleCategory::create(['category_name' => 'Ambulance ' . uniqid(), 'description' => 'x']);
        return Vehicle::create([
            'vehicle_name' => 'Test Ambulance',
            'plate_number' => 'TST ' . random_int(1000, 9999),
            'category_id' => $category->category_id,
            'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022,
            'capacity' => '12 pax', 'vehicle_color' => 'White', 'current_location' => 'Main Depot',
        ]);
    }

    private function schedule(Vehicle $vehicle, array $overrides = []): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create(array_merge([
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => 'Oil Change',
            'scheduled_date' => now()->toDateString(),
            'status' => 'Scheduled',
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    #[Test]
    public function a_new_schedule_always_starts_pending_approval_even_if_a_different_status_is_submitted(): void
    {
        $vehicle = $this->vehicle();
        // This test only cares about the status-ignored behavior, not role
        // gating — schedule.create is Custodian-only now.

        Sanctum::actingAs($this->custodian, ['*']);
        $response = $this->postJson('/api/maintenance-schedules', [
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => 'Oil Change',
            'scheduled_date' => now()->addDay()->toDateString(),
            'status' => 'Completed', // must be silently ignored — not a valid create input
        ])->assertCreated();

        // Parks at Pending Approval until an Admin reviews it — see
        // approveSchedule()/declineSchedule() in FleetController.
        $this->assertSame('Pending Approval', $response->json('status'));
    }

    #[Test]
    public function marking_a_schedule_completed_through_the_raw_update_endpoint_is_rejected(): void
    {
        // Completing must go through completeSchedule() (creates the proof-of-
        // work record and, if recurring, the next occurrence). The plain edit
        // form must not be able to silently skip both.
        $vehicle = $this->vehicle();
        $schedule = $this->schedule($vehicle, ['created_by' => $this->custodian->id]);

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", [
            'status' => 'Completed',
        ])->assertUnprocessable();

        $this->assertSame('Scheduled', $schedule->fresh()->status);
    }

    #[Test]
    public function a_schedule_can_still_be_cancelled_through_the_update_endpoint(): void
    {
        $vehicle = $this->vehicle();
        $schedule = $this->schedule($vehicle, ['created_by' => $this->custodian->id]);

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", [
            'status' => 'Cancelled',
        ])->assertOk();

        $this->assertSame('Cancelled', $schedule->fresh()->status);
    }
}
