<?php

namespace Tests\Feature;

use App\Mail\AccountMail;
use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The handover tools: a wrong email can be fixed, an Admin can be replaced, invitations show where they stand. */
class SuperAdminHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->superAdmin = User::factory()->create(['role' => 'Super Admin', 'roles' => ['Super Admin'], 'barangay_id' => null]);
        $province = Province::create(['code' => 'QA', 'name' => 'QA Province']);
        $city = City::create(['code' => 'QA-CITY', 'name' => 'QA City', 'province_id' => $province->id]);
        $this->barangay = Barangay::create(['name' => 'QA Barangay', 'city_id' => $city->id, 'psgc_code' => '9999900001']);
        Sanctum::actingAs($this->superAdmin, ['*']);
    }

    private function createAdmin(string $email = 'first.admin@example.test'): array
    {
        return $this->postJson("/api/superadmin/barangays/{$this->barangay->id}/admin", ['name' => 'First Admin', 'email' => $email])->assertCreated()->json();
    }

    #[Test]
    public function a_fresh_invitation_shows_as_invited_then_active_after_the_first_sign_in(): void
    {
        $made = $this->createAdmin();
        $row = collect($this->getJson('/api/superadmin/users')->json())->firstWhere('id', $made['id']);
        $this->assertSame('invited', $row['invitation']);
        $this->assertNotNull($row['invitation_expires_at']);
        $this->assertNull($row['last_login_at']);

        $barangay = collect($this->getJson('/api/superadmin/barangays')->json())->firstWhere('id', $this->barangay->id);
        $this->assertSame('invited', $barangay['admin_status']);
        $this->assertSame('first.admin@example.test', $barangay['admin_email']);

        $this->postJson('/api/login', ['email' => 'first.admin@example.test', 'password' => $made['temporary_password']])->assertOk();
        User::whereKey($made['id'])->update(['must_change_password' => false]);   // they chose their own password
        Sanctum::actingAs($this->superAdmin, ['*']);
        $row = collect($this->getJson('/api/superadmin/users')->json())->firstWhere('id', $made['id']);
        $this->assertSame('active', $row['invitation']);
        $this->assertNotNull($row['last_login_at']);
        $this->assertSame('active', collect($this->getJson('/api/superadmin/barangays')->json())->firstWhere('id', $this->barangay->id)['admin_status']);
    }

    #[Test]
    public function an_expired_invitation_is_shown_as_expired(): void
    {
        $made = $this->createAdmin();
        User::whereKey($made['id'])->update(['temp_password_expires_at' => now()->subDay()]);
        $this->assertSame('expired', collect($this->getJson('/api/superadmin/users')->json())->firstWhere('id', $made['id'])['invitation']);
        $this->assertSame('expired', collect($this->getJson('/api/superadmin/barangays')->json())->firstWhere('id', $this->barangay->id)['admin_status']);
    }

    #[Test]
    public function a_barangay_without_an_admin_says_none(): void
    {
        $this->assertSame('none', collect($this->getJson('/api/superadmin/barangays')->json())->firstWhere('id', $this->barangay->id)['admin_status']);
    }

    #[Test]
    public function a_mistyped_email_can_be_fixed_before_the_first_sign_in(): void
    {
        $made = $this->createAdmin('typo@example.test');
        $res = $this->putJson("/api/superadmin/users/{$made['id']}/change-email", ['email' => 'right@example.test'])->assertOk()->json();
        $this->assertSame('right@example.test', User::find($made['id'])->email);
        $this->assertTrue($res['email_sent']);
        Mail::assertSent(AccountMail::class, fn ($mail) => $mail->hasTo('right@example.test'));

        // The old temporary password no longer works; the new one does.
        $this->postJson('/api/login', ['email' => 'right@example.test', 'password' => $made['temporary_password']])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'right@example.test', 'password' => $res['temporary_password']])->assertOk();
    }

    #[Test]
    public function the_email_cannot_be_changed_after_signing_in_or_to_one_in_use_or_for_the_wrong_account(): void
    {
        $made = $this->createAdmin();
        $other = User::factory()->create(['email' => 'taken@example.test']);
        $this->putJson("/api/superadmin/users/{$made['id']}/change-email", ['email' => 'taken@example.test'])->assertUnprocessable();
        $this->putJson("/api/superadmin/users/{$made['id']}/change-email", ['email' => 'not-an-email'])->assertUnprocessable();

        User::whereKey($made['id'])->update(['must_change_password' => false]);
        $this->putJson("/api/superadmin/users/{$made['id']}/change-email", ['email' => 'late@example.test'])->assertUnprocessable();
        $this->putJson("/api/superadmin/users/{$this->superAdmin->id}/change-email", ['email' => 'me@example.test'])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]), ['*']);
        $this->putJson("/api/superadmin/users/{$other->id}/change-email", ['email' => 'x@example.test'])->assertForbidden();
    }

    #[Test]
    public function replace_admin_creates_the_new_admin_and_retires_the_old_one(): void
    {
        $made = $this->createAdmin();
        $this->postJson("/api/superadmin/barangays/{$this->barangay->id}/admin", ['name' => 'Second', 'email' => 'second@example.test'])->assertUnprocessable();

        $res = $this->postJson("/api/superadmin/barangays/{$this->barangay->id}/replace-admin", ['name' => 'New Admin', 'email' => 'new.admin@example.test'])->assertCreated()->json();
        $this->assertSame(['First Admin'], $res['replaced']);
        $this->assertFalse((bool) User::find($made['id'])->is_active);
        $new = User::find($res['id']);
        $this->assertTrue((bool) $new->is_active);
        $this->assertSame('Admin', $new->role);
        $this->assertSame($this->barangay->id, $new->barangay_id);
        Mail::assertSent(AccountMail::class, fn ($mail) => $mail->hasTo('new.admin@example.test'));

        $barangay = collect($this->getJson('/api/superadmin/barangays')->json())->firstWhere('id', $this->barangay->id);
        $this->assertSame('new.admin@example.test', $barangay['admin_email']);
        $this->assertSame(1, User::where('barangay_id', $this->barangay->id)->havingRole('Admin')->where('is_active', true)->count());
    }

    #[Test]
    public function replace_admin_needs_an_admin_to_replace_and_a_valid_new_email(): void
    {
        $this->postJson("/api/superadmin/barangays/{$this->barangay->id}/replace-admin", ['name' => 'New', 'email' => 'n@example.test'])->assertUnprocessable();
        $made = $this->createAdmin();
        $this->postJson("/api/superadmin/barangays/{$this->barangay->id}/replace-admin", ['name' => 'New', 'email' => 'first.admin@example.test'])->assertUnprocessable();
        $this->assertTrue((bool) User::find($made['id'])->is_active);
    }

    #[Test]
    public function account_events_done_by_a_barangay_admin_show_in_the_platform_log_but_fleet_entries_do_not(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]);
        ActivityLog::create(['user_id' => $admin->id, 'role' => 'Admin', 'action' => 'Add', 'module' => 'Users', 'details' => 'Created the Custodian Jane (jane@example.test).']);
        ActivityLog::create(['user_id' => $admin->id, 'role' => 'Admin', 'action' => 'Add', 'module' => 'Vehicle Management', 'details' => 'Added vehicle Dump Truck 01']);

        $details = collect($this->getJson('/api/superadmin/activity-log')->json())->pluck('details')->implode(' | ');
        $this->assertStringContainsString('Created the Custodian Jane', $details);
        $this->assertStringNotContainsString('Dump Truck', $details);
        $this->assertSame([], collect($this->getJson('/api/superadmin/activity-log?scope=superadmin')->json())->pluck('details')->filter(fn ($d) => str_contains($d, 'Jane'))->all());
    }

    #[Test]
    public function the_test_email_goes_to_the_super_admins_own_address_and_only_a_super_admin_can_send_it(): void
    {
        $this->postJson('/api/superadmin/test-email')->assertOk();
        Mail::assertSent(AccountMail::class, fn ($mail) => $mail->hasTo($this->superAdmin->email));

        Sanctum::actingAs(User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]), ['*']);
        $this->postJson('/api/superadmin/test-email')->assertForbidden();
    }

    #[Test]
    public function promoting_a_second_admin_is_refused_while_the_barangay_has_an_active_admin(): void
    {
        $this->createAdmin();
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $this->barangay->id]);

        $res = $this->putJson("/api/superadmin/users/{$custodian->id}/role", ['role' => 'Admin'])->assertUnprocessable();
        $this->assertStringContainsString('Replace Admin', $res->json('message'));
        $this->putJson("/api/superadmin/users/{$custodian->id}/role", ['role' => 'Admin', 'preserve_roles' => true])->assertUnprocessable();
        $this->assertSame('Custodian', $custodian->fresh()->role);
    }

    #[Test]
    public function promotion_still_recovers_a_barangay_whose_admin_is_gone(): void
    {
        $made = $this->createAdmin();
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $this->barangay->id]);
        User::whereKey($made['id'])->update(['is_active' => false]);   // the Admin left

        $this->putJson("/api/superadmin/users/{$custodian->id}/role", ['role' => 'Admin'])->assertOk();
        $this->assertSame('Admin', $custodian->fresh()->role);
    }

    #[Test]
    public function the_only_admin_cannot_be_deactivated_and_the_message_points_to_replace_admin(): void
    {
        $made = $this->createAdmin();
        $res = $this->putJson("/api/superadmin/users/{$made['id']}/deactivate")->assertUnprocessable();
        $this->assertStringContainsString('Replace Admin', $res->json('message'));
        $this->assertStringNotContainsString('Promote another user', $res->json('message'));
    }
}
