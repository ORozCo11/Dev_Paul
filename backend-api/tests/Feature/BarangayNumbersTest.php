<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\MaintenanceTicket;
use App\Models\Province;
use App\Models\TicketArchiveLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleConditionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BarangayNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name): array
    {
        $province = Province::firstOrCreate(['code' => 'TEST'], ['name' => 'Test Province']);
        $city = City::firstOrCreate(['code' => 'TESTCITY'], ['name' => 'Test City', 'province_id' => $province->id]);
        $barangay = Barangay::create(['city_id' => $city->id, 'name' => $name]);
        $user = User::factory()->create(['barangay_id' => $barangay->id, 'role' => 'Admin', 'roles' => ['Admin']]);
        $category = VehicleCategory::firstOrCreate(['category_name' => 'Ambulance']);
        $vehicle = Vehicle::create(['barangay_id' => $barangay->id, 'vehicle_name' => $name.' Ambulance', 'plate_number' => uniqid(), 'category_id' => $category->category_id, 'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '1000 kg', 'vehicle_color' => 'White', 'current_location' => 'Depot']);
        return [$user, $vehicle];
    }

    private function ticket(User $user, Vehicle $vehicle): MaintenanceTicket
    {
        return MaintenanceTicket::create(['vehicle_id' => $vehicle->vehicle_id, 'created_by' => $user->id, 'ticket_title' => 'MT-9999 — Ambulance — Brakes', 'ticket_description' => 'Brake issue', 'priority' => 'High', 'status' => 'Open']);
    }

    public function test_each_barangay_and_module_starts_at_one_and_deleted_numbers_are_not_reused(): void
    {
        [$a, $va] = $this->tenant('A');
        [$b, $vb] = $this->tenant('B');
        $ta = $this->ticket($a, $va);
        $ta2 = $this->ticket($a, $va);
        $tb = $this->ticket($b, $vb);
        $ca = VehicleConditionCheck::create(['vehicle_id' => $va->vehicle_id, 'condition_result' => 'Good', 'checked_by' => $a->id]);
        $cb = VehicleConditionCheck::create(['vehicle_id' => $vb->vehicle_id, 'condition_result' => 'Good', 'checked_by' => $b->id]);
        $this->assertSame([1, 2, 1], [$ta->barangay_number, $ta2->barangay_number, $tb->barangay_number]);
        $this->assertSame([1, 1], [$ca->barangay_number, $cb->barangay_number]);
        $this->assertSame([1, 1], [$va->barangay_number, $vb->barangay_number]);
        $this->assertStringStartsWith('MT-0001', $tb->ticket_title);
        $this->assertNotSame($ta->ticket_id, $tb->ticket_id);
        $ta2->delete();
        $this->assertSame(3, $this->ticket($a, $va)->barangay_number);
        Sanctum::actingAs($b, $b->allRoles());
        $this->getJson('/api/conditions')->assertOk()->assertJsonFragment(['barangay_number' => 1, 'condition_check_id' => $cb->condition_check_id]);
        $this->getJson('/api/tickets/'.$ta->ticket_id)->assertNotFound();
    }

    public function test_backfill_preserves_internal_ids_and_foreign_keys(): void
    {
        [$a, $va] = $this->tenant('A');
        [$b, $vb] = $this->tenant('B');
        $ta = $this->ticket($a, $va);
        $tb = $this->ticket($b, $vb);
        $archive = TicketArchiveLog::create([
            'ticket_id' => $tb->ticket_id, 'vehicle_id' => $vb->vehicle_id, 'ticket_title' => $tb->ticket_title,
            'vehicle_name' => $vb->vehicle_name, 'plate_number' => $vb->plate_number,
            'final_status' => 'Closed', 'maintenance_cost' => 0, 'full_ticket_snapshot' => $tb->toArray(),
            'archived_by' => $b->id, 'archived_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_22_000001_add_barangay_record_numbers.php');
        $migration->down();
        DB::table('ticket_archive_logs')->where('archive_id', $archive->archive_id)->update(['full_ticket_snapshot' => json_encode(json_encode($tb->toArray()))]);
        $migration->up();
        // A resumed migration must preserve numbers already allocated.
        $migration->up();
        $this->assertSame(1, $ta->fresh()->barangay_number);
        $this->assertSame(1, $tb->fresh()->barangay_number);
        $this->assertSame($vb->vehicle_id, $tb->fresh()->vehicle_id);
        $this->assertSame(1, $archive->fresh()->ticket_number);
        $this->assertIsArray($archive->fresh()->full_ticket_snapshot);
        $this->assertSame(1, $archive->fresh()->full_ticket_snapshot['barangay_number']);
        $this->assertSame(2, $this->ticket($b, $vb)->barangay_number);
    }
}
