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
    public function super_admin_can_switch_accounts_in_production_with_an_audited_reason(): void
    {
        app()['env'] = 'production';
        $actor = User::firstOrCreate(['email' => 'superadmin@barangay.gov'], ['name' => 'Super Admin', 'password' => bcrypt('secret123'), 'role' => 'Super Admin', 'roles' => ['Super Admin']]);
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->getJson('/api/impersonate/candidates')->assertOk()->assertJsonFragment(['id' => $target->id]);
        $this->postJson("/api/impersonate/{$target->id}", [])->assertUnprocessable();
        $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Investigate reported account issue'])->assertOk()->assertJsonPath('user.id', $target->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Impersonate', 'user_id' => $actor->id]);
        $this->postJson("/api/impersonate/{$actor->id}", ['reason' => 'Attempt elevated access'])->assertForbidden();
    }

    #[Test]
    public function only_the_two_designated_super_admin_accounts_can_impersonate_in_every_environment(): void
    {
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $allowed = collect(['superadmin@test.com', 'superadmin@barangay.gov'])->map(fn ($email) =>
            User::firstOrCreate(['email' => $email], ['name' => 'Super Admin', 'password' => bcrypt('secret123'), 'role' => 'Super Admin', 'roles' => ['Super Admin']]));
        $blocked = collect([
            ['email' => 'admin@barangay.gov', 'role' => 'Admin'],
            ['email' => 'other-superadmin@example.com', 'role' => 'Super Admin'],
            ['email' => 'superadmin@test.com.attacker.example', 'role' => 'Super Admin'],
        ])->map(fn ($attributes) => User::factory()->create($attributes + ['roles' => [$attributes['role']]]));
        foreach (['local', 'testing', 'production'] as $environment) {
            app()['env'] = $environment;
            foreach ($allowed as $actor) {
                Sanctum::actingAs($actor, $actor->allRoles());
                $this->getJson('/api/user')->assertOk()->assertJsonFragment(['impersonation.start']);
                $this->getJson('/api/impersonate/candidates')->assertOk();
                $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Check a reported issue'])->assertOk();
            }
            foreach ($blocked as $actor) {
                Sanctum::actingAs($actor, $actor->allRoles());
                $this->assertNotContains('impersonation.start', $actor->getAbilities());
                $this->getJson('/api/impersonate/candidates')->assertForbidden();
                $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Attempt account switch'])->assertForbidden();
            }
        }
        // An allowed email is insufficient without the Super Admin role.
        $actor = $allowed->first();
        $actor->update(['role' => 'Admin', 'roles' => ['Admin']]);
        Sanctum::actingAs($actor, $actor->allRoles());
        $this->assertFalse($actor->canDo('impersonation.start'));
        $this->getJson('/api/impersonate/candidates')->assertForbidden();
        $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Attempt account switch'])->assertForbidden();
    }

    #[Test]
    public function impersonated_accounts_cannot_chain_sessions_even_locally(): void
    {
        app()['env'] = 'local';
        $actor = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $token = $this->impersonationToken($actor);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/impersonate/candidates')->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/impersonate/{$target->id}", ['reason' => 'Attempt nested switch'])->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertOk();
    }

    #[Test]
    public function test_super_admin_seeder_sets_requested_login_without_removing_other_accounts(): void
    {
        $other = User::factory()->create(['role' => 'Admin']);
        $before = User::count();
        $this->seed(\Database\Seeders\TestSuperAdminSeeder::class);
        $this->seed(\Database\Seeders\TestSuperAdminSeeder::class);
        $this->assertDatabaseCount('users', $before + 1);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->postJson('/api/login', ['email' => 'superadmin@test.com', 'password' => 'test123'])
            ->assertOk()->assertJsonPath('user.role', 'Super Admin')->assertJsonFragment(['impersonation.start']);
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
