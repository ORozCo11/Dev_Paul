<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use App\Models\VehicleIssueReport;
use App\Models\VehicleMaintenanceRecord;

class VehicleOperationalState
{
    public static function refresh(Vehicle $vehicle, bool $releaseIfIdle = false): void
    {
        $vehicle->refresh();
        if (in_array($vehicle->status, ['Inactive', 'Decommissioned'], true)) {
            return;
        }
        $ongoing = MaintenanceTicket::where('vehicle_id', $vehicle->vehicle_id)->whereIn('status', ['Active', 'For Verification'])->exists()
            || VehicleMaintenanceRecord::where('vehicle_id', $vehicle->vehicle_id)->where('progress_status', '!=', 'Completed')->exists()
            || VehicleIssueReport::where('vehicle_id', $vehicle->vehicle_id)->where('status', 'In Maintenance')->whereDoesntHave('maintenanceTicket')->exists();
        if ($ongoing) {
            $repair = MaintenanceTicket::where('vehicle_id', $vehicle->vehicle_id)->whereIn('status', ['Active', 'For Verification'])->exists()
                || VehicleMaintenanceRecord::where('vehicle_id', $vehicle->vehicle_id)->whereIn('progress_status', ['Assigned', 'Under Repair'])->exists();
            $vehicle->update(['status' => 'Under Maintenance', 'condition' => $repair ? 'Needs Repair' : $vehicle->condition]);
        } elseif ($releaseIfIdle || $vehicle->status === 'Under Maintenance') {
            $hasIssue = VehicleIssueReport::where('vehicle_id', $vehicle->vehicle_id)->whereIn('status', ['Pending', 'Under Review', 'In Maintenance'])->exists();
            $vehicle->update(['status' => 'Available', 'condition' => $hasIssue ? 'Needs Inspection' : 'Good', 'estimated_return_date' => null]);
        }
    }

    public static function hold(Vehicle $vehicle, string $condition = 'Needs Repair'): void
    {
        $vehicle->refresh();
        if (! in_array($vehicle->status, ['Inactive', 'Decommissioned'], true)) {
            $vehicle->update(['status' => 'Under Maintenance', 'condition' => $condition]);
        }
    }
}
