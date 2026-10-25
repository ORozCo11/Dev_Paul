<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleHistory;
use App\Models\VehicleIssueReport;
use App\Models\VehicleMaintenanceSchedule;
use App\Models\VehicleReadinessCheck;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reset only the named Paknaan demo fleet, never a tenant's entire fleet. */
class SampleFleetSeeder extends Seeder
{
    private const LEGACY_FLEET = [
        'AMB-1101' => 'Metro EMS Ambulance',
        'INEM-112' => 'INEM Portuguese Ambulance',
        'FDR-303' => 'Rescue Response Ambulance',
        'BCP-01W' => 'Wagon Caddy Ambulance',
        'BFP-5501' => 'Barangay Fire Responder',
        'FDT-2801' => 'Ladder Company 28',
        'FDT-1502' => 'Firehouse Engine 15',
        'FDT-0903' => 'Highway Rescue Engine',
        'FDT-0704' => 'Aerial Ladder Unit',
        'FDT-0605' => 'London Brigade Pumper',
        'FDT-2306' => 'Engine 23',
        'FDT-1107' => 'Autumn Response Pumper',
        'AMB-2208' => 'Desert Rose EMS',
        'AMB-3309' => 'QuickCare Ambulance',
        'AMB-4410' => 'Noida Express Ambulance',
    ];

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Sample fleet reset skipped: local/testing only.');
            return;
        }

        $barangay = Barangay::where('name', 'Paknaan')
            ->whereHas('city', fn ($q) => $q->where('name', 'Mandaue City'))->first();
        if (!$barangay) {
            throw new RuntimeException('Seed the Cebu / Mandaue City / Paknaan references first.');
        }

        DB::transaction(function () use ($barangay) {
            $existing = Vehicle::withoutGlobalScopes()->whereIn('plate_number', array_keys(self::LEGACY_FLEET))->get();
            foreach ($existing as $vehicle) {
                if ((int) $vehicle->barangay_id !== $barangay->id || $vehicle->vehicle_name !== self::LEGACY_FLEET[$vehicle->plate_number]) {
                    throw new RuntimeException("Demo plate {$vehicle->plate_number} belongs to a different or renamed vehicle; nothing was reset.");
                }
            }

            $this->call([UserSeeder::class, FleetReferenceSeeder::class]);
            $staff = User::where('barangay_id', $barangay->id)->get()->keyBy('email');
            $admin = $staff['admin@barangay.gov'];
            $custodian = $staff['custodian@barangay.gov'];
            $custodian2 = $staff['custodian2@barangay.gov'];
            $mechanic = $staff['maintenance@barangay.gov'];
            $mechanic2 = $staff['maintenance2@barangay.gov'];
            $mechanic3 = $staff['maintenance3@barangay.gov'];

            $this->clearDemoWork($existing->modelKeys(), $barangay->id);
            $keep = ['AMB-1101', 'INEM-112', 'FDR-303', 'BFP-5501', 'FDT-2801'];
            Vehicle::withoutGlobalScopes()->whereIn('vehicle_id', $existing->modelKeys())->whereNotIn('plate_number', $keep)->delete();

            $vehicles = [];
            $specs = [
                ['BFP-5501', 'Fire Truck', 'Pierce', 'Enforcer Pumper', 2018, '2000 L', 'Red', 'Paknaan Brgy Hall', 'firetruck-01-pierce-pumper.jpg'],
                ['FDT-2801', 'Fire Truck', 'Pierce', 'Aerial Ladder Truck', 2020, '1135 L', 'Red / White', 'Paknaan Gymnasium', 'firetruck-03-ladder-28.jpg'],
                ['AMB-1101', 'Ambulance', 'Toyota', 'HiAce Carryboy EMS', 2023, '8 pax', 'White / Red', 'Twinbee Hub', 'ambulance-01-hiace-carryboy.jpg'],
                ['INEM-112', 'Ambulance', 'Mercedes-Benz', 'Sprinter 316 CDI', 2021, '8 pax', 'White / Red', 'Paknaan Brgy Hall', 'ambulance-02-sprinter.jpg'],
                ['FDR-303', 'Ambulance', 'Ford', 'E-450 Rescue Ambulance', 2020, '8 pax', 'White / Red', 'Paknaan Gymnasium', 'ambulance-03-ford-eseries.jpg'],
            ];
            foreach ($specs as [$plate, $type, $brand, $model, $year, $capacity, $color, $hub, $photo]) {
                $vehicle = Vehicle::withoutGlobalScopes()->updateOrCreate(['plate_number' => $plate], [
                    'barangay_id' => $barangay->id, 'vehicle_name' => self::LEGACY_FLEET[$plate],
                    'category_id' => VehicleCategory::where('category_name', $type)->value('category_id'),
                    'brand' => $brand, 'model' => $model, 'year_model' => $year, 'capacity' => $capacity,
                    'vehicle_color' => $color, 'fuel_type' => 'Diesel', 'current_location' => $hub,
                    'photo_url' => rtrim(config('app.url'), '/').'/mockups/vehicles/'.$photo,
                    'status' => 'Available', 'condition' => 'Good', 'criticality' => 'Critical',
                    'remarks' => 'Sample fleet: reset by SampleFleetSeeder.', 'custom_values' => null,
                    'estimated_return_date' => null, 'archived_at' => null, 'archived_by' => null,
                    'decommissioned_at' => null, 'decommissioned_by' => null, 'decommission_reason' => null,
                ]);
                $vehicles[$plate] = $vehicle;
                $this->history($vehicle, 'Vehicle Added', 'Sample vehicle registered for the five-vehicle demo.', 'vehicles', $vehicle->vehicle_id, $admin);
            }

            $active = $this->ticket($vehicles['BFP-5501'], $custodian, 'Active', 'Water pump pressure drops during testing', 'High');
            $active->update(['assigned_mechanic_id' => $mechanic->id, 'assigned_at' => now()->subDay(), 'down_since' => now()->subDay()]);
            $this->line($active, 'Replace leaking pump seal', 'Pump Repair', 'Under Repair', $admin, $mechanic);
            $this->line($active, 'Inspect pressure regulator', 'Pump Repair', 'Under Repair', $admin, $mechanic2);
            $this->notice($mechanic, 'Maintenance Assigned to You', 'Repair the fire truck pump seal.', 'mechanic_assigned', ['ticket_id' => $active->ticket_id]);
            $this->notice($mechanic2, 'Maintenance Assigned to You', 'Inspect the fire truck pressure regulator.', 'mechanic_assigned', ['ticket_id' => $active->ticket_id]);

            $verification = $this->ticket($vehicles['AMB-1101'], $custodian, 'For Verification', 'Emergency warning lights intermittent', 'High');
            $verification->update(['assigned_mechanic_id' => $mechanic->id, 'assigned_at' => now()->subDays(2), 'down_since' => now()->subDays(2)]);
            $line = $this->line($verification, 'Replace lightbar relay and test wiring', 'Electrical Repair', 'For Inspection', $admin, $mechanic);
            $line->update([
                'repair_logs' => '['.now()->subHours(2)->format('Y-m-d H:i').'] Replaced relay and secured wiring; ready for independent Custodian testing.',
                'parts_used' => 'Lightbar relay - 1 pc', 'maintenance_cost' => 850,
                'repair_started_at' => now()->subDay()->toDateString(), 'repair_completed_at' => now()->toDateString(),
                'verification_assigned_to' => $custodian->id,
            ]);
            $this->notice($custodian, 'Repair Ready for Verification', 'Test the ambulance warning lights and record your independent verdict.', 'repairs_completed', ['ticket_id' => $verification->ticket_id]);

            $proposal = $this->ticket($vehicles['FDR-303'], $custodian2, 'Pending Approval', 'Air conditioning loses cooling', 'Medium');
            $line = $this->line($proposal, 'Diagnose refrigerant leak at external shop', 'Air Conditioning Repair', 'Open', $admin);
            $line->update([
                'repair_type' => 'external',
                'external_reason' => 'Needs specialized shop equipment (e.g. alignment, A/C recovery, dyno)',
                'external_work_scope' => 'Leak-test the air conditioning circuit and provide a repair estimate.',
                'external_vendor' => 'Sample Cebu Auto Aircon', 'external_estimated_cost' => 3500,
            ]);
            $this->notice($admin, 'Maintenance Ticket Proposed', 'Review the ambulance external-shop proposal.', 'ticket_proposed', ['ticket_id' => $proposal->ticket_id]);

            $this->schedule($vehicles['BFP-5501'], 'Pump Repair', -1, 'Scheduled', $custodian, $mechanic, null, $active);
            $today = $this->schedule($vehicles['INEM-112'], 'Preventive Maintenance', 0, 'Scheduled', $custodian, $mechanic, 1);
            $this->schedule($vehicles['FDT-2801'], 'Preventive Maintenance', 2, 'Scheduled', $custodian2, $mechanic2, 3);
            $pending = $this->schedule($vehicles['AMB-1101'], 'Electrical Inspection', 4, 'Pending Approval', $custodian, $mechanic3);
            $this->notice($mechanic, 'Maintenance Assigned to You', 'Today: complete the scheduled ambulance preventive service.', 'schedule_assigned', ['schedule_id' => $today->schedule_id]);
            $this->notice($admin, 'Maintenance Schedule Proposed', 'Review the follow-up electrical inspection booking.', 'schedule_proposed', ['schedule_id' => $pending->schedule_id]);

            foreach ($vehicles as $vehicle) {
                $vehicle->refresh();
                VehicleConditionCheck::create([
                    'vehicle_id' => $vehicle->vehicle_id, 'condition_result' => $vehicle->condition,
                    'observations' => 'Sample inspection: '.$vehicle->condition.'.', 'checked_by' => $custodian->id,
                ]);
                if ($vehicle->status === 'Available' && $vehicle->condition === 'Good') {
                    $items = $vehicle->plate_number === 'FDT-2801'
                        ? ['Fuel sufficient', 'Engine starts', 'Water tank filled', 'Pump operates', 'Hoses checked', 'Lights and siren operate']
                        : ['Fuel sufficient', 'Engine starts', 'Oxygen stocked', 'Lights and siren operate', 'Stretcher secured'];
                    VehicleReadinessCheck::create([
                        'vehicle_id' => $vehicle->vehicle_id, 'checked_by' => $custodian->id,
                        'checklist' => array_map(fn ($item) => ['item' => $item, 'passed' => true], $items),
                        'all_passed' => true, 'notes' => 'Sample readiness check; refreshes each seed run.', 'checked_at' => now(),
                    ]);
                }
            }
        });
        $this->command?->info('Sample fleet reset: 2 fire trucks, 3 ambulances, 3 ongoing tickets, 4 schedules and 6 staff profiles.');
    }

    private function clearDemoWork(array $vehicleIds, int $barangayId): void
    {
        if (!$vehicleIds) return;
        $tickets = DB::table('maintenance_tickets')->whereIn('vehicle_id', $vehicleIds)->pluck('ticket_id')->all();
        $issues = DB::table('vehicle_issue_reports')->whereIn('vehicle_id', $vehicleIds)->pluck('issue_report_id')->all();
        $schedules = DB::table('vehicle_maintenance_schedules')->whereIn('vehicle_id', $vehicleIds)->pluck('schedule_id')->all();
        $transfers = DB::table('cannibalization_records')->whereIn('source_vehicle_id', $vehicleIds)->orWhereIn('destination_vehicle_id', $vehicleIds)->get();
        foreach ($transfers as $transfer) {
            if (!in_array($transfer->source_vehicle_id, $vehicleIds) || ($transfer->destination_vehicle_id && !in_array($transfer->destination_vehicle_id, $vehicleIds))) {
                throw new RuntimeException('A sample vehicle has a parts transfer with a non-demo vehicle; nothing was reset.');
            }
        }
        // Donor references on unrelated work must not be silently nulled by deletion.
        foreach (['ticket_sub_issues', 'vehicle_maintenance_records'] as $table) {
            $query = DB::table($table)->whereIn('source_vehicle_id', $vehicleIds);
            $outside = $table === 'ticket_sub_issues'
                ? $query->whereNotIn('ticket_id', $tickets)->exists()
                : $query->whereNotIn('vehicle_id', $vehicleIds)->exists();
            if ($outside) throw new RuntimeException('A sample vehicle is a donor for non-demo work; nothing was reset.');
        }

        DB::table('notifications')->whereIn('vehicle_id', $vehicleIds)->orWhereIn('ticket_id', $tickets)
            ->orWhereIn('issue_report_id', $issues)->orWhereIn('schedule_id', $schedules)->delete();
        $modules = [
            'Maintenance Tickets' => $tickets, 'Vehicle Issue Reports' => $issues,
            'Vehicle Maintenance Schedule' => $schedules, 'Cannibalization' => $transfers->pluck('cannibalization_id')->all(),
            'Vehicle Management' => $vehicleIds,
        ];
        foreach ([
            'Vehicle Maintenance Records' => ['vehicle_maintenance_records', 'maintenance_id'],
            'Vehicle Condition Monitoring' => ['vehicle_condition_checks', 'condition_check_id'],
            'Vehicle Location' => ['vehicle_locations', 'location_record_id'],
            'Vehicle Documents' => ['vehicle_documents', 'document_id'],
        ] as $module => [$table, $key]) {
            $modules[$module] = DB::table($table)->whereIn('vehicle_id', $vehicleIds)->pluck($key)->all();
        }
        foreach ($modules as $module => $ids) {
            DB::table('activity_logs')->where('barangay_id', $barangayId)->where('module', $module)->whereIn('affected_record_id', $ids)->delete();
        }
        DB::table('cannibalization_records')->whereIn('cannibalization_id', $transfers->pluck('cannibalization_id'))->delete();
        foreach (['vehicle_maintenance_schedules', 'vehicle_maintenance_records', 'ticket_archive_logs', 'maintenance_tickets', 'vehicle_issue_reports', 'vehicle_condition_checks', 'vehicle_readiness_checks', 'vehicle_histories', 'vehicle_locations', 'vehicle_documents'] as $table) {
            DB::table($table)->whereIn('vehicle_id', $vehicleIds)->delete();
        }
    }

    private function ticket(Vehicle $vehicle, User $custodian, string $status, string $summary, string $priority): MaintenanceTicket
    {
        $issue = VehicleIssueReport::create([
            'vehicle_id' => $vehicle->vehicle_id, 'issue_type' => $summary, 'issue_description' => $summary.'. Sample report for workflow demonstration.',
            'severity_level' => $priority, 'reported_by' => $custodian->id,
            'status' => $status === 'Pending Approval' ? 'Under Review' : 'In Maintenance',
        ]);
        $issue->forceFill(['created_at' => now()->subDays(3)])->save();
        $ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id, 'created_by' => $custodian->id, 'assigned_custodian_id' => $custodian->id,
            'issue_report_id' => $issue->issue_report_id, 'ticket_title' => 'pending',
            'ticket_description' => $issue->issue_description, 'priority' => $priority, 'status' => $status,
        ]);
        $ticket->forceFill([
            'ticket_title' => MaintenanceTicket::composeTitle($ticket->display_number, $vehicle->vehicle_name, $summary),
            'created_at' => now()->subDays(2),
        ])->save();
        $vehicle->update([
            'status' => $status === 'Pending Approval' ? 'Available' : 'Under Maintenance',
            'condition' => $status === 'Pending Approval' ? 'Needs Inspection' : 'Needs Repair',
            'estimated_return_date' => $status === 'Pending Approval' ? null : now()->addDays(2)->toDateString(),
        ]);
        $this->history($vehicle, 'Issue Reported', $summary, 'vehicle_issue_reports', $issue->issue_report_id, $custodian);
        return $ticket;
    }

    private function line(MaintenanceTicket $ticket, string $title, string $type, string $status, User $admin, ?User $mechanic = null): TicketSubIssue
    {
        $line = TicketSubIssue::create([
            'ticket_id' => $ticket->ticket_id, 'issue_report_id' => $ticket->issue_report_id, 'created_by' => $ticket->created_by,
            'title' => $title, 'maintenance_type' => $type, 'repair_type' => 'in_house', 'status' => $status,
            'assigned_mechanic_id' => $mechanic?->id, 'mechanic_assigned_by' => $mechanic ? $admin->id : null,
            'mechanic_assigned_at' => $mechanic ? now()->subDay() : null,
        ]);
        $line->forceFill(['created_at' => $ticket->created_at])->save();
        return $line;
    }

    private function schedule(Vehicle $vehicle, string $type, int $days, string $status, User $custodian, User $mechanic, ?int $recurrence = null, ?MaintenanceTicket $ticket = null): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create([
            'vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => $type,
            'scheduled_date' => now('Asia/Manila')->addDays($days)->toDateString(), 'scheduled_time' => '09:00:00',
            'service_location' => $vehicle->current_location, 'status' => $status,
            'created_by' => $custodian->id, 'assigned_to' => $mechanic->id, 'recurrence_months' => $recurrence,
            'notes' => $ticket ? 'Sample scheduled repair: continue through the linked Active ticket.' : 'Sample preventive service; record actual work before marking done.',
            'resulting_ticket_id' => $ticket?->ticket_id, 'due_notified_at' => $ticket ? now()->subDay() : null,
        ]);
    }

    private function notice(User $user, string $title, string $message, string $type, array $links): void
    {
        Notification::create($links + ['user_id' => $user->id, 'title' => $title, 'message' => $message, 'type' => $type]);
    }

    private function history(Vehicle $vehicle, string $type, string $description, string $table, int $id, User $user): void
    {
        VehicleHistory::create([
            'vehicle_id' => $vehicle->vehicle_id, 'activity_type' => $type, 'description' => $description,
            'related_table' => $table, 'related_record_id' => (string) $id, 'updated_by' => $user->id,
        ]);
    }
}
