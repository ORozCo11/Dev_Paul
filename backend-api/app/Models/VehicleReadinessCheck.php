<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughVehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * A single pre-deployment readiness check on a vehicle (Gap A). Records the
 * type-specific checklist result and whether the vehicle passed — the basis
 * for the "verified ready to respond" signal, separate from repair status.
 */
class VehicleReadinessCheck extends Model
{
    use ScopedThroughVehicle;

    protected $primaryKey = 'readiness_check_id';

    protected $fillable = [
        'vehicle_id',
        'checked_by',
        'checklist',
        'all_passed',
        'notes',
        'checked_at',
    ];

    protected $casts = [
        'checklist'   => 'array',
        'all_passed'  => 'boolean',
        'checked_at'  => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
