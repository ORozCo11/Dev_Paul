<?php

namespace Tests\Feature;

use App\Models\{Barangay, City, Province, RegistrationSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegisteredBarangaysOnlyTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['code' => 'QA', 'name' => 'QA Province']);
        $this->city = City::create(['code' => 'QA-CITY', 'name' => 'QA City', 'province_id' => $province->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'barangay_id' => null]), ['*']);
    }

    private function reference(string $name, string $psgc): Barangay
    {
        return Barangay::create(['name' => $name, 'city_id' => $this->city->id, 'psgc_code' => $psgc]);
    }

    private function listedNames(): array
    {
        return collect($this->getJson('/api/superadmin/barangays')->assertOk()->json())->pluck('name')->sort()->values()->all();
    }

    public function test_a_reference_barangay_with_only_a_pre_seeded_code_is_not_registered(): void
    {
        $seeded = $this->reference('Seeded Only', '9999900001');
        RegistrationSetting::for($seeded->id);

        $this->assertNotContains('Seeded Only', $this->listedNames());
    }

    public function test_provisioned_staffed_and_manual_barangays_are_registered(): void
    {
        $this->reference('Provisioned', '9999900002')->update(['provisioned_at' => now()]);
        $staffed = $this->reference('Staffed', '9999900003');
        User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $staffed->id]);
        Barangay::create(['name' => 'Manual', 'city_id' => $this->city->id]);
        $this->reference('Untouched Reference', '9999900004');

        $this->assertSame(['Manual', 'Provisioned', 'Staffed'], $this->listedNames());
    }
}
