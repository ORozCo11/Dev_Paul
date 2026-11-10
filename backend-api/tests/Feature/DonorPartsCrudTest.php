<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A cannibalized repair uses one donor vehicle and any number of donor parts,
 * each persisted on its own: created, read back after reload, edited without
 * duplicating, deleted while the work order is still being repaired, and
 * locked once the repair is logged. Submitting still goes straight to the
 * Custodian, files ONE issue report on the donor listing every part.
 */
class DonorPartsCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private Vehicle $vehicle;
    private Vehicle $donor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $cat = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $make = fn ($n, $p) => Vehicle::create(['vehicle_name' => $n, 'plate_number' => $p, 'category_id' => $cat->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
        $this->vehicle = $make('Repair Truck', 'REP 1001');
        $this->donor = $make('Donor Truck', 'DON 2002');
        // Parts are only taken from vehicles that are no longer in use.
        $this->donor->update(['status' => 'Decommissioned']);
    }

    /** An Active in-house ticket with one sub-issue, returns [ticketId, subIssueId, base url]. */
    private function ticket(): array
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $id = $this->postJson('/api/tickets/propose', ['vehicle_id' => $this->vehicle->vehicle_id, 'ticket_description' => 'Brakes', 'priority' => 'High',
            'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Brake job']]])->assertCreated()->json('ticket_id');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $sub = MaintenanceTicket::with('subIssues')->find($id)->subIssues->first();

        return [$id, $sub->sub_issue_id, "/api/tickets/{$id}/sub-issues/{$sub->sub_issue_id}"];
    }

    private function part(array $over = []): array
    {
        return array_merge(['part_name' => 'Brake caliper', 'quantity' => 2, 'part_condition' => 'Good', 'reason' => 'No stock locally.'], $over);
    }

    #[Test]
    public function parts_need_a_donor_vehicle_first_and_the_donor_must_be_eligible(): void
    {
        [, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);

        $this->postJson("{$base}/donor-parts", $this->part())->assertUnprocessable();            // no donor yet
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->vehicle->vehicle_id])->assertUnprocessable(); // itself
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk()->assertJsonPath('repair_type', 'cannibalized');
    }

    #[Test]
    public function a_part_that_is_still_missing_from_the_donor_cannot_be_taken_again_until_restored(): void
    {
        [, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        $first = $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Transmission', 'quantity' => 1]))->assertCreated()->json('part_id');

        // Same part again (any capitalisation) is refused while the first is still missing.
        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'transmission', 'quantity' => 1]))->assertUnprocessable();
        // A different part is fine.
        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Radiator', 'quantity' => 1]))->assertCreated();

        // Once it has been put back, it can be taken again.
        \Illuminate\Support\Facades\DB::table('ticket_sub_issue_donor_parts')->where('part_id', $first)->update(['restored_at' => now()]);
        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Transmission', 'quantity' => 1]))->assertCreated();
    }

    #[Test]
    public function multiple_parts_persist_edit_in_place_and_delete(): void
    {
        [$tid, $sid, $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        $a = $this->postJson("{$base}/donor-parts", $this->part())->assertCreated()->json('part_id');
        $b = $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Hose', 'quantity' => 1, 'installed_at' => now()->toDateString()]))->assertCreated()->json('part_id');

        // read back as a fresh request: both are on the ticket
        $parts = $this->getJson("/api/tickets/{$tid}")->assertOk()->json('sub_issues.0.donor_parts');
        $this->assertSame(['Brake caliper', 'Hose'], array_column($parts, 'part_name'));

        // edit updates the same row
        $this->putJson("{$base}/donor-parts/{$a}", $this->part(['quantity' => 4]))->assertOk()->assertJsonPath('quantity', 4);
        $this->assertCount(2, $this->getJson("/api/tickets/{$tid}")->json('sub_issues.0.donor_parts'));

        // delete one
        $this->deleteJson("{$base}/donor-parts/{$b}")->assertOk();
        $this->assertCount(1, $this->getJson("/api/tickets/{$tid}")->json('sub_issues.0.donor_parts'));

        // cannot drop back to in-house while a part remains
        $this->putJson("{$base}/donor", ['source_vehicle_id' => null])->assertUnprocessable();
        $this->deleteJson("{$base}/donor-parts/{$a}")->assertOk();
        $this->putJson("{$base}/donor", ['source_vehicle_id' => null])->assertOk()->assertJsonPath('repair_type', 'in_house');
    }

    #[Test]
    public function invalid_parts_are_rejected(): void
    {
        [, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => '']))->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part(['quantity' => 0]))->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part(['quantity' => -2]))->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part(['quantity' => 1.5]))->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part(['installed_at' => now()->addDay()->toDateString()]))->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part(['installed_at' => 'not-a-date']))->assertUnprocessable();
    }

    #[Test]
    public function an_empty_donor_can_be_removed_and_replaced_without_leaving_cannibalized_repair(): void
    {
        [$tid, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();
        $this->putJson("{$base}/donor", ['source_vehicle_id' => null, 'keep_cannibalized' => true])
            ->assertOk()->assertJsonPath('source_vehicle_id', null)->assertJsonPath('repair_type', 'cannibalized');
        $this->postJson("{$base}/donor-parts", $this->part())->assertUnprocessable();
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();
        $this->postJson("{$base}/donor-parts", $this->part())->assertCreated();
        $this->putJson("{$base}/donor", ['source_vehicle_id' => null, 'keep_cannibalized' => true])->assertUnprocessable();
        $sub = $this->getJson("/api/tickets/{$tid}")->assertOk()->json('sub_issues.0');
        $this->assertSame($this->donor->vehicle_id, $sub['source_vehicle_id']);
        $this->assertCount(1, $sub['donor_parts']);
    }

    #[Test]
    public function replacing_an_empty_primary_donor_keeps_the_other_donors_parts(): void
    {
        [$tid, , $base] = $this->ticket();
        $second = $this->donor->replicate();
        $second->vehicle_name = 'Second Donor';
        $second->plate_number = 'DON 3003';
        $second->save();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();
        $this->postJson("{$base}/donor-parts", $this->part(['donor_vehicle_id' => $second->vehicle_id]))->assertCreated();
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $second->vehicle_id])->assertOk();
        $sub = $this->getJson("/api/tickets/{$tid}")->assertOk()->json('sub_issues.0');
        $this->assertSame($second->vehicle_id, $sub['source_vehicle_id']);
        $this->assertCount(1, $sub['donor_parts']);
        $this->assertSame($second->vehicle_id, $sub['donor_parts'][0]['donor_vehicle_id']);
    }

    #[Test]
    public function only_the_assigned_mechanic_can_change_parts_and_other_barangays_cannot_reach_them(): void
    {
        [, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();
        $partId = $this->postJson("{$base}/donor-parts", $this->part())->assertCreated()->json('part_id');

        $otherMechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        foreach ([$this->admin, $this->custodian, $otherMechanic] as $user) {
            Sanctum::actingAs($user, ['*']);
            $this->postJson("{$base}/donor-parts", $this->part())->assertStatus(403);
            $this->putJson("{$base}/donor-parts/{$partId}", $this->part())->assertStatus(403);
            $this->deleteJson("{$base}/donor-parts/{$partId}")->assertStatus(403);
        }

        // a user from another barangay cannot even resolve the ticket
        $barangay = \App\Models\Barangay::create(['name' => 'Elsewhere', 'city_id' => \App\Models\City::create(['name' => 'X', 'province_id' => \App\Models\Province::create(['name' => 'P', 'code' => 'P1'])->id, 'code' => 'C1'])->id]);
        $outsider = User::factory()->create(['role' => 'Maintenance Personnel', 'barangay_id' => $barangay->id]);
        Sanctum::actingAs($outsider, ['*']);
        $this->deleteJson("{$base}/donor-parts/{$partId}")->assertStatus(404);
    }

    #[Test]
    public function submitting_needs_a_part_then_locks_them_and_lists_them_as_missing_on_the_donor(): void
    {
        [$tid, $sid, $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        // no parts yet -> cannot submit, and nothing happens to the donor
        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_type' => 'cannibalized', 'source_vehicle_id' => $this->donor->vehicle_id])->assertUnprocessable();
        $this->assertSame(0, VehicleIssueReport::where('vehicle_id', $this->donor->vehicle_id)->count());

        $partId = $this->postJson("{$base}/donor-parts", $this->part())->assertCreated()->json('part_id');
        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Hose', 'quantity' => 1]))->assertCreated();

        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Fitted both parts.'])->assertOk();

        // No issue report is filed any more and the donor's condition is left alone; the parts are
        // simply listed as missing on the donor, and the ticket moved on.
        $this->assertSame(0, VehicleIssueReport::where('vehicle_id', $this->donor->vehicle_id)->count());
        $this->assertNotSame('Needs Repair', $this->donor->fresh()->condition);
        $missing = $this->getJson("/api/vehicles/{$this->donor->vehicle_id}/missing-parts")->assertOk()->json();
        $this->assertEqualsCanonicalizing(['Brake caliper', 'Hose'], array_column($missing, 'name'));
        $this->assertSame('For Verification', MaintenanceTicket::find($tid)->status);

        // the parts are now protected history
        $this->putJson("{$base}/donor-parts/{$partId}", $this->part(['quantity' => 9]))->assertUnprocessable();
        $this->deleteJson("{$base}/donor-parts/{$partId}")->assertUnprocessable();
        $this->postJson("{$base}/donor-parts", $this->part())->assertUnprocessable();
    }

    #[Test]
    public function only_a_vehicle_that_is_no_longer_in_use_can_be_a_donor(): void
    {
        [, , $base] = $this->ticket();
        $this->donor->update(['status' => 'Available']);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])
            ->assertUnprocessable()->assertJsonFragment(['message' => 'Donor Truck is still in use. Parts can only be taken from a retired (decommissioned) vehicle.']);

        $this->donor->update(['status' => 'Inactive']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();
    }

    #[Test]
    public function a_retired_vehicle_can_be_a_donor_and_its_parts_never_block_anything(): void
    {
        [, , $base] = $this->ticket();
        $this->donor->update(['status' => 'Decommissioned']);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        // even if the form says "blocks", a retired donor is already out of the fleet
        $this->postJson("{$base}/donor-parts", $this->part(['blocks_readiness' => true]))
            ->assertCreated()->assertJsonPath('blocks_readiness', false);
    }

    #[Test]
    public function existing_single_part_data_is_kept_and_costs_stay_separate(): void
    {
        [$tid, , $base] = $this->ticket();
        Sanctum::actingAs($this->mechanic, ['*']);

        // legacy client path: one inline part, plus ordinary purchased parts and cost
        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 
            'repair_logs' => 'Swapped it.', 'repair_type' => 'cannibalized', 'source_vehicle_id' => $this->donor->vehicle_id,
            'part_needed' => 'Radiator', 'part_quantity' => 2, 'part_condition' => 'Good',
            'parts_used' => 'Coolant', 'maintenance_cost' => 500,
        ])->assertOk();

        $sub = $this->getJson("/api/tickets/{$tid}")->json('sub_issues.0');
        $this->assertSame('Radiator', $sub['donor_parts'][0]['part_name']);
        $this->assertSame(2, $sub['donor_parts'][0]['quantity']);
        $this->assertSame('Coolant', $sub['parts_used'], 'purchased materials are untouched');
        $this->assertEquals(500, $sub['maintenance_cost'], 'donor parts never add to the cost');
    }

    #[Test]
    public function a_repair_can_take_parts_from_several_donors_and_each_lists_its_own_missing_parts(): void
    {
        [$tid, , $base] = $this->ticket();
        $cat = VehicleCategory::first();
        $second = Vehicle::create(['vehicle_name' => 'Second Donor', 'plate_number' => 'DON 3003', 'category_id' => $cat->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2018, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
        $second->update(['status' => 'Decommissioned']);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("{$base}/donor", ['source_vehicle_id' => $this->donor->vehicle_id])->assertOk();

        // a part with no donor named belongs to the first donor; another donor can be named
        $this->postJson("{$base}/donor-parts", $this->part())->assertCreated()->assertJsonPath('donor_vehicle_id', $this->donor->vehicle_id);
        $this->postJson("{$base}/donor-parts", $this->part(['part_name' => 'Hose', 'donor_vehicle_id' => $second->vehicle_id]))
            ->assertCreated()->assertJsonPath('donor_vehicle_id', $second->vehicle_id);
        // the repaired vehicle itself can never be a donor
        $this->postJson("{$base}/donor-parts", $this->part(['donor_vehicle_id' => $this->vehicle->vehicle_id]))->assertUnprocessable();

        $parts = $this->getJson("/api/tickets/{$tid}")->assertOk()->json('sub_issues.0.donor_parts');
        $this->assertSame(['Donor Truck', 'Second Donor'], array_column(array_column($parts, 'donor_vehicle'), 'vehicle_name'));

        $this->putJson("{$base}/log-repairs", ['tested_ok' => true, 'repair_logs' => 'Fitted both parts.'])->assertOk();

        // each donor lists only its own parts as missing; no issue reports are filed
        $first = $this->getJson("/api/vehicles/{$this->donor->vehicle_id}/missing-parts")->json();
        $other = $this->getJson("/api/vehicles/{$second->vehicle_id}/missing-parts")->json();
        $this->assertSame(['Brake caliper'], array_column($first, 'name'));
        $this->assertSame(['Hose'], array_column($other, 'name'));
        $this->assertSame(0, VehicleIssueReport::whereIn('vehicle_id', [$this->donor->vehicle_id, $second->vehicle_id])->count());
    }
}
