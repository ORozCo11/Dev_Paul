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
 * Reopening a deleted ticket rebuilt it from its archive snapshot but left
 * out the ticket's assigned mechanic. Every sub-issue still named them, yet
 * the ticket itself had none — so they could never submit it for
 * verification, and Admin lost the Reassign Mechanic control.
 */
class ReopenedTicketMechanicTest extends TestCase
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
    public function a_reopened_ticket_keeps_its_mechanic_and_they_can_still_submit_it(): void
    {
        $ticket = $this->activeTicket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$ticket->subIssues->first()->sub_issue_id}/log-repairs", ['repair_logs' => 'Replaced hose.'])->assertOk();

        // What deleteTicket() archives for a ticket with real progress.
        $ticket = MaintenanceTicket::with('subIssues')->findOrFail($ticket->ticket_id);
        $ticket->update(['status' => 'Active']);
        $archive = TicketArchiveLog::create([
            'ticket_id' => $ticket->ticket_id,
            'vehicle_id' => $ticket->vehicle_id,
            'ticket_title' => $ticket->ticket_title,
            'vehicle_name' => $this->vehicle->vehicle_name,
            'plate_number' => $this->vehicle->plate_number,
            'final_status' => 'Deleted',
            'maintenance_cost' => 0,
            'full_ticket_snapshot' => $ticket->fresh('subIssues')->toArray(),
            'archived_by' => $this->admin->id,
            'archived_at' => now(),
        ]);
        $ticket->delete();

        Sanctum::actingAs($this->admin, ['*']);
        $reopenedId = $this->putJson("/api/ticket-archives/{$archive->getKey()}/reopen")->assertCreated()->json('ticket_id');

        $this->assertSame($this->mechanic->id, (int) MaintenanceTicket::findOrFail($reopenedId)->assigned_mechanic_id);

        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$reopenedId}/submit-for-verification")->assertOk();
    }
}
