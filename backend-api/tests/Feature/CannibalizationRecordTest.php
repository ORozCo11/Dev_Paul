<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CannibalizationRecord;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cannibalization is a direct log with no approval step and no inventory: an authorized user
 * records which part came off which vehicle (active or retired), how many, and the repair ticket it
 * went into. For an active donor the person says whether it can still respond without the part;
 * only a part it cannot respond without holds the vehicle back from being ready.
 */
class CannibalizationRecordTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $donor;
    private Vehicle $repaired;
    private User $admin;
    private User $custodian;
    private User $mechanic;
    private int $ticketId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 'test']);
        $make = fn ($name, $plate) => Vehicle::create([
            'vehicle_name' => $name, 'plate_number' => $plate, 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
        $this->donor = $make('Old Pumper', 'OLD 1001');
        $this->repaired = $make('Active Pumper', 'ACT 2002');

        // An open (Active) repair ticket on the vehicle that receives the parts.
        Sanctum::actingAs($this->custodian, ['*']);
        $this->ticketId = $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $this->repaired->vehicle_id, 'ticket_description' => 'Radiator leak', 'priority' => 'High',
            'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Radiator']],
        ])->assertCreated()->json('ticket_id');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$this->ticketId}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'donor_type' => 'active',
            'source_vehicle_id' => $this->donor->vehicle_id,
            'ticket_id' => $this->ticketId,
            'parts' => [
                ['part_name' => 'Radiator', 'quantity' => 1, 'blocks_readiness' => true],
                ['part_name' => 'Side mirror', 'quantity' => 2, 'blocks_readiness' => false],
            ],
        ], $over);
    }

    private function vehicleRow(Vehicle $vehicle): array
    {
        return collect($this->getJson('/api/vehicles')->assertOk()->json())->firstWhere('vehicle_id', $vehicle->vehicle_id);
    }

    #[Test]
    public function an_authorized_user_logs_parts_directly_with_no_approval_step(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);

        $res = $this->postJson('/api/cannibalizations', $this->payload())->assertCreated();
        $res->assertJsonPath('record_number', 'CAN-0001')->assertJsonPath('donor_type', 'active')->assertJsonCount(2, 'parts');
        $this->assertSame($this->mechanic->id, $res->json('recorded_by.id'), 'the recorder is the signed-in user');
        $this->assertArrayNotHasKey('status', $res->json(), 'there is no lifecycle/approval status');

        $record = CannibalizationRecord::with('parts')->first();
        $this->assertSame($this->ticketId, (int) $record->ticket_id);
        $this->assertSame($this->repaired->vehicle_id, (int) $record->destination_vehicle_id);
        $this->assertSame([1, 2], $record->parts->pluck('quantity')->all());
        $this->assertSame([true, false], $record->parts->pluck('blocks_readiness')->all());
        // Readiness comes from the missing parts only; the donor's condition is not changed.
        $this->assertSame('Good', $this->donor->fresh()->condition);
    }

    #[Test]
    public function the_record_appears_in_both_vehicles_history_and_the_activity_log(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $id = $this->postJson('/api/cannibalizations', $this->payload())->assertCreated()->json('cannibalization_id');

        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $this->donor->vehicle_id, 'activity_type' => 'Parts Removed', 'related_record_id' => (string) $id]);
        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $this->repaired->vehicle_id, 'activity_type' => 'Parts Received', 'related_record_id' => (string) $id]);
        $this->assertTrue(ActivityLog::where('action', 'Cannibalization Recorded')->exists());
        $this->assertStringContainsString('2 × Side mirror', VehicleHistory::where('vehicle_id', $this->donor->vehicle_id)->first()->description);
    }

    #[Test]
    public function only_a_part_the_vehicle_cannot_respond_without_makes_it_not_ready(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Side mirror', 'quantity' => 2, 'blocks_readiness' => false]]]))->assertCreated();

        // A minor part: still able to confirm readiness, and the reason does not name it.
        $row = $this->vehicleRow($this->donor);
        $this->assertSame(['Side mirror'], $row['minor_missing_parts']);
        $this->assertEmpty($row['missing_parts']);
        $this->assertStringNotContainsString('Missing part', (string) $row['not_ready_reason']);
        $this->assertNull($row['readiness_block_reason']);
        // the confirm screen gets the same list as a note
        $this->getJson("/api/vehicles/{$this->donor->vehicle_id}/readiness")->assertOk()->assertJsonPath('minor_missing_parts', ['Side mirror']);
        $this->postJson("/api/vehicles/{$this->donor->vehicle_id}/readiness-check", ['confirmed' => true, 'all_passed' => true])->assertSuccessful();

        // A part it cannot respond without: Not Ready, and readiness cannot be confirmed.
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Transmission', 'quantity' => 1, 'blocks_readiness' => true]]]))->assertCreated();
        $row = $this->vehicleRow($this->donor);
        $this->assertSame('not_ready', $row['readiness_state']);
        $this->assertSame('Missing part: Transmission', $row['not_ready_reason']);
        $this->assertStringContainsString('Transmission', $row['readiness_block_reason']);
        $this->postJson("/api/vehicles/{$this->donor->vehicle_id}/readiness-check", ['confirmed' => true, 'all_passed' => true])->assertUnprocessable();
    }

    #[Test]
    public function the_same_part_cannot_be_logged_again_until_it_is_put_back(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $partId = $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Transmission', 'quantity' => 1]]]))
            ->assertCreated()->json('parts.0.part_id');

        // any capitalisation or spacing is the same part
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => '  transmission ', 'quantity' => 1]]]))
            ->assertUnprocessable()->assertJsonFragment(['message' => 'transmission is already logged as removed from Old Pumper and has not been put back.']);
        // the same part twice in one request
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Hose', 'quantity' => 1], ['part_name' => 'HOSE', 'quantity' => 1]]]))
            ->assertUnprocessable();
        $this->assertSame(1, CannibalizationRecord::count());

        $this->putJson("/api/cannibalization-parts/{$partId}/restore")->assertOk();
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Transmission', 'quantity' => 1]]]))->assertCreated();
    }

    #[Test]
    public function donor_type_must_match_the_vehicle_and_a_retired_donor_never_holds_anything_up(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        // an in-service vehicle cannot be logged as retired, and a retired one not as active
        $this->postJson('/api/cannibalizations', $this->payload(['donor_type' => 'retired']))->assertUnprocessable();
        $this->donor->update(['status' => 'Decommissioned']);
        $this->postJson('/api/cannibalizations', $this->payload())->assertUnprocessable();

        $res = $this->postJson('/api/cannibalizations', $this->payload(['donor_type' => 'retired', 'parts' => [['part_name' => 'Radiator', 'quantity' => 1, 'blocks_readiness' => true]]]))->assertCreated();
        $res->assertJsonPath('donor_type', 'retired')->assertJsonPath('parts.0.blocks_readiness', false);

        // nothing to put back on a retired vehicle, and nothing to correct
        $partId = $res->json('parts.0.part_id');
        $this->putJson("/api/cannibalization-parts/{$partId}/restore")->assertUnprocessable();
        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", ['blocks_readiness' => true])->assertUnprocessable();
        $row = $this->getJson("/api/vehicles/{$this->donor->vehicle_id}/missing-parts")->assertOk()->json();
        $this->assertFalse($row[0]['can_restore']);
    }

    #[Test]
    public function the_ticket_is_required_and_must_be_open_and_the_donor_must_be_a_different_vehicle(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/cannibalizations', collect($this->payload())->except('ticket_id')->all())->assertUnprocessable();
        // the donor cannot be the vehicle being repaired
        $this->postJson('/api/cannibalizations', $this->payload(['source_vehicle_id' => $this->repaired->vehicle_id]))->assertUnprocessable();
        // no parts / bad quantities
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => []]))->assertUnprocessable();
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Hose', 'quantity' => 0]]]))->assertUnprocessable();
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => '', 'quantity' => 1]]]))->assertUnprocessable();
        // removed in the future
        $this->postJson('/api/cannibalizations', $this->payload(['removed_at' => now()->addDay()->toDateTimeString()]))->assertUnprocessable();
        // a closed ticket
        MaintenanceTicket::whereKey($this->ticketId)->update(['status' => 'Closed']);
        $this->postJson('/api/cannibalizations', $this->payload())->assertUnprocessable();

        $this->assertSame(0, CannibalizationRecord::count());
    }

    #[Test]
    public function the_can_it_still_respond_answer_can_be_corrected_by_the_logger_custodian_or_admin(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        $partId = $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Side mirror', 'quantity' => 1, 'blocks_readiness' => false]]]))
            ->assertCreated()->json('parts.0.part_id');

        // the mechanic who logged it may correct it
        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", ['blocks_readiness' => true])->assertOk()->assertJsonPath('blocks_readiness', true);
        $this->assertSame('not_ready', $this->vehicleRow($this->donor)['readiness_state']);

        // another mechanic may not
        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel']), ['*']);
        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", ['blocks_readiness' => false])->assertForbidden();

        // the Custodian and the Admin may, and each change is written to the log and the vehicle history
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", ['blocks_readiness' => false])->assertOk();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", ['blocks_readiness' => true])->assertOk();
        $this->assertSame(3, ActivityLog::where('action', 'Missing Part Updated')->count());
        $this->assertSame(3, VehicleHistory::where('vehicle_id', $this->donor->vehicle_id)->where('activity_type', 'Missing Part Updated')->count());

        $this->putJson("/api/cannibalization-parts/{$partId}/readiness", [])->assertUnprocessable();
    }

    #[Test]
    public function a_saved_record_has_no_edit_or_delete_and_a_forged_recorder_is_ignored(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $res = $this->postJson('/api/cannibalizations', $this->payload(['recorded_by' => $this->admin->id]))->assertCreated();
        $this->assertSame($this->custodian->id, $res->json('recorded_by.id'));
        $id = $res->json('cannibalization_id');

        $this->putJson("/api/cannibalizations/{$id}", ['reason' => 'x'])->assertStatus(405);
        $this->deleteJson("/api/cannibalizations/{$id}")->assertStatus(405);
        $this->assertSame(1, CannibalizationRecord::count());
    }

    #[Test]
    public function super_admin_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin']), ['*']);
        $this->getJson('/api/cannibalizations')->assertForbidden();
        $this->postJson('/api/cannibalizations', $this->payload())->assertForbidden();
    }
}
