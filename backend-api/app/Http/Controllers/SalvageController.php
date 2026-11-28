<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Models\ActivityLog;
use App\Models\SalvagePart;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Salvage inventory: after an Admin decommissions a vehicle, a mechanic lists the parts that can still be used.
 * Repairs then draw from that stock ("Uses parts from another vehicle"). Admin and Custodian can read it.
 */
class SalvageController extends Controller
{
    use AuthorizesAbilities;

    private const CONDITIONS = ['Good', 'Usable', 'Needs refurbishing'];

    private function log(Request $request, string $action, string $details, ?int $recordId = null): void
    {
        ActivityLog::create(['user_id' => $request->user()->id, 'role' => $request->user()->role, 'action' => $action, 'module' => 'Salvage Inventory', 'affected_record_id' => $recordId, 'details' => $details]);
    }

    /** The stock list. */
    public function index(Request $request)
    {
        $this->requireAbility($request, 'salvage.view');

        return SalvagePart::with(['vehicle:vehicle_id,vehicle_name,plate_number', 'loggedBy:id,name'])
            ->latest('salvage_part_id')->get()
            ->map(fn (SalvagePart $part) => $this->present($part));
    }

    /** Decommissioned vehicles, with how many parts were logged and whether the mechanic has finished listing them. */
    public function queue(Request $request)
    {
        $this->requireAbility($request, 'salvage.view');

        $sums = SalvagePart::selectRaw('vehicle_id, COUNT(*) as part_lines, SUM(quantity) as listed, SUM(quantity_available) as in_stock')->groupBy('vehicle_id')->get()->keyBy('vehicle_id');

        return Vehicle::with('category')->where('status', 'Decommissioned')->orderByDesc('decommissioned_at')->get()->map(fn (Vehicle $vehicle) => [
            'vehicle_id' => $vehicle->vehicle_id,
            'vehicle_name' => $vehicle->vehicle_name,
            'plate_number' => $vehicle->plate_number,
            'decommissioned_at' => $vehicle->decommissioned_at,
            'decommission_reason' => $vehicle->decommission_reason,
            'parts_logged' => (int) ($sums[$vehicle->vehicle_id]->part_lines ?? 0),
            'units_listed' => (int) ($sums[$vehicle->vehicle_id]->listed ?? 0),
            'units_in_stock' => (int) ($sums[$vehicle->vehicle_id]->in_stock ?? 0),
            'photo_url' => $vehicle->photo_url,
            'vehicle_type' => $vehicle->category?->category_name,
            'brand' => $vehicle->brand, 'model' => $vehicle->model, 'year_model' => $vehicle->year_model,
            'salvage_completed_at' => $vehicle->salvage_completed_at,
        ])->values();
    }

    public function store(Request $request)
    {
        $this->requireAbility($request, 'salvage.manage');
        $data = $this->validated($request);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        abort_unless($vehicle->status === 'Decommissioned', 422, 'Parts can only be logged from a decommissioned vehicle. Ask the Admin to decommission it first.');
        // A vehicle marked "no usable parts" that turns out to have some simply goes back to waiting for its list.
        if ($vehicle->salvage_completed_at) {
            $vehicle->update(['salvage_completed_at' => null, 'salvage_completed_by' => null]);
        }

        $this->abortIfListed((int) $data['vehicle_id'], $data['part_name']);
        $part = SalvagePart::create($data + ['barangay_id' => $vehicle->barangay_id, 'quantity_available' => $data['quantity'], 'logged_by' => $request->user()->id]);
        $this->log($request, 'Add', "Logged {$part->quantity} x {$part->part_name} from {$vehicle->vehicle_name} as salvageable.", $part->salvage_part_id);

        return response()->json($this->present($part->refresh()->load(['vehicle:vehicle_id,vehicle_name,plate_number', 'loggedBy:id,name'])), 201);
    }

    public function update(Request $request, SalvagePart $salvagePart)
    {
        $this->requireAbility($request, 'salvage.manage');
        $data = $this->validated($request, false);
        $this->abortIfListed((int) $salvagePart->vehicle_id, $data['part_name'], $salvagePart->salvage_part_id);
        $used = $salvagePart->quantity - $salvagePart->quantity_available;
        abort_if($data['quantity'] < $used, 422, "{$used} of this part have already been used in repairs, so the total cannot be below {$used}.");

        $salvagePart->update(collect($data)->except('vehicle_id')->all() + ['quantity_available' => $data['quantity'] - $used]);
        $this->log($request, 'Edit', "Updated salvageable part {$salvagePart->part_name}.", $salvagePart->salvage_part_id);

        return $this->present($salvagePart->fresh(['vehicle:vehicle_id,vehicle_name,plate_number', 'loggedBy:id,name']));
    }

    public function destroy(Request $request, SalvagePart $salvagePart)
    {
        $this->requireAbility($request, 'salvage.manage');
        abort_if($salvagePart->quantity_available < $salvagePart->quantity, 422, 'Some of this part were already used in repairs, so it cannot be removed. Lower the quantity to what is left instead.');
        $salvagePart->delete();
        $this->log($request, 'Delete', "Removed salvageable part {$salvagePart->part_name} from the inventory.", $salvagePart->salvage_part_id);

        return response()->json(['message' => 'Part removed from the inventory.']);
    }

    /** The mechanic says the list for this vehicle is finished (or that nothing could be salvaged). */
    public function complete(Request $request, Vehicle $vehicle)
    {
        $this->requireAbility($request, 'salvage.manage');
        abort_unless($vehicle->status === 'Decommissioned', 422, 'Only a decommissioned vehicle has a salvage list.');
        $logged = SalvagePart::where('vehicle_id', $vehicle->vehicle_id)->count();
        $data = $request->validate(['nothing_salvageable' => ['sometimes', 'boolean']]);
        abort_if($logged === 0 && ! $request->boolean('nothing_salvageable'), 422, 'No parts are logged yet. Add the parts, or confirm that nothing could be salvaged.');

        $vehicle->update(['salvage_completed_at' => now(), 'salvage_completed_by' => $request->user()->id]);
        $this->log($request, 'Edit', "Finished the salvage list for {$vehicle->vehicle_name} ({$logged} part line(s)).", $vehicle->vehicle_id);

        return response()->json(['message' => 'Salvage list finished.', 'salvage_completed_at' => $vehicle->salvage_completed_at]);
    }

    public function reopen(Request $request, Vehicle $vehicle)
    {
        $this->requireAbility($request, 'salvage.manage');
        abort_unless($vehicle->salvage_completed_at, 422, 'This vehicle\'s salvage list is not finished.');
        $vehicle->update(['salvage_completed_at' => null, 'salvage_completed_by' => null]);

        return response()->json(['message' => 'Salvage list reopened.']);
    }

    private function validated(Request $request, bool $withVehicle = true): array
    {
        if (is_string($request->input('part_name'))) {
            $request->merge(['part_name' => preg_replace('/\s+/u', ' ', trim($request->input('part_name')))]);
        }

        $data = $request->validate([
            'vehicle_id' => $withVehicle ? ['required', 'integer', 'exists:vehicles,vehicle_id'] : ['sometimes', 'integer'],
            'part_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            // Not asked for on screen any more; it stays optional so older callers keep working (default Good).
            'part_condition' => ['nullable', Rule::in(self::CONDITIONS)],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (($data['part_condition'] ?? null) === null) {
            unset($data['part_condition']);
        }

        return $data;
    }

    /** One line per part per vehicle: raise its quantity instead of listing it twice (spacing and capitals do not count). */
    private function abortIfListed(int $vehicleId, string $name, ?int $exceptId = null): void
    {
        $exists = SalvagePart::where('vehicle_id', $vehicleId)->when($exceptId, fn ($q) => $q->where('salvage_part_id', '!=', $exceptId))
            ->whereRaw('LOWER(TRIM(part_name)) = ?', [mb_strtolower(trim($name))])->exists();
        abort_if($exists, 422, "{$name} is already listed for this vehicle. Edit that line to change its quantity.");
    }

    private function present(SalvagePart $part): array
    {
        return [
            'salvage_part_id' => $part->salvage_part_id,
            'vehicle_id' => $part->vehicle_id,
            'vehicle_name' => $part->vehicle?->vehicle_name,
            'plate_number' => $part->vehicle?->plate_number,
            'part_name' => $part->part_name,
            'category' => $part->category,
            'part_condition' => $part->part_condition,
            'quantity' => $part->quantity,
            'quantity_available' => $part->quantity_available,
            'storage_location' => $part->storage_location,
            'notes' => $part->notes,
            'logged_by' => $part->loggedBy?->name,
            'created_at' => $part->created_at,
        ];
    }
}
