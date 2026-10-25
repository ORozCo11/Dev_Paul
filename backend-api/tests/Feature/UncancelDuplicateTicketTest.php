<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Once a ticket is cancelled, a new ticket for the same Main Issue on the
 * same vehicle is allowed. Restoring the cancelled one afterwards skipped the
 * duplicate check every other path (propose, reopen) runs, leaving two open
 * tickets for one problem. uncancelTicket() now refuses that.
 */
class UncancelDuplicateTicketTest extends TestCase
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

    private function activeTicket(string $subIssue): int
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $id = $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'ticket_description' => 'Engine runs hot.',
            'priority' => 'High',
            'sub_issues' => [['title' => $subIssue]],
        ])->assertCreated()->json('ticket_id');

        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();

        return $id;
    }

    #[Test]
    public function a_cancelled_ticket_cannot_be_restored_over_a_newer_open_ticket_for_the_same_issue(): void
    {
        $original = $this->activeTicket('Coolant leak');
        $this->putJson("/api/tickets/{$original}/cancel", ['closing_notes' => 'Reviewed cancellation for restoration test.'])->assertOk();

        $replacement = $this->activeTicket('Coolant leak');

        $this->putJson("/api/tickets/{$original}/uncancel")->assertUnprocessable();
        $this->assertSame('Cancelled', MaintenanceTicket::findOrFail($original)->status);
        $this->assertSame('Active', MaintenanceTicket::findOrFail($replacement)->status);
    }

    #[Test]
    public function a_cancelled_ticket_with_no_newer_duplicate_can_still_be_restored(): void
    {
        $original = $this->activeTicket('Coolant leak');
        $this->putJson("/api/tickets/{$original}/cancel", ['closing_notes' => 'Reviewed cancellation for restoration test.'])->assertOk();

        $this->putJson("/api/tickets/{$original}/uncancel")->assertOk();
        $this->assertSame('Active', MaintenanceTicket::findOrFail($original)->status);
    }
}
