<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An external-shop repair has no in-house mechanic: the Admin only approves it
 * (without choosing one). The ticket's own Custodian then logs the dispatch and
 * the return under their own identity, tests the vehicle and closes the ticket
 * by verifying. Internal repairs are unchanged, and a mechanic can never verify
 * their own repair.
 */
class ExternalShopTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'test']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Shop Test Ambulance', 'plate_number' => 'EXT 1001', 'category_id' => $category->category_id,
            'brand' => 'Ford', 'model' => 'E-350', 'year_model' => 2020, 'capacity' => '4', 'vehicle_color' => 'White', 'current_location' => 'Depot',
        ]);
    }

    private function propose(string $mode): int
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $payload = ['vehicle_id' => $this->vehicle->vehicle_id, 'ticket_description' => 'Transmission slipping.', 'priority' => 'High',
            'entry_mode' => $mode, 'sub_issues' => [['title' => 'Transmission service']]];
        if ($mode === 'external') {
            $payload += ['external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'Rebuild transmission', 'external_vendor' => 'Toyota Mandaue'];
        }
        return $this->postJson('/api/tickets/propose', $payload)->assertCreated()->json('ticket_id');
    }

    #[Test]
    public function an_external_ticket_has_no_mechanic_but_needs_a_named_shop_tester(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        // The Admin must name who will test the vehicle when it returns from the shop.
        $this->putJson("/api/tickets/{$id}/approve", [])->assertUnprocessable();
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();

        $ticket = MaintenanceTicket::with('subIssues')->find($id);
        $this->assertSame('Active', $ticket->status);
        $this->assertNull($ticket->assigned_mechanic_id);
        $this->assertNull($ticket->subIssues->first()->assigned_mechanic_id);
        $this->assertSame($this->mechanic->id, (int) $ticket->subIssues->first()->external_tester_id);
        $this->assertSame('Under Repair', $ticket->subIssues->first()->status);
    }

    #[Test]
    public function an_internal_ticket_still_requires_a_mechanic(): void
    {
        $id = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", [])->assertUnprocessable();
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
    }

    #[Test]
    public function the_custodian_sends_it_out_the_mechanic_receives_and_tests_it_and_the_custodian_verifies_and_closes(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $base = "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}";

        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("{$base}/external-sent", [
            'external_vendor' => 'Toyota Mandaue', 'external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'Rebuild transmission',
            'external_shop_address' => 'Mandaue City', 'external_expected_return' => now()->addDays(5)->toDateString(),
            'external_sent_by' => 'Someone Else', // a forged identity must be ignored
        ])->assertOk();
        $sub->refresh();
        $this->assertSame($this->custodian->name, $sub->external_sent_by);
        $this->assertSame($this->custodian->id, (int) $sub->external_handled_by);

        // The Custodian cannot receive it: the Maintenance Personnel who will test it does.
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'x'])->assertForbidden();

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'Back, road-tested by shop.', 'external_actual_cost' => 12000])->assertOk();
        $this->assertSame($this->mechanic->id, (int) $sub->fresh()->external_received_by);
        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Shop job order attached; transmission rebuilt.'])->assertOk();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->assertSame('For Verification', MaintenanceTicket::find($id)->status);

        // The Admin never verifies; the Custodian only verifies the mechanic's tested repair and closes the ticket.
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/verify", [])->assertForbidden();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/tickets/{$id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();
        $this->assertSame('Closed', MaintenanceTicket::find($id)->status);
    }

    #[Test]
    public function only_the_custodian_sends_it_out_and_only_the_mechanic_receives_it_back(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $base = "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}";
        $sent = ['external_vendor' => 'Shop', 'external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'x'];

        // Sending out is the Custodian's: Admin and mechanic cannot, and the Admin cannot receive it either.
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("{$base}/external-sent", $sent)->assertForbidden();
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'x'])->assertForbidden();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/external-sent", $sent)->assertForbidden();

        // Another Custodian of the same barangay cannot send a ticket that is not theirs, nor receive anything.
        $otherCustodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $this->custodian->barangay_id]);
        Sanctum::actingAs($otherCustodian, ['*']);
        $this->putJson("{$base}/external-sent", $sent)->assertForbidden();
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'x'])->assertForbidden();

        // The ticket's own Custodian sends it out but cannot receive it back.
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("{$base}/external-sent", $sent)->assertOk();
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'x'])->assertForbidden();

        // A different Maintenance Personnel than the named shop tester cannot receive it; the tester can.
        $otherMechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel'], 'barangay_id' => $this->custodian->barangay_id]);
        Sanctum::actingAs($otherMechanic, ['*']);
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'x'])->assertForbidden();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'Back and road-tested.'])->assertOk();
    }

    #[Test]
    public function a_mechanic_who_repaired_the_work_cannot_verify_it(): void
    {
        $id = $this->propose('in_house');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Replaced the clutch.'])->assertOk();
        $this->putJson("/api/tickets/{$id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertForbidden();
        $this->assertSame('For Verification', MaintenanceTicket::find($id)->status);
    }
}
