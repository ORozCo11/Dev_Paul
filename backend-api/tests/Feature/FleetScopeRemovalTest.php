<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleHistory;
use App\Models\VehicleReadinessCheck;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetScopeRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // DDL rollback tests need a separate connection, outside the shared
        // RefreshDatabase transaction used by the other feature tests.
        config(['database.default' => 'scope_removal_qa', 'database.connections.scope_removal_qa' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    private function removal()
    {
        return require database_path('migrations/2026_10_24_000001_remove_vehicle_operations.php');
    }

    private function fixture(): array
    {
        $barangay = Barangay::create(['name' => 'Scope QA']);
        $user = User::factory()->create(['role' => 'Admin', 'roles' => ['Admin'], 'barangay_id' => $barangay->id, 'is_active' => true, 'approved_at' => now()]);
        $category = VehicleCategory::create(['category_name' => 'Scope Rescue', 'domain' => 'Land']);
        $vehicle = Vehicle::create(['barangay_id' => $barangay->id, 'vehicle_name' => 'Scope Vehicle', 'plate_number' => 'SCOPE-1', 'category_id' => $category->category_id,
            'brand' => 'Toyota', 'model' => 'Rescue', 'year_model' => 2022, 'capacity' => '1000 kg', 'vehicle_color' => 'White', 'current_location' => 'Depot', 'status' => 'Available', 'condition' => 'Needs Repair']);
        return [$user, $vehicle];
    }

    public function test_fresh_database_can_roll_back_and_reapply_the_removal(): void
    {
        $migration = $this->removal();
        foreach ($migration::REMOVED_TABLES as $table => $key) $this->assertFalse(Schema::hasTable($table));
        // Exercise this migration, independent of later feature migrations.
        $migration->down();
        foreach ($migration::REMOVED_TABLES as $table => $key) $this->assertTrue(Schema::hasTable($table));
        $migration->up();
        foreach ($migration::REMOVED_TABLES as $table => $key) $this->assertFalse(Schema::hasTable($table));
    }

    public function test_existing_records_are_removed_from_active_views_and_restored_on_rollback(): void
    {
        $migration = $this->removal();
        $migration->down();
        [$user, $vehicle] = $this->fixture();
        $vehicle->update(['status' => $migration::RETIRED_STATUSES[0]]);
        $check = VehicleReadinessCheck::create(['vehicle_id' => $vehicle->vehicle_id, 'checked_by' => $user->id, 'checked_at' => now(), 'all_passed' => false, 'checklist' => [['item' => 'Fuel', 'passed' => false]]]);
        $ticket = MaintenanceTicket::create(['vehicle_id' => $vehicle->vehicle_id, 'created_by' => $user->id, 'ticket_title' => 'Repair', 'ticket_description' => 'Outstanding work', 'priority' => 'High', 'status' => 'Active']);
        [$logs, $assessments] = array_keys($migration::REMOVED_TABLES);
        $id = DB::table($logs)->insertGetId(['vehicle_id' => $vehicle->vehicle_id, 'logged_by' => $user->id, 'purpose' => 'Legacy data', 'started_at' => now(), 'notes' => 'Preserve this evidence', 'temporary_release' => json_encode(['reason' => 'Legacy release'])], 'usage_id');
        DB::table($assessments)->insert(['ticket_id' => $ticket->ticket_id, 'assessed_by' => $user->id, 'safe_to_operate' => false, 'assembled' => false, 'no_safety_critical_fault' => false, 'notes' => 'Historical assessment', 'context_hash' => str_repeat('a', 64), 'assessed_at' => now()]);
        $removedHistory = VehicleHistory::create(['vehicle_id' => $vehicle->vehicle_id, 'activity_type' => 'Vehicle Taken Out', 'description' => 'Legacy event', 'related_table' => $logs, 'related_record_id' => (string) $id, 'updated_by' => $user->id]);
        $keptHistory = VehicleHistory::create(['vehicle_id' => $vehicle->vehicle_id, 'activity_type' => 'Vehicle Updated', 'description' => 'Keep maintenance history', 'updated_by' => $user->id]);
        $removedNotice = Notification::create(['user_id' => $user->id, 'title' => 'Legacy notice', 'message' => 'Legacy event', 'type' => 'temporary_dispatch', 'vehicle_id' => $vehicle->vehicle_id]);
        $keptNotice = Notification::create(['user_id' => $user->id, 'title' => 'Repair notice', 'message' => 'Keep this', 'type' => 'repair_returned', 'ticket_id' => $ticket->ticket_id]);
        $removedLog = ActivityLog::create(['user_id' => $user->id, 'role' => 'Admin', 'action' => 'Add', 'module' => 'Vehicle Usage', 'details' => 'Legacy event']);
        $keptLog = ActivityLog::create(['user_id' => $user->id, 'role' => 'Admin', 'action' => 'Approve Ticket', 'module' => 'Maintenance Tickets', 'details' => 'Keep this']);

        $migration->up();
        foreach ($migration::REMOVED_TABLES as $table => $key) $this->assertFalse(Schema::hasTable($table));
        $this->assertSame('Under Maintenance', $vehicle->fresh()->status);
        $this->assertSame('Needs Repair', $vehicle->fresh()->condition);
        $this->assertFalse($check->fresh()->all_passed);
        $this->assertSame('Active', $ticket->fresh()->status);
        $this->assertNull($removedHistory->fresh());
        $this->assertNull($removedNotice->fresh());
        $this->assertNull($removedLog->fresh());
        $this->assertNotNull($keptHistory->fresh());
        $this->assertNotNull($keptNotice->fresh());
        $this->assertNotNull($keptLog->fresh());

        $migration->down();
        $this->assertSame($migration::RETIRED_STATUSES[0], $vehicle->fresh()->status);
        $this->assertSame('Preserve this evidence', DB::table($logs)->where('usage_id', $id)->value('notes'));
        $this->assertSame('Historical assessment', DB::table($assessments)->value('notes'));
        $this->assertNotNull($removedHistory->fresh());
        $this->assertNotNull($removedNotice->fresh());
        $this->assertNotNull($removedLog->fresh());
        // A second complete cycle must preserve the same evidence.
        $migration->up();
        $migration->down();
        $this->assertSame('Preserve this evidence', DB::table($logs)->where('usage_id', $id)->value('notes'));
    }

    public function test_legacy_availability_is_normalized_without_changing_other_vehicle_states(): void
    {
        $migration = $this->removal();
        $migration->down();
        [$user, $vehicle] = $this->fixture();
        foreach ($migration::RETIRED_STATUSES as $oldStatus) {
            $vehicle->update(['status' => $oldStatus]);
            $migration->up();
            $this->assertSame('Under Maintenance', $vehicle->fresh()->status);
            $this->assertSame('Needs Repair', $vehicle->fresh()->condition);
            $migration->down();
            $this->assertSame($oldStatus, $vehicle->fresh()->status);
        }
        foreach (['Available', 'Under Maintenance', 'Inactive', 'Decommissioned'] as $status) {
            $vehicle->update(['status' => $status]);
            $migration->up();
            $this->assertSame($status, $vehicle->fresh()->status);
            $migration->down();
        }
    }

    public function test_retired_routes_and_abilities_are_absent_but_fleet_readiness_is_available(): void
    {
        [$user, $vehicle] = $this->fixture();
        Sanctum::actingAs($user, ['*']);
        $this->getJson("/api/vehicles/{$vehicle->vehicle_id}/usage")->assertNotFound();
        $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/usage")->assertNotFound();
        $this->putJson('/api/usage-logs/1/end')->assertNotFound();
        $this->postJson('/api/tickets/1/dispatch-assessment')->assertNotFound();
        foreach (config('permissions') as $ability => $roles) $this->assertFalse(str_starts_with($ability, 'usage.'));
        $vehicles = $this->getJson('/api/vehicles')->assertOk()->json();
        $this->assertSame('Available', $vehicles[0]['status']);
        $this->assertSame('Needs Repair', $vehicles[0]['condition']);
        $this->assertArrayHasKey('readiness_state', $vehicles[0]);
        $this->assertArrayNotHasKey('open_trip', $vehicles[0]);
    }

    public function test_database_rejects_retired_availability_values(): void
    {
        [$user, $vehicle] = $this->fixture();
        $this->expectException(QueryException::class);
        $vehicle->update(['status' => $this->removal()::RETIRED_STATUSES[0]]);
    }
}
