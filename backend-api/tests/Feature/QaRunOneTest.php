<?php
namespace Tests\Feature;

use App\Models\{ActivityLog, MaintenanceTicket, MaintenanceType, User, Vehicle, VehicleCategory, VehicleMaintenanceRecord, VehicleMaintenanceSchedule, VehicleReadinessCheck};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QaRunOneTest extends TestCase
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
        $type = VehicleCategory::create(['category_name' => 'QA Ambulance', 'domain' => 'Land']);
        $this->vehicle = Vehicle::create(['vehicle_name' => 'QA Unit', 'plate_number' => 'QA-1025', 'category_id' => $type->category_id, 'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '10 pax', 'vehicle_color' => 'White', 'current_location' => 'Depot']);
    }
    private function schedule(string $status = 'Scheduled'): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create(['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'QA service', 'scheduled_date' => now()->subDay()->toDateString(), 'status' => $status, 'created_by' => $this->custodian->id, 'assigned_to' => $this->mechanic->id]);
    }
    private function proposal(): MaintenanceTicket
    {
        Sanctum::actingAs($this->custodian);
        $id = $this->postJson('/api/tickets/propose', ['vehicle_id' => $this->vehicle->vehicle_id, 'ticket_description' => 'Brake noise observed', 'priority' => 'High', 'sub_issues' => [['title' => 'Brakes squeal', 'maintenance_type' => 'Other']]])->assertCreated()->json('ticket_id');
        return MaintenanceTicket::with('subIssues')->findOrFail($id);
    }
    public function test_future_completion_is_rejected_without_creating_a_record_or_recurrence(): void
    {
        $schedule = $this->schedule();
        Sanctum::actingAs($this->mechanic);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/complete", ['date_completed' => now('Asia/Manila')->addYear()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('date_completed');
        $this->assertSame('Scheduled', $schedule->fresh()->status);
        $this->assertDatabaseCount('vehicle_maintenance_records', 0);
        $this->assertDatabaseCount('vehicle_maintenance_schedules', 1);
    }
    public function test_only_schedule_creator_can_edit_and_owner_change_returns_for_review(): void
    {
        $schedule = $this->schedule('Pending Approval');
        Sanctum::actingAs(User::factory()->create(['role' => 'Custodian']));
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'Other editor'])->assertForbidden();
        $this->assertNull($schedule->fresh()->notes);
        Sanctum::actingAs($this->custodian);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'Creator correction'])->assertOk();
        $this->assertSame('Creator correction', $schedule->fresh()->notes);
        $this->assertSame('Pending Approval', $schedule->fresh()->status);
    }
    public function test_standalone_verification_requires_failed_reason_and_pass_attestation(): void
    {
        $record = VehicleMaintenanceRecord::create(['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'QA', 'problem_reason' => 'Routine service', 'maintenance_personnel_id' => $this->mechanic->id, 'progress_status' => 'For Verification']);
        Sanctum::actingAs($this->custodian);
        $url = "/api/maintenance-records/{$record->maintenance_id}/verify";
        $this->putJson($url, ['verification_result' => 'Failed'])->assertUnprocessable()->assertJsonValidationErrors('verification_notes');
        $this->putJson($url, ['verification_result' => 'Passed'])->assertUnprocessable()->assertJsonValidationErrors('test_attested');
        $this->assertNull($record->fresh()->verification_result);
        $this->putJson($url, ['verification_result' => 'Passed', 'test_attested' => true])->assertOk();
        $this->assertTrue((bool) $record->fresh()->test_attested);
    }
    public function test_operational_staff_can_add_but_not_rename_or_delete_shared_types(): void
    {
        foreach ([$this->custodian, $this->mechanic] as $actor) {
            Sanctum::actingAs($actor);
            $id = $this->postJson('/api/maintenance-types', ['name' => 'QA type '.$actor->id])->assertCreated()->json('id');
            $this->putJson('/api/maintenance-types/'.$id, ['name' => 'Changed'])->assertForbidden();
            $this->deleteJson('/api/maintenance-types/'.$id)->assertForbidden();
            $this->assertDatabaseHas('maintenance_types', ['id' => $id, 'name' => 'QA type '.$actor->id]);
        }
    }
    public function test_capacity_validation_rejects_negative_and_zero_but_accepts_positive(): void
    {
        $rules = (new \App\Http\Controllers\FleetController())->vehicleRules('Land');
        foreach (['-5 kg', '0 pax', 'not a quantity'] as $capacity) {
            $this->assertTrue(validator(['capacity' => $capacity], ['capacity' => $rules['capacity']])->fails());
        }
        foreach (['5 kg', '2.5 tons', '10 pax', '4'] as $capacity) {
            $this->assertFalse(validator(['capacity' => $capacity], ['capacity' => $rules['capacity']])->fails());
        }
    }
    public function test_ticket_uses_problem_title_and_unchanged_correction_does_not_resubmit(): void
    {
        $ticket = $this->proposal();
        $this->assertStringContainsString('Brakes squeal', $ticket->ticket_title);
        $this->assertStringNotContainsString('Other', $ticket->ticket_title);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/request-corrections", ['correction_reason' => 'Explain observations'])->assertOk();
        $revision = $ticket->fresh()->decision_revision;
        Sanctum::actingAs($this->custodian);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/resubmit", ['ticket_description' => 'Brake noise observed', 'priority' => 'High', 'sub_issues' => [['sub_issue_id' => $ticket->subIssues[0]->sub_issue_id, 'title' => 'Brakes squeal', 'maintenance_type' => 'Other']]])->assertUnprocessable();
        $this->assertSame($revision, $ticket->fresh()->decision_revision);
        $this->assertNotNull($ticket->fresh()->correction_requested_at);
    }
    public function test_active_ticket_cannot_be_deleted_even_before_work_is_logged(): void
    {
        $ticket = $this->proposal();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        $this->deleteJson("/api/tickets/{$ticket->ticket_id}")->assertUnprocessable();
        $this->assertDatabaseHas('maintenance_tickets', ['ticket_id' => $ticket->ticket_id, 'status' => 'Active']);
    }
    public function test_readiness_eligibility_blocks_maintenance_and_stale_available_status_without_saving(): void
    {
        $ticket=$this->proposal();
        Sanctum::actingAs($this->custodian);
        foreach (['Active','For Verification'] as $status) {
            $ticket->update(['status'=>$status]);
            $this->vehicle->update(['status'=>'Available']);
            $response=$this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness")->assertOk()->assertJsonPath('can_confirm_readiness',false);
            $this->assertStringContainsString('assigned Custodian independently verify',$response->json('readiness_block_reason'));
            $this->getJson('/api/vehicles')->assertOk()->assertJsonPath('0.can_confirm_readiness',false);
            $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check",['confirmed'=>true])->assertUnprocessable();
        }
        $ticket->update(['status'=>'Closed']);
        $this->vehicle->update(['status'=>'Under Maintenance']);
        $this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness")->assertOk()->assertJsonPath('can_confirm_readiness',false);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check",['confirmed'=>true])->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_readiness_checks',0);
        $this->vehicle->update(['status'=>'Available','condition'=>'Good']);
        $this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness")->assertOk()->assertJsonPath('can_confirm_readiness',true);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check",['confirmed'=>true])->assertCreated()->assertJsonPath('state','ready');
    }

    public function test_repair_closure_does_not_reuse_a_pre_repair_readiness_pass(): void
    {
        $this->travelTo(now()->startOfSecond());
        VehicleReadinessCheck::create(['vehicle_id' => $this->vehicle->vehicle_id, 'checked_by' => $this->custodian->id, 'checklist' => [], 'all_passed' => true, 'checked_at' => now()->subHour()]);
        $ticket = $this->proposal();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/approve", ['assigned_mechanic_id' => $this->mechanic->id])->assertOk();
        Sanctum::actingAs($this->custodian);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check", ['confirmed' => true])->assertUnprocessable();
        Sanctum::actingAs($this->mechanic);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$ticket->subIssues[0]->sub_issue_id}/log-repairs", ['repair_logs' => 'Brake repaired'])->assertOk();
        Sanctum::actingAs($this->custodian);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/verify", ['verification_verdict' => 'Approved', 'test_attested' => true])->assertOk();
        $this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness")->assertOk()->assertJsonPath('state', 'stale');
        $this->travel(1)->seconds();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check", ['confirmed' => true])->assertCreated()->assertJsonPath('state', 'ready');
        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $this->vehicle->vehicle_id, 'activity_type' => 'Ticket Closed', 'related_table' => 'maintenance_tickets', 'related_record_id' => $ticket->ticket_id]);
    }
    public function test_impersonated_activity_keeps_real_initiator_and_reason(): void
    {
        $issued = $this->custodian->createToken('impersonation_token');
        $issued->accessToken->forceFill(['impersonator_user_id' => $this->admin->id, 'impersonation_reason' => 'Investigate QA finding'])->save();
        auth()->setUser($this->custodian->withAccessToken($issued->accessToken));
        $log = ActivityLog::create(['user_id' => $this->custodian->id, 'role' => 'Custodian', 'action' => 'QA event', 'module' => 'QA', 'details' => 'Synthetic QA action']);
        $this->assertSame($this->admin->id, $log->impersonator_user_id);
        $this->assertSame($issued->accessToken->id, $log->impersonation_session_id);
        $this->assertStringContainsString('Impersonated', $log->details);
        $this->assertSame('Investigate QA finding', $log->impersonation_reason);
    }
    public function test_schedule_receipt_waits_for_inspection_without_diagnosing_a_new_repair(): void
    {
        $schedule = $this->schedule();
        Sanctum::actingAs($this->mechanic);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/complete", [])->assertOk();
        $this->assertSame('Needs Inspection', $this->vehicle->fresh()->condition);
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
    }
    public function test_pending_proposal_does_not_claim_that_repairs_have_started(): void
    {
        $ticket = $this->proposal();
        $this->assertSame('Under Review', $ticket->issueReport->status);
        \App\Services\VehicleOperationalState::refresh($this->vehicle);
        $this->assertNotSame('Needs Repair', $this->vehicle->fresh()->condition);
    }
    public function test_safeguard_migration_rolls_down_and_up_without_losing_vehicles(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_qa_audit_safeguards.php');
        $id = $this->vehicle->vehicle_id;
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('vehicles', 'readiness_invalidated_at'));
        $migration->up();
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $id, 'plate_number' => 'QA-1025']);
        $this->assertNotNull($this->vehicle->fresh()->readiness_invalidated_at);
    }

    public function test_pending_work_does_not_erase_a_newer_good_physical_observation(): void
    {
        $schedule = $this->schedule();
        Sanctum::actingAs($this->mechanic);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/complete", [])->assertOk();
        $this->vehicle->refresh()->update(['condition' => 'Good']);
        \App\Services\VehicleOperationalState::refresh($this->vehicle);
        $this->assertSame('Good', $this->vehicle->fresh()->condition);
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
    }

    public function test_reports_flag_legacy_invalid_data_without_guessing_or_rewriting_it(): void
    {
        $this->vehicle->update(['capacity' => '-5 kg']);
        $schedule = $this->schedule('Completed');
        $record = VehicleMaintenanceRecord::create(['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'QA', 'problem_reason' => 'Synthetic historic receipt', 'date_started' => now()->addYear()->toDateString(), 'progress_status' => 'For Verification', 'maintenance_cost' => 1234.50]);
        $schedule->update(['resulting_maintenance_id' => $record->maintenance_id]);
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/reports?report_type=Vehicle%20Inventory%20Report')->assertOk()->assertJsonPath('rows.0.data_quality_warnings.0', 'Invalid capacity: factual correction required');
        $this->getJson('/api/reports?report_type=Vehicle%20Maintenance%20Report')->assertOk()->assertJsonPath('rows.0.data_quality_warnings.0', 'Future completed-work date: factual correction required');
        $this->assertSame('-5 kg', $this->vehicle->fresh()->capacity);
        $this->assertSame(now()->addYear()->toDateString(), $record->fresh()->date_started);
    }

}
