<?php

namespace Tests\Feature;

use App\Models\{User, Vehicle, VehicleCategory, VehicleReadinessCheck};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Bulk readiness confirm, "valid until" on the vehicle list, and the one-day expiry notice. */
class ReadinessBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private VehicleCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->category = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
    }

    private function vehicle(string $plate, array $over = []): Vehicle
    {
        return Vehicle::create($over + [
            'vehicle_name' => "Truck {$plate}", 'plate_number' => $plate, 'category_id' => $this->category->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot',
        ]);
    }

    #[Test]
    public function a_bulk_confirm_checks_the_ready_ones_and_skips_the_blocked_one_with_its_reason(): void
    {
        $a = $this->vehicle('BLK 1001');
        $b = $this->vehicle('BLK 1002');
        $blocked = $this->vehicle('BLK 1003', ['condition' => 'Needs Repair']);
        Sanctum::actingAs($this->custodian, ['*']);

        $response = $this->postJson('/api/vehicles/readiness-check-bulk', [
            'vehicle_ids' => [$a->vehicle_id, $b->vehicle_id, $blocked->vehicle_id], 'confirmed' => true,
        ])->assertOk();

        $this->assertCount(2, $response->json('confirmed'));
        $this->assertCount(1, $response->json('skipped'));
        $this->assertSame($blocked->vehicle_id, $response->json('skipped.0.vehicle_id'));
        $this->assertStringContainsString('Needs Repair', $response->json('skipped.0.reason'));
        $this->assertDatabaseCount('vehicle_readiness_checks', 2);
    }

    #[Test]
    public function a_bulk_confirm_needs_the_confirmation_and_the_custodian_role(): void
    {
        $a = $this->vehicle('BLK 2001');
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson('/api/vehicles/readiness-check-bulk', ['vehicle_ids' => [$a->vehicle_id]])->assertUnprocessable();
        $this->postJson('/api/vehicles/readiness-check-bulk', ['vehicle_ids' => [], 'confirmed' => true])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]), ['*']);
        $this->postJson('/api/vehicles/readiness-check-bulk', ['vehicle_ids' => [$a->vehicle_id], 'confirmed' => true])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]), ['*']);
        $this->postJson('/api/vehicles/readiness-check-bulk', ['vehicle_ids' => [$a->vehicle_id], 'confirmed' => true])->assertForbidden();
    }

    #[Test]
    public function the_vehicle_list_shows_who_checked_and_how_many_days_are_left(): void
    {
        $v = $this->vehicle('VLD 1001');
        VehicleReadinessCheck::create([
            'vehicle_id' => $v->vehicle_id, 'checked_by' => $this->custodian->id, 'checklist' => [['item' => 'ok', 'passed' => true]],
            'all_passed' => true, 'checked_at' => now()->subDays(5),
        ]);
        Sanctum::actingAs($this->custodian, ['*']);
        $row = collect($this->getJson('/api/vehicles')->assertOk()->json())->firstWhere('vehicle_id', $v->vehicle_id);

        $this->assertSame($this->custodian->name, $row['readiness_checked_by']);
        $this->assertSame('ready', $row['readiness_state']);
        $this->assertSame(2, $row['readiness_days_left']);
        $this->assertNotNull($row['readiness_valid_until']);
    }

    #[Test]
    public function the_daily_command_warns_custodians_only_about_checks_that_lapse_within_a_day(): void
    {
        $soon = $this->vehicle('EXP 1001');
        $fresh = $this->vehicle('EXP 1002');
        foreach ([[$soon, now()->subDays(6)->subHours(2)], [$fresh, now()->subDay()]] as [$vehicle, $at]) {
            VehicleReadinessCheck::create([
                'vehicle_id' => $vehicle->vehicle_id, 'checked_by' => $this->custodian->id, 'checklist' => [['item' => 'ok', 'passed' => true]],
                'all_passed' => true, 'checked_at' => $at,
            ]);
        }
        $this->artisan('readiness:warn-expiring')->assertExitCode(0);

        $notice = \App\Models\Notification::where('user_id', $this->custodian->id)->where('type', 'readiness_expiring')->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Truck EXP 1001', $notice->message);
        $this->assertStringNotContainsString('Truck EXP 1002', $notice->message);
    }
}
