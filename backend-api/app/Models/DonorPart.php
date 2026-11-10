<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One part taken from a cannibalized repair's donor vehicle. */
class DonorPart extends Model
{
    protected $table = 'ticket_sub_issue_donor_parts';
    protected $primaryKey = 'part_id';

    protected $fillable = ['sub_issue_id', 'donor_vehicle_id', 'part_name', 'quantity', 'blocks_readiness', 'part_condition', 'installed_at', 'reason', 'notes', 'restored_at', 'restored_by'];

    protected $casts = ['quantity' => 'integer', 'blocks_readiness' => 'boolean', 'installed_at' => 'date:Y-m-d', 'restored_at' => 'datetime'];

    /** The vehicle this part was taken from. */
    public function donorVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'donor_vehicle_id', 'vehicle_id');
    }
}
