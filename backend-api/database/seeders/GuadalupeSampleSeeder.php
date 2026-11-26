<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\Notification;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleHistory;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use App\Models\VehicleReadinessCheck;
use App\Models\MaintenanceTicket;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Small, idempotent sample data for Barangay Guadalupe, Cebu City:
 * 3 staff accounts, 3 vehicles and 1 active repair ticket. Local/testing only.
 */
class GuadalupeSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('GuadalupeSampleSeeder skipped: local/testing only.');
            return;
        }

        $barangay = Barangay::where('name', 'like', 'Guadalupe%')
            ->whereHas('city', fn ($q) => $q->where('name', 'Cebu City'))->first();
        if (!$barangay) {
            throw new RuntimeException('Barangay Guadalupe (Cebu City) not found; run the geographic seeders first.');
        }

        // Vehicle categories come from FleetReferenceSeeder.
        if (!VehicleCategory::where('category_name', 'Ambulance')->exists()) {
            $this->call(FleetReferenceSeeder::class);
        }

        DB::transaction(function () use ($barangay) {
            $people = [
                ['guadalupe.admin@barangay.gov', 'Guadalupe Admin', 'Admin', 'admin123'],
                ['guadalupe.custodian@barangay.gov', 'Guadalupe Custodian', 'Custodian', 'custodian123'],
                ['guadalupe.mechanic@barangay.gov', 'Guadalupe Mechanic', 'Maintenance Personnel', 'maintenance123'],
            ];
            $users = [];
            foreach ($people as [$email, $name, $role, $password]) {
                $users[$role] = User::updateOrCreate(['email' => $email], [
                    'name' => $name, 'password' => Hash::make($password),
                    'role' => $role, 'roles' => [$role],
                    'barangay_id' => $barangay->id, 'city_id' => $barangay->city_id,
                    'address' => $barangay->name.', Cebu City, Cebu',
                    'is_active' => true, 'approved_at' => now(),
                ]);
            }

            $specs = [
                ['GDP-1001', 'Ambulance', 'Toyota', 'HiAce Commuter EMS', 2022, 'Guadalupe Ambulance 1'],
                ['GDP-2001', 'Fire Truck', 'Isuzu', 'Giga Pumper', 2019, 'Guadalupe Fire Truck 1'],
                ['GDP-3001', 'Ambulance', 'Ford', 'Transit Rescue', 2021, 'Guadalupe Rescue Van'],
            ];
            $vehicles = [];
            foreach ($specs as [$plate, $type, $brand, $model, $year, $name]) {
                $vehicles[$plate] = Vehicle::withoutGlobalScopes()->updateOrCreate(['plate_number' => $plate], [
                    'barangay_id' => $barangay->id, 'vehicle_name' => $name,
                    'category_id' => VehicleCategory::where('category_name', $type)->value('category_id'),
                    'brand' => $brand, 'model' => $model, 'year_model' => $year,
                    'capacity' => $type === 'Fire Truck' ? '2000 L' : '8 pax', 'vehicle_color' => 'White / Red',
                    'fuel_type' => 'Diesel', 'current_location' => 'Guadalupe Barangay Hall',
                    'status' => 'Available', 'condition' => 'Good', 'criticality' => 'Critical',
                    'remarks' => 'Sample data: GuadalupeSampleSeeder.',
                ]);
            }

            // One active repair ticket on the fire truck, only if it has none yet.
            $truck = $vehicles['GDP-2001'];
            if (!MaintenanceTicket::where('vehicle_id', $truck->vehicle_id)->exists()) {
                $summary = 'Water pump pressure drops during testing';
                $issue = VehicleIssueReport::create([
                    'vehicle_id' => $truck->vehicle_id, 'issue_type' => $summary,
                    'issue_description' => $summary.'. Sample report.', 'severity_level' => 'High',
                    'reported_by' => $users['Custodian']->id, 'status' => 'In Maintenance',
                ]);
                $ticket = MaintenanceTicket::create([
                    'vehicle_id' => $truck->vehicle_id, 'created_by' => $users['Custodian']->id,
                    'assigned_custodian_id' => $users['Custodian']->id, 'issue_report_id' => $issue->issue_report_id,
                    'ticket_title' => 'pending', 'ticket_description' => $issue->issue_description,
                    'priority' => 'High', 'status' => 'Active',
                ]);
                $ticket->forceFill([
                    'ticket_title' => MaintenanceTicket::composeTitle($ticket->display_number, $truck->vehicle_name, $summary),
                    'assigned_mechanic_id' => $users['Maintenance Personnel']->id,
                    'assigned_at' => now()->subDay(), 'down_since' => now()->subDay(),
                ])->save();
                TicketSubIssue::create([
                    'ticket_id' => $ticket->ticket_id, 'issue_report_id' => $issue->issue_report_id,
                    'created_by' => $users['Custodian']->id, 'title' => 'Replace leaking pump seal',
                    'maintenance_type' => 'Pump Repair', 'repair_type' => 'in_house', 'status' => 'Under Repair',
                    'assigned_mechanic_id' => $users['Maintenance Personnel']->id,
                    'mechanic_assigned_by' => $users['Admin']->id, 'mechanic_assigned_at' => now()->subDay(),
                ]);
                $truck->update(['status' => 'Under Maintenance', 'condition' => 'Needs Repair',
                    'estimated_return_date' => now()->addDays(2)->toDateString()]);
            }

            $this->seedRest($barangay, $users, $vehicles);
        });

        $this->command?->info('Guadalupe sample data: 3 staff, 3 vehicles, 2 tickets, schedules, records, checks, notifications and activity log.');
    }

    /** Data for the remaining pages (Records, Scheduled Services, Dashboard, Activity Log, bell). */
    private function seedRest(Barangay $barangay, array $users, array $vehicles): void
    {
        $admin = $users['Admin'];
        $custodian = $users['Custodian'];
        $mechanic = $users['Maintenance Personnel'];
        $ambulance = $vehicles['GDP-1001'];
        $truck = $vehicles['GDP-2001'];
        $van = $vehicles['GDP-3001'];

        // Vehicle history + condition/readiness checks (View Vehicles, Dashboard).
        if (!VehicleHistory::where('vehicle_id', $ambulance->vehicle_id)->exists()) {
            foreach ($vehicles as $v) {
                VehicleHistory::create([
                    'vehicle_id' => $v->vehicle_id, 'activity_type' => 'Vehicle Added',
                    'description' => 'Sample vehicle registered for the demo.', 'related_table' => 'vehicles',
                    'related_record_id' => (string) $v->vehicle_id, 'updated_by' => $admin->id,
                ]);
                VehicleConditionCheck::create([
                    'vehicle_id' => $v->vehicle_id, 'condition_result' => $v->fresh()->condition,
                    'observations' => 'Sample inspection.', 'checked_by' => $custodian->id,
                ]);
            }
            VehicleReadinessCheck::create([
                'vehicle_id' => $ambulance->vehicle_id, 'checked_by' => $custodian->id,
                'checklist' => array_map(fn ($i) => ['item' => $i, 'passed' => true],
                    ['Fuel sufficient', 'Engine starts', 'Oxygen stocked', 'Lights and siren operate']),
                'all_passed' => true, 'notes' => 'Sample readiness check.', 'checked_at' => now(),
            ]);
        }

        // Proposal awaiting Admin approval on the rescue van (Repair Tickets: Proposals).
        if (!MaintenanceTicket::where('vehicle_id', $van->vehicle_id)->exists()) {
            $summary = 'Air conditioning loses cooling';
            $issue = VehicleIssueReport::create([
                'vehicle_id' => $van->vehicle_id, 'issue_type' => $summary, 'issue_description' => $summary.'. Sample report.',
                'severity_level' => 'Medium', 'reported_by' => $custodian->id, 'status' => 'Under Review',
            ]);
            $ticket = MaintenanceTicket::create([
                'vehicle_id' => $van->vehicle_id, 'created_by' => $custodian->id, 'assigned_custodian_id' => $custodian->id,
                'issue_report_id' => $issue->issue_report_id, 'ticket_title' => 'pending',
                'ticket_description' => $issue->issue_description, 'priority' => 'Medium', 'status' => 'Pending Approval',
            ]);
            $ticket->forceFill(['ticket_title' => MaintenanceTicket::composeTitle($ticket->display_number, $van->vehicle_name, $summary)])->save();
            $van->update(['condition' => 'Needs Repair']);
            Notification::create(['user_id' => $admin->id, 'title' => 'Maintenance Ticket Proposed',
                'message' => 'Review the rescue van repair proposal.', 'type' => 'ticket_proposed', 'ticket_id' => $ticket->ticket_id]);
        }

        // Completed past service (Records page).
        if (!VehicleMaintenanceRecord::where('vehicle_id', $ambulance->vehicle_id)->exists()) {
            VehicleMaintenanceRecord::create([
                'vehicle_id' => $ambulance->vehicle_id, 'maintenance_type' => 'Preventive Maintenance',
                'problem_reason' => 'Scheduled 5,000 km service', 'date_started' => now()->subDays(20)->toDateString(),
                'date_completed' => now()->subDays(19)->toDateString(), 'maintenance_personnel_id' => $mechanic->id,
                'is_external' => false, 'action_taken' => 'Changed engine oil and filters; checked brakes.',
                'parts_used' => 'Engine oil 6L, oil filter', 'maintenance_cost' => 2400, 'progress_status' => 'Completed',
            ]);
        }

        // Scheduled Services.
        if (!VehicleMaintenanceSchedule::where('vehicle_id', $ambulance->vehicle_id)->exists()) {
            foreach ([[$ambulance, 'Preventive Maintenance', 0, 1], [$truck, 'Pump Inspection', 3, 3]] as [$v, $type, $days, $rec]) {
                VehicleMaintenanceSchedule::create([
                    'vehicle_id' => $v->vehicle_id, 'maintenance_type' => $type,
                    'scheduled_date' => now('Asia/Manila')->addDays($days)->toDateString(), 'scheduled_time' => '09:00:00',
                    'service_location' => $v->current_location, 'status' => 'Scheduled',
                    'created_by' => $custodian->id, 'assigned_to' => $mechanic->id, 'recurrence_months' => $rec,
                    'notes' => 'Sample scheduled service.',
                ]);
            }
        }

        // Notifications (bell) and Activity Log.
        if (!Notification::where('user_id', $mechanic->id)->exists()) {
            Notification::create(['user_id' => $mechanic->id, 'title' => 'Maintenance Assigned to You',
                'message' => 'Repair the fire truck pump seal.', 'type' => 'mechanic_assigned', 'vehicle_id' => $truck->vehicle_id]);
        }
        if (!ActivityLog::where('barangay_id', $barangay->id)->exists()) {
            foreach ([
                [$admin, 'Created', 'Vehicle Management', $ambulance->vehicle_id, 'Registered '.$ambulance->vehicle_name],
                [$admin, 'Created', 'Vehicle Management', $truck->vehicle_id, 'Registered '.$truck->vehicle_name],
                [$custodian, 'Created', 'Vehicle Issue Reports', $truck->vehicle_id, 'Reported pump pressure issue'],
                [$admin, 'Approved', 'Maintenance Tickets', $truck->vehicle_id, 'Approved and assigned pump repair'],
            ] as [$u, $action, $module, $recordId, $details]) {
                ActivityLog::create(['user_id' => $u->id, 'role' => $u->role, 'action' => $action, 'module' => $module,
                    'affected_record_id' => $recordId, 'details' => $details, 'barangay_id' => $barangay->id]);
            }
        }
    }
}
