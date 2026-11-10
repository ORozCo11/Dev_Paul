<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenIssuesReservedTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_reports_say_which_ticket_already_holds_them(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian']);
        $cat = VehicleCategory::create(['category_name' => 'Fire Truck', 'description' => 't']);
        $vehicle = Vehicle::create(['vehicle_name' => 'Truck', 'plate_number' => 'OPN 1001', 'category_id' => $cat->category_id,
            'brand' => 'Isuzu', 'model' => 'FTR', 'year_model' => 2019, 'capacity' => '5000 L', 'vehicle_color' => 'Red', 'current_location' => 'Depot']);
        $taken = VehicleIssueReport::create(['vehicle_id' => $vehicle->vehicle_id, 'issue_type' => 'Other', 'issue_description' => 'Leak', 'severity_level' => 'Low', 'reported_by' => $custodian->id, 'status' => 'Pending']);
        $free = VehicleIssueReport::create(['vehicle_id' => $vehicle->vehicle_id, 'issue_type' => 'Other', 'issue_description' => 'Noise', 'severity_level' => 'Low', 'reported_by' => $custodian->id, 'status' => 'Pending']);

        Sanctum::actingAs($custodian, ['*']);
        $ticketId = $this->postJson('/api/tickets/propose', ['vehicle_id' => $vehicle->vehicle_id, 'ticket_description' => 'Leak', 'priority' => 'Low',
            'entry_mode' => 'in_house', 'issue_report_id' => $taken->issue_report_id, 'sub_issues' => [['title' => 'Fix leak']]])->assertCreated()->json('ticket_id');
        $number = MaintenanceTicket::find($ticketId)->display_number;

        $rows = collect($this->getJson("/api/vehicles/{$vehicle->vehicle_id}/open-issues")->assertOk()->json())->keyBy('issue_report_id');
        $this->assertSame($number, $rows[$taken->issue_report_id]['reserved_by_ticket']);
        $this->assertNull($rows[$free->issue_report_id]['reserved_by_ticket']);
    }
}
