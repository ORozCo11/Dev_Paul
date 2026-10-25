<?php

namespace Tests\Feature;

use App\Models\{Barangay, City, Province, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NationwideBarangaySearchTest extends TestCase
{
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Central Visayas (07), NCR (13, grouped under a pseudo-province) and Davao (11).
        $cebu = Province::create(['code' => '072200000', 'name' => 'Cebu']);
        $metro = Province::create(['code' => 'PSEUDO-NCR', 'name' => 'Metro Manila']);
        $davao = Province::create(['code' => '112400000', 'name' => 'Davao del Sur']);
        $mandaue = City::create(['code' => '072230000', 'name' => 'Mandaue City', 'province_id' => $cebu->id]);
        $cebuCity = City::create(['code' => '072217000', 'name' => 'Cebu City', 'province_id' => $cebu->id]);
        $makati = City::create(['code' => '137602000', 'name' => 'Makati City', 'province_id' => $metro->id]);
        $davaoCity = City::create(['code' => '112402000', 'name' => 'Davao City', 'province_id' => $davao->id]);

        $this->ids['banilad_mandaue'] = $this->registered('Banilad', $mandaue, '0722300003');
        $this->ids['banilad_cebu'] = $this->registered('Banilad', $cebuCity, '0722170010');
        $this->ids['poblacion_makati'] = $this->registered('Poblacion', $makati, '1376020020');
        $this->ids['talomo_davao'] = $this->registered('Talomo', $davaoCity, '1124020099');
        // Reference-only PSGC rows are never registered.
        Barangay::create(['name' => 'Bakilid', 'city_id' => $mandaue->id, 'psgc_code' => '0722300002']);
        Barangay::create(['name' => 'Agdao', 'city_id' => $davaoCity->id, 'psgc_code' => '1124020001']);

        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'barangay_id' => null]), ['*']);
    }

    private function registered(string $name, City $city, string $psgc): int
    {
        return Barangay::create(['name' => $name, 'city_id' => $city->id, 'psgc_code' => $psgc, 'provisioned_at' => now()])->id;
    }

    private function search(array $params = []): array
    {
        return $this->getJson('/api/superadmin/barangays/search?'.http_build_query($params))->assertOk()->json();
    }

    public function test_lists_registered_barangays_nationwide_with_full_location(): void
    {
        $page = $this->search();

        $this->assertSame(4, $page['total']);
        $this->assertEqualsCanonicalizing(array_values($this->ids), array_column($page['data'], 'id'));
        $makati = collect($page['data'])->firstWhere('id', $this->ids['poblacion_makati']);
        $this->assertSame('Makati City', $makati['city_name']);
        $this->assertSame('Metro Manila', $makati['province_name']);
        $this->assertSame('13', $makati['region_code']);
        $this->assertSame('NCR', $makati['region_name']);
        $this->assertSame('1376020020', $makati['psgc_code']);
        $this->assertArrayHasKey('has_registration_code', $makati);
        $this->assertArrayHasKey('has_boundary', $makati);
        $this->assertArrayHasKey('has_pending_boundary', $makati);
        $this->assertArrayNotHasKey('staff_code', $makati);
    }

    public function test_search_matches_barangay_city_province_and_psgc(): void
    {
        $this->assertSame([$this->ids['talomo_davao']], array_column($this->search(['q' => 'talomo'])['data'], 'id'));
        $this->assertSame([$this->ids['poblacion_makati']], array_column($this->search(['q' => 'makati'])['data'], 'id'));
        $this->assertSame(2, $this->search(['q' => 'cebu'])['total']);
        $this->assertSame([$this->ids['banilad_cebu']], array_column($this->search(['q' => '0722170010'])['data'], 'id'));
        $this->assertSame(0, $this->search(['q' => '%'])['total']);
    }

    public function test_duplicate_names_stay_distinct_by_location(): void
    {
        $rows = collect($this->search(['q' => 'banilad'])['data']);

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['Mandaue City', 'Cebu City'], $rows->pluck('city_name')->all());
    }

    public function test_filters_by_region_province_city_and_barangay_ids(): void
    {
        $this->assertSame([$this->ids['poblacion_makati']], array_column($this->search(['region' => '13'])['data'], 'id'));
        $cebuProvince = Province::where('name', 'Cebu')->value('id');
        $this->assertSame(2, $this->search(['province_id' => $cebuProvince])['total']);
        $cebuCity = City::where('name', 'Cebu City')->value('id');
        $this->assertSame([$this->ids['banilad_cebu']], array_column($this->search(['city_id' => $cebuCity])['data'], 'id'));
        $this->assertSame([$this->ids['talomo_davao']], array_column($this->search(['barangay_id' => $this->ids['talomo_davao']])['data'], 'id'));
    }

    public function test_results_are_paginated_server_side(): void
    {
        $first = $this->search(['per_page' => 10]);
        $this->assertSame(1, $first['last_page']);
        $this->getJson('/api/superadmin/barangays/search?per_page=1000')->assertUnprocessable();
        $this->getJson('/api/superadmin/barangays/search?region=ABC')->assertUnprocessable();
    }

    public function test_location_options_only_offer_places_with_registered_barangays(): void
    {
        $all = $this->getJson('/api/superadmin/barangays/locations')->assertOk()->json();
        $this->assertSame(['07', '11', '13'], array_column($all['regions'], 'code'));
        $this->assertSame('Central Visayas', collect($all['regions'])->firstWhere('code', '07')['name']);
        $this->assertEqualsCanonicalizing(['Cebu', 'Davao del Sur', 'Metro Manila'], array_column($all['provinces'], 'name'));

        $ncr = $this->getJson('/api/superadmin/barangays/locations?region=13')->json();
        $this->assertSame(['Metro Manila'], array_column($ncr['provinces'], 'name'));

        $cebuProvince = Province::where('name', 'Cebu')->value('id');
        $cebu = $this->getJson("/api/superadmin/barangays/locations?province_id={$cebuProvince}")->json();
        $this->assertEqualsCanonicalizing(['Cebu City', 'Mandaue City'], array_column($cebu['cities'], 'name'));

        $davaoCity = City::where('name', 'Davao City')->value('id');
        $davao = $this->getJson("/api/superadmin/barangays/locations?city_id={$davaoCity}")->json();
        $this->assertSame(['Talomo'], array_column($davao['barangays'], 'name'));
    }

    public function test_only_a_super_admin_can_search(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $this->ids['talomo_davao'], 'approved_at' => now()]), ['*']);

        $this->getJson('/api/superadmin/barangays/search')->assertForbidden();
        $this->getJson('/api/superadmin/barangays/locations')->assertForbidden();
    }
}
