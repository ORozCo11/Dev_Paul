<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BarangayNumbers
{
    // Each module has its own durable counter within each barangay.
    public const RESOURCES = [
        'vehicles' => 'vehicle_id',
        'users' => 'id',
        'vehicle_condition_checks' => 'condition_check_id',
        'vehicle_issue_reports' => 'issue_report_id',
        'maintenance_tickets' => 'ticket_id',
        'vehicle_maintenance_records' => 'maintenance_id',
        'vehicle_maintenance_schedules' => 'schedule_id',
        'vehicle_locations' => 'location_record_id',
        'vehicle_histories' => 'history_id',
        'ticket_archive_logs' => 'archive_id',
    ];

    public static function next(int $barangayId, string $resource): int
    {
        return DB::transaction(function () use ($barangayId, $resource) {
            DB::table('barangay_number_sequences')->insertOrIgnore([
                'barangay_id' => $barangayId, 'resource' => $resource, 'last_number' => 0,
            ]);
            $query = DB::table('barangay_number_sequences')->where('barangay_id', $barangayId)->where('resource', $resource);
            $number = (int) $query->lockForUpdate()->value('last_number') + 1;
            $query->update(['last_number' => $number]);
            return $number;
        }, 5);
    }
}
