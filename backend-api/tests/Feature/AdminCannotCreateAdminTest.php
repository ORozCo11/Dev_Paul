<?php

namespace Tests\Feature;

use App\Models\{Barangay, City, Province, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A barangay has one Admin, made by the Super Admin; an Admin only adds Custodians and Maintenance Personnel. */
class AdminCannotCreateAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['code' => 'AC', 'name' => 'AC Province']);
        $city = City::create(['code' => 'AC-CITY', 'name' => 'AC City', 'province_id' => $province->id]);
        $barangay = Barangay::create(['name' => 'AC Barangay', 'city_id' => $city->id, 'psgc_code' => '9999900055']);
        $this->admin = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $barangay->id, 'approved_at' => now()]);
    }

    #[Test]
    public function an_admin_cannot_create_another_admin_but_can_create_the_other_roles(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/users', ['name' => 'Second Admin', 'email' => 'second.admin@example.com', 'role' => 'Admin'])->assertUnprocessable();
        $this->postJson('/api/users', ['name' => 'Second Admin', 'email' => 'second.admin@example.com', 'roles' => ['Admin']])->assertUnprocessable();
        $this->assertNull(User::where('email', 'second.admin@example.com')->first());

        foreach (['Custodian', 'Maintenance Personnel'] as $i => $role) {
            $this->postJson('/api/users', ['name' => "Colleague {$i}", 'email' => "colleague{$i}@example.com", 'role' => $role])->assertCreated();
        }
    }

    #[Test]
    public function an_admin_cannot_promote_a_colleague_to_admin_and_keeps_their_own_role(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $colleague = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $this->admin->barangay_id, 'approved_at' => now()]);

        $this->putJson("/api/users/{$colleague->id}", ['roles' => ['Admin']])->assertUnprocessable();
        $this->assertSame('Custodian', $colleague->fresh()->role);

        // The Admin can still edit their own profile without being refused.
        $this->putJson("/api/users/{$this->admin->id}", ['name' => 'Renamed Admin', 'roles' => ['Admin']])->assertOk();
    }
}
