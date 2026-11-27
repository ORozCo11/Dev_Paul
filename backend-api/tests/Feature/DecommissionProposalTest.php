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

/** The Custodian proposes decommissioning a vehicle; the Admin approves (it joins the salvage queue) or declines. */
class DecommissionProposalTest extends TestCase
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
        $category = VehicleCategory::create(['category_name' => 'Truck', 'description' => 't']);
        $this->vehicle = Vehicle::create(['vehicle_name' => 'Old Truck', 'plate_number' => 'OLD 1', 'category_id' => $category->category_id, 'brand' => 'Isuzu', 'model' => 'N', 'year_model' => 2005, 'capacity' => '2', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
    }

    private function propose(string $reason = 'Engine is beyond repair and costs more than the truck is worth.')
    {
        Sanctum::actingAs($this->custodian, ['*']);
        return $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal", ['reason' => $reason]);
    }

    #[Test]
    public function the_custodian_proposes_and_the_vehicle_stays_in_service_until_the_admin_decides(): void
    {
        $res = $this->propose()->assertOk()->json();
        $this->assertSame('Proposed', $res['decommission_status']);
        $this->assertSame($this->custodian->name, $res['decommission_proposed_by_name']);
        $this->assertNotSame('Decommissioned', $this->vehicle->fresh()->status);
        $this->assertTrue(Notification::where('user_id', $this->admin->id)->where('type', 'decommission_proposed')->exists());
        // not in the salvage queue yet
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->assertCount(0, $this->getJson('/api/salvage-parts/queue')->json());
    }

    #[Test]
    public function only_the_custodian_proposes_and_a_reason_is_required(): void
    {
        foreach ([$this->mechanic, $this->admin] as $other) {
            Sanctum::actingAs($other, ['*']);
            $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal", ['reason' => 'A good enough reason'])->assertForbidden();
        }
        $this->propose('abc')->assertUnprocessable();
        $this->propose('')->assertUnprocessable();
    }

    #[Test]
    public function a_second_proposal_an_open_ticket_or_a_decommissioned_vehicle_blocks_it(): void
    {
        $this->propose()->assertOk();
        $this->propose('Another reason here')->assertUnprocessable();

        $this->vehicle->update(['decommission_status' => null]);
        MaintenanceTicket::create(['vehicle_id' => $this->vehicle->vehicle_id, 'created_by' => $this->custodian->id, 'ticket_title' => 'T', 'ticket_description' => 'x', 'priority' => 'Low', 'status' => 'Active', 'assigned_custodian_id' => $this->custodian->id]);
        $this->propose()->assertUnprocessable();

        MaintenanceTicket::query()->update(['status' => 'Closed']);
        $this->vehicle->update(['status' => 'Decommissioned']);
        $this->propose()->assertUnprocessable();
    }

    #[Test]
    public function approving_decommissions_it_tells_the_custodian_and_opens_the_salvage_queue(): void
    {
        $this->propose()->assertOk();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission", ['decommission_reason' => 'Engine is beyond repair'])->assertOk();

        $v = $this->vehicle->fresh();
        $this->assertSame('Decommissioned', $v->status);
        $this->assertNull($v->decommission_status);
        $this->assertTrue(Notification::where('user_id', $this->custodian->id)->where('type', 'decommission_approved')->exists());
        $this->assertTrue(Notification::where('user_id', $this->mechanic->id)->where('type', 'salvage_ready')->exists());
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->assertCount(1, $this->getJson('/api/salvage-parts/queue')->json());
    }

    #[Test]
    public function declining_needs_a_reason_keeps_the_vehicle_and_tells_the_custodian_why(): void
    {
        $this->propose()->assertOk();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal/decline", ['decline_reason' => ''])->assertUnprocessable();
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal/decline", ['decline_reason' => 'Still has two good years in it.'])->assertOk();

        $v = $this->vehicle->fresh();
        $this->assertSame('Declined', $v->decommission_status);
        $this->assertSame('Still has two good years in it.', $v->decommission_decline_reason);
        $this->assertNotSame('Decommissioned', $v->status);
        $note = Notification::where('user_id', $this->custodian->id)->where('type', 'decommission_declined')->first();
        $this->assertStringContainsString('Still has two good years', $note->message);

        // the Custodian can propose again later
        $this->propose('Now it will not start at all.')->assertOk();
        $this->assertSame('Proposed', $this->vehicle->fresh()->decommission_status);
        $this->assertNull($this->vehicle->fresh()->decommission_decline_reason);
    }

    #[Test]
    public function the_custodian_can_withdraw_and_only_the_admin_can_decline(): void
    {
        $this->propose()->assertOk();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal/decline", ['decline_reason' => 'I changed my mind'])->assertForbidden();
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal/withdraw")->assertOk();
        $this->assertNull($this->vehicle->fresh()->decommission_status);
        $this->putJson("/api/vehicles/{$this->vehicle->vehicle_id}/decommission-proposal/withdraw")->assertUnprocessable();
    }
}
