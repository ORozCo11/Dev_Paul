<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Super Admin user search dropped the typed text straight into a LIKE
 * pattern, so "%" and "_" acted as wildcards and matched every account.
 * They're now escaped the same way activityLog()'s search already does.
 */
class SuperAdminUserSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $q): array
    {
        Sanctum::actingAs(User::firstWhere('email', 'owner@vms.test')
            ?? User::factory()->create(['name' => 'Platform Owner', 'email' => 'owner@vms.test', 'role' => 'Super Admin', 'roles' => ['Super Admin']]), ['*']);

        return collect($this->getJson('/api/superadmin/users?q='.urlencode($q))->assertOk()->json())->pluck('email')->all();
    }

    #[Test]
    public function wildcard_characters_are_matched_literally(): void
    {
        User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana@vms.test']);
        User::factory()->create(['name' => 'Ben Cruz', 'email' => 'ben@vms.test']);

        $this->assertSame([], $this->search('%'));
        $this->assertSame([], $this->search('_'));
    }

    #[Test]
    public function an_underscore_in_an_email_is_still_found(): void
    {
        User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana_reyes@vms.test']);
        User::factory()->create(['name' => 'Ben Cruz', 'email' => 'anaxreyes@vms.test']);

        $this->assertSame(['ana_reyes@vms.test'], $this->search('ana_reyes'));
    }
}
