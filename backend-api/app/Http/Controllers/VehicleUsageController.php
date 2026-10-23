<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Models\ActivityLog;
use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use App\Models\VehicleHistory;
use App\Models\VehicleUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vehicle Usage Log — a trip is opened when a vehicle goes out and closed when
 * it comes back. It records usage only; it never changes the vehicle's
 * status (that stays owned by the maintenance/readiness flow).
 */
class VehicleUsageController extends Controller
{
    use AuthorizesAbilities;

    public function index(Request $request, Vehicle $vehicle)
    {
        $this->requireAbility($request, 'usage.view');

        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $vehicle->hasMany(VehicleUsageLog::class, 'vehicle_id', 'vehicle_id')
            ->with('loggedBy:id,name')->orderByDesc('started_at')->orderByDesc('usage_id');

        if ($request->has('page') || $request->has('per_page')) {
            $result = $query->paginate($data['per_page'] ?? 10);
            return response()->json(array_merge($result->toArray(), [
                'blocking_tickets' => $this->blockingTickets($vehicle),
                'open_trip' => $vehicle->hasMany(VehicleUsageLog::class, 'vehicle_id', 'vehicle_id')
                    ->whereNull('ended_at')->first(),
            ]));
        }

        return $query->limit(50)->get();
    }

    public function start(Request $request, Vehicle $vehicle)
    {
        $this->requireAbility($request, 'usage.log');
        $data = $request->validate([
            'purpose' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'odometer_start' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['purpose'] = $data['purpose'] ?? 'Dispatch';

        $log = DB::transaction(function () use ($data, $vehicle, $request) {
            $vehicle = Vehicle::whereKey($vehicle->vehicle_id)->lockForUpdate()->firstOrFail();
            $blocking = $this->blockingTickets($vehicle);
            abort_if($blocking->isNotEmpty(), response()->json([
                'message' => 'Dispatch is blocked while this vehicle has an ongoing maintenance ticket. Open the ticket and resolve it before recording a departure.',
                'blocking_tickets' => $blocking,
            ], 422));
            abort_unless($vehicle->status === 'Available', 422, 'This vehicle is no longer available for dispatch.');
            abort_if(VehicleUsageLog::where('vehicle_id', $vehicle->vehicle_id)->whereNull('ended_at')->exists(), 422, 'This vehicle already has an open trip. End it first.');
            $log = VehicleUsageLog::create($data + ['vehicle_id' => $vehicle->vehicle_id, 'logged_by' => $request->user()->id, 'started_at' => $data['started_at'] ?? now()]);
            $this->record($request, $vehicle, 'Vehicle Taken Out', "{$vehicle->vehicle_name} went out: {$log->purpose}" . ($log->destination ? " → {$log->destination}" : '') . '.', $log);

            return $log;
        });

        return response()->json($log->load('loggedBy:id,name'), 201);
    }

    public function end(Request $request, VehicleUsageLog $log)
    {
        $this->requireAbility($request, 'usage.log');
        abort_if($log->ended_at, 422, 'This trip has already been ended.');

        $data = $request->validate([
            'ended_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:' . $log->started_at->toDateTimeString()],
            'odometer_end' => ['nullable', 'integer', 'min:' . ($log->odometer_start ?? 0)],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($log, $data, $request) {
            $log = VehicleUsageLog::whereKey($log->usage_id)->lockForUpdate()->firstOrFail();
            abort_if($log->ended_at, 422, 'This trip has already been ended.');
            $log->update([
                'ended_at' => $data['ended_at'] ?? now(),
                'odometer_end' => $data['odometer_end'] ?? null,
                'notes' => $data['notes'] ?? $log->notes,
            ]);
            $this->record($request, $log->vehicle, 'Vehicle Returned', "{$log->vehicle->vehicle_name} returned from: {$log->purpose}.", $log);
        });

        return $log->fresh()->load('loggedBy:id,name');
    }

    private function blockingTickets(Vehicle $vehicle)
    {
        return MaintenanceTicket::where('vehicle_id', $vehicle->vehicle_id)
            ->whereNotIn('status', ['Closed', 'Cancelled', 'Declined'])
            ->orderBy('ticket_id')
            ->get(['ticket_id', 'barangay_number', 'ticket_title', 'status']);
    }

    private function record(Request $request, Vehicle $vehicle, string $type, string $description, VehicleUsageLog $log): void
    {
        VehicleHistory::create([
            'vehicle_id' => $vehicle->vehicle_id,
            'activity_type' => $type,
            'description' => $description,
            'related_table' => 'vehicle_usage_logs',
            'related_record_id' => (string) $log->usage_id,
            'updated_by' => $request->user()->id,
        ]);
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'role' => $request->attributes->get('vms_acted_as_role') ?? $request->user()->role,
            'action' => $type === 'Vehicle Taken Out' ? 'Add' : 'Edit',
            'module' => 'Vehicle Usage',
            'affected_record_id' => (string) $log->usage_id,
            'details' => $description,
        ]);
    }
}
