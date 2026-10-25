<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Super Admin overview dates each "Review candidate boundary" item from
 * the barangay's updated_at, but barangays() never returned that column, so
 * every item read "Date not recorded".
 */
class BoundaryReviewDateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_barangay_list_includes_when_each_barangay_last_changed(): void
    {
        $province = Province::create(['code' => 'TEST', 'name' => 'Test Province']);
        $city = City::create(['province_id' => $province->id, 'code' => 'TESTCITY', 'name' => 'Test City']);
        $barangay = Barangay::create(['city_id' => $city->id, 'name' => 'Manually added']);
        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]), ['*']);

        $row = collect($this->getJson('/api/superadmin/barangays')->assertOk()->json())->firstWhere('id', $barangay->id);

        $this->assertNotNull($row['updated_at'] ?? null);
    }
}
