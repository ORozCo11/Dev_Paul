<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughVehicle;
use App\Models\Concerns\HasBarangayNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ticket snapshot retained when moved to the recoverable deleted list.
 */
class TicketArchiveLog extends Model
{
    use ScopedThroughVehicle;
    use HasBarangayNumber;
    use SoftDeletes;

    protected $primaryKey = 'archive_id';

    // Archive creation has its own archived_at timestamp.
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'vehicle_id',
        'ticket_title',
        'vehicle_name',
        'plate_number',
        'final_status',
        'maintenance_cost',
        'full_ticket_snapshot',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'full_ticket_snapshot' => 'array',
        'archived_at'          => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(MaintenanceTicket::class, 'ticket_id', 'ticket_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
