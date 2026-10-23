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
 * An external-shop repair has no in-house mechanic: the Admin approves it
 * without choosing one, then records the dispatch and the return under their
 * own identity. Internal repairs are unchanged, and whoever handled the
 * vehicle can never verify the work.
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
    public function an_external_ticket_is_approved_without_a_mechanic(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", [])->assertOk();

        $ticket = MaintenanceTicket::with('subIssues')->find($id);
        $this->assertSame('Active', $ticket->status);
        $this->assertNull($ticket->assigned_mechanic_id);
        $this->assertNull($ticket->subIssues->first()->assigned_mechanic_id);
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
    public function admin_records_dispatch_and_return_under_their_own_identity_then_the_custodian_verifies(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", [])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $base = "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}";

        $this->putJson("{$base}/external-sent", [
            'external_vendor' => 'Toyota Mandaue', 'external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'Rebuild transmission',
            'external_shop_address' => 'Mandaue City', 'external_expected_return' => now()->addDays(5)->toDateString(),
            'external_sent_by' => 'Someone Else', // a forged identity must be ignored
        ])->assertOk();
        $sub->refresh();
        $this->assertSame($this->admin->name, $sub->external_sent_by);
        $this->assertSame($this->admin->id, (int) $sub->external_handled_by);

        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'Back, road-tested by shop.', 'external_actual_cost' => 12000])->assertOk();
        $this->assertSame($this->admin->id, (int) $sub->fresh()->external_received_by);

        $this->putJson("{$base}/log-repairs", ['repair_logs' => 'Shop job order attached; transmission rebuilt.'])->assertOk();
        $this->assertSame('For Verification', MaintenanceTicket::find($id)->status);

        // Only the assigned Custodian verifies; Admin never does.
        $this->putJson("/api/tickets/{$id}/verify", [])->assertForbidden();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/tickets/{$id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();
        $this->assertSame('Closed', MaintenanceTicket::find($id)->status);
    }

    #[Test]
    public function a_dual_role_admin_who_handled_the_vehicle_cannot_verify_it(): void
    {
        $both = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin', 'Custodian']]);
        $id = $this->propose('external');
        MaintenanceTicket::find($id)->update(['assigned_custodian_id' => $both->id]);
        Sanctum::actingAs($both, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", [])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();
        $base = "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}";
        $this->putJson("{$base}/external-sent", ['external_vendor' => 'Shop', 'external_reason' => 'Parts or service only available from an authorized shop', 'external_work_scope' => 'x'])->assertOk();
        $this->putJson("{$base}/external-returned", ['external_return_notes' => 'ok'])->assertOk();
        $this->putJson("{$base}/log-repairs", ['repair_logs' => 'done'])->assertOk();

        $this->putJson("/api/tickets/{$id}/verify", [])->assertForbidden();
        $this->assertSame('For Verification', MaintenanceTicket::find($id)->status);
    }

    #[Test]
    public function only_admin_may_record_an_external_work_order_without_a_mechanic(): void
    {
        $id = $this->propose('external');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", [])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();

        foreach ([$this->custodian, $this->mechanic] as $user) {
            Sanctum::actingAs($user, ['*']);
            $this->putJson("/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}/external-sent", [
                'external_vendor' => 'Shop', 'external_reason' => 'x', 'external_work_scope' => 'x',
            ])->assertForbidden();
        }
    }
}
