<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ConcernLocationTest extends TestCase {
    use RefreshDatabase;
    public function test_public_concern_retains_manual_location_without_a_registered_account(): void {
        $this->postJson('/api/concern-reports', ['concern_type' => 'Other', 'province_name' => 'Cebu', 'city_name' => 'Mandaue City', 'barangay_name' => 'Paknaan', 'description' => 'QA concern'])->assertCreated();
        $this->assertDatabaseHas('concern_reports', ['province_name' => 'Cebu', 'city_name' => 'Mandaue City', 'barangay_name' => 'Paknaan', 'description' => 'QA concern']);
    }
    public function test_location_remains_optional_and_oversized_names_are_rejected(): void {
        $this->postJson('/api/concern-reports', ['concern_type' => 'Other', 'description' => 'Location unknown'])->assertCreated();
        $this->postJson('/api/concern-reports', ['concern_type' => 'Other', 'description' => 'QA', 'province_name' => str_repeat('x', 256)])->assertUnprocessable();
        $this->assertDatabaseCount('concern_reports', 1);
    }
}
