<?php

namespace Tests\Feature;

use App\Http\Controllers\FleetController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VehicleUsageController;
use App\Models\Barangay;
use App\Models\MaintenanceTicket;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use App\Models\VehicleUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SystemWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $custodian;

    private User $mechanic;

    private Vehicle $vehicle;

    private int $barangayId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->barangayId = Barangay::create(['name' => 'Audit Barangay'])->id;
        $this->admin = $this->person('Admin');
        $this->custodian = $this->person('Custodian');
        $this->mechanic = $this->person('Maintenance Personnel');
        $type = VehicleCategory::create(['category_name' => 'Audit Rescue', 'domain' => 'Land']);
        $this->vehicle = Vehicle::create(['barangay_id' => $this->barangayId, 'vehicle_name' => 'Audit Vehicle', 'plate_number' => 'AUD-001', 'category_id' => $type->category_id, 'brand' => 'Toyota', 'model' => 'Rescue', 'year_model' => 2022, 'capacity' => '1000 kg', 'vehicle_color' => 'White', 'current_location' => 'Depot', 'status' => 'Available', 'condition' => 'Good']);
    }

    private function person(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => $role, 'roles' => [$role], 'barangay_id' => $this->barangayId, 'is_active' => true, 'approved_at' => now()]);
    }

    private function schedule(array $extra = []): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create($extra + ['vehicle_id' => $this->vehicle->vehicle_id, 'created_by' => $this->custodian->id, 'maintenance_type' => 'Engine Service', 'scheduled_date' => now()->addDays(5)->toDateString(), 'status' => 'Pending Approval']);
    }

    private function ticket(array $extra = []): MaintenanceTicket
    {
        return MaintenanceTicket::create($extra + ['vehicle_id' => $this->vehicle->vehicle_id, 'created_by' => $this->custodian->id, 'assigned_custodian_id' => $this->custodian->id, 'ticket_title' => 'Audit Repair', 'ticket_description' => 'Repair engine', 'priority' => 'High', 'status' => 'Active']);
    }

    private function line(MaintenanceTicket $ticket, User $mechanic, array $extra = []): TicketSubIssue
    {
        return TicketSubIssue::create($extra + ['ticket_id' => $ticket->ticket_id, 'created_by' => $this->custodian->id, 'title' => 'Repair item '.uniqid(), 'repair_type' => 'in_house', 'assigned_mechanic_id' => $mechanic->id, 'status' => 'Under Repair']);
    }

    private function record(array $extra = []): VehicleMaintenanceRecord
    {
        return VehicleMaintenanceRecord::create($extra + ['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Service', 'problem_reason' => 'Engine fault', 'date_started' => now()->toDateString(), 'maintenance_personnel_id' => $this->mechanic->id, 'progress_status' => 'For Verification']);
    }

    public function test_schedule_edit_cannot_self_approve_but_preserves_shared_custodian_planning(): void
    {
        $schedule = $this->schedule();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['status' => 'Scheduled'])->assertUnprocessable();
        Sanctum::actingAs($this->person('Custodian'), ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'Shared plan update'])->assertOk()->assertJsonPath('status', 'Pending Approval');
    }

    public function test_schedule_assignment_rejects_wrong_roles_inactive_and_cross_barangay_users(): void
    {
        $outsider = $this->person('Maintenance Personnel', ['barangay_id' => Barangay::create(['name' => 'Other barangay'])->id]);
        $inactive = $this->person('Maintenance Personnel', ['is_active' => false]);
        foreach ([$this->admin, $outsider, $inactive] as $invalid) {
            Sanctum::actingAs($this->custodian, ['*']);
            $this->postJson('/api/maintenance-schedules', ['vehicle_id' => $this->vehicle->vehicle_id, 'maintenance_type' => 'Service', 'scheduled_date' => now()->addDays(5)->toDateString(), 'assigned_to' => $invalid->id])->assertUnprocessable();
            $schedule = $this->schedule(['status' => 'Scheduled']);
            Sanctum::actingAs($this->admin, ['*']);
            $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/reassign", ['assigned_to' => $invalid->id])->assertUnprocessable();
        }
    }

    public function test_only_admin_reassignment_changes_an_approved_schedules_mechanic(): void
    {
        $schedule = $this->schedule(['status' => 'Scheduled', 'assigned_to' => $this->mechanic->id]);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['assigned_to' => $this->person('Maintenance Personnel')->id])->assertForbidden();
    }

    public function test_restoring_a_cancelled_proposal_still_requires_approval(): void
    {
        $schedule = $this->schedule(['status' => 'Cancelled']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/maintenance-schedules/{$schedule->schedule_id}/restore")->assertOk()->assertJsonPath('status', 'Pending Approval');
    }

    public function test_completed_schedules_cannot_be_recycled_into_new_work(): void
    {
        $schedule = $this->schedule(['status' => 'Completed']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'Rewrite history'])->assertUnprocessable();
        $this->deleteJson("/api/maintenance-schedules/{$schedule->schedule_id}")->assertUnprocessable();
    }

    public function test_editing_an_approved_plan_returns_it_for_review(): void
    {
        $schedule = $this->schedule(['status' => 'Scheduled']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['notes' => 'Different work scope'])->assertOk()->assertJsonPath('status', 'Pending Approval');
    }

    public function test_linked_proposals_already_lock_the_source_schedule(): void
    {
        $ticket = $this->ticket(['status' => 'Pending Approval']);
        $schedule = $this->schedule(['status' => 'Scheduled', 'resulting_ticket_id' => $ticket->ticket_id]);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}", ['maintenance_type' => 'Unrelated work'])->assertUnprocessable();
    }

    public function test_reactivation_cannot_demote_an_existing_admin(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/users/{$this->admin->id}/activate", ['role' => 'Custodian'])->assertUnprocessable();
        $this->assertTrue($this->admin->fresh()->hasRole('Admin'));
    }

    public function test_admin_created_accounts_are_approved_and_active_legacy_accounts_cannot_be_rejected(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson('/api/users', ['name' => 'New Staff', 'email' => 'new-staff@test.invalid', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'Custodian'])->assertCreated()->assertJsonPath('is_active', true);
        $this->assertNotNull(User::where('email', 'new-staff@test.invalid')->firstOrFail()->approved_at);
        $legacy = $this->person('Custodian', ['approved_at' => null]);
        Sanctum::actingAs($this->person('Super Admin', ['barangay_id' => null]), ['*']);
        $this->deleteJson("/api/superadmin/users/{$legacy->id}/reject")->assertUnprocessable();
        $ids = collect($this->getJson('/api/superadmin/pending-approvals')->assertOk()->json())->pluck('id');
        $this->assertFalse($ids->contains($legacy->id));
    }

    public function test_password_resets_revoke_old_sessions(): void
    {
        $this->custodian->createToken('old-session-one');
        $this->custodian->createToken('old-session-two');
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/users/{$this->custodian->id}", ['password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertOk();
        $this->assertSame(0, $this->custodian->tokens()->count());
    }

    public function test_multi_mechanic_bulk_reassignment_notifies_all_outgoing_mechanics(): void
    {
        $ticket = $this->ticket();
        $second = $this->person('Maintenance Personnel');
        $this->line($ticket, $this->mechanic);
        $this->line($ticket, $second);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/assign-mechanic", ['assigned_mechanic_id' => $this->person('Maintenance Personnel')->id, 'reassign_reason' => 'Shift change'])->assertOk();
        foreach ([$this->mechanic, $second] as $outgoing) {
            $this->assertDatabaseHas('notifications', ['user_id' => $outgoing->id, 'type' => 'ticket_reassigned']);
        }
    }

    public function test_new_sub_issues_on_split_assignments_need_an_explicit_mechanic(): void
    {
        $ticket = $this->ticket();
        $this->line($ticket, $this->mechanic);
        $this->line($ticket, $this->person('Maintenance Personnel'));
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson("/api/tickets/{$ticket->ticket_id}/sub-issues", ['title' => 'Another repair'])->assertUnprocessable();
        $this->postJson("/api/tickets/{$ticket->ticket_id}/sub-issues", ['title' => 'Another repair', 'assigned_mechanic_id' => $this->mechanic->id])->assertCreated()->assertJsonPath('status', 'Under Repair')->assertJsonPath('assigned_mechanic_id', $this->mechanic->id);
    }

    public function test_cancelled_ticket_work_does_not_prevent_account_deactivation(): void
    {
        $ticket = $this->ticket(['status' => 'Cancelled']);
        $this->line($ticket, $this->mechanic);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/users/{$this->mechanic->id}/deactivate")->assertOk();
    }

    public function test_record_confirmation_cannot_release_a_vehicle_with_other_active_work(): void
    {
        $this->vehicle->update(['status' => 'Under Maintenance', 'condition' => 'Needs Repair']);
        $this->ticket();
        $record = $this->record(['verification_result' => 'Passed', 'verified_by' => $this->custodian->id]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/confirm", ['confirmed' => true])->assertOk();
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
    }

    public function test_record_verification_never_unretires_a_vehicle(): void
    {
        $record = $this->record();
        $this->vehicle->update(['status' => 'Decommissioned', 'condition' => 'Needs Repair']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/verify", ['verification_result' => 'Passed'])->assertOk();
        $this->assertSame('Decommissioned', $this->vehicle->fresh()->status);
    }

    public function test_reassignment_requires_active_personnel_and_relogs_submitted_repairs(): void
    {
        $ticket = $this->ticket(['status' => 'For Verification', 'assigned_mechanic_id' => $this->mechanic->id]);
        $line = $this->line($ticket, $this->mechanic, ['status' => 'For Inspection', 'repair_logs' => 'Original work', 'repair_completed_at' => now()]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/assign-mechanic", ['assigned_mechanic_id' => $this->person('Maintenance Personnel', ['is_active' => false])->id, 'reassign_reason' => 'Change shift'])->assertUnprocessable();
        $new = $this->person('Maintenance Personnel');
        $this->putJson("/api/tickets/{$ticket->ticket_id}/assign-mechanic", ['assigned_mechanic_id' => $new->id, 'reassign_reason' => 'Change shift'])->assertOk();
        $this->assertSame('Under Repair', $line->fresh()->status);
        $this->assertSame('Original work', $line->fresh()->repair_logs);
        Sanctum::actingAs($new, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/submit-for-verification")->assertUnprocessable();
    }

    public function test_changing_repair_method_cannot_skip_a_shop_return(): void
    {
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic, ['repair_type' => 'external', 'external_sent_at' => now()]);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}/log-repairs", ['repair_type' => 'in_house', 'repair_logs' => 'Changed method'])->assertUnprocessable();
        $this->assertSame('Under Repair', $line->fresh()->status);
    }

    public function test_platform_role_edit_cannot_turn_the_tenantless_impersonation_account_into_staff(): void
    {
        $support = $this->person('Impersonator', ['barangay_id' => null]);
        Sanctum::actingAs($this->person('Super Admin', ['barangay_id' => null]), ['*']);
        $this->putJson("/api/superadmin/users/{$support->id}/role", ['role' => 'Admin'])->assertUnprocessable();
        $this->assertTrue($support->fresh()->hasRole('Impersonator'));
    }

    public function test_password_change_revokes_other_sessions_and_preserves_the_current_session(): void
    {
        $this->custodian->update(['password' => Hash::make('oldpassword123')]);
        $current = $this->custodian->createToken('current');
        $this->custodian->createToken('other');
        $this->withToken($current->plainTextToken)->putJson('/api/profile/password', ['old_password' => 'oldpassword123', 'new_password' => 'newpassword123', 'new_password_confirmation' => 'newpassword123'])->assertOk();
        $this->assertSame(1, $this->custodian->tokens()->count());
        $this->assertTrue($this->custodian->tokens()->whereKey($current->accessToken->id)->exists());
    }

    public function test_stale_trip_return_cannot_overwrite_an_already_recorded_return(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $trip = VehicleUsageLog::create(['vehicle_id' => $this->vehicle->vehicle_id, 'logged_by' => $this->admin->id, 'purpose' => 'Dispatch', 'started_at' => now()->subHour()]);
        $trip->newQuery()->whereKey($trip->usage_id)->update(['ended_at' => now(), 'notes' => 'First return']);
        $request = Request::create('/api/usage-logs/'.$trip->usage_id.'/end', 'PUT', ['notes' => 'Stale overwrite']);
        $request->setUserResolver(fn () => $this->admin);
        try {
            app(VehicleUsageController::class)->end($request, $trip);
            $this->fail('A stale return must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame('First return', $trip->fresh()->notes);
    }

    public function test_stale_dispatch_cannot_use_a_newly_retired_vehicle(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->vehicle->newQuery()->whereKey($this->vehicle->vehicle_id)->update(['status' => 'Decommissioned']);
        $request = Request::create('/api/vehicles/'.$this->vehicle->vehicle_id.'/usage', 'POST', ['purpose' => 'Dispatch']);
        $request->setUserResolver(fn () => $this->admin);
        try {
            app(VehicleUsageController::class)->start($request, $this->vehicle);
            $this->fail('A retired vehicle must not be dispatched from stale state.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('vehicle_usage_logs', 0);
    }

    public function test_due_conversion_uses_a_custodian_from_the_vehicles_barangay(): void
    {
        $outsider = $this->person('Custodian', ['barangay_id' => Barangay::create(['name' => 'Another place'])->id]);
        $schedule = $this->schedule(['status' => 'Scheduled', 'scheduled_date' => now()->subDay()->toDateString(), 'created_by' => $outsider->id]);
        $this->artisan('schedules:convert-due-to-tickets')->assertSuccessful();
        $ticket = MaintenanceTicket::findOrFail($schedule->fresh()->resulting_ticket_id);
        $this->assertSame($this->custodian->id, $ticket->assigned_custodian_id);
        $this->artisan('schedules:convert-due-to-tickets')->assertSuccessful();
        $this->assertSame(1, MaintenanceTicket::count());
    }

    public function test_record_decision_close_preserves_other_active_repairs_and_retirement(): void
    {
        $ticket = $this->ticket();
        $record = $this->record();
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/decision-close", ['closure_reason' => 'Historical duplicate', 'returned_to_service' => true])->assertOk();
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
        $ticket->update(['status' => 'Closed']);
        $this->vehicle->update(['status' => 'Decommissioned']);
        $record = $this->record();
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/decision-close", ['closure_reason' => 'Retired', 'returned_to_service' => true])->assertOk();
        $this->assertSame('Decommissioned', $this->vehicle->fresh()->status);
    }

    public function test_stale_schedule_completion_cannot_race_a_new_ticket_proposal(): void
    {
        $schedule = $this->schedule(['status' => 'Scheduled', 'assigned_to' => $this->mechanic->id]);
        $ticket = $this->ticket(['status' => 'Pending Approval']);
        $schedule->newQuery()->whereKey($schedule->schedule_id)->update(['resulting_ticket_id' => $ticket->ticket_id]);
        Sanctum::actingAs($this->mechanic, ['*']);
        $request = Request::create('/api/maintenance-schedules/'.$schedule->schedule_id.'/complete', 'PUT');
        $request->setUserResolver(fn () => $this->mechanic);
        try {
            app(FleetController::class)->completeSchedule($request, $schedule);
            $this->fail('Ticket-linked work must not also be completed from a stale schedule.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('vehicle_maintenance_records', 0);
    }

    public function test_retired_vehicles_cannot_be_unretired_by_an_old_scheduled_job(): void
    {
        $schedule = $this->schedule(['status' => 'Scheduled', 'assigned_to' => $this->mechanic->id]);
        $this->vehicle->update(['status' => 'Decommissioned']);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/maintenance-schedules/{$schedule->schedule_id}/complete")->assertUnprocessable();
        $this->assertSame('Decommissioned', $this->vehicle->fresh()->status);
    }

    public function test_stale_verification_cannot_use_the_previous_custodians_assignment(): void
    {
        $ticket = $this->ticket(['status' => 'For Verification']);
        $this->line($ticket, $this->mechanic, ['status' => 'For Inspection']);
        $newCustodian = $this->person('Custodian');
        $ticket->newQuery()->whereKey($ticket->ticket_id)->update(['assigned_custodian_id' => $newCustodian->id]);
        Sanctum::actingAs($this->custodian, ['*']);
        $request = Request::create('/api/tickets/'.$ticket->ticket_id.'/verify', 'PUT');
        $request->setUserResolver(fn () => $this->custodian);
        try {
            app(TicketController::class)->verifyTicket($request, $ticket);
            $this->fail('A former Custodian assignment must not authorize verification.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('For Verification', $ticket->fresh()->status);
        $this->assertDatabaseCount('vehicle_maintenance_records', 0);
    }

    public function test_stale_repair_submission_cannot_use_the_previous_mechanics_assignment(): void
    {
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic);
        $line->newQuery()->whereKey($line->sub_issue_id)->update(['assigned_mechanic_id' => $this->person('Maintenance Personnel')->id]);
        Sanctum::actingAs($this->mechanic, ['*']);
        $request = Request::create('/api/tickets/'.$ticket->ticket_id.'/sub-issues/'.$line->sub_issue_id.'/log-repairs', 'PUT', ['repair_logs' => 'Old assignment work']);
        $request->setUserResolver(fn () => $this->mechanic);
        try {
            app(TicketController::class)->logRepairs($request, $ticket, $line);
            $this->fail('A former mechanic assignment must not authorize a repair submission.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertNull($line->fresh()->repair_logs);
    }

    public function test_record_repair_edits_invalidate_a_passed_verification(): void
    {
        $record = $this->record(['verification_result' => 'Passed', 'verified_by' => $this->custodian->id]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}", ['action_taken' => 'Changed repair', 'maintenance_personnel_id' => $this->custodian->id])->assertOk();
        $this->assertNull($record->fresh()->verification_result);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/confirm", ['confirmed' => true])->assertUnprocessable();
    }

    public function test_record_confirmation_rejects_legacy_self_verification(): void
    {
        $record = $this->record(['verification_result' => 'Passed', 'verified_by' => $this->mechanic->id]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}/confirm", ['confirmed' => true])->assertUnprocessable();
    }

    public function test_record_edit_rejects_self_donor_and_another_vehicles_issue(): void
    {
        $record = $this->record();
        $other = $this->vehicle->replicate();
        $other->plate_number = 'AUD-002';
        $other->save();
        $issue = VehicleIssueReport::create(['vehicle_id' => $other->vehicle_id, 'issue_type' => 'Other issue', 'issue_description' => 'Other vehicle', 'severity_level' => 'Low', 'reported_by' => $this->custodian->id, 'status' => 'Pending']);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}", ['source_vehicle_id' => $this->vehicle->vehicle_id])->assertUnprocessable();
        $this->putJson("/api/maintenance-records/{$record->maintenance_id}", ['issue_report_id' => $issue->issue_report_id])->assertUnprocessable();
        $this->assertSame('Pending', $issue->fresh()->status);
    }

    public function test_inactive_custodian_cannot_receive_ticket_assignment(): void
    {
        $ticket = $this->ticket();
        $inactive = $this->person('Custodian', ['is_active' => false]);
        Sanctum::actingAs($this->admin, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/reassign-custodian", ['assigned_custodian_id' => $inactive->id, 'reassign_reason' => 'Shift change'])->assertUnprocessable();
    }

    public function test_cancelled_ticket_rejects_external_dispatch_and_return(): void
    {
        $ticket = $this->ticket(['status' => 'Cancelled']);
        $line = $this->line($ticket, $this->mechanic);
        Sanctum::actingAs($this->mechanic, ['*']);
        $base = "/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}";
        $this->putJson($base.'/external-sent', ['external_vendor' => 'Shop', 'external_reason' => 'Repair', 'external_work_scope' => 'Repair engine'])->assertUnprocessable();
        $line->update(['external_sent_at' => now()]);
        $this->putJson($base.'/external-returned', ['external_return_notes' => 'Returned'])->assertUnprocessable();
    }

    public function test_restored_vehicle_with_unfinished_work_stays_under_maintenance(): void
    {
        $this->record();
        $this->vehicle->update(['status' => 'Inactive']);
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/restore")->assertOk();
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
    }

    public function test_recurrence_uses_a_free_date_beyond_sixty_occupied_days(): void
    {
        $source = $this->schedule(['status' => 'Completed', 'recurrence_months' => 1]);
        $base = now()->addMonthsNoOverflow(1)->startOfDay();
        for ($i = 0; $i < 60; $i++) {
            $this->schedule(['status' => 'Scheduled', 'scheduled_date' => $base->copy()->addDays($i)->toDateString()]);
        }
        $next = $source->seedNextRecurrence(now()->toDateString(), $this->custodian->id);
        $this->assertSame($base->copy()->addDays(60)->toDateString(), $next->scheduled_date);
    }
    public function test_ongoing_tickets_block_dispatch_even_when_vehicle_is_available(): void
    {
        foreach (['Pending Approval', 'Open', 'Active', 'For Verification'] as $status) {
            $ticket = $this->ticket(['status' => $status]);
            foreach ([$this->custodian, $this->admin] as $actor) {
                Sanctum::actingAs($actor, ['*']);
                $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", ['purpose' => 'Emergency response'])
                    ->assertUnprocessable()->assertJsonPath('blocking_tickets.0.ticket_id', $ticket->ticket_id);
            }
            $ticket->delete();
        }
        $this->assertDatabaseCount('vehicle_usage_logs', 0);
    }

    public function test_terminal_tickets_do_not_block_dispatch_and_returns_remain_allowed(): void
    {
        Sanctum::actingAs($this->custodian, ['*']);
        foreach (['Closed', 'Cancelled', 'Declined'] as $status) {
            $this->ticket(['status' => $status]);
        }
        $trip = $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", ['purpose' => 'Supply run'])
            ->assertCreated()->json();
        $ticket = $this->ticket(['status' => 'Active']);
        $this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage?page=1&per_page=10")
            ->assertOk()->assertJsonCount(1, 'blocking_tickets')
            ->assertJsonPath('blocking_tickets.0.ticket_id', $ticket->ticket_id)
            ->assertJsonPath('open_trip.usage_id', $trip['usage_id']);
        $this->putJson("/api/usage-logs/{$trip['usage_id']}/end")->assertOk();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage")->assertUnprocessable();
    }

    private function temporaryReleaseData(array $tickets, array $extra = []): array
    {
        return $extra + ['temporary_release' => true, 'release_reason' => 'Urgent response; cosmetic repair can wait',
            'expected_return_at' => now()->addHours(2)->toIso8601String(), 'personally_tested' => true,
            'safe_for_intended_use' => true, 'fully_assembled' => true, 'no_safety_critical_fault' => true,
            'ticket_ids' => array_map(fn ($ticket) => $ticket->ticket_id, $tickets),
            'assessment_ids' => array_map(fn ($row) => $row->id, app(\App\Services\TemporaryDispatchService::class)->assessments(app(\App\Services\TemporaryDispatchService::class)->tickets($this->vehicle->fresh()))),
            'driver_name' => 'Responder', 'destination' => 'City Hall', 'purpose' => 'Emergency response'];
    }

    private function assessDispatch(MaintenanceTicket $ticket, User $actor, array $extra = [])
    {
        Sanctum::actingAs($actor, ['*']);
        return $this->postJson("/api/tickets/{$ticket->ticket_id}/dispatch-assessment", $extra + [
            'safe_to_operate' => true, 'assembled' => true, 'no_safety_critical_fault' => true,
            'notes' => 'Cosmetic work only; safe to operate for this mission.',
        ]);
    }

    public function test_temporary_release_needs_no_admin_approval_and_preserves_open_ticket(): void
    {
        $ticket = $this->ticket(['status' => 'Pending Approval']);
        $this->vehicle->update(['status' => 'Under Maintenance']);
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertForbidden();
        Sanctum::actingAs($this->custodian, ['*']);
        $trip = $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))
            ->assertCreated()->assertJsonPath('temporary_release.authorized_by', $this->custodian->id)->json();
        $this->assertSame('Pending Approval', $ticket->fresh()->status);
        $this->assertSame('Under Maintenance', $this->vehicle->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'type' => 'temporary_dispatch']);
        $this->assertDatabaseHas('vehicle_readiness_checks', ['checked_by' => $this->custodian->id, 'all_passed' => true]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Temporary Dispatch Authorized', 'role' => 'Custodian']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_usage_logs', 1);
        $this->assertDatabaseCount('vehicle_readiness_checks', 1);
        $this->putJson("/api/usage-logs/{$trip['usage_id']}/end")->assertOk();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage")->assertUnprocessable();
    }

    public function test_temporary_release_requires_every_assigned_mechanics_current_assessment(): void
    {
        $other = $this->person('Maintenance Personnel');
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic);
        $this->line($ticket, $other);
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->assessDispatch($ticket, $other)->assertCreated();
        $line->update(['work_order_notes' => 'Work changed after assessment']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        $this->assessDispatch($ticket, $other)->assertCreated();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertCreated();
    }

    public function test_unsafe_dismantled_or_stale_assessments_cannot_be_overridden_by_custodian(): void
    {
        $ticket = $this->ticket();
        $this->line($ticket, $this->mechanic);
        foreach (['safe_to_operate', 'assembled', 'no_safety_critical_fault'] as $field) {
            $this->assessDispatch($ticket, $this->mechanic, [$field => false])->assertCreated();
            Sanctum::actingAs($this->custodian, ['*']);
            $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        }
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        $this->travel(25)->hours();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_usage_logs', 0);
    }

    public function test_failed_readiness_and_missing_attestation_prevent_temporary_release(): void
    {
        $ticket = $this->ticket(['status' => 'Pending Approval']);
        Sanctum::actingAs($this->custodian, ['*']);
        foreach (['personally_tested', 'safe_for_intended_use', 'fully_assembled', 'no_safety_critical_fault'] as $field) {
            $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket], [$field => false]))->assertUnprocessable();
        }
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket], ['expected_return_at' => now()->subHour()->toIso8601String()]))->assertUnprocessable();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket], ['started_at' => now()->subMinute()->toIso8601String()]))->assertUnprocessable();
        \App\Models\VehicleReadinessCheck::create(['vehicle_id' => $this->vehicle->vehicle_id, 'checked_by' => $this->custodian->id, 'checked_at' => now()->subHour(), 'all_passed' => false, 'checklist' => [['item' => 'Brakes', 'passed' => false]]]);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/readiness-check", ['confirmed' => true])->assertCreated();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertCreated();
    }

    public function test_all_current_tickets_must_be_reviewed_and_retired_vehicles_cannot_be_released(): void
    {
        $ticket = $this->ticket(['status' => 'Pending Approval']);
        $other = $this->ticket(['status' => 'Pending Approval']);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        foreach (['Inactive', 'Decommissioned'] as $status) {
            $this->vehicle->update(['status' => $status]);
            $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket, $other]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('vehicle_usage_logs', 0);
    }

    public function test_only_assigned_mechanics_can_record_dispatch_assessments(): void
    {
        $ticket = $this->ticket();
        $this->line($ticket, $this->mechanic);
        foreach ([$this->admin, $this->custodian, $this->person('Maintenance Personnel')] as $actor) {
            $this->assessDispatch($ticket, $actor)->assertForbidden();
        }
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        $ticket->update(['status' => 'Closed']);
        $this->assessDispatch($ticket, $this->mechanic)->assertUnprocessable();
    }

    public function test_repairs_pause_during_temporary_dispatch_and_resume_after_return(): void
    {
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic);
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        Sanctum::actingAs($this->custodian, ['*']);
        $trip = $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertCreated()->json();
        $this->putJson("/api/tickets/{$ticket->ticket_id}/verify", ['verdict' => 'Approved'])->assertUnprocessable();
        $this->assessDispatch($ticket, $this->mechanic)->assertUnprocessable();
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}/log-repairs", ['repair_logs' => 'Repaired'])->assertUnprocessable();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->putJson("/api/usage-logs/{$trip['usage_id']}/end")->assertOk();
        $this->assertDatabaseHas('notifications', ['user_id' => $this->mechanic->id, 'type' => 'temporary_dispatch_returned']);
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}/log-repairs", ['repair_logs' => 'Repaired'])->assertOk();
        $this->assertSame('For Verification', $ticket->fresh()->status);
    }

    public function test_external_shop_departure_blocks_temporary_release_until_return_is_recorded(): void
    {
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic, ['repair_type' => 'external', 'assigned_mechanic_id' => null, 'external_sent_at' => now()->subHour()]);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $line->update(['external_returned_at' => now()]);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertCreated();
    }

    public function test_release_stores_reviewed_assessment_copy_and_donor_changes_invalidate_it(): void
    {
        $ticket = $this->ticket();
        $line = $this->line($ticket, $this->mechanic);
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        $part = $line->donorParts()->create(['part_name' => 'Replacement bracket', 'quantity' => 1]);
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertUnprocessable();
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->getJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage?page=1")
            ->assertOk()->assertJsonPath('release_assessments.0.current', true)
            ->assertJsonPath('release_assessments.0.notes', 'Cosmetic work only; safe to operate for this mission.');
        $trip = $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))
            ->assertCreated()->assertJsonPath('temporary_release.assessments.0.notes', 'Cosmetic work only; safe to operate for this mission.')->json();
        Sanctum::actingAs($this->mechanic, ['*']);
        $this->putJson("/api/tickets/{$ticket->ticket_id}/sub-issues/{$line->sub_issue_id}/donor-parts/{$part->part_id}", ['part_name' => 'Changed', 'quantity' => 2])->assertUnprocessable();
        $this->assertSame(1, $part->fresh()->quantity);
        $this->assertSame('Cosmetic work only; safe to operate for this mission.', VehicleUsageLog::find($trip['usage_id'])->temporary_release['assessments'][0]['notes']);
    }

    public function test_temporary_release_and_assessment_cannot_cross_barangays(): void
    {
        $ticket = $this->ticket();
        $this->line($ticket, $this->mechanic);
        $otherBarangay = Barangay::create(['name' => 'Different scope'])->id;
        $outsider = $this->person('Custodian', ['barangay_id' => $otherBarangay]);
        Sanctum::actingAs($outsider, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertNotFound();
        $this->assessDispatch($ticket, $this->person('Maintenance Personnel', ['barangay_id' => $otherBarangay]))->assertNotFound();
        $this->assertDatabaseCount('ticket_dispatch_assessments', 0);
    }

    public function test_custodian_must_review_new_assessment_notes_before_departure(): void
    {
        $ticket = $this->ticket();
        $this->line($ticket, $this->mechanic);
        $this->assessDispatch($ticket, $this->mechanic)->assertCreated();
        $reviewed = $this->temporaryReleaseData([$ticket]);
        $this->assessDispatch($ticket, $this->mechanic, ['notes' => 'Updated restriction: local travel only'])->assertCreated();
        Sanctum::actingAs($this->custodian, ['*']);
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $reviewed)->assertUnprocessable();
        $this->postJson("/api/vehicles/{$this->vehicle->vehicle_id}/usage", $this->temporaryReleaseData([$ticket]))->assertCreated();
    }

}
