<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecPhase5UsageAndCriticalityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private VehicleCategory $ambulance;
    private VehicleCategory $van;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $this->custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'can_register_vehicles' => true]);
        $this->ambulance = VehicleCategory::create(['category_name' => 'Ambulance', 'domain' => 'Land', 'description' => 'x', 'default_criticality' => 'Critical']);
        $this->van = VehicleCategory::create(['category_name' => 'Service Van', 'domain' => 'Land', 'description' => 'x']);
        VehicleHub::create(['hub_key' => 'main-depot', 'name' => 'Main Depot', 'label' => 'MD', 'lat' => 10.3, 'lng' => 123.9]);
    }

    private function vehicle(VehicleCategory $category, string $plate, array $extra = []): Vehicle
    {
        return Vehicle::create($extra + [
            'vehicle_name' => "V {$plate}", 'plate_number' => $plate, 'category_id' => $category->category_id,
            'brand' => 'T', 'model' => 'T', 'year_model' => 2022, 'capacity' => '1', 'vehicle_color' => 'x', 'current_location' => 'Main Depot',
            'status' => 'Available', 'condition' => 'Good',
        ]);
    }

    #[Test]
    public function criticality_defaults_from_the_type_and_an_admin_override_wins(): void
    {
        $a = $this->vehicle($this->ambulance, 'AMB 1111');
        $v = $this->vehicle($this->van, 'VAN 1111');
        $this->assertSame('Critical', $a->effectiveCriticality());
        $this->assertSame('Normal', $v->effectiveCriticality());

        $v->update(['criticality' => 'High']);
        $this->assertSame('High', $v->fresh()->effectiveCriticality());

        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/categories/{$this->van->category_id}", ['category_name' => 'Service Van', 'domain' => 'Land', 'default_criticality' => 'Bogus'])->assertUnprocessable();
        $this->putJson("/api/categories/{$this->van->category_id}", ['category_name' => 'Service Van', 'domain' => 'Land', 'default_criticality' => 'High'])->assertOk();
    }

    #[Test]
    public function a_custodian_cannot_set_criticality_when_registering_a_vehicle(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson('/api/vehicles', [
            'vehicle_name' => 'New', 'plate_number' => 'NEW 1234', 'category_id' => $this->van->category_id, 'brand' => 'T', 'model' => 'T',
            'year_model' => 2022, 'capacity' => '1', 'fuel_type' => 'Diesel', 'vehicle_color' => 'x', 'current_location' => 'Main Depot', 'criticality' => 'Critical',
        ])->assertCreated();
        $this->assertNull(Vehicle::where('plate_number', 'NEW 1234')->first()->criticality);
    }

    #[Test]
    public function the_dashboard_watch_ranks_unready_vehicles_by_criticality(): void
    {
        $this->vehicle($this->van, 'VAN 2222');                                      // Normal, never checked
        $this->vehicle($this->ambulance, 'AMB 2222', ['status' => 'Under Maintenance']); // Critical, in maintenance
        $this->vehicle($this->van, 'VAN 3333', ['criticality' => 'High']);           // High override, never checked
        $this->vehicle($this->van, 'VAN 4444', ['status' => 'Decommissioned']);     // retired — excluded

        Sanctum::actingAs($this->admin, ['*']);
        $watch = $this->getJson('/api/dashboard')->assertOk()->json('criticality_watch');

        $this->assertSame(['Critical', 'High', 'Normal'], array_column($watch, 'criticality'));
        $this->assertSame('In maintenance', $watch[0]['reason']);
        $this->assertCount(3, $watch);
    }

}
