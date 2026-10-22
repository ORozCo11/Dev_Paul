<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Account switching must preserve role, environment, and read-only boundaries.
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_and_ordinary_staff_cannot_start_impersonation(): void
    {
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->getJson('/api/impersonate/candidates')->assertUnauthorized();
        $this->postJson("/api/impersonate/{$target->id}", [])->assertUnauthorized();
        foreach (['Custodian', 'Maintenance Personnel'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'roles' => [$role]]);
            Sanctum::actingAs($actor, ['*']);
            $this->getJson('/api/impersonate/candidates')->assertForbidden();
            $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Inspect a reported problem'])->assertForbidden();
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function session_metadata_distinguishes_direct_logins_from_impersonation(): void
    {
        foreach (['Admin', 'Custodian', 'Maintenance Personnel', 'Super Admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'roles' => [$role]]);
            $token = $user->createToken('auth_token', ['*'])->plainTextToken;
            $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')
                ->assertOk()->assertJsonPath('is_impersonating', false);
            app('auth')->forgetGuards();
            $token = $this->impersonationToken($user);
            $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')
                ->assertOk()->assertJsonPath('is_impersonating', true);
            app('auth')->forgetGuards();
        }
    }

    #[Test]
    public function dedicated_switcher_can_open_staff_and_super_admin_accounts_with_an_audited_reason(): void
    {
        app()['env'] = 'production';
        $actor = User::factory()->create(['email' => 'impersonation@test.com', 'role' => 'Impersonator', 'roles' => ['Impersonator']]);
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->getJson('/api/impersonate/candidates')->assertOk()->assertJsonFragment(['id' => $target->id]);
        $this->postJson("/api/impersonate/{$target->id}", [])->assertUnprocessable();
        $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Investigate reported account issue'])->assertOk()->assertJsonPath('user.id', $target->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Impersonate', 'user_id' => $actor->id]);
        $this->postJson("/api/impersonate/{$actor->id}", ['reason' => 'Attempt elevated access'])->assertForbidden();
    }

    #[Test]
    public function super_admin_can_impersonate_staff_but_not_another_super_admin(): void
    {
        $super = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]);
        $other = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]);
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->assertContains('impersonation.start', $super->getAbilities());
        Sanctum::actingAs($super, $super->allRoles());
        $this->getJson('/api/impersonate/candidates')->assertOk()
            ->assertJsonFragment(['id' => $target->id])->assertJsonMissing(['id' => $other->id]);
        $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Investigate reported account issue'])->assertOk();
        $this->postJson("/api/impersonate/{$other->id}", ['reason' => 'Attempt elevated access'])->assertForbidden();
    }

    #[Test]
    public function only_the_dedicated_login_can_start_impersonation_in_every_environment(): void
    {
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $blocked = collect([
            ['email' => 'admin@barangay.gov', 'role' => 'Admin'],
            ['email' => 'other@test.com', 'role' => 'Impersonator'],
        ])->map(fn ($attributes) => User::factory()->create($attributes + ['roles' => [$attributes['role']]]));
        $actor = User::factory()->create(['email' => 'impersonation@test.com', 'role' => 'Impersonator', 'roles' => ['Impersonator']]);
        foreach (['local', 'testing', 'production'] as $environment) {
            app()['env'] = $environment;
            Sanctum::actingAs($actor, $actor->allRoles());
            $this->getJson('/api/impersonate/candidates')->assertOk();
            $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Check account workspace'])->assertOk();
            foreach ($blocked as $account) {
                Sanctum::actingAs($account, $account->allRoles());
                $this->assertNotContains('impersonation.start', $account->getAbilities());
                $this->getJson('/api/impersonate/candidates')->assertForbidden();
                $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Try switching accounts'])->assertForbidden();
            }
        }
        $actor->update(['role' => 'Admin', 'roles' => ['Admin']]);
        $this->assertFalse($actor->canDo('impersonation.start'));
    }

    #[Test]
    public function switcher_seeder_provides_the_requested_login_and_no_direct_administrative_access(): void
    {
        $before = User::count();
        $this->seed(\Database\Seeders\ImpersonationAccountSeeder::class);
        $this->seed(\Database\Seeders\ImpersonationAccountSeeder::class);
        $this->assertDatabaseCount('users', $before + 1);
        $data = $this->postJson('/api/login', ['email' => 'impersonation@test.com', 'password' => 'test123'])
            ->assertOk()->assertJsonPath('user.role', 'Impersonator')
            ->assertJsonPath('user.abilities', ['impersonation.start'])->json();
        $this->withHeader('Authorization', 'Bearer '.$data['access_token'])->getJson('/api/vehicles')->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$data['access_token'])->getJson('/api/superadmin/barangays')->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$data['access_token'])->getJson('/api/user')->assertOk();
    }

    #[Test]
    public function switcher_candidates_cover_every_barangay_and_platform_accounts(): void
    {
        $a = \App\Models\Barangay::create(['name' => 'First Barangay']);
        $b = \App\Models\Barangay::create(['name' => 'Second Barangay']);
        $first = User::factory()->create(['role' => 'Admin', 'barangay_id' => $a->id]);
        $second = User::factory()->create(['role' => 'Custodian', 'barangay_id' => $b->id]);
        $superAdmin = User::where('email', 'superadmin@barangay.gov')->firstOrFail();
        $actor = User::factory()->create(['email' => 'impersonation@test.com', 'role' => 'Impersonator', 'roles' => ['Impersonator']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->getJson('/api/impersonate/candidates')->assertOk()
            ->assertJsonFragment(['id' => $first->id])->assertJsonFragment(['id' => $second->id])
            ->assertJsonFragment(['id' => $superAdmin->id])->assertJsonMissing(['id' => $actor->id]);
        foreach ([$first, $second, $superAdmin] as $target) {
            $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Inspect selected workspace'])
                ->assertOk()->assertJsonPath('user.id', $target->id);
        }
    }

    #[Test]
    public function switched_accounts_cannot_start_nested_sessions_even_locally(): void
    {
        app()['env'] = 'local';
        $target = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $other = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $token = $this->impersonationToken($target);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/impersonate/candidates')->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/impersonate/{$other->id}", ['reason' => 'Try another switch'])->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();
    }

    #[Test]
    public function default_workspace_prefers_the_demo_admin_and_falls_back_to_an_active_barangay_admin(): void
    {
        $barangay = \App\Models\Barangay::create(['name' => 'Paknaan']);
        $fallback = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $barangay->id]);
        $preferred = User::factory()->create(['email' => 'admin@barangay.gov', 'role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $barangay->id]);
        $actor = User::factory()->create(['email' => 'impersonation@test.com', 'role' => 'Impersonator', 'roles' => ['Impersonator']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->postJson('/api/impersonate/default')->assertOk()->assertJsonPath('user.id', $preferred->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Impersonate', 'user_id' => $actor->id, 'barangay_id' => $barangay->id]);
        $preferred->update(['is_active' => false]);
        $this->postJson('/api/impersonate/default')->assertOk()->assertJsonPath('user.id', $fallback->id);
    }

    #[Test]
    public function default_workspace_never_uses_an_unassigned_or_inactive_admin_or_a_super_admin(): void
    {
        $barangay = \App\Models\Barangay::create(['name' => 'Test Barangay']);
        User::factory()->create(['role' => 'Admin', 'barangay_id' => null]);
        User::factory()->create(['role' => 'Admin', 'barangay_id' => $barangay->id, 'is_active' => false]);
        $actor = User::factory()->create(['email' => 'impersonation@test.com', 'role' => 'Impersonator', 'roles' => ['Impersonator']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->postJson('/api/impersonate/default')->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function default_workspace_requires_the_dedicated_login(): void
    {
        $this->postJson('/api/impersonate/default')->assertUnauthorized();
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        Sanctum::actingAs($admin, $admin->allRoles());
        $this->postJson('/api/impersonate/default')->assertForbidden();
    }

    private function impersonationToken(User $user): string
    {
        return $user->createToken('impersonation_token', [...$user->allRoles(), 'impersonated'], now()->addMinutes(30))->plainTextToken;
    }

    #[Test]
    public function an_impersonated_session_cannot_write_but_can_still_read(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $token = $this->impersonationToken($custodian);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $custodian->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/issues', ['vehicle_id' => 999, 'issue_type' => 'x', 'issue_description' => 'x', 'severity_level' => 'Low'])
            ->assertStatus(403);
    }

    #[Test]
    public function a_legacy_impersonated_session_can_log_out_but_cannot_switch_accounts(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]);
        $token = $this->impersonationToken($custodian);

        // Existing sessions can exit, but cannot start another impersonation.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/impersonate/{$mechanic->id}", ['reason' => 'Same ticket, different account'])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();
    }

    #[Test]
    public function returning_to_self_via_logout_logs_an_end_entry(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $token = $this->impersonationToken($custodian);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Impersonation Ended',
            'user_id' => $custodian->id,
        ]);
    }

    #[Test]
    public function an_ordinary_logout_does_not_log_an_impersonation_end_entry(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);

        // Deliberately NOT Sanctum::actingAs($custodian, ['*']) — this
        // suite's usual convention for "any authenticated user", but the
        // wildcard '*' ability it grants would itself satisfy
        // can('impersonated') and defeat the point of this test. A real
        // login token only ever carries the user's own role names (see
        // AuthController::login()), never '*'.
        Sanctum::actingAs($custodian, $custodian->allRoles());
        $this->postJson('/api/logout')->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['action' => 'Impersonation Ended']);
    }
}
