<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CannibalizationRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cannibalization is a direct record, with no approval step: an authorized
 * user saves it and it is final, attributed to them, listed in the history of
 * both vehicles, with every part and quantity preserved.
 */
class CannibalizationRecordTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $source;
    private Vehicle $dest;

    protected function setUp(): void
    {
        parent::setUp();
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 'test']);
        $make = fn ($name, $plate) => Vehicle::create([
            'vehicle_name' => $name, 'plate_number' => $plate, 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
        $this->source = $make('Old Pumper', 'OLD 1001');
        $this->dest = $make('Active Pumper', 'ACT 2002');
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'source_vehicle_id' => $this->source->vehicle_id,
            'source_reason' => 'Unit is awaiting disposal; salvaging working parts.',
            'destination_type' => 'vehicle',
            'destination_vehicle_id' => $this->dest->vehicle_id,
            'reason' => 'Active Pumper needs a radiator and two hoses now.',
            'removed_at' => now()->subHour()->toDateTimeString(),
            'received_by_name' => 'Jake Engana',
            'transferred_at' => now()->toDateTimeString(),
            'parts' => [
                ['part_name' => 'Radiator', 'quantity' => 1, 'unit' => 'pc', 'part_condition' => 'Good'],
                ['part_name' => 'Hose', 'part_number' => 'H-22', 'quantity' => 2, 'unit' => 'pc'],
            ],
        ], $over);
    }

    #[Test]
    public function an_authorized_user_records_a_transfer_directly_with_no_approval_step(): void
    {
        $user = User::factory()->create(['role' => 'Maintenance Personnel']);
        Sanctum::actingAs($user, ['*']);

        $res = $this->postJson('/api/cannibalizations', $this->payload())->assertCreated();
        $res->assertJsonPath('record_number', 'CAN-0001')->assertJsonCount(2, 'parts');
        $this->assertSame($user->id, $res->json('recorded_by.id'), 'the recorder is the signed-in user');
        $this->assertSame($user->name, $res->json('performed_by_name'));
        $this->assertArrayNotHasKey('status', $res->json(), 'there is no lifecycle/approval status');

        $record = CannibalizationRecord::with('parts')->first();
        $this->assertSame([1, 2], $record->parts->pluck('quantity')->all());
        $this->assertSame('Needs Inspection', $this->source->fresh()->condition);
    }

    #[Test]
    public function the_record_appears_in_both_vehicles_history_and_the_activity_log(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']), ['*']);
        $id = $this->postJson('/api/cannibalizations', $this->payload())->assertCreated()->json('cannibalization_id');

        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $this->source->vehicle_id, 'activity_type' => 'Parts Removed', 'related_record_id' => (string) $id]);
        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $this->dest->vehicle_id, 'activity_type' => 'Parts Received', 'related_record_id' => (string) $id]);
        $this->assertTrue(ActivityLog::where('action', 'Cannibalization Recorded')->exists());
        $this->assertStringContainsString('2 × Hose', VehicleHistory::where('vehicle_id', $this->source->vehicle_id)->first()->description);
    }

    #[Test]
    public function storage_is_a_valid_destination_and_needs_a_location(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Custodian']), ['*']);
        $base = $this->payload(['destination_type' => 'storage', 'destination_vehicle_id' => null, 'storage_location' => null]);
        $this->postJson('/api/cannibalizations', $base)->assertUnprocessable();
        $this->postJson('/api/cannibalizations', array_merge($base, ['storage_location' => 'Parts room, Brgy Hall']))->assertCreated();
        $this->assertDatabaseMissing('vehicle_histories', ['activity_type' => 'Parts Received']);
    }

    #[Test]
    public function invalid_records_are_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']), ['*']);

        // same vehicle as source and destination
        $this->postJson('/api/cannibalizations', $this->payload(['destination_vehicle_id' => $this->source->vehicle_id]))->assertUnprocessable();
        // no parts / bad quantities
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => []]))->assertUnprocessable();
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Hose', 'quantity' => 0]]]))->assertUnprocessable();
        $this->postJson('/api/cannibalizations', $this->payload(['parts' => [['part_name' => 'Hose', 'quantity' => -3]]]))->assertUnprocessable();
        // installed before it was removed, or removed in the future
        $this->postJson('/api/cannibalizations', $this->payload(['transferred_at' => now()->subDay()->toDateTimeString()]))->assertUnprocessable();
        $this->postJson('/api/cannibalizations', $this->payload(['removed_at' => now()->addDay()->toDateTimeString()]))->assertUnprocessable();
        // archived destination
        $this->dest->update(['status' => 'Inactive']);
        $this->postJson('/api/cannibalizations', $this->payload())->assertUnprocessable();

        $this->assertSame(0, CannibalizationRecord::count());
    }

    #[Test]
    public function a_vehicles_condition_is_never_upgraded_and_there_is_no_edit_or_delete(): void
    {
        $this->source->update(['condition' => 'Needs Repair']);
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']), ['*']);
        $id = $this->postJson('/api/cannibalizations', $this->payload())->assertCreated()->json('cannibalization_id');
        $this->assertSame('Needs Repair', $this->source->fresh()->condition);

        $this->putJson("/api/cannibalizations/{$id}", ['reason' => 'x'])->assertStatus(405);
        $this->deleteJson("/api/cannibalizations/{$id}")->assertStatus(405);
        $this->assertSame(1, CannibalizationRecord::count());
    }

    #[Test]
    public function a_forged_recorder_is_ignored_and_super_admin_is_blocked(): void
    {
        $user = User::factory()->create(['role' => 'Custodian']);
        $other = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($user, ['*']);
        $res = $this->postJson('/api/cannibalizations', $this->payload(['recorded_by' => $other->id]))->assertCreated();
        $this->assertSame($user->id, $res->json('recorded_by.id'));

        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin']), ['*']);
        $this->getJson('/api/cannibalizations')->assertForbidden();
        $this->postJson('/api/cannibalizations', $this->payload())->assertForbidden();
    }
}
