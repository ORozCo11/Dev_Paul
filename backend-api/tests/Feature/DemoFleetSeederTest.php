<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DemoFleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The demo fleet seeder fills every screen, can be run twice, and never touches vehicles it did not create. */
class DemoFleetSeederTest extends TestCase
{
    use RefreshDatabase;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['code' => 'DF', 'name' => 'Demo Province']);
        $city = City::create(['code' => 'DF-CITY', 'name' => 'Demo City', 'province_id' => $province->id]);
        $this->barangay = Barangay::create(['name' => 'Demo Barangay', 'city_id' => $city->id, 'psgc_code' => '9999900077']);
        foreach (['Admin', 'Custodian', 'Maintenance Personnel'] as $role) {
            User::factory()->create(['role' => $role, 'roles' => [$role], 'barangay_id' => $this->barangay->id, 'is_active' => true]);
        }
        putenv('DEMO_FLEET_BARANGAY='.$this->barangay->id);
    }

    protected function tearDown(): void
    {
        putenv('DEMO_FLEET_BARANGAY');
        parent::tearDown();
    }

    private function demoVehicles()
    {
        return Vehicle::withoutGlobalScopes()->where('barangay_id', $this->barangay->id)->where('remarks', 'like', DemoFleetSeeder::MARK.'%');
    }

    #[Test]
    public function it_creates_thirteen_vehicles_with_photos_and_sample_work_everywhere(): void
    {
        $this->seed(DemoFleetSeeder::class);

        $this->assertSame(13, $this->demoVehicles()->count());
        $this->assertSame(0, $this->demoVehicles()->whereNull('photo_url')->count());
        $this->assertSame(13, $this->demoVehicles()->distinct()->count('photo_url'), 'every vehicle has its own photo');
        foreach ($this->demoVehicles()->pluck('photo_url') as $url) {
            $this->assertFileExists(public_path(parse_url($url, PHP_URL_PATH)), "missing image for {$url}");
        }

        $ids = $this->demoVehicles()->pluck('vehicle_id');
        $statuses = DB::table('maintenance_tickets')->whereIn('vehicle_id', $ids)->pluck('status')->sort()->values()->all();
        $this->assertSame(['Active', 'Active', 'Closed', 'Declined', 'For Verification', 'Pending Approval'], $statuses);
        foreach (['vehicle_condition_checks', 'vehicle_readiness_checks', 'vehicle_issue_reports', 'vehicle_maintenance_schedules', 'vehicle_maintenance_records', 'vehicle_locations', 'vehicle_histories'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->whereIn('vehicle_id', $ids)->count(), "{$table} has sample rows");
        }
        $this->assertSame(1, DB::table('ticket_sub_issue_donor_parts')->count(), 'one donor part from the retired vehicle');
        $this->assertSame(['Available', 'Decommissioned', 'Inactive', 'Under Maintenance'], $this->demoVehicles()->pluck('status')->unique()->sort()->values()->all());
        $this->assertGreaterThan(0, DB::table('notifications')->count());
    }

    #[Test]
    public function the_fleet_shows_up_through_the_api_for_the_barangay_admin(): void
    {
        $this->seed(DemoFleetSeeder::class);
        Sanctum::actingAs(User::where('role', 'Admin')->first(), ['*']);

        $rows = collect($this->getJson('/api/vehicles')->assertOk()->json());
        $this->assertGreaterThanOrEqual(11, $rows->count()); // archived and retired vehicles are hidden by default
        $ready = $rows->where('readiness_state', 'ready')->count();
        $this->assertGreaterThanOrEqual(4, $ready);
        $this->assertGreaterThanOrEqual(4, $rows->count() - $ready);
        $this->getJson('/api/tickets')->assertOk();
        $this->getJson('/api/maintenance-schedules')->assertOk();
        $this->getJson('/api/dashboard')->assertOk();
    }

    #[Test]
    public function running_it_twice_replaces_only_its_own_vehicles(): void
    {
        $other = Vehicle::withoutGlobalScopes()->create([
            'barangay_id' => $this->barangay->id, 'vehicle_name' => 'Real Unit', 'plate_number' => 'REAL-001', 'category_id' => \App\Models\VehicleCategory::create(['category_name' => 'Truck', 'description' => 't'])->category_id,
            'brand' => 'Isuzu', 'model' => 'NPR', 'year_model' => 2020, 'capacity' => '5 t', 'vehicle_color' => 'White', 'fuel_type' => 'Diesel', 'current_location' => 'Depot', 'remarks' => 'Not demo data.',
        ]);

        $this->seed(DemoFleetSeeder::class);
        $this->seed(DemoFleetSeeder::class);

        $this->assertSame(13, $this->demoVehicles()->count());
        $this->assertNotNull(Vehicle::withoutGlobalScopes()->find($other->vehicle_id));
        $this->assertSame(6, DB::table('maintenance_tickets')->count());
    }
}
