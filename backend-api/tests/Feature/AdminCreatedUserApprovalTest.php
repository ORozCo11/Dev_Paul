<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An account an Admin creates directly through Add User is active from the
 * start, but UserController::store never stamped approved_at — so it sat in
 * the Super Admin's Pending Approvals queue forever, and "Reject" (which
 * hard-deletes anything with a null approved_at) would wipe out a working
 * staff member. Admin-created accounts are approved at creation, and reject
 * refuses any account that is already active regardless of its stamp.
 */
class AdminCreatedUserApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function createStaffAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'approved_at' => now()]);
        Sanctum::actingAs($admin, ['*']);

        $id = $this->postJson('/api/users', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Custodian',
        ])->assertCreated()->json('id');

        return User::findOrFail($id);
    }

    private function actAsSuperAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'approved_at' => now()]), ['*']);
    }

    #[Test]
    public function an_admin_created_user_is_approved_at_creation(): void
    {
        $this->assertNotNull($this->createStaffAsAdmin()->approved_at);
    }

    #[Test]
    public function an_admin_created_user_is_not_listed_as_pending_for_the_super_admin(): void
    {
        $staff = $this->createStaffAsAdmin();
        $this->actAsSuperAdmin();

        $ids = collect($this->getJson('/api/superadmin/pending-approvals')->assertOk()->json())->pluck('id');
        $this->assertNotContains($staff->id, $ids);
    }

    #[Test]
    public function the_super_admin_cannot_reject_an_active_account_even_without_an_approval_stamp(): void
    {
        $staff = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'is_active' => true, 'approved_at' => null]);
        $this->actAsSuperAdmin();

        $this->deleteJson("/api/superadmin/users/{$staff->id}/reject")->assertUnprocessable();
        $this->assertNotNull($staff->fresh());
    }

    #[Test]
    public function a_genuinely_pending_registration_can_still_be_rejected(): void
    {
        $pending = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'is_active' => false, 'approved_at' => null]);
        $this->actAsSuperAdmin();

        $this->deleteJson("/api/superadmin/users/{$pending->id}/reject", ['reason' => 'Could not verify', 'subject' => 'Not approved', 'message' => 'Sorry.'])->assertOk();
        $this->assertNull($pending->fresh());
    }

    #[Test]
    public function the_backfill_approves_existing_active_accounts_but_not_pending_registrations(): void
    {
        $active = User::factory()->create(['is_active' => true, 'approved_at' => null]);
        $pending = User::factory()->create(['is_active' => false, 'approved_at' => null]);

        (require database_path('migrations/2026_10_22_000001_backfill_approved_at_for_admin_created_users.php'))->up();

        $this->assertNotNull($active->fresh()->approved_at);
        $this->assertNull($pending->fresh()->approved_at);
    }
}
