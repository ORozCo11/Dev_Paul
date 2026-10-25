<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FaultCategory;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceType;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fault categories and maintenance types are global catalogs referenced by
 * NAME (not FK) from tickets/issue-reports/records/schedules. These tests
 * prove destroy is blocked while a name is still referenced ANYWHERE — even
 * in a barangay other than the deleting Admin's own, since the underlying
 * tables are normally tenant-scoped — and allowed once nothing depends on it.
 */
class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function operational_roles_can_add_but_cannot_edit_or_delete_shared_catalogs(): void
    {
        foreach (['Custodian', 'Maintenance Personnel'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]), ['*']);
            foreach (['fault-categories', 'maintenance-types', 'reported-persons'] as $endpoint) {
                $name = 'Dropdown ' . uniqid();
                $created = $this->postJson('/api/' . $endpoint, ['name' => $name])->assertCreated();
                $id = $created->json('id');
                $this->putJson('/api/' . $endpoint . '/' . $id, ['name' => $name . ' renamed'])
                    ->assertForbidden();
                $this->deleteJson('/api/' . $endpoint . '/' . $id)->assertForbidden();
            }
        }
    }

    private function vehicle(?int $barangayId = null): Vehicle
    {
        $category = VehicleCategory::create([
            'category_name' => 'Ambulance ' . uniqid(),
            'description' => 'For testing',
        ]);

        return Vehicle::create([
            'vehicle_name' => 'Test Ambulance',
            'plate_number' => 'TST ' . random_int(1000, 9999),
            'category_id' => $category->category_id,
            'brand' => 'Toyota',
            'model' => 'HiAce',
            'year_model' => 2022,
            'capacity' => '1000 kg',
            'vehicle_color' => 'White',
            'current_location' => 'Main Depot',
            'barangay_id' => $barangayId,
        ]);
    }

    // -------------------------------------------------------------------
    // Fault categories
    // -------------------------------------------------------------------

    #[Test]
    public function fault_category_delete_is_blocked_while_a_ticket_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $creator = User::factory()->create(['role' => 'Admin']);
        $category = FaultCategory::create(['name' => 'Test Engine Problem ' . uniqid()]);
        $vehicle = $this->vehicle();

        MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'created_by' => $creator->id,
            'ticket_title' => 'Overheating',
            'fault_category' => $category->name,
            'ticket_description' => 'Runs hot.',
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/fault-categories/{$category->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Can't delete \"{$category->name}\" — it's still used by 1 ticket."]);
        $this->assertDatabaseHas('fault_categories', ['id' => $category->id]);
    }

    #[Test]
    public function fault_category_delete_is_blocked_by_an_issue_report_in_a_different_barangay()
    {
        // The Admin deleting is in barangayA; the referencing issue report's
        // vehicle is in barangayB. ScopedThroughVehicle would normally hide
        // that row from this Admin's queries — the guard must see it anyway.
        $barangayA = Barangay::create(['name' => 'Barangay A ' . uniqid()]);
        $barangayB = Barangay::create(['name' => 'Barangay B ' . uniqid()]);
        $admin = User::factory()->create(['role' => 'Admin', 'barangay_id' => $barangayA->id]);
        $reporter = User::factory()->create(['role' => 'Custodian', 'barangay_id' => $barangayB->id]);
        $category = FaultCategory::create(['name' => 'Test Tire Problem ' . uniqid()]);
        $vehicle = $this->vehicle($barangayB->id);

        VehicleIssueReport::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'issue_type' => $category->name,
            'issue_description' => 'Flat tire.',
            'reported_by' => $reporter->id,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/fault-categories/{$category->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('fault_categories', ['id' => $category->id]);
    }

    #[Test]
    public function fault_category_delete_succeeds_once_nothing_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $category = FaultCategory::create(['name' => 'Test Unused Category ' . uniqid()]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/fault-categories/{$category->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('fault_categories', ['id' => $category->id]);
    }

    #[Test]
    public function renaming_a_fault_category_cascades_to_every_ticket_and_issue_report_that_used_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $reporter = User::factory()->create(['role' => 'Custodian']);
        $category = FaultCategory::create(['name' => 'Brake Problem ' . uniqid()]);
        $vehicle = $this->vehicle();

        $ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'created_by' => $admin->id,
            'ticket_title' => 'Overheating',
            'fault_category' => $category->name,
            'ticket_description' => 'Runs hot.',
        ]);
        $issue = VehicleIssueReport::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'issue_type' => $category->name,
            'issue_description' => 'Flat tire.',
            'reported_by' => $reporter->id,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $newName = 'Braking System Problem ' . uniqid();
        $this->putJson("/api/fault-categories/{$category->id}", ['name' => $newName])->assertOk();

        $this->assertDatabaseHas('fault_categories', ['id' => $category->id, 'name' => $newName]);
        $this->assertSame($newName, $ticket->fresh()->fault_category);
        $this->assertSame($newName, $issue->fresh()->issue_type);
    }

    #[Test]
    public function renaming_a_fault_category_to_an_existing_name_is_rejected()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $a = FaultCategory::create(['name' => 'Engine Problem ' . uniqid()]);
        $b = FaultCategory::create(['name' => 'Electrical Problem ' . uniqid()]);

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/fault-categories/{$a->id}", ['name' => $b->name])->assertStatus(422);

        $this->assertDatabaseHas('fault_categories', ['id' => $a->id, 'name' => $a->name]);
    }

    // -------------------------------------------------------------------
    // Maintenance types
    // -------------------------------------------------------------------

    #[Test]
    public function maintenance_type_delete_is_blocked_while_a_sub_issue_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $type = MaintenanceType::create(['name' => 'Test Brake Repair ' . uniqid()]);
        $vehicle = $this->vehicle();

        $ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'created_by' => $admin->id,
            'ticket_title' => 'Brake noise',
            'ticket_description' => 'Squeaking.',
        ]);

        TicketSubIssue::create([
            'ticket_id' => $ticket->ticket_id,
            'created_by' => $admin->id,
            'title' => 'Worn brake pads',
            'maintenance_type' => $type->name,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/maintenance-types/{$type->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Can't delete \"{$type->name}\" — it's still used by 1 work order."]);
        $this->assertDatabaseHas('maintenance_types', ['id' => $type->id]);
    }

    #[Test]
    public function maintenance_type_delete_is_blocked_while_a_maintenance_record_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $type = MaintenanceType::create(['name' => 'Test Oil Change ' . uniqid()]);
        $vehicle = $this->vehicle();

        VehicleMaintenanceRecord::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => $type->name,
            'problem_reason' => 'Scheduled oil change.',
            'maintenance_personnel_id' => $mechanic->id,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/maintenance-types/{$type->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('maintenance_types', ['id' => $type->id]);
    }

    #[Test]
    public function maintenance_type_delete_is_blocked_while_a_schedule_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $type = MaintenanceType::create(['name' => 'Test General Inspection ' . uniqid()]);
        $vehicle = $this->vehicle();

        VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => $type->name,
            'scheduled_date' => now()->addWeek()->toDateString(),
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/maintenance-types/{$type->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('maintenance_types', ['id' => $type->id]);
    }

    #[Test]
    public function renaming_a_maintenance_type_cascades_to_sub_issues_records_and_schedules()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $type = MaintenanceType::create(['name' => 'Brake Repair ' . uniqid()]);
        $vehicle = $this->vehicle();

        $ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'created_by' => $admin->id,
            'ticket_title' => 'Brake noise',
            'ticket_description' => 'Squeaking.',
        ]);
        $subIssue = TicketSubIssue::create([
            'ticket_id' => $ticket->ticket_id,
            'created_by' => $admin->id,
            'title' => 'Worn brake pads',
            'maintenance_type' => $type->name,
        ]);
        $record = VehicleMaintenanceRecord::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => $type->name,
            'problem_reason' => 'Worn pads.',
            'maintenance_personnel_id' => $mechanic->id,
        ]);
        $schedule = VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'maintenance_type' => $type->name,
            'scheduled_date' => now()->addWeek()->toDateString(),
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin, ['*']);
        $newName = 'Brake System Repair ' . uniqid();
        $this->putJson("/api/maintenance-types/{$type->id}", ['name' => $newName])->assertOk();

        $this->assertDatabaseHas('maintenance_types', ['id' => $type->id, 'name' => $newName]);
        $this->assertSame($newName, $subIssue->fresh()->maintenance_type);
        $this->assertSame($newName, $record->fresh()->maintenance_type);
        $this->assertSame($newName, $schedule->fresh()->maintenance_type);
    }

    #[Test]
    public function renaming_a_maintenance_type_to_an_existing_name_is_rejected()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $a = MaintenanceType::create(['name' => 'Oil Change ' . uniqid()]);
        $b = MaintenanceType::create(['name' => 'Tire Rotation ' . uniqid()]);

        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/maintenance-types/{$a->id}", ['name' => $b->name])->assertStatus(422);

        $this->assertDatabaseHas('maintenance_types', ['id' => $a->id, 'name' => $a->name]);
    }

    #[Test]
    public function maintenance_type_delete_succeeds_once_nothing_references_it()
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $type = MaintenanceType::create(['name' => 'Test Unused Type ' . uniqid()]);

        Sanctum::actingAs($admin, ['*']);
        $response = $this->deleteJson("/api/maintenance-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('maintenance_types', ['id' => $type->id]);
    }
}
