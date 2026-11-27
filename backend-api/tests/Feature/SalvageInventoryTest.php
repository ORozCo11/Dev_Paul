<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\SalvagePart;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Decommissioned vehicle -> the mechanic lists what is salvageable -> repairs draw from that stock. */
class SalvageInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private Vehicle $donor;
    private Vehicle $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Truck', 'description' => 't']);
        $make = fn (string $name, string $plate) => Vehicle::create([
            'vehicle_name' => $name, 'plate_number' => $plate, 'category_id' => $category->category_id, 'brand' => 'Isuzu', 'model' => 'N', 'year_model' => 2010,
            'capacity' => '2', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
        $this->donor = $make('Old Truck', 'OLD 1001');
        $this->target = $make('Working Truck', 'WRK 2002');
    }

    private function decommission(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/vehicles/{$this->donor->vehicle_id}/decommission", ['decommission_reason' => 'End of life'])->assertOk();
    }

    private function logPart(array $over = []): array
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        return $this->postJson('/api/salvage-parts', $over + ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'Alternator', 'category' => 'Electrical', 'part_condition' => 'Good', 'quantity' => 3, 'storage_location' => 'Shelf A'])->assertCreated()->json();
    }

    #[Test]
    public function the_mechanic_logs_parts_only_from_a_decommissioned_vehicle(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->postJson('/api/salvage-parts', ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'Alternator', 'part_condition' => 'Good', 'quantity' => 1])->assertUnprocessable();

        $this->decommission();
        $part = $this->logPart();
        $this->assertSame(3, $part['quantity_available']);
        $this->assertSame('Old Truck', $part['vehicle_name']);
    }

    #[Test]
    public function only_the_mechanic_edits_and_admin_and_custodian_can_read(): void
    {
        $this->decommission();
        $part = $this->logPart();
        foreach ([$this->admin, $this->custodian] as $reader) {
            Sanctum::actingAs($reader, ['*']);
            $this->getJson('/api/salvage-parts')->assertOk()->assertJsonCount(1);
            $this->getJson('/api/salvage-parts/queue')->assertOk();
            $this->postJson('/api/salvage-parts', ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'X', 'part_condition' => 'Good', 'quantity' => 1])->assertForbidden();
            $this->putJson("/api/salvage-parts/{$part['salvage_part_id']}", ['part_name' => 'X', 'part_condition' => 'Good', 'quantity' => 1])->assertForbidden();
            $this->deleteJson("/api/salvage-parts/{$part['salvage_part_id']}")->assertForbidden();
        }
    }

    #[Test]
    public function the_queue_shows_decommissioned_vehicles_and_finishing_needs_parts_or_a_confirmation(): void
    {
        $this->decommission();
        Sanctum::actingAs($this->mechanic, ['*']);
        $queue = $this->getJson('/api/salvage-parts/queue')->assertOk()->json();
        $this->assertCount(1, $queue);
        $this->assertSame(0, $queue[0]['parts_logged']);
        $this->assertNull($queue[0]['salvage_completed_at']);

        $this->putJson("/api/vehicles/{$this->donor->vehicle_id}/salvage-complete")->assertUnprocessable();
        $this->putJson("/api/vehicles/{$this->donor->vehicle_id}/salvage-complete", ['nothing_salvageable' => true])->assertOk();
        $this->assertNotNull($this->getJson('/api/salvage-parts/queue')->json('0.salvage_completed_at'));

        // Finished lists take no more parts until reopened.
        $this->postJson('/api/salvage-parts', ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'X', 'part_condition' => 'Good', 'quantity' => 1])->assertUnprocessable();
        $this->putJson("/api/vehicles/{$this->donor->vehicle_id}/salvage-reopen")->assertOk();
        $this->postJson('/api/salvage-parts', ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'X', 'part_condition' => 'Good', 'quantity' => 1])->assertCreated();
        $this->putJson("/api/vehicles/{$this->working()}/salvage-complete")->assertUnprocessable();   // not decommissioned
    }

    private function working(): int
    {
        return $this->target->vehicle_id;
    }

    #[Test]
    public function validation_rejects_bad_quantities_and_conditions(): void
    {
        $this->decommission();
        Sanctum::actingAs($this->mechanic, ['*']);
        $base = ['vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'Alternator', 'part_condition' => 'Good', 'quantity' => 1];
        $this->postJson('/api/salvage-parts', array_replace($base, ['quantity' => 0]))->assertUnprocessable();
        $this->postJson('/api/salvage-parts', array_replace($base, ['quantity' => 'many']))->assertUnprocessable();
        $this->postJson('/api/salvage-parts', array_replace($base, ['part_condition' => 'Broken']))->assertUnprocessable();
        $this->postJson('/api/salvage-parts', array_replace($base, ['part_name' => '']))->assertUnprocessable();
        $this->postJson('/api/salvage-parts', array_replace($base, ['part_name' => str_repeat('x', 300)]))->assertUnprocessable();
    }

    #[Test]
    public function a_repair_draws_from_the_stock_and_deleting_the_use_gives_it_back(): void
    {
        $this->decommission();
        $part = $this->logPart();
        [$ticketId, $subId] = $this->cannibalizedJob();
        $base = "/api/tickets/{$ticketId}/sub-issues/{$subId}";

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->postJson("{$base}/donor-parts", ['salvage_part_id' => $part['salvage_part_id'], 'quantity' => 5])->assertUnprocessable();   // only 3 in stock
        $used = $this->postJson("{$base}/donor-parts", ['salvage_part_id' => $part['salvage_part_id'], 'quantity' => 2])->assertCreated()->json();
        $this->assertSame('Alternator', $used['part_name']);
        $this->assertSame($this->donor->vehicle_id, $used['donor_vehicle_id']);
        $this->assertSame(1, SalvagePart::find($part['salvage_part_id'])->quantity_available);

        // Raising the quantity beyond what is left is refused; lowering it gives stock back.
        $this->putJson("{$base}/donor-parts/{$used['part_id']}", ['part_name' => 'Alternator', 'quantity' => 4])->assertUnprocessable();
        $this->putJson("{$base}/donor-parts/{$used['part_id']}", ['part_name' => 'Alternator', 'quantity' => 1])->assertOk();
        $this->assertSame(2, SalvagePart::find($part['salvage_part_id'])->quantity_available);

        // A used part cannot be removed from the inventory; its total cannot drop below what was used.
        $this->deleteJson("/api/salvage-parts/{$part['salvage_part_id']}")->assertUnprocessable();
        $this->putJson("/api/salvage-parts/{$part['salvage_part_id']}", ['part_name' => 'Alternator', 'part_condition' => 'Good', 'quantity' => 0])->assertUnprocessable();
        $this->putJson("/api/salvage-parts/{$part['salvage_part_id']}", ['part_name' => 'Alternator', 'part_condition' => 'Good', 'quantity' => 4])->assertOk();
        $this->assertSame(3, SalvagePart::find($part['salvage_part_id'])->quantity_available);

        $this->deleteJson("{$base}/donor-parts/{$used['part_id']}")->assertOk();
        $this->assertSame(4, SalvagePart::find($part['salvage_part_id'])->quantity_available);
        $this->deleteJson("/api/salvage-parts/{$part['salvage_part_id']}")->assertOk();
    }

    #[Test]
    public function a_part_from_another_barangay_is_not_visible_or_changeable(): void
    {
        $province = \App\Models\Province::create(['code' => 'QA', 'name' => 'QA Province']);
        $city = \App\Models\City::create(['code' => 'QA-CITY', 'name' => 'QA City', 'province_id' => $province->id]);
        $mine = \App\Models\Barangay::create(['name' => 'Mine', 'city_id' => $city->id, 'psgc_code' => '9999900001']);
        $theirs = \App\Models\Barangay::create(['name' => 'Theirs', 'city_id' => $city->id, 'psgc_code' => '9999900002']);
        $this->donor->update(['barangay_id' => $theirs->id, 'status' => 'Decommissioned']);
        $part = SalvagePart::create(['barangay_id' => $theirs->id, 'vehicle_id' => $this->donor->vehicle_id, 'part_name' => 'Alternator', 'part_condition' => 'Good', 'quantity' => 2, 'quantity_available' => 2]);

        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel', 'barangay_id' => $mine->id]), ['*']);
        $this->getJson('/api/salvage-parts')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/salvage-parts/queue')->assertOk()->assertJsonCount(0);
        $this->putJson("/api/salvage-parts/{$part->salvage_part_id}", ['part_name' => 'X', 'part_condition' => 'Good', 'quantity' => 1])->assertNotFound();
        $this->deleteJson("/api/salvage-parts/{$part->salvage_part_id}")->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel', 'barangay_id' => $theirs->id]), ['*']);
        $this->getJson('/api/salvage-parts')->assertOk()->assertJsonCount(1);
    }

    /** A ticket for the working truck, approved with the mechanic, switched to "uses parts from another vehicle". */
    private function cannibalizedJob(): array
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $ticketId = $this->postJson('/api/tickets/propose', ['vehicle_id' => $this->target->vehicle_id, 'ticket_description' => 'Charging fault.', 'priority' => 'High', 'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Charging']]])->assertCreated()->json('ticket_id');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$ticketId}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $subId = MaintenanceTicket::with('subIssues')->find($ticketId)->subIssues->first()->sub_issue_id;
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticketId}/sub-issues/{$subId}/donor", ['source_vehicle_id' => null, 'keep_cannibalized' => true])->assertOk();

        return [$ticketId, $subId];
    }
}
