<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Concerns\UploadsImages;
use App\Models\ActivityLog;
use App\Models\CannibalizationPart;
use App\Models\CannibalizationRecord;
use App\Models\DonorPart;
use App\Models\MaintenanceTicket;
use App\Models\TicketSubIssue;
use App\Models\Vehicle;
use App\Models\VehicleHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cannibalization is a direct log of parts taken off a vehicle: no request,
 * approval or pending state, and no inventory. Each row says which vehicle the
 * part came off (an active or a retired one), what and how many, which repair
 * ticket it went into, and, for an active vehicle, whether it can still respond
 * without the part. A saved record is final except for marking a part put back
 * and correcting the "can still respond" answer; who recorded it and when is
 * taken from the signed-in user, never from the form.
 */
class CannibalizationController extends Controller
{
    use AuthorizesAbilities;
    use UploadsImages;

    private const EAGER = ['sourceVehicle:vehicle_id,vehicle_name,plate_number,condition,status,photo_url', 'destinationVehicle:vehicle_id,vehicle_name,plate_number,condition,status,photo_url', 'recordedBy:id,name', 'ticket:ticket_id,ticket_title,vehicle_id', 'parts'];

    private const RETIRED = ['Inactive', 'Decommissioned'];

    public function index(Request $request)
    {
        $this->requireAbility($request, 'cannibalization.view');

        return CannibalizationRecord::with(self::EAGER)->orderByDesc('cannibalization_id')->get();
    }

    public function show(Request $request, int $id)
    {
        $this->requireAbility($request, 'cannibalization.view');

        return CannibalizationRecord::with(self::EAGER)->findOrFail($id);
    }

    /**
     * A part that was taken off a vehicle has been put back. Until then an active donor is shown as
     * missing that part (and cannot be ready to respond if it needs it). No inventory is touched.
     */
    public function restorePart(Request $request, int $part)
    {
        $this->requireAbility($request, 'cannibalization.record');
        $row = CannibalizationPart::findOrFail($part);
        abort_if($row->restored_at, 422, 'This part is already marked as restored.');
        $record = CannibalizationRecord::findOrFail($row->cannibalization_id);
        abort_if($record->donor_type === 'retired', 422, 'This part came off a retired vehicle, so there is nothing to put back.');

        $row->update(['restored_at' => now(), 'restored_by' => $request->user()->id]);
        VehicleHistory::create([
            'vehicle_id' => $record->source_vehicle_id, 'activity_type' => 'Part Restored',
            'description' => "{$record->record_number}: {$row->quantity} × {$row->part_name} put back.",
            'related_table' => 'cannibalization_records', 'related_record_id' => (string) $record->cannibalization_id, 'updated_by' => $request->user()->id,
        ]);
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'role' => $request->attributes->get('vms_acted_as_role', $request->user()->role),
            'action' => 'Part Restored',
            'module' => 'Cannibalization',
            'affected_record_id' => (string) $record->cannibalization_id,
            'details' => "{$record->record_number}: {$row->part_name} marked restored.",
        ]);

        return response()->json($row->fresh());
    }

    /** Same as restorePart(), for a part that was taken during a cannibalized repair on a ticket. */
    public function restoreDonorPart(Request $request, int $part)
    {
        $this->requireAbility($request, 'cannibalization.record');
        $row = DonorPart::findOrFail($part);
        abort_if($row->restored_at, 422, 'This part is already marked as restored.');
        $donor = $row->donor_vehicle_id ? Vehicle::find($row->donor_vehicle_id) : null;
        abort_if($donor && in_array($donor->status, self::RETIRED, true), 422, 'This part came off a retired vehicle, so there is nothing to put back.');

        $row->update(['restored_at' => now(), 'restored_by' => $request->user()->id]);
        if ($row->donor_vehicle_id) {
            VehicleHistory::create([
                'vehicle_id' => $row->donor_vehicle_id, 'activity_type' => 'Part Restored',
                'description' => "{$row->quantity} × {$row->part_name} put back.",
                'related_table' => 'ticket_sub_issue_donor_parts', 'related_record_id' => (string) $row->part_id, 'updated_by' => $request->user()->id,
            ]);
        }
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'role' => $request->attributes->get('vms_acted_as_role', $request->user()->role),
            'action' => 'Part Restored',
            'module' => 'Cannibalization',
            'affected_record_id' => (string) $row->part_id,
            'details' => "{$row->part_name} marked restored.",
        ]);

        return response()->json($row->fresh());
    }

    private function normalise(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Correct the answer to "can this vehicle still respond without the part?". The person who
     * logged it (for a mechanic), the Custodian and the Admin may do it; the change is logged.
     */
    private function changeReadinessFlag(Request $request, $row, ?int $loggedBy, ?int $donorVehicleId, string $label)
    {
        $this->requireAbility($request, 'cannibalization.record');
        $user = $request->user();
        if ($user->role === 'Maintenance Personnel') {
            abort_unless($loggedBy !== null && (int) $loggedBy === (int) $user->id, 403, 'Only the person who logged this part, the Custodian or the Admin can change this.');
        }
        $data = $request->validate(['blocks_readiness' => ['required', 'boolean']]);
        abort_if($row->restored_at, 422, 'This part has already been put back.');
        $donor = $donorVehicleId ? Vehicle::find($donorVehicleId) : null;
        abort_if($donor && in_array($donor->status, self::RETIRED, true), 422, 'A retired vehicle is not part of readiness, so this does not apply.');

        $row->update(['blocks_readiness' => (bool) $data['blocks_readiness']]);
        $answer = $data['blocks_readiness'] ? 'cannot respond without it' : 'can still respond without it';
        if ($donorVehicleId) {
            VehicleHistory::create([
                'vehicle_id' => $donorVehicleId, 'activity_type' => 'Missing Part Updated',
                'description' => "{$label}: marked as '{$answer}'.",
                'related_table' => $row->getTable(), 'related_record_id' => (string) $row->part_id, 'updated_by' => $user->id,
            ]);
        }
        ActivityLog::create([
            'user_id' => $user->id,
            'role' => $request->attributes->get('vms_acted_as_role', $user->role),
            'action' => 'Missing Part Updated',
            'module' => 'Cannibalization',
            'affected_record_id' => (string) $row->part_id,
            'details' => "{$label}: marked as '{$answer}'.",
            'barangay_id' => $donor?->barangay_id,
        ]);

        return response()->json($row->fresh());
    }

    public function updatePartReadiness(Request $request, int $part)
    {
        $row = CannibalizationPart::findOrFail($part);
        $record = CannibalizationRecord::findOrFail($row->cannibalization_id);

        return $this->changeReadinessFlag($request, $row, $record->recorded_by, $record->source_vehicle_id, "{$record->record_number}: {$row->part_name}");
    }

    public function updateDonorPartReadiness(Request $request, int $part)
    {
        $row = DonorPart::findOrFail($part);
        $mechanicId = TicketSubIssue::where('sub_issue_id', $row->sub_issue_id)->value('assigned_mechanic_id');

        return $this->changeReadinessFlag($request, $row, $mechanicId ? (int) $mechanicId : null, $row->donor_vehicle_id, $row->part_name);
    }

    public function store(Request $request)
    {
        $this->requireAbility($request, 'cannibalization.record');

        // Parts arrive as JSON text when sent with evidence files (multipart).
        if (is_string($request->input('parts'))) {
            $request->merge(['parts' => json_decode($request->input('parts'), true) ?? []]);
        }

        $data = $request->validate([
            'donor_type'               => ['required', 'in:active,retired'],
            'source_vehicle_id'        => ['required', 'exists:vehicles,vehicle_id'],
            'ticket_id'                => ['required', 'exists:maintenance_tickets,ticket_id'],
            'notes'                    => ['nullable', 'string', 'max:5000'],
            'removed_at'               => ['nullable', 'date', 'before_or_equal:now'],
            'parts'                    => ['required', 'array', 'min:1', 'max:50'],
            'parts.*.part_name'        => ['required', 'string', 'max:255'],
            'parts.*.quantity'         => ['required', 'integer', 'min:1', 'max:100000'],
            'parts.*.blocks_readiness' => ['nullable', 'boolean'],
            'parts.*.part_condition'   => ['nullable', 'string', 'max:100'],
            'parts.*.notes'            => ['nullable', 'string', 'max:2000'],
            'evidence'                 => ['nullable', 'array', 'max:10'],
            'evidence.*'               => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:8192'],
        ]);

        $source = Vehicle::findOrFail($data['source_vehicle_id']);
        $retired = in_array($source->status, self::RETIRED, true);
        abort_if($data['donor_type'] === 'active' && $retired, 422, 'That vehicle is retired. Choose "Retired vehicle" as the donor type.');
        abort_if($data['donor_type'] === 'retired' && ! $retired, 422, 'That vehicle is still in service. Choose "Active vehicle" as the donor type.');

        // The parts go into the vehicle on this repair ticket.
        $ticket = MaintenanceTicket::with('vehicle')->findOrFail($data['ticket_id']);
        abort_unless($ticket->status === 'Active', 422, "Parts can only be logged against an open repair ticket. This ticket is {$ticket->status}.");
        $destination = $ticket->vehicle;
        abort_if((int) $destination->vehicle_id === (int) $source->vehicle_id, 422, 'The parts must come from a different vehicle from the one being repaired.');
        abort_unless($destination->barangay_id === $source->barangay_id, 422, 'The donor vehicle must be in the same barangay as the vehicle being repaired.');

        // The same part cannot be logged twice while it is still missing, in this request or from before.
        $seen = [];
        foreach ($data['parts'] as $part) {
            $key = $this->normalise($part['part_name']);
            abort_if(isset($seen[$key]), 422, "{$part['part_name']} is listed twice. Use the quantity instead.");
            $seen[$key] = true;
            abort_if(
                CannibalizationPart::stillMissing((int) $source->vehicle_id, $part['part_name']),
                422,
                "{$part['part_name']} is already logged as removed from {$source->vehicle_name} and has not been put back."
            );
        }

        $user = $request->user();
        $urls = [];
        foreach ($request->file('evidence', []) as $file) {
            $urls[] = $this->storeUploadedImage($file, 'cannibalization-evidence');
        }

        $record = DB::transaction(function () use ($data, $source, $destination, $ticket, $retired, $user, $urls, $request) {
            $record = CannibalizationRecord::create([
                'source_vehicle_id'      => $source->vehicle_id,
                'donor_type'             => $data['donor_type'],
                'ticket_id'              => $ticket->ticket_id,
                'destination_type'       => 'vehicle',
                'destination_vehicle_id' => $destination->vehicle_id,
                'storage_location'       => null,
                'reason'                 => "Used on repair ticket #{$ticket->display_number} ({$destination->vehicle_name}).",
                'source_reason'          => "Taken from {$source->vehicle_name} for repair ticket #{$ticket->display_number}.",
                'notes'                  => $data['notes'] ?? null,
                'recorded_by'            => $user->id,
                'performed_by_name'      => $user->name,
                'removed_at'             => $data['removed_at'] ?? now(),
                'evidence_urls'          => $urls ?: null,
            ]);
            $record->update(['record_number' => 'CAN-' . str_pad((string) $record->cannibalization_id, 4, '0', STR_PAD_LEFT)]);

            foreach ($data['parts'] as $part) {
                CannibalizationPart::create([
                    'cannibalization_id' => $record->cannibalization_id,
                    'part_name'          => trim((string) preg_replace('/\s+/', ' ', $part['part_name'])),
                    'quantity'           => (int) $part['quantity'],
                    // A retired vehicle is already out of the fleet, so its parts never hold anything up.
                    'blocks_readiness'   => $retired ? false : (bool) ($part['blocks_readiness'] ?? true),
                    'unit'               => 'pc',
                    'part_condition'     => $part['part_condition'] ?? null,
                    'notes'              => $part['notes'] ?? null,
                ]);
            }

            $summary = collect($data['parts'])->map(fn ($p) => "{$p['quantity']} × {$p['part_name']}")->implode(', ');
            $where = "{$destination->vehicle_name} ({$destination->plate_number})";

            VehicleHistory::create([
                'vehicle_id' => $source->vehicle_id, 'activity_type' => 'Parts Removed',
                'description' => "{$record->record_number}: {$summary} removed for {$where} (ticket #{$ticket->display_number}).",
                'related_table' => 'cannibalization_records', 'related_record_id' => (string) $record->cannibalization_id, 'updated_by' => $user->id,
            ]);
            VehicleHistory::create([
                'vehicle_id' => $destination->vehicle_id, 'activity_type' => 'Parts Received',
                'description' => "{$record->record_number}: {$summary} received from {$source->vehicle_name} ({$source->plate_number}).",
                'related_table' => 'cannibalization_records', 'related_record_id' => (string) $record->cannibalization_id, 'updated_by' => $user->id,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'role' => $request->attributes->get('vms_acted_as_role', $user->role),
                'action' => 'Cannibalization Recorded',
                'module' => 'Cannibalization',
                'affected_record_id' => (string) $record->cannibalization_id,
                'details' => "{$record->record_number}: {$summary} from {$source->vehicle_name} to {$where}.",
                'barangay_id' => $source->barangay_id,
            ]);

            return $record;
        });

        return response()->json($record->load(self::EAGER), 201);
    }
}
