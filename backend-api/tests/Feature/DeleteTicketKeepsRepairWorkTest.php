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

/** Repair evidence stays in the original ticket; deletion cannot erase it. */
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

    private function activeTicket(bool $approve = true): MaintenanceTicket
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $id = $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'ticket_description' => 'Engine runs hot.',
            'priority' => 'High',
            'sub_issues' => [['title' => 'Coolant leak']],
        ])->assertCreated()->json('ticket_id');

        Sanctum::actingAs($this->admin, ['*']);
        if ($approve) $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();

        return MaintenanceTicket::with('subIssues')->findOrFail($id);
    }

    #[Test]
    public function deleting_a_ticket_with_logged_repairs_is_rejected_and_preserves_the_original_work(): void
    {
        $ticket = $this->activeTicket();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$ticket->subIssues->first()->sub_issue_id}/log-repairs", ['repair_logs' => 'Replaced hose.'])->assertOk();

        Sanctum::actingAs($this->admin, ['*']);
        $this->deleteJson("/api/tickets/{$ticket->ticket_id}")->assertUnprocessable();
        $this->assertSame('For Verification', $ticket->fresh()->status);
        $this->assertStringContainsString('Replaced hose.', $ticket->subIssues->first()->fresh()->repair_logs);
        $this->assertFalse(TicketArchiveLog::where('ticket_id', $ticket->ticket_id)->exists());
    }

    #[Test]
    public function deleting_an_untouched_proposal_is_still_a_plain_discard(): void
    {
        $ticket = $this->activeTicket(false);

        $this->deleteJson("/api/tickets/{$ticket->ticket_id}")->assertOk();

        $this->assertNull($ticket->fresh());
        $this->assertFalse(TicketArchiveLog::where('ticket_id', $ticket->ticket_id)->exists());
    }
}
