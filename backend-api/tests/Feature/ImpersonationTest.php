<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// The removed feature cannot issue sessions; legacy sessions can still exit safely.
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function impersonation_endpoints_are_removed_for_guests_and_all_roles(): void
    {
        $target = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $this->getJson('/api/impersonate/candidates')->assertNotFound();
        $this->postJson("/api/impersonate/{$target->id}", [])->assertNotFound();
        foreach (['Admin', 'Custodian', 'Maintenance Personnel', 'Super Admin'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'roles' => [$role]]);
            Sanctum::actingAs($actor, ['*']);
            $this->getJson('/api/impersonate/candidates')->assertNotFound();
            $this->postJson("/api/impersonate/{$target->id}", ['reason' => 'Removed feature'])->assertNotFound();
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
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
            ->assertNotFound();

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
