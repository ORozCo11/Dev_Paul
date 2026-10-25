<?php

namespace App\Models;

use App\Models\Concerns\HasBarangayNumber;

use App\Models\Concerns\ScopedThroughVehicle;
use Illuminate\Database\Eloquent\Model;

class VehicleConditionCheck extends Model
{
    use ScopedThroughVehicle;
    use HasBarangayNumber;

    protected $primaryKey = 'condition_check_id';

    protected $fillable = [
        'vehicle_id',
        'condition_result',
        'observations',
        'checked_by',
        'resulting_ticket_id',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // The ticket this check was escalated into, if any — set once at
    // ticket-creation time and never changed again. Its STATUS is read
    // live through this relation (not snapshotted), so Condition Monitoring
    // always shows the ticket's current state without any extra sync step.
    public function resultingTicket()
    {
        return $this->belongsTo(MaintenanceTicket::class, 'resulting_ticket_id', 'ticket_id');
    }
}
