<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\RegistrationSetting;
use App\Services\BarangayGeography;
use Database\Seeders\CitySeeder;
use Database\Seeders\PhilippineBarangaySeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class RegistrationGeographyTest extends TestCase
{
    use RefreshDatabase;

    private function cities(): array
    {
        $province = Province::create(['name' => 'Cebu', 'code' => '072200000']);
        return [
            City::create(['name' => 'Cebu City', 'code' => '072217000', 'province_id' => $province->id]),
            City::create(['name' => 'Mandaue City', 'code' => '072230000', 'province_id' => $province->id]),
        ];
    }

    private function payload(City $city, Barangay $barangay): array
    {
        return [
            'name' => 'Geography Tester', 'email' => 'geography@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'phone' => '09171234567', 'address' => 'Test Street',
            'province_id' => $city->province_id, 'city_id' => $city->id,
            'barangay_id' => $barangay->id,
            'staff_code' => RegistrationSetting::for($barangay->id)->staff_code,
        ];
    }

    public function test_registration_cannot_create_a_barangay_from_free_text(): void
    {
        [$city] = $this->cities();
        $this->postJson('/api/register', [
            'name' => 'Geography Tester', 'email' => 'geography@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'phone' => '09171234567', 'address' => 'Test Street',
            'city_id' => $city->id, 'barangay_name' => 'Invented Barangay', 'staff_code' => 'INVALID',
        ])->assertUnprocessable()->assertJsonValidationErrors(['barangay_id', 'barangay_name']);
        $this->assertDatabaseCount('barangays', 0);
    }

    public function test_same_named_barangays_have_distinct_code_matched_boundaries(): void
    {
        [$cebu, $mandaue] = $this->cities();
        $a = Barangay::create(['name' => 'Banilad', 'city_id' => $cebu->id, 'psgc_code' => '0730600007']);
        $b = Barangay::create(['name' => 'Banilad', 'city_id' => $mandaue->id, 'psgc_code' => '0731300003']);
        $first = $this->getJson('/api/barangays/'.$a->id)->assertOk()->json('boundary');
        $second = $this->getJson('/api/barangays/'.$b->id)->assertOk()->json('boundary');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotEquals($first, $second);
        $this->getJson('/api/barangays?city_id='.$cebu->id)->assertJsonCount(1)->assertJsonPath('0.id', $a->id);
    }

    public function test_registration_rejects_wrong_city_province_and_non_reference_ids(): void
    {
        [$cebu, $mandaue] = $this->cities();
        $barangay = Barangay::create(['name' => 'Banilad', 'city_id' => $mandaue->id, 'psgc_code' => '0731300003']);
        $payload = $this->payload($cebu, $barangay);
        $this->postJson('/api/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('barangay_id');
        $this->getJson('/api/registration-status?city_id='.$cebu->id.'&barangay_id='.$barangay->id)
            ->assertUnprocessable()->assertJsonValidationErrors('barangay_id');
        $other = Province::create(['name' => 'Other Province', 'code' => '990000000']);
        $payload = $this->payload($mandaue, $barangay);
        $payload['province_id'] = $other->id;
        $this->postJson('/api/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $legacy = Barangay::create(['name' => 'Unknown Legacy Name', 'city_id' => $cebu->id]);
        $this->postJson('/api/register', $this->payload($cebu, $legacy))->assertUnprocessable()->assertJsonValidationErrors('barangay_id');
        $this->assertDatabaseMissing('users', ['email' => 'geography@example.com']);
    }

    public function test_successful_registration_attaches_the_selected_boundary_and_requires_approval(): void
    {
        [$cebu] = $this->cities();
        $barangay = Barangay::create(['name' => 'Banilad', 'city_id' => $cebu->id, 'psgc_code' => '0730600007']);
        $this->postJson('/api/register', $this->payload($cebu, $barangay))->assertCreated();
        $this->assertDatabaseHas('users', ['barangay_id' => $barangay->id, 'city_id' => $cebu->id, 'is_active' => false]);
        $this->assertNotNull($barangay->fresh()->boundary);
        $this->assertSame(BarangayGeography::SOURCE, $barangay->fresh()->boundary_source);
        $this->assertSame('needs_review', $barangay->fresh()->boundary_status);
        $this->assertNull($barangay->fresh()->boundary_verified_at);
        $this->getJson('/api/barangays/'.$barangay->id)->assertJsonPath('boundary_verified', false);
    }

    public function test_unknown_boundaries_never_fall_back_to_another_barangay(): void
    {
        [$cebu] = $this->cities();
        $barangay = Barangay::create(['name' => 'Unknown', 'city_id' => $cebu->id, 'psgc_code' => '9999999999']);
        $this->getJson('/api/barangays/'.$barangay->id)->assertOk()->assertJsonPath('boundary', null);
    }

    public function test_suba_registration_and_existing_accounts_use_detailed_boundaries(): void
    {
        $province = Province::create(['name' => 'Cebu', 'code' => '072200000']);
        $city = City::create(['name' => 'Bantayan', 'code' => '072209000', 'province_id' => $province->id]);
        $triangle = ['type' => 'Polygon', 'coordinates' => [[[123.716802, 11.166481], [123.721854, 11.161616], [123.714108, 11.164751], [123.716802, 11.166481]]]];
        $barangay = Barangay::create(['name' => 'Suba (Pob.)', 'city_id' => $city->id, 'psgc_code' => '0702209022', 'boundary' => $triangle, 'boundary_source' => BarangayGeography::LEGACY_SOURCE]);
        $geometry = $this->getJson('/api/barangays/'.$barangay->id)->assertOk()
            ->assertJsonPath('boundary_source', BarangayGeography::SOURCE)->json('boundary');
        $this->assertSame('Polygon', $geometry['type']);
        $this->assertGreaterThan(1000, count($geometry['coordinates'][0]), 'Suba must retain its coastline and street-level detail.');
        $this->assertNotEquals($triangle, $geometry);
        $this->postJson('/api/register', $this->payload($city, $barangay))->assertCreated();
        $this->assertSame($geometry, $barangay->fresh()->boundary);
        $this->assertSame(BarangayGeography::SOURCE, $barangay->fresh()->boundary_source);

        // Deployment repair covers existing automatic assignments, without changing tenant IDs or codes.
        $barangay->update(['boundary' => $triangle, 'boundary_source' => BarangayGeography::LEGACY_SOURCE]);
        $code = RegistrationSetting::for($barangay->id)->staff_code;
        $this->artisan('geography:refresh-boundaries')->assertSuccessful();
        $this->assertSame($geometry, $barangay->fresh()->boundary);
        $this->assertSame($code, RegistrationSetting::for($barangay->id)->staff_code);
    }

    public function test_manually_confirmed_boundaries_are_preserved_during_reference_upgrades(): void
    {
        [$city] = $this->cities();
        $reviewer = \App\Models\User::factory()->create(['role' => 'Super Admin']);
        $geometry = ['type' => 'Polygon', 'coordinates' => [[[123, 10], [124, 10], [124, 11], [123, 10]]]];
        $barangay = Barangay::create(['name' => 'Banilad', 'city_id' => $city->id, 'psgc_code' => '0730600007', 'boundary' => $geometry, 'boundary_source' => BarangayGeography::LEGACY_SOURCE, 'boundary_verified_by' => $reviewer->id]);
        $this->getJson('/api/barangays/'.$barangay->id)->assertOk()->assertJsonPath('boundary', $geometry);
        app(BarangayGeography::class)->attachBoundary($barangay);
        $this->artisan('geography:refresh-boundaries')->assertSuccessful();
        $this->assertSame($geometry, $barangay->fresh()->boundary);
    }

    public function test_nationwide_seed_is_repeatable_and_preserves_existing_tenant_ids_and_geometry(): void
    {
        $this->seed([ProvinceSeeder::class, CitySeeder::class]);
        $city = City::where('code', '072230000')->firstOrFail();
        $geometry = ['type' => 'Polygon', 'coordinates' => [[[123, 10], [124, 10], [124, 11], [123, 10]]]];
        $existing = Barangay::create(['name' => 'Paknaan', 'city_id' => $city->id, 'boundary' => $geometry]);
        $code = RegistrationSetting::for($existing->id)->staff_code;
        $this->seed(PhilippineBarangaySeeder::class);
        $this->assertSame('0731300020', $existing->fresh()->psgc_code);
        $this->assertSame($geometry, $existing->fresh()->boundary);
        $this->assertSame($code, RegistrationSetting::for($existing->id)->staff_code);
        $this->assertSame(80, Barangay::where('city_id', City::where('code', '072217000')->value('id'))->whereNotNull('psgc_code')->count());
        $this->assertSame(42046, Barangay::whereNotNull('psgc_code')->count());
        $this->seed(PhilippineBarangaySeeder::class);
        $this->assertSame(42046, Barangay::whereNotNull('psgc_code')->count());
    }
}
