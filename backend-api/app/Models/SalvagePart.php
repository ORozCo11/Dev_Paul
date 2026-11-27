<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

/** One kind of salvageable part logged from a decommissioned vehicle, with how many are still in stock. */
class SalvagePart extends Model
{
    use BelongsToBarangay;

    protected $primaryKey = 'salvage_part_id';

    protected $fillable = ['barangay_id', 'vehicle_id', 'part_name', 'category', 'part_condition', 'quantity', 'quantity_available', 'storage_location', 'notes', 'logged_by'];

    protected $casts = ['quantity' => 'integer', 'quantity_available' => 'integer'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
