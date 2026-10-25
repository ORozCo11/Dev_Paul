<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Tickets page shows each ticket's Custodian and mechanic as avatars from
 * the list response alone (no per-avatar requests). Pins that data contract.
 */
class TicketPersonnelAvatarDataTest extends TestCase
{
    use RefreshDatabase;

    private function ticketFor(User $custodian, User $admin, Vehicle $vehicle, ?User $mechanic): int
    {
        Sanctum::actingAs($custodian, ['*']);
        $id = $this->postJson('/api/tickets/propose', ['vehicle_id' => $vehicle->vehicle_id, 'ticket_description' => 'Brakes', 'priority' => 'High',
            'entry_mode' => 'in_house', 'sub_issues' => [['title' => 'Brake job']]])->assertCreated()->json('ticket_id');
        if ($mechanic) {
            Sanctum::actingAs($admin, ['*']);
            $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $mechanic->id])->assertOk();
        }

        return $id;
    }

    #[Test]
    public function list_carries_personnel_with_photos_and_pending_tickets_have_no_mechanic_and_no_n_plus_one(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $custodian = User::factory()->create(['role' => 'Custodian', 'photo_url' => '/storage/profile-photos/c.jpg']);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'photo_url' => '/storage/profile-photos/m.jpg']);
        $cat = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $mk = fn ($p) => Vehicle::create(['vehicle_name' => "V {$p}", 'plate_number' => $p, 'category_id' => $cat->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);

        $active = $this->ticketFor($custodian, $admin, $mk('AAA 1'), $mechanic);
        $pending = $this->ticketFor($custodian, $admin, $mk('BBB 2'), null);

        Sanctum::actingAs($admin, ['*']);
        $rows = collect($this->getJson('/api/tickets')->assertOk()->json());
        $a = $rows->firstWhere('ticket_id', $active);
        $p = $rows->firstWhere('ticket_id', $pending);

        $this->assertSame('/storage/profile-photos/c.jpg', $a['assigned_custodian']['photo_url']);
        $this->assertSame($mechanic->id, $a['sub_issues'][0]['assigned_mechanic']['id']);
        $this->assertSame('/storage/profile-photos/m.jpg', $a['sub_issues'][0]['assigned_mechanic']['photo_url']);
        $this->assertSame($custodian->id, $p['assigned_custodian']['id']);
        $this->assertNull($p['sub_issues'][0]['assigned_mechanic'], 'no fabricated mechanic before approval');
        $this->assertArrayNotHasKey('password', $a['assigned_custodian']);

        // Query count must not grow with the number of tickets.
        DB::enableQueryLog();
        $this->getJson('/api/tickets')->assertOk();
        $two = count(DB::getQueryLog());
        $this->ticketFor($custodian, $admin, $mk('CCC 3'), $mechanic);
        $this->ticketFor($custodian, $admin, $mk('DDD 4'), $mechanic);
        Sanctum::actingAs($admin, ['*']);
        DB::flushQueryLog();
        $this->getJson('/api/tickets')->assertOk();
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($two, $after, 'ticket list must not N+1 on personnel');
    }

    #[Test]
    public function a_reassigned_mechanic_replaces_the_previous_one_in_the_list(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $custodian = User::factory()->create(['role' => 'Custodian']);
        $old = User::factory()->create(['role' => 'Maintenance Personnel']);
        $new = User::factory()->create(['role' => 'Maintenance Personnel']);
        $cat = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $v = Vehicle::create(['vehicle_name' => 'V', 'plate_number' => 'ZZZ 9', 'category_id' => $cat->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
        $id = $this->ticketFor($custodian, $admin, $v, $old);

        Sanctum::actingAs($admin, ['*']);
        $sid = $this->getJson("/api/tickets/{$id}")->json('sub_issues.0.sub_issue_id');
        \App\Models\TicketSubIssue::where('sub_issue_id', $sid)->update(['assigned_mechanic_id' => $new->id]);

        $row = collect($this->getJson('/api/tickets')->json())->firstWhere('ticket_id', $id);
        $this->assertSame($new->id, $row['sub_issues'][0]['assigned_mechanic']['id']);
    }
}
