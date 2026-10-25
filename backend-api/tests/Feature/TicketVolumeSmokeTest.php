<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\TicketArchiveLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VOLUME SMOKE TESTS â€” can the ticket workflow carry a realistic barangay
 * fleet load of 50-100 vehicle tickets at once?
 *
 * Every ticket is built through the real HTTP path (Custodian proposes ->
 * Admin approves) on its own vehicle, then the read endpoints that list
 * them all are hit at that volume. TicketController::index() returns every
 * ticket unpaginated with ~25 eager-loaded relations, so the key guard is
 * a FIXED query budget for the list â€” an N+1 regression adds a query per
 * ticket and blows straight through it at 50+ tickets.
 */
class TicketVolumeSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Max queries for one /api/tickets listing, independent of ticket count.
     * Measured at 13 for both 50 and 100 tickets (2026-10-20); the headroom
     * absorbs a new eager load or two, not a per-ticket query.
     */
    private const LIST_QUERY_BUDGET = 20;

    private User $admin;
    private User $custodianA;
    private User $custodianB;
    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodianA = User::factory()->create(['role' => 'Custodian']);
        $this->custodianB = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
    }

    public static function ticketVolumes(): array
    {
        return [
            '50 tickets' => [50],
            '100 tickets' => [100],
        ];
    }

    /**
     * Proposes and approves one ticket per vehicle, alternating the two
     * Custodians so role scoping can be checked at volume. Returns the ids.
     */
    private function openTickets(int $count): array
    {
        $category = VehicleCategory::create([
            'category_name' => 'Patrol Vehicle',
            'description' => 'For volume testing',
        ]);

        $ticketIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $vehicle = Vehicle::create([
                'vehicle_name' => "Patrol Unit {$i}",
                'plate_number' => sprintf('VOL %04d', $i),
                'category_id' => $category->category_id,
                'brand' => 'Toyota',
                'model' => 'Hilux',
                'year_model' => 2022,
                'capacity' => '5 seats',
                'vehicle_color' => 'White',
                'current_location' => 'Main Depot',
            ]);

            Sanctum::actingAs($i % 2 ? $this->custodianA : $this->custodianB, ['*']);
            $ticketId = $this->postJson('/api/tickets/propose', [
                'vehicle_id' => $vehicle->vehicle_id,
                'ticket_description' => "Brakes feel soft on unit {$i}.",
                'priority' => 'High',
                'sub_issues' => [['title' => 'Replace brake pads']],
            ])->assertCreated()->json('ticket_id');

            Sanctum::actingAs($this->admin, ['*']);
            $this->putJson("/api/tickets/{$ticketId}/approve", [
                'assigned_mechanic_id' => $this->mechanic->id,
            ])->assertOk();

            $ticketIds[] = $ticketId;
        }

        return $ticketIds;
    }

    /** Runs $callback and returns how many SQL queries it issued. */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function every_proposed_ticket_is_approved_and_takes_its_vehicle_out_of_service(int $count): void
    {
        $this->openTickets($count);

        $this->assertSame($count, MaintenanceTicket::where('status', 'Active')->count());
        $this->assertSame($count, Vehicle::where('status', 'Under Maintenance')->count());
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function admin_ticket_list_returns_every_ticket_with_its_sub_issues(int $count): void
    {
        $ticketIds = $this->openTickets($count);

        Sanctum::actingAs($this->admin, ['*']);
        $listed = $this->getJson('/api/tickets')->assertOk()->json();

        $this->assertCount($count, $listed);
        $this->assertEqualsCanonicalizing($ticketIds, array_column($listed, 'ticket_id'));
        foreach ($listed as $ticket) {
            $this->assertCount(1, $ticket['sub_issues']);
            $this->assertNotNull($ticket['vehicle']);
        }
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function admin_ticket_list_stays_within_a_fixed_query_budget(int $count): void
    {
        $this->openTickets($count);

        Sanctum::actingAs($this->admin, ['*']);
        $queries = $this->countQueries(fn () => $this->getJson('/api/tickets')->assertOk());

        $this->assertLessThanOrEqual(
            self::LIST_QUERY_BUDGET,
            $queries,
            "Listing {$count} tickets took {$queries} queries â€” likely an N+1 in TicketController::index() or a ticket accessor."
        );
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function each_custodian_sees_only_their_own_half_of_the_tickets(int $count): void
    {
        $this->openTickets($count);

        foreach ([$this->custodianA, $this->custodianB] as $custodian) {
            Sanctum::actingAs($custodian, ['*']);
            $listed = $this->getJson('/api/tickets')->assertOk()->json();

            $this->assertCount($count / 2, $listed);
            foreach ($listed as $ticket) {
                $this->assertSame($custodian->id, $ticket['assigned_custodian_id']);
            }
        }
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function the_mechanics_dashboard_and_workload_count_every_ticket(int $count): void
    {
        $this->openTickets($count);

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('badge_counts.ticketWorkOrders', $count);
        $this->assertCount($count, $this->getJson('/api/tickets')->assertOk()->json());

        Sanctum::actingAs($this->admin, ['*']);
        $mechanic = collect($this->getJson('/api/tickets/lookups')->assertOk()->json('maintenance_personnel'))
            ->firstWhere('id', $this->mechanic->id);
        $this->assertSame($count, $mechanic['active_tickets']);
    }

    #[Test]
    #[DataProvider('ticketVolumes')]
    public function every_ticket_can_be_driven_to_closed_and_returns_its_vehicle_to_service(int $count): void
    {
        $ticketIds = $this->openTickets($count);

        foreach (MaintenanceTicket::with('subIssues')->whereIn('ticket_id', $ticketIds)->get() as $ticket) {
            Sanctum::actingAs($this->mechanic, ['*']);
            foreach ($ticket->subIssues as $subIssue) {
                $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$subIssue->sub_issue_id}/log-repairs", [
                    'repair_logs' => 'Replaced front brake pads.',
                ])->assertOk();
            }
            $this->putJson("/api/tickets/{$ticket->ticket_id}/submit-for-verification", [])->assertOk();

            Sanctum::actingAs($ticket->assignedCustodian, ['*']);
            $this->putJson("/api/tickets/{$ticket->ticket_id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();
        }

        $this->assertSame($count, MaintenanceTicket::where('status', 'Closed')->count());
        $this->assertSame($count, TicketArchiveLog::whereIn('ticket_id', $ticketIds)->count());
        $this->assertSame($count, Vehicle::where('status', 'Available')->count());
    }
}
