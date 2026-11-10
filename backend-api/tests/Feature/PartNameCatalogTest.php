<?php

namespace Tests\Feature;

use App\Models\CannibalizationPart;
use App\Models\CannibalizationRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The part-name list offered when logging a cannibalized part: seeded, add-able, renamable, deletable. */
class PartNameCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_list_is_seeded_and_users_can_add_a_name_without_duplicating_it(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel']), ['*']);

        $names = collect($this->getJson('/api/part-names')->assertOk()->json())->pluck('name');
        $this->assertTrue($names->contains('Transmission'));

        $this->postJson('/api/part-names', ['name' => '  Ladder   rack '])->assertCreated()->assertJsonPath('name', 'Ladder rack');
        // same name in any capitalisation is the same entry
        $this->postJson('/api/part-names', ['name' => 'LADDER RACK'])->assertCreated()->assertJsonPath('name', 'Ladder rack');
        $this->assertSame(1, DB::table('part_names')->whereRaw('LOWER(name) = ?', ['ladder rack'])->count());
        $this->postJson('/api/part-names', ['name' => ''])->assertUnprocessable();
    }

    #[Test]
    public function a_name_can_be_renamed_and_is_updated_on_the_parts_that_use_it_and_deleted_only_when_unused(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin, ['*']);
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $vehicle = Vehicle::create(['vehicle_name' => 'Old', 'plate_number' => 'OLD 1', 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
        $record = CannibalizationRecord::create([
            'source_vehicle_id' => $vehicle->vehicle_id, 'donor_type' => 'active', 'destination_type' => 'vehicle', 'destination_vehicle_id' => $vehicle->vehicle_id,
            'reason' => 'r', 'source_reason' => 's', 'recorded_by' => $admin->id, 'removed_at' => now(),
        ]);
        CannibalizationPart::create(['cannibalization_id' => $record->cannibalization_id, 'part_name' => 'Transmission', 'quantity' => 1]);

        $id = DB::table('part_names')->where('name', 'Transmission')->value('id');
        $this->deleteJson("/api/part-names/{$id}")->assertUnprocessable();   // still used

        $this->putJson("/api/part-names/{$id}", ['name' => 'Gearbox'])->assertOk()->assertJsonPath('name', 'Gearbox');
        $this->assertSame('Gearbox', CannibalizationPart::first()->part_name);
        $this->putJson("/api/part-names/{$id}", ['name' => 'engine'])->assertUnprocessable();   // already in the list

        $unused = DB::table('part_names')->where('name', 'Siren')->value('id');
        $this->deleteJson("/api/part-names/{$unused}")->assertNoContent();
        $this->assertDatabaseMissing('part_names', ['name' => 'Siren']);
    }
}
