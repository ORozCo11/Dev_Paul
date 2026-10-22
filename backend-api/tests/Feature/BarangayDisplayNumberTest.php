<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\MaintenanceTicket;
use App\Models\Province;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BarangayDisplayNumberTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function issue_reports_and_linked_tickets_start_their_display_numbers_in_each_barangay(): void
    {
        $province = Province::create(['code' => 'TST', 'name' => 'Test Province']);
        $city = City::create(['province_id' => $province->id, 'code' => 'TSTC', 'name' => 'Test City']);
        $firstBarangay = Barangay::create(['name' => 'First Barangay', 'city_id' => $city->id]);
        $secondBarangay = Barangay::create(['name' => 'Second Barangay', 'city_id' => $city->id]);
        $category = VehicleCategory::create(['category_name' => 'Test Category', 'description' => 'x']);

        $firstAdmin = $this->user($firstBarangay);
        $secondAdmin = $this->user($secondBarangay);
        $firstVehicle = $this->vehicle($firstBarangay, $category, 'FIRST-1');
        $secondVehicle = $this->vehicle($secondBarangay, $category, 'SECOND-1');

        $firstIssue = $this->issue($firstVehicle, $firstAdmin, 'First issue');
        $secondIssue = $this->issue($secondVehicle, $secondAdmin, 'Second issue');
        $laterFirstIssue = $this->issue($firstVehicle, $firstAdmin, 'Later first issue');

        // Created globally between the first barangay's records, but each
        // barangay's visible ticket sequence still begins at one.
        MaintenanceTicket::create([
            'vehicle_id' => $secondVehicle->vehicle_id, 'issue_report_id' => $secondIssue->issue_report_id,
            'created_by' => $secondAdmin->id, 'ticket_title' => 'Second ticket', 'ticket_description' => 'x',
        ]);
        $firstTicket = MaintenanceTicket::create([
            'vehicle_id' => $firstVehicle->vehicle_id, 'issue_report_id' => $laterFirstIssue->issue_report_id,
            'created_by' => $firstAdmin->id, 'ticket_title' => 'First ticket', 'ticket_description' => 'x',
        ]);

        Sanctum::actingAs($firstAdmin, ['*']);

        $rows = collect($this->getJson('/api/issues')->assertOk()->json())->keyBy('issue_report_id');

        $this->assertSame(1, $rows[$firstIssue->issue_report_id]['display_issue_report_id']);
        $this->assertSame(2, $rows[$laterFirstIssue->issue_report_id]['display_issue_report_id']);
        $this->assertSame(1, $rows[$laterFirstIssue->issue_report_id]['maintenance_ticket']['display_ticket_id']);
        $this->assertSame($firstTicket->ticket_id, $rows[$laterFirstIssue->issue_report_id]['maintenance_ticket']['ticket_id']);
    }

    private function user(Barangay $barangay): User
    {
        return User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $barangay->id, 'is_active' => true]);
    }

    private function vehicle(Barangay $barangay, VehicleCategory $category, string $plate): Vehicle
    {
        return Vehicle::create([
            'vehicle_name' => $plate, 'plate_number' => $plate, 'category_id' => $category->category_id,
            'brand' => 'Test', 'model' => 'Model', 'year_model' => 2024, 'capacity' => '1 kg',
            'vehicle_color' => 'White', 'current_location' => 'Depot', 'barangay_id' => $barangay->id,
        ]);
    }

    private function issue(Vehicle $vehicle, User $reporter, string $type): VehicleIssueReport
    {
        return VehicleIssueReport::create([
            'vehicle_id' => $vehicle->vehicle_id, 'issue_type' => $type, 'issue_description' => 'x',
            'severity_level' => 'Low', 'reported_by' => $reporter->id,
        ]);
    }
}
