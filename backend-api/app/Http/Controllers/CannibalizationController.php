<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Concerns\UploadsImages;
use App\Models\ActivityLog;
use App\Models\CannibalizationPart;
use App\Models\CannibalizationRecord;
use App\Models\Vehicle;
use App\Models\VehicleHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cannibalization is a direct record of parts moved off a vehicle: no
 * request, approval or pending state. A saved record is complete and final
 * (no update or delete endpoint exists); who recorded it and when is taken
 * from the signed-in user, never from the form. Inventory balances are not
 * maintained by this application, so none are touched.
 */
class CannibalizationController extends Controller
{
    use AuthorizesAbilities;
    use UploadsImages;

    private const EAGER = ['sourceVehicle:vehicle_id,vehicle_name,plate_number,condition,status,photo_url', 'destinationVehicle:vehicle_id,vehicle_name,plate_number,condition,status,photo_url', 'recordedBy:id,name', 'parts'];

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

    public function store(Request $request)
    {
        $this->requireAbility($request, 'cannibalization.record');

        // Parts arrive as JSON text when sent with evidence files (multipart).
        if (is_string($request->input('parts'))) {
            $request->merge(['parts' => json_decode($request->input('parts'), true) ?? []]);
        }

        $data = $request->validate([
            'source_vehicle_id'      => ['required', 'exists:vehicles,vehicle_id'],
            'source_reason'          => ['required', 'string', 'max:2000'],
            'destination_type'       => ['required', 'in:vehicle,storage'],
            'destination_vehicle_id' => ['nullable', 'required_if:destination_type,vehicle', 'exists:vehicles,vehicle_id'],
            'storage_location'       => ['nullable', 'required_if:destination_type,storage', 'string', 'max:255'],
            'reason'                 => ['required', 'string', 'max:2000'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'removed_at'             => ['required', 'date', 'before_or_equal:now'],
            'performed_by_name'      => ['nullable', 'string', 'max:255'],
            'received_by_name'       => ['nullable', 'string', 'max:255'],
            'transferred_at'         => ['nullable', 'date', 'after_or_equal:removed_at', 'before_or_equal:now'],
            'parts'                  => ['required', 'array', 'min:1', 'max:50'],
            'parts.*.part_name'      => ['required', 'string', 'max:255'],
            'parts.*.part_number'    => ['nullable', 'string', 'max:255'],
            'parts.*.quantity'       => ['required', 'integer', 'min:1', 'max:100000'],
            'parts.*.unit'           => ['nullable', 'string', 'max:30'],
            'parts.*.part_condition' => ['nullable', 'string', 'max:100'],
            'parts.*.installation_details' => ['nullable', 'string', 'max:2000'],
            'parts.*.notes'          => ['nullable', 'string', 'max:2000'],
            'evidence'               => ['nullable', 'array', 'max:10'],
            'evidence.*'             => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:8192'],
        ]);

        $source = Vehicle::findOrFail($data['source_vehicle_id']);
        abort_if(
            in_array($source->status, ['Inactive', 'Decommissioned'], true),
            422,
            'Parts cannot be recorded against an archived or decommissioned source vehicle here.'
        );

        $destination = null;
        if ($data['destination_type'] === 'vehicle') {
            abort_if((int) $data['destination_vehicle_id'] === (int) $source->vehicle_id, 422, 'The destination must be a different vehicle from the source.');
            $destination = Vehicle::findOrFail($data['destination_vehicle_id']);
            abort_if(
                in_array($destination->status, ['Inactive', 'Decommissioned'], true),
                422,
                'The destination vehicle is archived or decommissioned.'
            );
        }

        $user = $request->user();
        $urls = [];
        foreach ($request->file('evidence', []) as $file) {
            $urls[] = $this->storeUploadedImage($file, 'cannibalization-evidence');
        }

        $record = DB::transaction(function () use ($data, $source, $destination, $user, $urls, $request) {
            $record = CannibalizationRecord::create([
                'source_vehicle_id'      => $source->vehicle_id,
                'destination_type'       => $data['destination_type'],
                'destination_vehicle_id' => $destination?->vehicle_id,
                'storage_location'       => $data['destination_type'] === 'storage' ? $data['storage_location'] : null,
                'reason'                 => $data['reason'],
                'source_reason'          => $data['source_reason'],
                'notes'                  => $data['notes'] ?? null,
                'recorded_by'            => $user->id,
                'performed_by_name'      => $data['performed_by_name'] ?? $user->name,
                'removed_at'             => $data['removed_at'],
                'received_by_name'       => $data['received_by_name'] ?? null,
                'transferred_at'         => $data['transferred_at'] ?? null,
                'evidence_urls'          => $urls ?: null,
            ]);
            $record->update(['record_number' => 'CAN-' . str_pad((string) $record->cannibalization_id, 4, '0', STR_PAD_LEFT)]);

            foreach ($data['parts'] as $part) {
                CannibalizationPart::create([
                    'cannibalization_id'   => $record->cannibalization_id,
                    'part_name'            => $part['part_name'],
                    'part_number'          => $part['part_number'] ?? null,
                    'quantity'             => (int) $part['quantity'],
                    'unit'                 => $part['unit'] ?? 'pc',
                    'part_condition'       => $part['part_condition'] ?? null,
                    'installation_details' => $part['installation_details'] ?? null,
                    'notes'                => $part['notes'] ?? null,
                ]);
            }

            $summary = collect($data['parts'])->map(fn ($p) => "{$p['quantity']} × {$p['part_name']}")->implode(', ');
            $where = $destination ? "{$destination->vehicle_name} ({$destination->plate_number})" : "storage ({$data['storage_location']})";

            // A part coming off a vehicle can make it unsafe to rely on, so a
            // vehicle recorded as Good is flagged for inspection. Nothing is
            // ever upgraded to operational here, and no status is cleared.
            if ($source->condition === 'Good') {
                $source->update(['condition' => 'Needs Inspection']);
            }

            VehicleHistory::create([
                'vehicle_id' => $source->vehicle_id, 'activity_type' => 'Parts Removed',
                'description' => "{$record->record_number}: {$summary} removed and sent to {$where}.",
                'related_table' => 'cannibalization_records', 'related_record_id' => (string) $record->cannibalization_id, 'updated_by' => $user->id,
            ]);
            if ($destination) {
                VehicleHistory::create([
                    'vehicle_id' => $destination->vehicle_id, 'activity_type' => 'Parts Received',
                    'description' => "{$record->record_number}: {$summary} received from {$source->vehicle_name} ({$source->plate_number}).",
                    'related_table' => 'cannibalization_records', 'related_record_id' => (string) $record->cannibalization_id, 'updated_by' => $user->id,
                ]);
            }

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
