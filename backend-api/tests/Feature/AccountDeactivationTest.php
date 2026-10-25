<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deactivation must take effect immediately — a session already in progress
 * should not keep working just because it authenticated before the account
 * was turned off. `is_active` was previously only checked at login.
 *
 * Note: Laravel's HTTP test helpers cache the resolved guard user for the
 * rest of the test process once any request has authenticated — issuing two
 * DIFFERENT real bearer tokens across two calls in one test and expecting
 * each to resolve independently does not work (a well-known Sanctum/testing
 * caveat, unrelated to app code). So the two halves of the fix are verified
 * separately: the middleware via Sanctum::actingAs (this suite's normal
 * pattern), and the token revocation via direct DB/model state.
 */
class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_deactivated_users_session_is_rejected_on_every_protected_route(): void
    {
        $inactive = User::factory()->create([
            'role' => 'Custodian', 'roles' => ['Custodian'], 'is_active' => false,
        ]);

        Sanctum::actingAs($inactive, ['*']);
        $this->getJson('/api/user')->assertUnauthorized();
    }

    #[Test]
    public function deactivating_a_user_deletes_their_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $custodian->createToken('session-1');
        $custodian->createToken('session-2');

        $this->assertSame(2, $custodian->tokens()->count());

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/users/{$custodian->id}/deactivate")->assertOk();

        $this->assertFalse($custodian->fresh()->is_active);
        $this->assertSame(0, $custodian->tokens()->count(), 'Every existing token should be revoked immediately on deactivation.');
    }

    #[Test]
    public function a_deactivated_user_cannot_log_in_to_get_a_new_token(): void
    {
        $custodian = User::factory()->create([
            'role' => 'Custodian',
            'roles' => ['Custodian'],
            'password' => bcrypt('secret123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $custodian->email,
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    /**
     * Same self-lockout family as the deactivation guards above, but for
     * stripping one's own "Admin" role via UserController::update() — the
     * normal Users-management form, not deactivate(). A sole barangay Admin
     * unchecking their own Admin box there would lock themselves (and the
     * whole barangay) out of user management with no other way back in.
     */
    #[Test]
    public function an_admin_cannot_remove_their_own_admin_role_via_roles_array(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/users/{$admin->id}", [
            'roles' => ['Custodian'],
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    #[Test]
    public function an_admin_cannot_remove_their_own_admin_role_via_single_role_field(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/users/{$admin->id}", [
            'role' => 'Maintenance Personnel',
        ])->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    #[Test]
    public function an_admin_can_still_edit_their_own_other_fields_while_keeping_admin(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/users/{$admin->id}", [
            'name' => 'Updated Name',
            'roles' => ['Admin', 'Custodian'],
        ])->assertOk();

        $this->assertSame('Updated Name', $admin->fresh()->name);
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    /**
     * Same self-lockout gap, one tier up: SuperAdminController::updateUserRole()
     * previously had no guard at all, unlike deactivateUser() right above it.
     */
    #[Test]
    public function a_super_admin_cannot_change_their_own_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]);

        Sanctum::actingAs($superAdmin, ['*']);
        $this->putJson("/api/superadmin/users/{$superAdmin->id}/role", [
            'role' => 'Admin',
        ])->assertStatus(422);

        $this->assertTrue($superAdmin->fresh()->hasRole('Super Admin'));
    }

    #[Test]
    public function a_super_admin_cannot_change_another_super_admins_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]);
        $otherSuperAdmin = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin']]);

        Sanctum::actingAs($superAdmin, ['*']);
        $this->putJson("/api/superadmin/users/{$otherSuperAdmin->id}/role", [
            'role' => 'Admin',
        ])->assertStatus(422);

        $this->assertTrue($otherSuperAdmin->fresh()->hasRole('Super Admin'));
    }
}
