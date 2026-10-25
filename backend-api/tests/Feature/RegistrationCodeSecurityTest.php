<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Barangay, City, Province, RegistrationSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationCodeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Barangay $barangay;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['code' => 'QA', 'name' => 'QA Province']);
        $city = City::create(['code' => 'QA-CITY', 'name' => 'QA City', 'province_id' => $province->id]);
        $this->barangay = Barangay::create(['name' => 'QA Barangay', 'city_id' => $city->id, 'psgc_code' => '9999900001',
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[123, 10], [124, 10], [124, 11], [123, 10]]]]]);
        $this->superAdmin = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'barangay_id' => null]);
        Sanctum::actingAs($this->superAdmin, ['*']);
        $this->url = "/api/superadmin/barangays/{$this->barangay->id}/registration-code";
    }

    public function test_viewing_reports_when_the_code_was_issued_and_last_changed(): void
    {
        $this->getJson($this->url)->assertOk()
            ->assertJsonStructure(['staff_code', 'created_at', 'updated_at']);
    }

    public function test_issuing_a_first_code_is_recorded_but_viewing_again_is_not(): void
    {
        $this->getJson($this->url)->assertOk();
        $this->getJson($this->url)->assertOk();

        $entries = ActivityLog::where('details', 'like', '%registration code for QA Barangay%')->get();
        $this->assertCount(1, $entries);
        $this->assertStringStartsWith('Issued', $entries->first()->details);
    }

    public function test_audit_entries_never_contain_the_code_itself(): void
    {
        $code = $this->getJson($this->url)->json('staff_code');
        $newCode = $this->postJson("{$this->url}/regenerate")->assertOk()->json('staff_code');

        foreach (ActivityLog::pluck('details') as $details) {
            $this->assertStringNotContainsString($code, $details);
            $this->assertStringNotContainsString($newCode, $details);
        }
    }

    public function test_regenerating_against_a_stale_version_is_refused_and_keeps_the_newer_code(): void
    {
        $stale = $this->getJson($this->url)->json('updated_at');
        $this->travel(5)->seconds();
        $newer = $this->postJson("{$this->url}/regenerate")->assertOk()->json('staff_code');
        $this->travel(5)->seconds();

        $this->postJson("{$this->url}/regenerate", ['expected_updated_at' => $stale])
            ->assertStatus(409);
        $this->assertSame($newer, RegistrationSetting::for($this->barangay->id)->staff_code);
    }

    public function test_regenerating_against_the_current_version_succeeds(): void
    {
        $current = $this->getJson($this->url)->json();

        $fresh = $this->postJson("{$this->url}/regenerate", ['expected_updated_at' => $current['updated_at']])->assertOk()->json();
        $this->assertNotSame($current['staff_code'], $fresh['staff_code']);
    }

    public function test_regeneration_is_rate_limited(): void
    {
        $this->getJson($this->url)->assertOk();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson("{$this->url}/regenerate")->assertOk();
        }

        $this->postJson("{$this->url}/regenerate")->assertStatus(429);
    }

    public function test_barangay_admin_regeneration_is_rate_limited_and_audited(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $this->barangay->id, 'approved_at' => now(), 'is_active' => true]);
        Sanctum::actingAs($admin, ['*']);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/registration-settings/regenerate')->assertOk();
        }
        $this->postJson('/api/registration-settings/regenerate')->assertStatus(429);

        $this->assertSame(10, ActivityLog::where('user_id', $admin->id)->where('details', 'like', 'Regenerated the staff registration code%')->count());
    }

    public function test_only_a_super_admin_can_view_or_regenerate_through_the_platform_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $this->barangay->id, 'approved_at' => now(), 'is_active' => true]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson($this->url)->assertForbidden();
        $this->postJson("{$this->url}/regenerate")->assertForbidden();
    }
}
