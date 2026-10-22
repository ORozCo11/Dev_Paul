<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\TicketArchiveLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deleting a ticket keeps a recoverable "Deleted" archive only when it had
 * real progress — but progress was measured as a sub-issue reaching Done,
 * which now only ever happens as the ticket closes (and a Closed ticket can't
 * be deleted). So a ticket with every repair already logged was wiped with no
 * way back. A logged repair now counts as progress.
 */
class DeleteTicketKeepsRepairWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $custodian;
    private User $mechanic;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        $this->custodian = User::factory()->create(['role' => 'Custodian']);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'Test']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Test Ambulance', 'plate_number' => 'TST 1234', 'category_id' => $category->category_id,
            'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '1000 kg',
            'vehicle_color' => 'White', 'current_location' => 'Main Depot',
        ]);
    }

    private function activeTicket(): MaintenanceTicket
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $id = $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'ticket_description' => 'Engine runs hot.',
            'priority' => 'High',
            'sub_issues' => [['title' => 'Coolant leak']],
        ])->assertCreated()->json('ticket_id');

        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();

        return MaintenanceTicket::with('subIssues')->findOrFail($id);
    }

    #[Test]
    public function deleting_a_ticket_with_logged_repairs_keeps_a_recoverable_archive(): void
    {
        $ticket = $this->activeTicket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$ticket->subIssues->first()->sub_issue_id}/log-repairs", ['repair_logs' => 'Replaced hose.'])->assertOk();

        Sanctum::actingAs($this->admin, ['*']);
        $this->deleteJson("/api/tickets/{$ticket->ticket_id}")->assertOk();

        $archive = TicketArchiveLog::where('ticket_id', $ticket->ticket_id)->first();
        $this->assertNotNull($archive, 'Logged repair work must stay recoverable after a delete.');
        $this->assertSame('Deleted', $archive->final_status);
    }

    #[Test]
    public function deleting_a_ticket_with_no_repair_logged_is_still_a_plain_discard(): void
    {
        $ticket = $this->activeTicket();

        $this->deleteJson("/api/tickets/{$ticket->ticket_id}")->assertOk();

        $this->assertFalse(TicketArchiveLog::where('ticket_id', $ticket->ticket_id)->exists());
    }
}
