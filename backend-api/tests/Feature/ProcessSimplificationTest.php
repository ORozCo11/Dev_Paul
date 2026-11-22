<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 19 Nov 2026 process simplification: On Hold work items, one stalled-ticket
 * reminder rule, schedule hold reasons, no past-dated schedules, and notices
 * cleaned up when a ticket is deleted.
 */
class ProcessSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private User $other;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $this->other = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'test']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Process Ambulance', 'plate_number' => 'PRC 1001', 'category_id' => $category->category_id,
            'brand' => 'Ford', 'model' => 'E-350', 'year_model' => 2020, 'capacity' => '4', 'vehicle_color' => 'White', 'current_location' => 'Depot',
        ]);
    }

    private function proposed(): int
    {
        Sanctum::actingAs($this->custodian, ['*']);

        return $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $this->vehicle->vehicle_id, 'ticket_description' => 'Needs work', 'priority' => 'High',
            'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Brake job']],
        ])->assertCreated()->json('ticket_id');
    }

    private function approved(): array
    {
        $id = $this->proposed();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = TicketSubIssue::where('ticket_id', $id)->first();

        return [$id, $sub, "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}"];
    }

    private function ageTicket(int $id, int $days): void
    {
        MaintenanceTicket::whereKey($id)->update(['updated_at' => now()->subDays($days)]);
        TicketSubIssue::where('ticket_id', $id)->update(['updated_at' => now()->subDays($days)]);
    }

    #[Test]
    public function a_mechanic_can_put_a_work_item_on_hold_and_resume_it(): void
    {
        [$id, $sub, $url] = $this->approved();
        Sanctum::actingAs($this->mechanic, ['*']);

        $this->putJson("{$url}/hold", [])->assertUnprocessable();
        $this->putJson("{$url}/hold", ['reason' => 'Other'])->assertUnprocessable();
        $this->putJson("{$url}/hold", ['reason' => 'Waiting for parts'])->assertOk();

        $item = $sub->fresh();
        $this->assertSame('Under Repair', $item->status);
        $this->assertSame($this->mechanic->id, (int) $item->assigned_mechanic_id);
        $this->assertSame('Waiting for parts', $item->on_hold_reason);
        $this->assertNotNull($item->on_hold_since);
        $this->assertTrue(Notification::where('user_id', $this->custodian->id)->where('type', 'work_order_on_hold')->exists());
        $this->assertTrue(Notification::where('user_id', $this->admin->id)->where('type', 'work_order_on_hold')->exists());

        $this->putJson("{$url}/resume")->assertOk();
        $this->assertNull($sub->fresh()->on_hold_since);
        $this->putJson("{$url}/resume")->assertUnprocessable();
    }

    #[Test]
    public function only_the_assigned_mechanic_can_hold_a_work_item(): void
    {
        [, , $url] = $this->approved();

        Sanctum::actingAs($this->other, ['*']);
        $this->putJson("{$url}/hold", ['reason' => 'Waiting for parts'])->assertForbidden();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("{$url}/hold", ['reason' => 'Waiting for parts'])->assertForbidden();
    }

    #[Test]
    public function a_hold_is_cleared_when_the_work_is_returned_to_the_admin(): void
    {
        [, $sub, $url] = $this->approved();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$url}/hold", ['reason' => 'Waiting for parts'])->assertOk();
        $this->putJson("{$url}/return-to-admin", ['reason' => 'Needs a welder, not a mechanic.'])->assertOk();

        $this->assertNull($sub->fresh()->on_hold_since);
    }

    #[Test]
    public function a_pending_ticket_idle_for_three_days_reminds_the_admin_once(): void
    {
        $id = $this->proposed();
        $this->ageTicket($id, 4);

        $this->artisan('tickets:remind-stalled')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->where('type', 'ticket_stalled')->count());

        $this->artisan('tickets:remind-stalled')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->where('type', 'ticket_stalled')->count());
    }

    #[Test]
    public function a_fresh_ticket_is_not_reminded(): void
    {
        $this->proposed();
        $this->artisan('tickets:remind-stalled')->assertSuccessful();
        $this->assertSame(0, Notification::where('type', 'ticket_stalled')->count());
    }

    #[Test]
    public function an_active_ticket_reminds_the_mechanic_unless_the_work_is_on_hold(): void
    {
        [$id, , $url] = $this->approved();

        $this->ageTicket($id, 4);
        $this->artisan('tickets:remind-stalled')->assertSuccessful();
        $this->assertTrue(Notification::where('user_id', $this->mechanic->id)->where('type', 'ticket_stalled')->exists());

        Notification::where('type', 'ticket_stalled')->delete();
        MaintenanceTicket::whereKey($id)->update(['stall_reminded_at' => null]);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$url}/hold", ['reason' => 'Waiting for parts'])->assertOk();
        $this->ageTicket($id, 4);
        $this->artisan('tickets:remind-stalled')->assertSuccessful();
        $this->assertSame(0, Notification::where('type', 'ticket_stalled')->count());
    }

    #[Test]
    public function a_schedule_in_the_past_is_refused_but_a_future_date_is_accepted(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $base = ['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Oil Change'];

        $this->postJson('/api/maintenance-schedules', $base + ['scheduled_date' => now('Asia/Manila')->subDays(3)->toDateString()])
            ->assertUnprocessable();
        $this->postJson('/api/maintenance-schedules', $base + ['scheduled_date' => now('Asia/Manila')->addDay()->toDateString()])
            ->assertCreated();
    }

    #[Test]
    public function a_due_schedule_that_cannot_become_a_ticket_shows_why(): void
    {
        $id = $this->proposed();
        MaintenanceTicket::whereKey($id)->update(['ticket_title' => $this->vehicle->vehicle_name . ' - Preventive Maintenance - Oil Change']);

        $schedule = VehicleMaintenanceSchedule::create([
            'vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Oil Change',
            'scheduled_date' => now('Asia/Manila')->subDay()->toDateString(), 'status' => 'Scheduled', 'created_by' => $this->custodian->id,
        ]);

        $this->artisan('schedules:convert-due-to-tickets')->assertSuccessful();
        $reason = (string) $schedule->fresh()->hold_reason;
        $this->assertNotSame('', $reason);
        $this->assertSame(1, Notification::where('type', 'schedule_on_hold')->count());

        $this->artisan('schedules:convert-due-to-tickets')->assertSuccessful();
        $this->assertSame(1, Notification::where('type', 'schedule_on_hold')->count());
    }

    #[Test]
    public function deleting_an_untouched_ticket_removes_its_notices(): void
    {
        $id = $this->proposed();
        $this->assertTrue(Notification::where('ticket_id', $id)->exists());

        Sanctum::actingAs($this->admin, ['*']);
        $this->deleteJson("/api/tickets/{$id}")->assertOk();

        $this->assertFalse(Notification::where('ticket_id', $id)->exists());
    }
}
