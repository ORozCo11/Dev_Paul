<?php

namespace Tests\Feature;

use App\Models\{User, Vehicle, VehicleCategory, VehicleMaintenanceSchedule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Fixes from the 21 November 2026 QA pass. */
class QaFixesTest extends TestCase
{
    use RefreshDatabase;

    private function fleet(): array
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]);
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $vehicle = Vehicle::create([
            'vehicle_name' => 'QA Truck', 'plate_number' => 'QAT 1001', 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
        return [$admin, $custodian, $mechanic, $vehicle];
    }

    #[Test]
    public function a_schedule_cannot_be_planned_for_a_past_date(): void
    {
        [, $custodian, , $vehicle] = $this->fleet();
        Sanctum::actingAs($custodian, ['*']);
        $past = now('Asia/Manila')->subDays(3)->toDateString();
        $this->postJson('/api/maintenance-schedules', ['vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => $past])->assertUnprocessable();
        $today = now('Asia/Manila')->toDateString();
        $this->postJson('/api/maintenance-schedules', ['vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => $today])->assertCreated();
    }

    #[Test]
    public function a_schedule_cannot_be_approved_without_a_mechanic(): void
    {
        [$admin, $custodian, $mechanic, $vehicle] = $this->fleet();
        $make = fn (?int $mechanicId) => VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now('Asia/Manila')->addDay()->toDateString(),
            'status' => 'Pending Approval', 'created_by' => $custodian->id, 'assigned_to' => $mechanicId,
        ]);
        Sanctum::actingAs($admin, ['*']);
        $this->putJson('/api/maintenance-schedules/'.$make(null)->schedule_id.'/approve')->assertUnprocessable();
        $this->putJson('/api/maintenance-schedules/'.$make($mechanic->id)->schedule_id.'/approve')->assertOk();
    }

    #[Test]
    public function a_ticket_description_has_a_maximum_length(): void
    {
        [, $custodian, , $vehicle] = $this->fleet();
        Sanctum::actingAs($custodian, ['*']);
        $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $vehicle->vehicle_id, 'ticket_description' => str_repeat('x', 2001), 'priority' => 'Medium',
            'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Item']],
        ])->assertUnprocessable()->assertJsonValidationErrors('ticket_description');
    }

    #[Test]
    public function the_account_switcher_is_not_listed_for_the_super_admin_and_registered_barangays_need_no_city(): void
    {
        User::factory()->create(['role' => 'Impersonator', 'roles' => ['Impersonator'], 'name' => 'Zed Switcher']);
        $super = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'name' => 'Boss']);
        Sanctum::actingAs($super, ['*']);
        $names = collect($this->getJson('/api/superadmin/users')->assertOk()->json())->pluck('name')->all();
        $this->assertContains('Boss', $names);
        $this->assertNotContains('Zed Switcher', $names);

        $this->getJson('/api/barangays/registered')->assertOk();
    }

    #[Test]
    public function an_admin_can_choose_the_mechanic_while_approving_a_proposal_that_had_none(): void
    {
        [$admin, $custodian, $mechanic, $vehicle] = $this->fleet();
        $schedule = VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now('Asia/Manila')->addDay()->toDateString(),
            'status' => 'Pending Approval', 'created_by' => $custodian->id,
        ]);
        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/approve", ['assigned_to' => $custodian->id])->assertUnprocessable();
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/approve", ['assigned_to' => $mechanic->id])->assertOk();
        $this->assertSame($mechanic->id, (int) $schedule->fresh()->assigned_to);
        $this->assertSame('Scheduled', $schedule->fresh()->status);
    }

    #[Test]
    public function an_existing_schedule_cannot_be_moved_to_a_past_date_but_an_overdue_one_can_still_be_edited(): void
    {
        [, $custodian, , $vehicle] = $this->fleet();
        $schedule = VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now('Asia/Manila')->subDays(5)->toDateString(),
            'status' => 'Pending Approval', 'created_by' => $custodian->id,
        ]);
        Sanctum::actingAs($custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['scheduled_date' => now('Asia/Manila')->subDays(2)->toDateString()])->assertUnprocessable();
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'still editable', 'scheduled_date' => substr((string) $schedule->scheduled_date, 0, 10)])->assertOk();
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['scheduled_date' => now('Asia/Manila')->addDays(2)->toDateString()])->assertOk();
    }

    #[Test]
    public function rate_limits_do_not_share_one_counter_across_different_routes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/address-suggestions?q=a');
        }
        // Ten Support-form sends must still be allowed after five address lookups.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/concern-reports', [])->assertUnprocessable();
        }
        $this->postJson('/api/concern-reports', [])->assertStatus(429);
    }

    #[Test]
    public function reports_refuse_an_unknown_type_and_a_range_that_ends_before_it_starts(): void
    {
        [$admin] = $this->fleet();
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/reports?report_type=Nonsense')->assertUnprocessable();
        $this->getJson('/api/reports?report_type=Vehicle%20Inventory%20Report')->assertOk();
        $this->getJson('/api/reports?report_type=Vehicle%20Issue%20Report&from=2026-11-20&to=2026-11-01')->assertUnprocessable();
        $this->getJson('/api/reports?report_type=Vehicle%20Issue%20Report&from=2026-11-01&to=2026-11-20')->assertOk();
    }
}
