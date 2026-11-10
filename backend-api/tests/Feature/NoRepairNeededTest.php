<?php

namespace Tests\Feature;

use App\Models\{Barangay, City, MaintenanceTicket, Province, TicketSubIssue, User, Vehicle, VehicleCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoRepairNeededTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private User $mechanic;
    private MaintenanceTicket $ticket;
    private TicketSubIssue $line;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['code' => 'NR', 'name' => 'NR Province']);
        $city = City::create(['code' => 'NR-CITY', 'name' => 'NR City', 'province_id' => $province->id]);
        $barangay = Barangay::create(['name' => 'NR Barangay', 'city_id' => $city->id, 'psgc_code' => '9999900004']);
        $this->custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian'], 'barangay_id' => $barangay->id]);
        $this->mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel'], 'barangay_id' => $barangay->id]);
        $category = VehicleCategory::create(['category_name' => 'Pickup', 'domain' => 'Land', 'barangay_id' => $barangay->id]);
        $vehicle = Vehicle::create([
            'vehicle_name' => 'NR Truck', 'plate_number' => 'NR-1234', 'brand' => 'Isuzu', 'model' => 'D-Max', 'category_id' => $category->category_id,
            'year_model' => 2020, 'vehicle_color' => 'White', 'capacity' => '5 pax', 'current_location' => 'Hall', 'status' => 'Under Maintenance', 'condition' => 'Needs Repair', 'barangay_id' => $barangay->id,
        ]);
        $this->ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id, 'ticket_title' => 'Strange noise', 'ticket_description' => 'Noise when braking',
            'created_by' => $this->custodian->id, 'assigned_custodian_id' => $this->custodian->id, 'assigned_mechanic_id' => $this->mechanic->id, 'status' => 'Active',
        ]);
        $this->line = $this->ticket->subIssues()->create([
            'title' => 'Check brakes', 'created_by' => $this->custodian->id, 'assigned_mechanic_id' => $this->mechanic->id, 'status' => 'Under Repair', 'repair_type' => 'in_house',
        ]);
    }

    private function url(string $suffix): string
    {
        return "/api/tickets/{$this->ticket->ticket_id}/sub-issues/{$this->line->sub_issue_id}/{$suffix}";
    }

    public function test_the_mechanic_marks_no_repair_needed_and_the_ticket_goes_to_the_custodian(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson($this->url('no-repair-needed'), ['notes' => 'Brakes measured within spec, noise was a loose cover.'])->assertOk();

        $line = $this->line->fresh();
        $this->assertTrue($line->no_repair_needed);
        $this->assertSame('For Inspection', $line->status);
        $this->assertNotNull($line->mechanic_tested_at);
        $this->assertSame('For Verification', $this->ticket->fresh()->status);
    }

    public function test_a_reason_is_required(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson($this->url('no-repair-needed'), [])->assertUnprocessable()->assertJsonValidationErrors('notes');
    }

    public function test_the_custodian_cannot_mark_no_repair_needed(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson($this->url('no-repair-needed'), ['notes' => 'Looks fine to me.'])->assertForbidden();
    }

    public function test_submitting_a_repair_requires_the_mechanics_own_test(): void
    {
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson($this->url('log-repairs'), ['repair_logs' => 'Replaced pads.'])->assertUnprocessable()->assertJsonValidationErrors('tested_ok');
        $this->putJson($this->url('log-repairs'), ['repair_logs' => 'Replaced pads.', 'tested_ok' => true, 'test_notes' => 'Test drive OK'])->assertOk();
        $this->assertSame('Test drive OK', $this->line->fresh()->mechanic_test_notes);
    }
}
