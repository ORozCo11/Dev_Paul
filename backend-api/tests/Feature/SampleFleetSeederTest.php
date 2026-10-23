<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\City;
use App\Models\MaintenanceTicket;
use App\Models\Province;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleMaintenanceSchedule;
use Database\Seeders\SampleFleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SampleFleetSeederTest extends TestCase
{
    use RefreshDatabase;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();
        $province = Province::create(['name' => 'Cebu', 'code' => '070220000']);
        $city = City::create(['name' => 'Mandaue City', 'province_id' => $province->id]);
        $this->barangay = Barangay::create(['name' => 'Paknaan', 'city_id' => $city->id]);
    }

    private function staff(string $email): User
    {
        return User::where('email', $email.'@barangay.gov')->firstOrFail();
    }

    private function vehicle(string $plate, string $name, ?int $barangayId = null): Vehicle
    {
        $type = VehicleCategory::firstOrCreate(['category_name' => 'Ambulance'], ['domain' => 'Land']);
        return Vehicle::create([
            'plate_number' => $plate, 'vehicle_name' => $name, 'barangay_id' => $barangayId ?? $this->barangay->id,
            'category_id' => $type->category_id, 'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2023,
            'capacity' => '8 pax', 'vehicle_color' => 'White', 'current_location' => 'Twinbee Hub',
        ]);
    }

    public function test_reset_prunes_old_demo_vehicles_and_is_repeatable_without_deleting_other_data(): void
    {
        $extraUser = User::factory()->create(['email' => 'non-demo@example.com']);
        $old = $this->vehicle('BCP-01W', 'Wagon Caddy Ambulance');
        $custom = $this->vehicle('CUSTOM-1', 'User-entered ambulance');
        $otherBarangay = Barangay::create(['name' => 'Other Barangay', 'city_id' => $this->barangay->city_id]);
        $other = $this->vehicle('OTHER-1', 'Other barangay ambulance', $otherBarangay->id);
        $category = VehicleCategory::where('category_name', 'Ambulance')->firstOrFail();
        $category->update(['description' => 'Locally edited catalog description', 'default_criticality' => 'High']);

        $this->seed(SampleFleetSeeder::class);
        $this->assertDatabaseMissing('vehicles', ['vehicle_id' => $old->vehicle_id]);
        $ids = Vehicle::whereIn('plate_number', ['BFP-5501', 'FDT-2801', 'AMB-1101', 'INEM-112', 'FDR-303'])->orderBy('vehicle_id')->pluck('vehicle_id')->all();
        $this->assertCount(5, $ids);
        $this->assertSame(2, Vehicle::whereIn('vehicle_id', $ids)->whereHas('category', fn ($q) => $q->where('category_name', 'Fire Truck'))->count());
        $this->assertSame(3, Vehicle::whereIn('vehicle_id', $ids)->whereHas('category', fn ($q) => $q->where('category_name', 'Ambulance'))->count());

        $this->seed(SampleFleetSeeder::class);
        $this->assertSame($ids, Vehicle::whereIn('vehicle_id', $ids)->orderBy('vehicle_id')->pluck('vehicle_id')->all());
        $this->assertDatabaseCount('vehicles', 7);
        $this->assertDatabaseCount('maintenance_tickets', 3);
        $this->assertDatabaseCount('ticket_sub_issues', 4);
        $this->assertDatabaseCount('vehicle_maintenance_schedules', 4);
        $this->assertDatabaseCount('vehicle_condition_checks', 5);
        $this->assertDatabaseCount('vehicle_readiness_checks', 2);
        $this->assertDatabaseCount('notifications', 6);
        $this->assertDatabaseHas('users', ['id' => $extraUser->id, 'email' => 'non-demo@example.com']);
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $custom->vehicle_id]);
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $other->vehicle_id]);
        $this->assertSame('Locally edited catalog description', $category->fresh()->description);
        $this->assertSame('High', $category->fresh()->default_criticality);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_all_demo_staff_have_active_profiles_and_can_log_in(): void
    {
        $this->seed(SampleFleetSeeder::class);
        foreach (['admin' => 'admin123', 'custodian' => 'custodian123', 'custodian2' => 'custodian123', 'maintenance' => 'maintenance123', 'maintenance2' => 'maintenance123', 'maintenance3' => 'maintenance123'] as $email => $password) {
            $user = $this->staff($email);
            $this->assertTrue($user->is_active);
            $this->assertNotNull($user->approved_at);
            $this->assertSame($this->barangay->id, $user->barangay_id);
            $this->assertSame($this->barangay->city_id, $user->city_id);
            $this->assertFileExists(public_path(parse_url($user->photo_url, PHP_URL_PATH)));
            $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])->assertOk()->assertJsonStructure(['access_token', 'user']);
        }
    }

    public function test_initial_vehicle_states_do_not_change_when_the_list_is_opened(): void
    {
        $this->seed(SampleFleetSeeder::class);
        $snapshot = fn () => Vehicle::orderBy('vehicle_id')->get()->map(fn ($v) => [$v->status, $v->condition, $v->estimated_return_date?->toDateString()])->all();
        $before = $snapshot();
        Sanctum::actingAs($this->staff('admin'), ['*']);
        $this->getJson('/api/vehicles')->assertOk();
        $this->assertSame($before, $snapshot());
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'FDR-303', 'status' => 'Available', 'condition' => 'Needs Inspection', 'estimated_return_date' => null]);
        $this->assertDatabaseCount('vehicle_readiness_checks', 2);
    }

    public function test_sample_work_orders_verification_and_schedule_completion_are_actionable(): void
    {
        $this->seed(SampleFleetSeeder::class);
        $ticket = MaintenanceTicket::where('status', 'Active')->with('subIssues')->firstOrFail();
        foreach ($ticket->subIssues as $line) {
            Sanctum::actingAs(User::findOrFail($line->assigned_mechanic_id), ['*']);
            $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}/log-repairs", ['repair_logs' => 'Sample repair completed and ready for testing.'])->assertOk();
        }
        $this->assertSame('For Verification', $ticket->fresh()->status);

        $ready = MaintenanceTicket::where('status', 'For Verification')->where('ticket_id', '!=', $ticket->ticket_id)->firstOrFail();
        Sanctum::actingAs(User::findOrFail($ready->assigned_custodian_id), ['*']);
        $this->putJson("/api/tickets/{$ready->ticket_id}/verify", [
            'verification_verdict' => 'Approved', 'test_attested' => true,
            'functional_test' => [['item' => 'Warning lights tested and operating', 'passed' => true]],
        ])->assertOk();
        $this->assertSame('Closed', $ready->fresh()->status);

        $today = VehicleMaintenanceSchedule::where('status', 'Scheduled')->whereNull('resulting_ticket_id')->where('recurrence_months', 1)->firstOrFail();
        Sanctum::actingAs(User::findOrFail($today->assigned_to), ['*']);
        $this->putJson("/api/maintenance-schedules/{$today->schedule_id}/complete", ['date_completed' => now()->toDateString(), 'notes' => 'Sample service completed.'])->assertOk();
        $this->assertSame('Completed', $today->fresh()->status);
        $this->assertNotNull($today->fresh()->resulting_maintenance_id);
        $this->assertDatabaseHas('vehicle_maintenance_schedules', ['vehicle_id' => $today->vehicle_id, 'scheduled_date' => now()->addMonthNoOverflow()->toDateString(), 'status' => 'Scheduled']);
    }

    public function test_production_does_not_reset_data_or_create_demo_accounts(): void
    {
        $old = $this->vehicle('BCP-01W', 'Wagon Caddy Ambulance');
        $this->app['env'] = 'production';
        (new SampleFleetSeeder())->run();
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $old->vehicle_id]);
        $this->assertDatabaseMissing('users', ['email' => 'admin@barangay.gov']);
    }

    public function test_plate_conflicts_abort_before_resetting_anything(): void
    {
        $vehicle = $this->vehicle('BFP-5501', 'Renamed custom fire truck');
        try {
            $this->seed(SampleFleetSeeder::class);
            $this->fail('A conflicting plate must not be overwritten.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('nothing was reset', $error->getMessage());
        }
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $vehicle->vehicle_id, 'vehicle_name' => 'Renamed custom fire truck']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@barangay.gov']);
    }
}
