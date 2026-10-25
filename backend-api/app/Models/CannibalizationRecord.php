<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughVehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * A completed record of parts removed from one vehicle and where they went.
 * Scoped to the barangay through its source vehicle. Saved records are final.
 */
class CannibalizationRecord extends Model
{
    use ScopedThroughVehicle;

    protected $primaryKey = 'cannibalization_id';

    protected $fillable = [
        'record_number', 'source_vehicle_id', 'destination_type', 'destination_vehicle_id', 'storage_location',
        'reason', 'source_reason', 'notes', 'recorded_by', 'performed_by_name', 'removed_at',
        'received_by_name', 'transferred_at', 'evidence_urls',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
        'transferred_at' => 'datetime',
        'evidence_urls' => 'array',
    ];

    protected static function vehicleRelationPath(): string
    {
        return 'sourceVehicle';
    }

    public function sourceVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'source_vehicle_id', 'vehicle_id');
    }

    public function destinationVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'destination_vehicle_id', 'vehicle_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function parts()
    {
        return $this->hasMany(CannibalizationPart::class, 'cannibalization_id', 'cannibalization_id');
    }
}
