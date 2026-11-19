<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleReadinessCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A passing readiness check no longer lapses after 24 hours: it holds until the configured limit or until something changes. */
class ReadinessValidityTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Validity Truck', 'plate_number' => 'VAL 1001', 'category_id' => $category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
        Sanctum::actingAs($this->custodian, ['*']);
    }

    private function checkedDaysAgo(int $days): void
    {
        VehicleReadinessCheck::create([
            'vehicle_id' => $this->vehicle->vehicle_id, 'checked_by' => $this->custodian->id,
            'checklist' => [['item' => 'Engine starts', 'passed' => true]], 'all_passed' => true, 'checked_at' => now()->subDays($days),
        ]);
    }

    private function state(): string
    {
        return collect($this->getJson('/api/vehicles')->assertOk()->json())->firstWhere('vehicle_id', $this->vehicle->vehicle_id)['readiness_state'];
    }

    #[Test]
    public function a_check_is_still_ready_three_days_later(): void
    {
        $this->checkedDaysAgo(3);
        $this->assertSame('ready', $this->state());
    }

    #[Test]
    public function a_check_goes_stale_after_the_configured_limit(): void
    {
        config(['vms.readiness_max_days' => 7]);
        $this->checkedDaysAgo(8);
        $this->assertSame('stale', $this->state());
    }

    #[Test]
    public function the_limit_is_configurable(): void
    {
        config(['vms.readiness_max_days' => 2]);
        $this->checkedDaysAgo(3);
        $this->assertSame('stale', $this->state());
    }

    #[Test]
    public function an_event_invalidates_a_check_before_the_limit(): void
    {
        $this->checkedDaysAgo(1);
        $this->assertSame('ready', $this->state());

        // A condition change (the vehicle is found Needs Repair) invalidates the earlier check at once.
        $this->vehicle->update(['condition' => 'Needs Repair']);
        $this->assertNotSame('ready', $this->state());
    }
}
