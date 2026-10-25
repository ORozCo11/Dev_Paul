<?php
namespace Tests\Feature;
use App\Models\{Barangay, City, Province};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class AddressSuggestionTest extends TestCase {
    use RefreshDatabase;
    public function test_public_map_suggestions_use_selected_location_and_ignore_foreign_results(): void {
        $province = Province::create(['name' => 'Cebu']);
        $city = City::create(['name' => 'Mandaue City', 'province_id' => $province->id]);
        $barangay = Barangay::create(['name' => 'Paknaan', 'city_id' => $city->id]);
        Http::fake(['*' => Http::sequence()->push(['features' => [
            ['properties' => ['name' => 'Sample Street', 'city' => 'Mandaue City', 'countrycode' => 'PH']],
            ['properties' => ['name' => 'Foreign Street', 'countrycode' => 'US']],
        ]])->push([], 503)]);
        $this->getJson('/api/address-suggestions?barangay_id='.$barangay->id.'&q=Sample')->assertOk()->assertExactJson(['Sample Street, Mandaue City']);
        Http::assertSent(fn ($request) => $request['countrycode'] === 'PH' && str_contains($request['q'], 'Paknaan, Mandaue City, Cebu'));
        $this->getJson('/api/address-suggestions?barangay_id='.$barangay->id.'&q=Sample')->assertOk()->assertExactJson([]);
    }
    public function test_invalid_queries_do_not_call_the_provider(): void {
        Http::fake();
        $this->getJson('/api/address-suggestions?q=ab')->assertUnprocessable();
        Http::assertNothingSent();
    }
}
