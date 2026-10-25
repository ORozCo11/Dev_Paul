<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughVehicle;
use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    use ScopedThroughVehicle;

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'vehicle_id',
        'title',
        'category',
        'file_url',
        'added_by',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
