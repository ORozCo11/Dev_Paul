<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rule: a notice that links to a ticket must open for the person who got it. Someone who is told "you are assigned"
 * (or "you have work to do") but then sees "Ticket unavailable" has been failed by the process. After every step of
 * every ticket flow, each notice with a ticket link is opened as its recipient.
 */
class TicketNoticeLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $custodian2;
    private User $mechanic;
    private User $mechanic2;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->custodian2 = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $this->mechanic2 = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'test']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Notice Test Ambulance', 'plate_number' => 'NTC 1001', 'category_id' => $category->category_id,
            'brand' => 'Ford', 'model' => 'E-350', 'year_model' => 2020, 'capacity' => '4', 'vehicle_color' => 'White', 'current_location' => 'Depot',
        ]);
    }

    private int $plate = 2000;

    private function propose(string $mode): int
    {
        $vehicle = Vehicle::create([
            'vehicle_name' => 'Notice Test Van ' . (++$this->plate), 'plate_number' => 'NTC ' . $this->plate, 'category_id' => $this->vehicle->category_id,
            'brand' => 'Ford', 'model' => 'Transit', 'year_model' => 2020, 'capacity' => '4', 'vehicle_color' => 'White', 'current_location' => 'Depot',
        ]);
        Sanctum::actingAs($this->custodian, ['*']);
        $payload = ['vehicle_id' => $vehicle->vehicle_id, 'ticket_description' => 'Brakes grinding.', 'priority' => 'High',
            'entry_mode' => $mode, 'sub_issues' => [['title' => 'Brake service']]];
        if ($mode === 'external') {
            $payload += ['external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'Rebuild', 'external_vendor' => 'Toyota Mandaue'];
        }
        return $this->postJson('/api/tickets/propose', $payload)->assertCreated()->json('ticket_id');
    }

    /** Open every ticket-linked notice as the person it was sent to. */
    private function assertEveryNoticeOpens(string $step): void
    {
        $broken = [];
        foreach (Notification::whereNotNull('ticket_id')->get() as $notice) {
            Sanctum::actingAs(User::findOrFail($notice->user_id), ['*']);
            $status = $this->getJson("/api/tickets/{$notice->ticket_id}")->status();
            if ($status !== 200) {
                $broken[] = "{$notice->type} \"{$notice->title}\" to user {$notice->user_id} ({$status})";
            }
        }
        Sanctum::actingAs($this->admin, ['*']);
        $this->assertSame([], array_values(array_unique($broken)), "After: {$step}");
    }

    #[Test]
    public function in_house_flow_notices_always_open(): void
    {
        $id = $this->propose('in_house');
        $this->assertEveryNoticeOpens('proposed');

        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $this->assertEveryNoticeOpens('approved');

        $this->putJson("/api/tickets/{$id}/assign-mechanic", ['assigned_mechanic_id' => $this->mechanic2->id, 'reassign_reason' => 'Coverage'])->assertOk();
        $this->assertEveryNoticeOpens('mechanic reassigned');

        $this->putJson("/api/tickets/{$id}/reassign-custodian", ['assigned_custodian_id' => $this->custodian2->id, 'reassign_reason' => 'Coverage'])->assertOk();
        $this->assertEveryNoticeOpens('custodian reassigned');
    }

    #[Test]
    public function shop_flow_notices_always_open(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $this->assertEveryNoticeOpens('approved with a shop tester');

        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}/external-tester", ['tester_id' => $this->mechanic2->id])->assertOk();
        $this->assertEveryNoticeOpens('shop tester changed');
    }

    #[Test]
    public function decline_corrections_and_cancel_notices_always_open(): void
    {
        $id = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/request-corrections", ['correction_reason' => 'Add the odometer reading.'])->assertOk();
        $this->assertEveryNoticeOpens('corrections requested');

        $id2 = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id2}/decline", ['decline_reason' => 'Not needed.'])->assertOk();
        $this->assertEveryNoticeOpens('declined');

        $id3 = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id3}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $this->putJson("/api/tickets/{$id3}/cancel", ['closing_notes' => 'Vehicle sold.'])->assertOk();
        $this->assertEveryNoticeOpens('cancelled');
        $this->putJson("/api/tickets/{$id3}/uncancel", [])->assertOk();
        $this->assertEveryNoticeOpens('restored');
    }

    #[Test]
    public function full_shop_lifecycle_notices_always_open(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $base = "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}";

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("{$base}/external-sent", ['external_vendor' => 'Shop', 'external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'x'])->assertOk();
        $this->assertEveryNoticeOpens('sent to the shop');

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'Back.', 'external_actual_cost' => 1000])->assertOk();
        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Tested after the shop.'])->assertOk();
        $this->assertEveryNoticeOpens('returned and tested');

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/tickets/{$id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();
        $this->assertEveryNoticeOpens('verified and closed');
    }

    #[Test]
    public function in_house_repair_and_return_to_admin_notices_always_open(): void
    {
        $id = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}/return-to-admin", ['reason' => 'It needs a welder.'])->assertOk();
        $this->assertEveryNoticeOpens('returned to the Admin');

        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/assign-mechanic", ['assigned_mechanic_id' => $this->mechanic2->id, 'reassign_reason' => 'Welder'])->assertOk();
        Sanctum::actingAs($this->mechanic2, ['*']);
        $this->putJson("/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}/log-repairs", [
            'repair_logs' => 'Welded.', 'parts_used' => 'rod', 'repair_started_at' => now()->subDay()->toDateString(), 'repair_completed_at' => now()->toDateString(), 'maintenance_cost' => 100, 'tested_ok' => true,
        ])->assertOk();
        $this->putJson("/api/tickets/{$id}/submit-for-verification", [])->assertOk();
        $this->assertEveryNoticeOpens('submitted for verification');

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/tickets/{$id}/verify", ['verification_verdict' => 'Rejected', 'verification_notes' => 'Redo', 'failed_sub_issue_ids' => [$sub->sub_issue_id]])->assertOk();
        $this->assertEveryNoticeOpens('rejected for rework');
    }

    #[Test]
    public function a_reassignment_keeps_its_reason_for_the_new_owner_and_puts_it_in_the_notice(): void
    {
        $id = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();

        $this->putJson("/api/tickets/{$id}/assign-mechanic", ['assigned_mechanic_id' => $this->mechanic2->id, 'reassign_reason' => 'First mechanic is on leave.'])->assertOk();
        $item = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $this->assertSame('First mechanic is on leave.', $item->reassign_reason);
        $this->assertSame($this->admin->name, $item->reassigned_by_name);
        $this->assertNotNull($item->reassigned_at);
        $this->assertStringContainsString('First mechanic is on leave.', Notification::where('user_id', $this->mechanic2->id)->where('type', 'work_order_assigned')->latest('notification_id')->value('message'));

        $this->putJson("/api/tickets/{$id}/reassign-custodian", ['assigned_custodian_id' => $this->custodian2->id, 'reassign_reason' => 'Original Custodian is away.'])->assertOk();
        $ticket = MaintenanceTicket::find($id);
        $this->assertSame('Original Custodian is away.', $ticket->custodian_reassign_reason);
        $this->assertSame($this->admin->name, $ticket->custodian_reassigned_by_name);
        $this->assertStringContainsString('Original Custodian is away.', Notification::where('user_id', $this->custodian2->id)->where('type', 'ticket_reassigned')->value('message'));

        // the new Custodian and mechanic can read it on the ticket itself
        Sanctum::actingAs($this->custodian2, ['*']);
        $this->getJson("/api/tickets/{$id}")->assertOk()->assertJsonPath('custodian_reassign_reason', 'Original Custodian is away.')->assertJsonPath('sub_issues.0.reassign_reason', 'First mechanic is on leave.');
    }
}
