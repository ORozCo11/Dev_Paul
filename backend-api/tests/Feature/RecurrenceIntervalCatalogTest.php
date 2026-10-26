<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurrenceIntervalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_shared_month_options(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']), ['*']);
        $this->getJson('/api/recurrence-intervals')->assertOk()->assertJsonCount(4);
        $created = $this->postJson('/api/recurrence-intervals', ['name' => '4'])->assertCreated();
        $id = $created->json('id');
        $this->postJson('/api/recurrence-intervals', ['name' => '4'])->assertCreated()->assertJsonPath('id', $id);
        $this->putJson("/api/recurrence-intervals/{$id}", ['name' => '5'])->assertOk()->assertJsonPath('name', 5);
        $this->getJson('/api/recurrence-intervals')->assertJsonFragment(['id' => $id, 'name' => 5]);
        $this->putJson("/api/recurrence-intervals/{$id}", ['name' => '3'])->assertUnprocessable();
        $this->deleteJson("/api/recurrence-intervals/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('recurrence_intervals', ['id' => $id]);
    }

    public function test_invalid_months_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']), ['*']);
        foreach ([0, 61, 1.5, 'abc'] as $value) {
            $this->postJson('/api/recurrence-intervals', ['name' => $value])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }
    }

    public function test_operational_users_can_add_but_not_rename_or_delete(): void
    {
        foreach (['Custodian', 'Maintenance Personnel'] as $index => $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]), ['*']);
            $id = $this->postJson('/api/recurrence-intervals', ['name' => 4 + $index])->assertCreated()->json('id');
            $this->putJson("/api/recurrence-intervals/{$id}", ['name' => 8])->assertForbidden();
            $this->deleteJson("/api/recurrence-intervals/{$id}")->assertForbidden();
        }
    }
}
