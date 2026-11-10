<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CannibalizationPart extends Model
{
    protected $primaryKey = 'part_id';

    protected $fillable = [
        'cannibalization_id', 'part_name', 'part_number', 'quantity', 'blocks_readiness', 'unit', 'part_condition', 'installation_details', 'notes', 'restored_at', 'restored_by',
    ];

    protected $casts = ['quantity' => 'integer', 'blocks_readiness' => 'boolean', 'restored_at' => 'datetime'];

    /**
     * Is this part still missing (taken off the vehicle and not put back)? Looks at both places a
     * taken part can be recorded - a cannibalization record and a cannibalized repair on a ticket -
     * and compares names ignoring capitals and extra spaces.
     */
    public static function stillMissing(int $vehicleId, string $name): bool
    {
        $clean = fn ($value) => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
        $needle = $clean($name);

        $fromRecords = static::query()
            ->join('cannibalization_records', 'cannibalization_records.cannibalization_id', '=', 'cannibalization_parts.cannibalization_id')
            ->where('cannibalization_records.source_vehicle_id', $vehicleId)
            ->whereNull('cannibalization_parts.restored_at')
            ->pluck('cannibalization_parts.part_name');
        $fromRepairs = DonorPart::query()
            ->where('donor_vehicle_id', $vehicleId)->whereNull('restored_at')
            ->pluck('part_name');

        return $fromRecords->concat($fromRepairs)->contains(fn ($existing) => $clean($existing) === $needle);
    }
}
