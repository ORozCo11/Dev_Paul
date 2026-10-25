<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A ticket moves to For Verification on its own once every sub-issue has its
 * repair logged — but only logRepairs() checked for that. When the Admin
 * removed the one sub-issue still waiting on the mechanic, every remaining
 * line was already logged, yet the ticket sat Active and the Custodian was
 * never told it was ready to verify.
 */
class SubIssueRemovalAdvanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function removing_the_last_unrepaired_sub_issue_sends_the_ticket_to_verification(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $custodian = User::factory()->create(['role' => 'Custodian']);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'Test']);
        $vehicle = Vehicle::create([
            'vehicle_name' => 'Test Ambulance', 'plate_number' => 'TST 1234', 'category_id' => $category->category_id,
            'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '1000 kg',
            'vehicle_color' => 'White', 'current_location' => 'Main Depot',
        ]);

        Sanctum::actingAs($custodian, ['*']);
        $ticketId = $this->postJson('/api/tickets/propose', [
            'vehicle_id' => $vehicle->vehicle_id,
            'ticket_description' => 'Engine runs hot.',
            'priority' => 'High',
            'sub_issues' => [['title' => 'Coolant leak'], ['title' => 'Worn fan belt']],
        ])->assertCreated()->json('ticket_id');

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/tickets/{$ticketId}/approve", ['assigned_mechanic_id' => $mechanic->id])->assertOk();

        [$repaired, $leftover] = MaintenanceTicket::findOrFail($ticketId)->subIssues()->orderBy('sub_issue_id')->get()->all();

        Sanctum::actingAs($mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticketId}/sub-issues/{$repaired->sub_issue_id}/log-repairs", ['repair_logs' => 'Replaced hose.'])->assertOk();
        $this->assertSame('Active', MaintenanceTicket::findOrFail($ticketId)->status);

        Sanctum::actingAs($custodian, ['*']);
        $this->deleteJson("/api/tickets/{$ticketId}/sub-issues/{$leftover->sub_issue_id}")->assertForbidden();
        $this->assertSame('Active', MaintenanceTicket::findOrFail($ticketId)->status);

        Sanctum::actingAs($admin, ['*']);
        $this->deleteJson("/api/tickets/{$ticketId}/sub-issues/{$leftover->sub_issue_id}")->assertOk();

        $this->assertSame('For Verification', MaintenanceTicket::findOrFail($ticketId)->status);
        $this->assertTrue(Notification::where('user_id', $custodian->id)->where('ticket_id', $ticketId)->where('type', 'repairs_completed')->exists());
    }
}
