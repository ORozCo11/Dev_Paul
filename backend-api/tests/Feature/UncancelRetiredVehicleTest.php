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
 * A vehicle can be retired (Inactive/Decommissioned) after one of its tickets
 * was cancelled. Restoring that ticket put repair work back on a vehicle that
 * is out of the fleet — approve and propose both refuse retired vehicles, so
 * uncancelTicket() now does too.
 */
class UncancelRetiredVehicleTest extends TestCase
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
    public function a_cancelled_ticket_cannot_be_restored_once_its_vehicle_is_retired(): void
    {
        $ticket = $this->activeTicket('Coolant leak');
        $this->putJson("/api/tickets/{$ticket}/cancel", ['closing_notes' => 'Reviewed cancellation for restoration test.'])->assertOk();
        $this->vehicle->update(['status' => 'Decommissioned']);

        $this->putJson("/api/tickets/{$ticket}/uncancel")->assertUnprocessable();
        $this->assertSame('Cancelled', MaintenanceTicket::findOrFail($ticket)->status);
    }
}
