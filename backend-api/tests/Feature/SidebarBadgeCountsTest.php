<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SalvagePart;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SidebarBadgeCountsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_badge_counts_include_records_awaiting_verification_and_pending_users(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $admin->barangay_id, 'is_active' => false, 'approved_at' => null]);

        Sanctum::actingAs($admin, ['*']);
        $counts = $this->getJson('/api/dashboard')->assertOk()->json('badge_counts');

        $this->assertSame(1, $counts['users']);
        $this->assertSame(0, $counts['maintenance']);
        $this->assertArrayHasKey('schedules', $counts);
    }

    private function vehicle(string $plate): Vehicle
    {
        $category = VehicleCategory::first() ?? VehicleCategory::create(['category_name' => 'Truck', 'description' => 't']);

        return Vehicle::create(['vehicle_name' => $plate, 'plate_number' => $plate, 'category_id' => $category->category_id, 'brand' => 'x', 'model' => 'y', 'year_model' => 2020, 'capacity' => '1', 'vehicle_color' => 'w', 'current_location' => 'Depot']);
    }

    #[Test]
    public function the_admins_schedule_badge_counts_services_waiting_for_approval_and_nobody_counts_a_job_twice(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel'], 'barangay_id' => $admin->barangay_id]);
        $v = $this->vehicle('BDG 1');
        $make = fn (array $over) => VehicleMaintenanceSchedule::create($over + ['vehicle_id' => $v->vehicle_id, 'maintenance_type' => 'Oil Change', 'scheduled_date' => now()->addDays(4)->toDateString(), 'created_by' => $admin->id, 'assigned_to' => $mechanic->id]);
        $make(['status' => 'Pending Approval']);
        $make(['status' => 'Scheduled']);
        $make(['status' => 'Declined']);
        $make(['status' => 'Cancelled']);
        $ticket = \App\Models\MaintenanceTicket::create(['vehicle_id' => $v->vehicle_id, 'created_by' => $admin->id, 'ticket_title' => 'T', 'ticket_description' => 'x', 'priority' => 'Low', 'status' => 'Active', 'assigned_custodian_id' => $admin->id]);
        $make(['status' => 'Scheduled', 'resulting_ticket_id' => $ticket->ticket_id]);   // already a job

        Sanctum::actingAs($admin, ['*']);
        $this->assertSame(2, $this->getJson('/api/dashboard')->json('badge_counts.schedules'), 'one waiting for approval plus one plain scheduled service');
        Sanctum::actingAs($mechanic, ['*']);
        $this->assertSame(1, $this->getJson('/api/dashboard')->json('badge_counts.schedules'), 'the job is a work order, not a second count here');
    }

    #[Test]
    public function the_salvage_badge_counts_requests_for_the_admin_and_unlisted_vehicles_for_the_mechanic(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel'], 'barangay_id' => $admin->barangay_id]);
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $admin->barangay_id]);
        $this->vehicle('REQ 1')->update(['decommission_status' => 'Proposed']);
        $this->vehicle('REQ 2')->update(['decommission_status' => 'Proposed']);
        $needs = $this->vehicle('SAL 1'); $needs->update(['status' => 'Decommissioned']);
        $listed = $this->vehicle('SAL 2'); $listed->update(['status' => 'Decommissioned']);
        SalvagePart::create(['vehicle_id' => $listed->vehicle_id, 'part_name' => 'Pump', 'quantity' => 1, 'quantity_available' => 1]);
        $none = $this->vehicle('SAL 3'); $none->update(['status' => 'Decommissioned', 'salvage_completed_at' => now()]);

        Sanctum::actingAs($admin, ['*']);
        $this->assertSame(2, $this->getJson('/api/dashboard')->json('badge_counts.salvage'));
        Sanctum::actingAs($mechanic, ['*']);
        $this->assertSame(1, $this->getJson('/api/dashboard')->json('badge_counts.salvage'), 'only the decommissioned vehicle with no parts and no "no usable parts" mark');
        Sanctum::actingAs($custodian, ['*']);
        $this->assertSame(0, $this->getJson('/api/dashboard')->json('badge_counts.salvage'));
    }

    #[Test]
    public function the_bell_lists_every_unread_notice_even_beyond_the_latest_fifty(): void
    {
        $user = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        foreach (range(1, 60) as $i) {
            Notification::create(['user_id' => $user->id, 'barangay_id' => $user->barangay_id, 'title' => "N{$i}", 'message' => 'm', 'type' => 'x', 'read_at' => $i > 5 ? now() : null]);
        }
        Sanctum::actingAs($user, ['*']);
        $list = $this->getJson('/api/notifications')->assertOk()->json();
        $this->assertSame(5, collect($list)->whereNull('read_at')->count(), 'the five oldest are unread and still counted');
    }
}
