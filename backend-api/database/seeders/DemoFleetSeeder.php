<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\DonorPart;
use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\TicketSubIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleHistory;
use App\Models\VehicleHub;
use App\Models\VehicleIssueReport;
use App\Models\VehicleLocation;
use App\Models\VehicleMaintenanceRecord;
use App\Models\VehicleMaintenanceSchedule;
use App\Models\VehicleReadinessCheck;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A 13-vehicle demo fleet with sample data on every screen: vehicles in every state (ready, needing repair,
 * under repair, waiting for verification, retired donor, archived), condition and readiness checks, issue
 * reports, repair tickets at every stage (proposed, active, external shop, for verification, closed, declined),
 * work items with donor parts, maintenance schedules and records, locations, histories and notifications.
 *
 *   php artisan db:seed --class=DemoFleetSeeder
 *
 * Target barangay: DEMO_FLEET_BARANGAY (an id or a name); otherwise Hippodromo, otherwise the barangay of the
 * first active Admin. It needs an active Admin, Custodian and Maintenance Personnel there and uses the ones it
 * finds. Every vehicle it creates carries the remark "Demo fleet seed", so running it again replaces only its
 * own vehicles (and their work) and never touches anything else. Local/testing only.
 */
class DemoFleetSeeder extends Seeder
{
    public const MARK = 'Demo fleet seed';

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo fleet skipped: local/testing only.');
            return;
        }

        $barangay = $this->targetBarangay();
        $admin = $this->staff($barangay, 'Admin');
        $custodians = $this->staffList($barangay, 'Custodian');
        $mechanics = $this->staffList($barangay, 'Maintenance Personnel');
        $custodian = $custodians[0];
        $custodian2 = $custodians[1] ?? $custodians[0];
        $mechanic = $mechanics[0];
        $mechanic2 = $mechanics[1] ?? $mechanics[0];

        DB::transaction(function () use ($barangay, $admin, $custodian, $custodian2, $mechanic, $mechanic2) {
            $this->clearPreviousRun($barangay->id);

            $hubs = VehicleHub::withoutGlobalScopes()->where('barangay_id', $barangay->id)->get();
            if ($hubs->isEmpty()) {
                $hubs = collect([VehicleHub::withoutGlobalScopes()->create([
                    'hub_key' => 'demo-depot-'.$barangay->id, 'name' => 'Demo Depot', 'label' => 'DD',
                    'lat' => 10.3157, 'lng' => 123.8854, 'is_default' => false, 'barangay_id' => $barangay->id,
                ])]);
            }
            $hubName = fn (int $i) => $hubs[$i % $hubs->count()]->name;
            $category = fn (string $name, string $domain = 'Land') => VehicleCategory::firstOrCreate(
                ['category_name' => $name], ['domain' => $domain, 'description' => "{$name} fleet classification"]
            )->category_id;

            $specs = [
                'pumper'   => ['Alpha Pumper Engine', 'DMO-FT-01', 'Fire Truck', 'Pierce', 'Enforcer Pumper', 2018, '2000 L', 'Red', 'firetruck-01-pierce-pumper.jpg', 60],
                'autumn'   => ['Autumn Response Pumper', 'DMO-FT-02', 'Fire Truck', 'Spartan', 'Gladiator Pumper', 2019, '3000 L', 'Red', 'firetruck-02-pumper-autumn.jpg', 58],
                'ladder'   => ['Ladder Company 28', 'DMO-FT-03', 'Fire Truck', 'Pierce', 'Aerial Ladder Truck', 2020, '1135 L', 'Red / White', 'firetruck-03-ladder-28.jpg', 55],
                'engine'   => ['Firehouse Engine 15', 'DMO-FT-04', 'Fire Truck', 'Rosenbauer', 'Commander Engine', 2017, '2500 L', 'Red', 'firetruck-04-firehouse-engine.jpg', 52],
                'highway'  => ['Highway Rescue Engine', 'DMO-FT-05', 'Fire Truck', 'Isuzu', 'Giga Rescue Pumper', 2021, '1800 L', 'Red / Yellow', 'firetruck-05-highway-engine.jpg', 50],
                'aerial'   => ['Aerial Ladder Unit', 'DMO-FT-06', 'Fire Truck', 'Magirus', 'DLK 23-12', 2005, '800 L', 'Red', 'firetruck-06-aerial-ladder.jpg', 120],
                'volvo'    => ['London Brigade Pumper', 'DMO-FT-07', 'Fire Truck', 'Volvo', 'FL6 Pumper', 2016, '2200 L', 'Red', 'firetruck-07-volvo-fl6.jpg', 48],
                'sffd'     => ['Barangay Engine 8', 'DMO-FT-08', 'Fire Truck', 'Hino', 'FM Pumper', 2015, '2000 L', 'Red', 'firetruck-08-sffd-engine.jpg', 46],
                'metro'    => ['Metro EMS Ambulance', 'DMO-AM-01', 'Ambulance', 'Toyota', 'HiAce Carryboy EMS', 2023, '8 pax', 'White / Red', 'ambulance-01-hiace-carryboy.jpg', 44],
                'rescue'   => ['Rescue Response Ambulance', 'DMO-AM-02', 'Ambulance', 'Ford', 'E-450 Rescue Ambulance', 2020, '8 pax', 'White / Red', 'ambulance-03-ford-eseries.jpg', 42],
                'quick'    => ['QuickCare Ambulance', 'DMO-AM-03', 'Ambulance', 'Mercedes-Benz', 'Sprinter 316 CDI', 2021, '8 pax', 'White / Red', 'ambulance-06-quickcare.jpg', 40],
                'noida'    => ['Express Ambulance (Old)', 'DMO-AM-04', 'Ambulance', 'Isuzu', 'Traviz', 2012, '6 pax', 'White', 'ambulance-07-noida-express.jpg', 150],
                'boat'     => ['Sea Guardian Rescue Boat', 'DMO-RB-01', 'Rescue Boat', 'Jiangsu', 'Davit Rescue Boat', 2022, '10 pax', 'Orange', 'rescueboat-01-davit.jpg', 38],
            ];

            /** @var array<string, Vehicle> $v */
            $v = [];
            $i = 0;
            foreach ($specs as $key => [$name, $plate, $type, $brand, $model, $year, $capacity, $color, $photo, $daysOld]) {
                $water = $type === 'Rescue Boat';
                $vehicle = Vehicle::withoutGlobalScopes()->create([
                    'barangay_id' => $barangay->id, 'vehicle_name' => $name, 'plate_number' => $plate,
                    'category_id' => $category($type, $water ? 'Water' : 'Land'),
                    'brand' => $brand, 'model' => $model, 'year_model' => $year, 'capacity' => $capacity,
                    'vehicle_color' => $color, 'fuel_type' => $water ? 'Gasoline' : 'Diesel',
                    'hull_material' => $water ? 'Rubber/Inflatable' : null, 'engine_type' => $water ? 'Outboard 4-stroke, 40 HP' : null,
                    'current_location' => $hubName($i++), 'photo_url' => rtrim(config('app.url'), '/').'/mockups/vehicles/'.$photo,
                    'status' => 'Available', 'condition' => 'Good', 'remarks' => self::MARK.'. Sample vehicle for demonstrations.',
                ]);
                $vehicle->forceFill(['created_at' => now()->subDays($daysOld), 'updated_at' => now()->subDays($daysOld)])->saveQuietly();
                $v[$key] = $vehicle;
                $this->history($vehicle, 'Vehicle Added', "{$name} was added to the system.", 'vehicles', $vehicle->vehicle_id, $admin, $daysOld);
                VehicleLocation::create([
                    'vehicle_id' => $vehicle->vehicle_id, 'current_location' => $vehicle->current_location,
                    'address_area' => $barangay->name, 'updated_by' => $admin->id, 'remarks' => 'Parked at the hub.',
                ]);
            }

            // ---- Retired donor: Aerial Ladder Unit gave parts to Ladder Company 28 -------------------------------------
            $v['aerial']->update([
                'status' => 'Decommissioned', 'condition' => 'Needs Repair', 'decommissioned_at' => now()->subDays(20),
                'decommissioned_by' => $admin->id, 'decommission_reason' => 'Beyond economical repair after flood damage; kept as a parts donor.',
            ]);
            $this->history($v['aerial'], 'Vehicle Decommissioned', 'Retired from the fleet; kept as a parts donor.', 'vehicles', $v['aerial']->vehicle_id, $admin, 20);

            // ---- Archived vehicle ---------------------------------------------------------------------------------------
            $v['noida']->update(['status' => 'Inactive', 'archived_at' => now()->subDays(30), 'archived_by' => $admin->id]);
            $this->history($v['noida'], 'Vehicle Archived', 'Archived: replaced by a newer unit.', 'vehicles', $v['noida']->vehicle_id, $admin, 30);

            // ---- Active repair with a donor part: Ladder Company 28 -----------------------------------------------------
            $active = $this->ticket($v['ladder'], $custodian, 'Active', 'Water pump pressure drops during testing', 'High', 3, 'In Maintenance');
            $active->update(['assigned_mechanic_id' => $mechanic->id, 'assigned_at' => now()->subDays(2), 'down_since' => now()->subDays(3), 'inspected_by' => $custodian->id, 'inspected_at' => now()->subDays(2), 'inspection_result' => 'Needs Maintenance']);
            $this->decision($active, 'Approved', $admin, 2);
            $this->line($active, 'Replace leaking pump seal', 'Pump Service', 'Under Repair', $admin, $mechanic, 2);
            $donorLine = $this->line($active, 'Replace failed fire pump', 'Pump Service', 'Under Repair', $admin, $mechanic2, 2);
            $donorLine->update(['repair_type' => 'cannibalized', 'source_vehicle_id' => $v['aerial']->vehicle_id, 'part_missing' => 'Fire pump', 'part_needed' => 'Fire pump', 'part_quantity' => 1]);
            DonorPart::create([
                'sub_issue_id' => $donorLine->sub_issue_id, 'donor_vehicle_id' => $v['aerial']->vehicle_id, 'part_name' => 'Fire pump',
                'quantity' => 1, 'blocks_readiness' => false, 'part_condition' => 'Good', 'reason' => 'Original pump seized; the retired Aerial Ladder Unit has a matching one.',
            ]);
            $this->vehicleState($v['ladder'], 'Under Maintenance', 'Needs Repair', 4, $admin);
            $this->notice($mechanic, 'Maintenance Assigned to You', 'Replace the leaking pump seal on Ladder Company 28.', 'mechanic_assigned', ['ticket_id' => $active->ticket_id]);
            $this->notice($mechanic2, 'Maintenance Assigned to You', 'Replace the failed fire pump on Ladder Company 28 using the donor part.', 'mechanic_assigned', ['ticket_id' => $active->ticket_id]);

            // ---- Repair waiting for the Custodian's verification: Firehouse Engine 15 ----------------------------------
            $verify = $this->ticket($v['engine'], $custodian, 'For Verification', 'Brakes feel soft on long descents', 'High', 5, 'In Maintenance');
            $verify->update(['assigned_mechanic_id' => $mechanic->id, 'assigned_at' => now()->subDays(4), 'down_since' => now()->subDays(5)]);
            $this->decision($verify, 'Approved', $admin, 4);
            $line = $this->line($verify, 'Replace brake pads and bleed the lines', 'Brake System', 'For Inspection', $admin, $mechanic, 4);
            $line->update([
                'repair_logs' => '['.now()->subHours(5)->toIso8601String().'] Replaced front pads and rotors, bled the brake lines and road-tested the truck on a downhill stretch.',
                'parts_used' => 'Brake pads (front), Brake rotors (2)', 'maintenance_cost' => 6400,
                'repair_started_at' => now()->subDays(2)->toDateString(), 'repair_completed_at' => now()->toDateString(),
                'mechanic_tested_at' => now()->subHours(5), 'mechanic_tested_by' => $mechanic->id, 'verification_assigned_to' => $custodian2->id,
            ]);
            $this->vehicleState($v['engine'], 'Under Maintenance', 'Needs Repair', 5, $admin);
            $this->notice($custodian2, 'Repair Ready for Verification', 'Test the brakes on Firehouse Engine 15 and record your verdict.', 'repairs_completed', ['ticket_id' => $verify->ticket_id]);

            // ---- Proposal waiting for the Admin: Highway Rescue Engine -------------------------------------------------
            $proposal = $this->ticket($v['highway'], $custodian, 'Pending Approval', 'Hydraulic rescue tool leaks oil', 'Medium', 1, 'Under Review');
            $this->line($proposal, 'Replace hydraulic hose and refill oil', 'Hydraulic System', 'Open', $admin, null, 1);
            $this->vehicleState($v['highway'], 'Available', 'Needs Repair', 1, $admin);
            $this->notice($admin, 'New Ticket Proposal Awaiting Review', 'Justine proposed a repair for Highway Rescue Engine. Review, then approve or decline.', 'ticket_proposed', ['ticket_id' => $proposal->ticket_id]);

            // ---- External shop repair in progress: QuickCare Ambulance -------------------------------------------------
            $external = $this->ticket($v['quick'], $custodian2, 'Active', 'Air conditioning loses cooling', 'Medium', 6, 'In Maintenance');
            $external->update(['assigned_at' => now()->subDays(5), 'down_since' => now()->subDays(6)]);
            $this->decision($external, 'Approved', $admin, 5);
            $shop = $this->line($external, 'Diagnose and recharge the A/C circuit', 'Air-Conditioning', 'Under Repair', $admin, null, 5);
            $shop->update([
                'repair_type' => 'external', 'external_vendor' => 'Cebu Auto Aircon Center', 'external_reason' => 'Needs specialized shop equipment (e.g. alignment, A/C recovery, dyno)',
                'external_work_scope' => 'Leak-test the circuit, replace the compressor seal if needed and recharge.', 'external_estimated_cost' => 4500,
                'external_shop_address' => 'N. Bacalso Ave, Cebu City', 'external_expected_return' => now()->addDays(2)->toDateString(),
                'external_sent_at' => now()->subDays(3), 'external_tester_id' => $mechanic2->id,
            ]);
            $this->vehicleState($v['quick'], 'Under Maintenance', 'Needs Repair', 6, $admin);

            // ---- Closed repair with its maintenance record: London Brigade Pumper --------------------------------------
            $closed = $this->ticket($v['volvo'], $custodian, 'Closed', 'Alternator not charging the battery', 'High', 20, 'Resolved');
            $closed->update([
                'assigned_mechanic_id' => $mechanic->id, 'assigned_at' => now()->subDays(19), 'down_since' => now()->subDays(20),
                'closed_by' => $custodian->id, 'closed_at' => now()->subDays(12), 'archived_at' => now()->subDays(12), 'returned_to_service' => true,
            ]);
            $this->decision($closed, 'Approved', $admin, 19);
            $done = $this->line($closed, 'Replace alternator and test the charging system', 'Electrical System', 'Done', $admin, $mechanic, 19);
            $done->update([
                'repair_logs' => '['.now()->subDays(14)->toIso8601String().'] Replaced the alternator and drive belt; battery now charges at 14.2 V at idle.',
                'parts_used' => 'Alternator, Drive belt', 'maintenance_cost' => 8900,
                'repair_started_at' => now()->subDays(16)->toDateString(), 'repair_completed_at' => now()->subDays(14)->toDateString(),
                'mechanic_tested_at' => now()->subDays(14), 'mechanic_tested_by' => $mechanic->id,
                'verification_verdict' => 'Approved', 'verification_notes' => 'Starts first time and holds charge.', 'verified_by' => $custodian->id,
                'verified_at' => now()->subDays(12), 'verification_assigned_to' => $custodian->id,
                'confirmation_verdict' => 'Confirmed', 'confirmed_by' => $custodian->id, 'confirmed_at' => now()->subDays(12), 'test_attested' => true,
            ]);
            VehicleMaintenanceRecord::create([
                'ticket_sub_issue_id' => $done->sub_issue_id, 'vehicle_id' => $v['volvo']->vehicle_id, 'maintenance_type' => 'Electrical System',
                'problem_reason' => $done->title, 'date_started' => now()->subDays(16)->toDateString(), 'date_completed' => now()->subDays(14)->toDateString(),
                'maintenance_personnel_id' => $mechanic->id, 'is_external' => false, 'action_taken' => $done->repair_logs, 'parts_used' => $done->parts_used,
                'maintenance_cost' => 8900, 'progress_status' => 'Completed', 'remarks' => 'Confirmed through the repair ticket.', 'verification_result' => 'Passed',
                'verification_notes' => $done->verification_notes, 'verified_by' => $custodian->id, 'verified_at' => now()->subDays(12),
                'confirmed_by' => $custodian->id, 'confirmed_at' => now()->subDays(12), 'test_attested' => true,
            ]);
            $this->history($v['volvo'], 'Maintenance Completed', 'Alternator replaced; ticket closed.', 'maintenance_tickets', $closed->ticket_id, $custodian, 12);

            // ---- Declined proposal: Barangay Engine 8 (the report stays open, so it stays Needs Repair) ----------------
            $declined = $this->ticket($v['sffd'], $custodian2, 'Declined', 'Rear lights flicker', 'Low', 8, 'Under Review');
            $declined->update(['decline_reason' => 'Loose connector only; the Custodian will re-seat it during the next check.']);
            $this->line($declined, 'Check rear lamp wiring', 'Electrical System', 'Open', $admin, null, 8);
            $this->vehicleState($v['sffd'], 'Available', 'Needs Repair', 7, $admin);
            $this->notice($custodian2, 'Ticket Proposal Declined', 'Your proposal for Barangay Engine 8 was declined. Record a new Condition Check with the result Good to clear it.', 'ticket_declined', ['ticket_id' => $declined->ticket_id]);

            // ---- Needs Repair with no ticket yet: Rescue Response Ambulance -------------------------------------------
            VehicleIssueReport::create([
                'vehicle_id' => $v['rescue']->vehicle_id, 'issue_type' => 'Oxygen regulator faulty', 'issue_description' => 'The oxygen regulator hisses and loses pressure after a few minutes.',
                'severity_level' => 'High', 'reported_by' => $custodian->id, 'status' => 'Pending',
            ])->forceFill(['created_at' => now()->subDays(1)])->saveQuietly();
            $this->vehicleState($v['rescue'], 'Under Maintenance', 'Needs Repair', 1, $admin);
            $this->notice($admin, 'New Issue Report', 'Rescue Response Ambulance: oxygen regulator faulty (High).', 'issue_reported', ['vehicle_id' => $v['rescue']->vehicle_id]);

            // ---- Checks: a condition check for every vehicle in the fleet, readiness for the ready ones -------------------
            foreach ($v as $key => $vehicle) {
                if (in_array($key, ['aerial', 'noida'], true)) continue;
                $vehicle->refresh();
                VehicleConditionCheck::create([
                    'vehicle_id' => $vehicle->vehicle_id, 'condition_result' => $vehicle->condition, 'checked_by' => ($key === 'sffd' || $key === 'quick' ? $custodian2 : $custodian)->id,
                    'observations' => $vehicle->condition === 'Good' ? 'Walk-around inspection: no defects found.' : 'Defect found during the inspection; see the open report or ticket.',
                ])->forceFill(['created_at' => now()->subDays(random_int(1, 6))])->saveQuietly();
                if ($vehicle->status === 'Available' && $vehicle->condition === 'Good') {
                    $items = $vehicle->category?->domain === 'Water'
                        ? ['Fuel sufficient', 'Outboard starts', 'Life jackets stocked', 'Hull checked', 'Lights and horn operate']
                        : ($vehicle->category?->category_name === 'Ambulance'
                            ? ['Fuel sufficient', 'Engine starts', 'Oxygen stocked', 'Lights and siren operate', 'Stretcher secured']
                            : ['Fuel sufficient', 'Engine starts', 'Water tank filled', 'Pump operates', 'Hoses checked', 'Lights and siren operate']);
                    VehicleReadinessCheck::create([
                        'vehicle_id' => $vehicle->vehicle_id, 'checked_by' => $custodian->id,
                        'checklist' => array_map(fn ($item) => ['item' => $item, 'passed' => true], $items),
                        'all_passed' => true, 'notes' => 'Ready to respond.',
                        // One vehicle's check is a few days old, so it reads as stale and shows how a re-check is asked for.
                        'checked_at' => $key === 'autumn' ? now()->subDays(3) : now()->subHours(random_int(1, 4)),
                    ]);
                }
            }

            // ---- Schedules and a standalone maintenance record ---------------------------------------------------------
            $oil = VehicleMaintenanceRecord::create([
                'vehicle_id' => $v['pumper']->vehicle_id, 'maintenance_type' => 'Oil Change', 'problem_reason' => 'Scheduled oil and filter change.',
                'date_started' => now()->subDays(30)->toDateString(), 'date_completed' => now()->subDays(30)->toDateString(), 'maintenance_personnel_id' => $mechanic->id,
                'is_external' => false, 'action_taken' => 'Drained and refilled engine oil, replaced the oil and fuel filters, reset the service reminder.',
                'parts_used' => 'Engine oil 15W-40 (20 L), Oil filter, Fuel filter', 'maintenance_cost' => 4200, 'progress_status' => 'Completed',
                'remarks' => 'Routine preventive maintenance.', 'verification_result' => 'Passed', 'verified_by' => $custodian->id, 'verified_at' => now()->subDays(30),
                'confirmed_by' => $custodian->id, 'confirmed_at' => now()->subDays(30), 'test_attested' => true,
            ]);
            $this->schedule($v['pumper'], 'Oil Change', -30, 'Completed', $custodian, $mechanic, 3, ['resulting_maintenance_id' => $oil->maintenance_id]);
            $this->schedule($v['pumper'], 'Oil Change', 60, 'Scheduled', $custodian, $mechanic, 3);
            $this->schedule($v['autumn'], 'Preventive Maintenance', 10, 'Pending Approval', $custodian, $mechanic2);
            $today = $this->schedule($v['metro'], 'Preventive Maintenance', 0, 'Scheduled', $custodian, $mechanic, 6);
            $this->schedule($v['boat'], 'Outboard Service', 30, 'Scheduled', $custodian2, $mechanic2, 12);
            $this->schedule($v['volvo'], 'Tire Service', 5, 'Declined', $custodian, $mechanic, null, ['decline_reason' => 'Tires were replaced last month; reschedule after the next inspection.']);
            $this->notice($mechanic, 'Maintenance Assigned to You', 'Today: complete the scheduled preventive service on Metro EMS Ambulance.', 'schedule_assigned', ['schedule_id' => $today->schedule_id]);
        });

        $this->command?->info("Demo fleet seeded for {$barangay->name}: 13 vehicles, 6 tickets, 6 schedules and sample checks, reports, records and notices.");
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function targetBarangay(): Barangay
    {
        $wanted = env('DEMO_FLEET_BARANGAY');
        if ($wanted) {
            $barangay = is_numeric($wanted) ? Barangay::find((int) $wanted) : Barangay::where('name', $wanted)->first();
            if (!$barangay) throw new RuntimeException("DEMO_FLEET_BARANGAY '{$wanted}' was not found.");
            return $barangay;
        }
        $barangay = Barangay::where('name', 'Hippodromo')->whereHas('users', fn ($q) => $q->where('role', 'Admin'))->first()
            ?? Barangay::whereIn('id', User::where('role', 'Admin')->where('is_active', true)->whereNotNull('barangay_id')->pluck('barangay_id'))->first();
        if (!$barangay) throw new RuntimeException('No barangay with an active Admin was found. Register one first, or set DEMO_FLEET_BARANGAY.');
        return $barangay;
    }

    private function staffList(Barangay $barangay, string $role): array
    {
        $users = User::where('barangay_id', $barangay->id)->where('role', $role)->where('is_active', true)->orderBy('id')->get()->all();
        if (!$users) throw new RuntimeException("{$barangay->name} has no active {$role}; add one first.");
        return $users;
    }

    private function staff(Barangay $barangay, string $role): User
    {
        return $this->staffList($barangay, $role)[0];
    }

    /** Remove only what an earlier run created: vehicles marked with MARK and everything hanging off them. */
    private function clearPreviousRun(int $barangayId): void
    {
        $ids = Vehicle::withoutGlobalScopes()->where('barangay_id', $barangayId)->where('remarks', 'like', self::MARK.'%')->pluck('vehicle_id')->all();
        if (!$ids) return;
        $tickets = DB::table('maintenance_tickets')->whereIn('vehicle_id', $ids)->pluck('ticket_id')->all();
        $subs = DB::table('ticket_sub_issues')->whereIn('ticket_id', $tickets)->pluck('sub_issue_id')->all();
        $schedules = DB::table('vehicle_maintenance_schedules')->whereIn('vehicle_id', $ids)->pluck('schedule_id')->all();
        $records = DB::table('cannibalization_records')->whereIn('source_vehicle_id', $ids)->orWhereIn('destination_vehicle_id', $ids)->pluck('cannibalization_id')->all();

        Schema::disableForeignKeyConstraints();
        try {
            DB::table('ticket_sub_issue_donor_parts')->whereIn('sub_issue_id', $subs)->orWhereIn('donor_vehicle_id', $ids)->delete();
            if ($records) {
                DB::table('cannibalization_parts')->whereIn('cannibalization_id', $records)->delete();
                DB::table('cannibalization_records')->whereIn('cannibalization_id', $records)->delete();
            }
            DB::table('notifications')->where(fn ($q) => $q->whereIn('ticket_id', $tickets)->orWhereIn('schedule_id', $schedules)->orWhereIn('vehicle_id', $ids))->delete();
            DB::table('vehicle_maintenance_records')->whereIn('vehicle_id', $ids)->delete();
            DB::table('ticket_sub_issues')->whereIn('sub_issue_id', $subs)->delete();
            DB::table('ticket_archive_logs')->whereIn('vehicle_id', $ids)->delete();
            DB::table('maintenance_tickets')->whereIn('ticket_id', $tickets)->delete();
            foreach (['vehicle_maintenance_schedules', 'vehicle_condition_checks', 'vehicle_readiness_checks', 'vehicle_issue_reports', 'vehicle_histories', 'vehicle_documents', 'vehicle_locations'] as $table) {
                DB::table($table)->whereIn('vehicle_id', $ids)->delete();
            }
            DB::table('vehicles')->whereIn('vehicle_id', $ids)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function vehicleState(Vehicle $vehicle, string $status, string $condition, int $daysAgo, User $by): void
    {
        $vehicle->update(['status' => $status, 'condition' => $condition, 'estimated_return_date' => $status === 'Under Maintenance' ? now()->addDays(3)->toDateString() : null]);
        $this->history($vehicle, 'Issue Reported', "Marked {$condition}; vehicle {$status}.", 'vehicles', $vehicle->vehicle_id, $by, $daysAgo);
    }

    private function ticket(Vehicle $vehicle, User $custodian, string $status, string $summary, string $priority, int $daysAgo, string $issueStatus): MaintenanceTicket
    {
        $issue = VehicleIssueReport::create([
            'vehicle_id' => $vehicle->vehicle_id, 'issue_type' => 'Other', 'issue_description' => $summary.'. Reported during a routine inspection.',
            'severity_level' => $priority, 'reported_by' => $custodian->id, 'status' => $issueStatus,
            'remarks' => 'Auto-created from a maintenance ticket proposal.',
        ]);
        $issue->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays(max(0, $daysAgo - 1))])->saveQuietly();
        $ticket = MaintenanceTicket::create([
            'vehicle_id' => $vehicle->vehicle_id, 'created_by' => $custodian->id, 'assigned_custodian_id' => $custodian->id,
            'issue_report_id' => $issue->issue_report_id, 'ticket_title' => 'pending', 'ticket_description' => $issue->issue_description,
            'priority' => $priority, 'status' => $status,
        ]);
        $ticket->forceFill([
            'ticket_title' => MaintenanceTicket::composeTitle($ticket->display_number, $vehicle->vehicle_name, $summary),
            'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays(max(0, $daysAgo - 1)),
        ])->saveQuietly();
        $this->history($vehicle, 'Ticket Proposed', $summary, 'maintenance_tickets', $ticket->ticket_id, $custodian, $daysAgo);
        return $ticket;
    }

    private function decision(MaintenanceTicket $ticket, string $event, User $admin, int $daysAgo): void
    {
        $ticket->forceFill(['decision_revision' => 1, 'decision_history' => [[
            'at' => now()->subDays($daysAgo)->toIso8601String(), 'name' => $admin->name, 'event' => $event, 'user_id' => $admin->id, 'details' => [],
        ]]])->saveQuietly();
    }

    private function line(MaintenanceTicket $ticket, string $title, string $type, string $status, User $admin, ?User $mechanic, int $daysAgo): TicketSubIssue
    {
        $line = TicketSubIssue::create([
            'ticket_id' => $ticket->ticket_id, 'issue_report_id' => $ticket->issue_report_id, 'created_by' => $ticket->created_by,
            'title' => $title, 'maintenance_type' => $type, 'repair_type' => 'in_house', 'status' => $status,
            'assigned_mechanic_id' => $mechanic?->id, 'suggested_mechanic_id' => $mechanic?->id,
            'mechanic_assigned_by' => $mechanic ? $admin->id : null, 'mechanic_assigned_at' => $mechanic ? now()->subDays($daysAgo) : null,
        ]);
        $line->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
        return $line;
    }

    private function schedule(Vehicle $vehicle, string $type, int $days, string $status, User $custodian, User $mechanic, ?int $recurrence = null, array $extra = []): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::create($extra + [
            'vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => $type, 'scheduled_date' => now('Asia/Manila')->addDays($days)->toDateString(),
            'service_location' => $vehicle->current_location, 'status' => $status, 'created_by' => $custodian->id, 'assigned_to' => $mechanic->id,
            'recurrence_months' => $recurrence, 'notes' => 'Sample '.strtolower($type).'; record the actual work before marking it done.',
        ]);
    }

    private function notice(User $user, string $title, string $message, string $type, array $links): void
    {
        Notification::create($links + ['user_id' => $user->id, 'title' => $title, 'message' => $message, 'type' => $type]);
    }

    private function history(Vehicle $vehicle, string $type, string $description, string $table, int $id, User $user, int $daysAgo): void
    {
        VehicleHistory::create([
            'vehicle_id' => $vehicle->vehicle_id, 'activity_type' => $type, 'description' => $description,
            'related_table' => $table, 'related_record_id' => (string) $id, 'updated_by' => $user->id,
        ])->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
    }
}
