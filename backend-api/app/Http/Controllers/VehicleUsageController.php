<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Models\ActivityLog;
use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\User;
use App\Models\VehicleReadinessCheck;
use App\Services\TemporaryDispatchService;
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
            $service = app(TemporaryDispatchService::class);
            $tickets = $service->tickets($vehicle);
            return response()->json(array_merge($result->toArray(), [
                'blocking_tickets' => $tickets->map->only(['ticket_id', 'barangay_number', 'ticket_title', 'status']),
                'release_assessments' => $service->assessments($tickets),
                'can_temporary_release' => $request->user()->canDo('usage.temporary_release'),
                'release_requirements' => $service->requirements($vehicle, $tickets),
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
            'temporary_release' => ['sometimes', 'boolean'],
        ]);

        $release = $request->boolean('temporary_release');
        unset($data['temporary_release']);
        $releaseData = [];
        if ($release) {
            $this->requireAbility($request, 'usage.temporary_release');
            $releaseData = $request->validate([
                'release_reason' => ['required', 'string', 'max:2000'],
                'expected_return_at' => ['required', 'date', 'after:now'],
                'personally_tested' => ['required', 'accepted'],
                'safe_for_intended_use' => ['required', 'accepted'],
                'fully_assembled' => ['required', 'accepted'],
                'no_safety_critical_fault' => ['required', 'accepted'],
                'ticket_ids' => ['required', 'array', 'min:1'],
                'ticket_ids.*' => ['required', 'integer', 'distinct'],
                'assessment_ids' => ['present', 'array'],
                'assessment_ids.*' => ['required', 'integer', 'distinct'],
                'driver_name' => ['required', 'string', 'max:255'],
                'destination' => ['required', 'string', 'max:255'],
                'purpose' => ['required', 'string', 'max:255'],
            ]);
            abort_if($request->filled('started_at'), 422, 'Temporary dispatch must be recorded at departure, without backdating.');
        }

        $data['purpose'] = $data['purpose'] ?? 'Dispatch';

        $log = DB::transaction(function () use ($data, $vehicle, $request, $release, $releaseData) {
            $vehicle = Vehicle::whereKey($vehicle->vehicle_id)->lockForUpdate()->firstOrFail();
            $service = app(TemporaryDispatchService::class);
            $tickets = $service->tickets($vehicle, true);
            $blocking = $tickets->map->only(['ticket_id', 'barangay_number', 'ticket_title', 'status']);
            abort_if($blocking->isNotEmpty() && !$release, response()->json([
                'message' => 'Normal dispatch is blocked by ongoing maintenance. Resolve the ticket or use Custodian temporary release when all release requirements are met.',
                'blocking_tickets' => $blocking,
            ], 422));
            $releaseRecord = null;
            if ($release) {
                abort_if($tickets->isEmpty(), 422, 'There are no ongoing tickets to release. Refresh and use normal dispatch.');
                abort_unless($tickets->pluck('ticket_id')->map(fn ($id) => (int) $id)->sort()->values()->all() === collect($releaseData['ticket_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all(), 422, 'The ongoing tickets changed. Refresh and review all tickets before release.');
                $assessments = $service->assessments($tickets);
                abort_unless(collect($assessments)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all() === collect($releaseData['assessment_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all(), 422, 'The mechanic assessments changed. Refresh and review the latest assessment notes before release.');
                $problems = $service->requirements($vehicle, $tickets);
                abort_if(count($problems) > 0, response()->json(['message' => implode(' ', $problems), 'release_requirements' => $problems], 422));
                abort_unless(in_array($vehicle->status, ['Available', 'Under Maintenance'], true), 422, 'This vehicle cannot be temporarily released.');
                $check = VehicleReadinessCheck::create([
                    'vehicle_id' => $vehicle->vehicle_id, 'checked_by' => $request->user()->id,
                    'checked_at' => now(), 'all_passed' => true, 'notes' => $releaseData['release_reason'],
                    'checklist' => collect(['Personally operated and tested', 'Safe for intended use', 'Fully assembled', 'No safety-critical fault'])->map(fn ($item) => ['item' => $item, 'passed' => true])->all(),
                ]);
                $releaseRecord = [
                    'authorized_by' => $request->user()->id, 'authorized_at' => now()->toIso8601String(),
                    'reason' => $releaseData['release_reason'], 'expected_return_at' => \Carbon\Carbon::parse($releaseData['expected_return_at'])->toIso8601String(),
                    'ticket_ids' => $tickets->pluck('ticket_id')->all(), 'readiness_check_id' => $check->readiness_check_id,
                    'assessments' => $assessments,
                ];
            } else {
                abort_unless($vehicle->status === 'Available', 422, 'This vehicle is no longer available for dispatch.');
            }
            abort_if(VehicleUsageLog::where('vehicle_id', $vehicle->vehicle_id)->whereNull('ended_at')->exists(), 422, 'This vehicle already has an open trip. End it first.');
            $log = VehicleUsageLog::create($data + ['temporary_release' => $releaseRecord, 'vehicle_id' => $vehicle->vehicle_id, 'logged_by' => $request->user()->id, 'started_at' => $data['started_at'] ?? now()]);
            $this->record($request, $vehicle, 'Vehicle Taken Out', "{$vehicle->vehicle_name} went out: {$log->purpose}" . ($log->destination ? " → {$log->destination}" : '') . '.', $log);

            if ($releaseRecord) {
                $this->record($request, $vehicle, 'Temporary Dispatch Authorized', 'Custodian authorized one-trip release. Repairs remain open. Reason: '.$releaseRecord['reason'].'. Expected return: '.$releaseRecord['expected_return_at'], $log);
                foreach ($tickets as $ticket) {
                    ActivityLog::create(['user_id' => $request->user()->id, 'role' => 'Custodian', 'action' => 'Temporary Dispatch Authorized', 'module' => 'Maintenance Tickets', 'affected_record_id' => (string) $ticket->ticket_id, 'details' => 'Repairs paused for one trip. Reason: '.$releaseRecord['reason'].'. Expected return: '.$releaseRecord['expected_return_at']]);
                    foreach ($ticket->subIssues->pluck('assigned_mechanic_id')->push($ticket->assigned_mechanic_id)->filter()->unique() as $id) {
                        Notification::create(['user_id' => $id, 'title' => 'Repair Paused - Temporary Dispatch', 'message' => "{$vehicle->vehicle_name} was temporarily dispatched by {$request->user()->name}. Resume repair after its return.", 'type' => 'temporary_dispatch', 'ticket_id' => $ticket->ticket_id, 'vehicle_id' => $vehicle->vehicle_id]);
                    }
                }
                foreach (User::where('barangay_id', $vehicle->barangay_id)->havingRole('Admin')->where('is_active', true)->get() as $admin) {
                    Notification::create(['user_id' => $admin->id, 'title' => 'Vehicle Temporarily Dispatched', 'message' => "{$request->user()->name} released {$vehicle->vehicle_name} while repairs remain open. Reason: {$releaseRecord['reason']}. Expected return: {$releaseRecord['expected_return_at']}.", 'type' => 'temporary_dispatch', 'ticket_id' => $tickets->first()->ticket_id, 'vehicle_id' => $vehicle->vehicle_id]);
                }
            }
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
            if ($log->temporary_release) {
                $ids = MaintenanceTicket::whereIn('ticket_id', $log->temporary_release['ticket_ids'])->whereNotIn('status', ['Closed', 'Cancelled', 'Declined'])->get();
                foreach ($ids as $ticket) {
                    ActivityLog::create(['user_id' => $request->user()->id, 'role' => $request->attributes->get('vms_acted_as_role'), 'action' => 'Temporary Dispatch Returned', 'module' => 'Maintenance Tickets', 'affected_record_id' => (string) $ticket->ticket_id, 'details' => 'Vehicle returned; outstanding repairs can resume.']);
                    $recipients = $ticket->subIssues()->pluck('assigned_mechanic_id')->push($ticket->assigned_mechanic_id)->push($ticket->assigned_custodian_id)->filter()->unique();
                    foreach ($recipients as $id) Notification::create(['user_id' => $id, 'title' => 'Vehicle Returned - Resume Repair', 'message' => "{$log->vehicle->vehicle_name} returned from temporary dispatch. Resume the outstanding work on Ticket #{$ticket->display_number}.", 'type' => 'temporary_dispatch_returned', 'ticket_id' => $ticket->ticket_id, 'vehicle_id' => $log->vehicle_id]);
                }
            }
            $this->record($request, $log->vehicle, 'Vehicle Returned', "{$log->vehicle->vehicle_name} returned from: {$log->purpose}.", $log);
        });

        return $log->fresh()->load('loggedBy:id,name');
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
            'action' => $type === 'Temporary Dispatch Authorized' ? $type : ($type === 'Vehicle Taken Out' ? 'Add' : 'Edit'),
            'module' => 'Vehicle Usage',
            'affected_record_id' => (string) $log->usage_id,
            'details' => $description,
        ]);
    }
}
