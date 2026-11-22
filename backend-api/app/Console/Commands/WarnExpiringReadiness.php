<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReadinessCheck;
use Illuminate\Console\Command;

/**
 * A confirmed readiness check stays good for config('vms.readiness_max_days') days. Once a day this tells the
 * barangay's Custodians which vehicles' checks lapse within the next day, so they can re-check before the vehicle
 * drops out of "Ready to Respond".
 */
class WarnExpiringReadiness extends Command
{
    protected $signature = 'readiness:warn-expiring';

    protected $description = 'Notify Custodians of readiness checks that lapse within a day';

    public function handle(): int
    {
        $days = (int) config('vms.readiness_max_days', 7);
        // Checked between (days) and (days - 1) days ago: the check lapses within the next 24 hours.
        $from = now()->subDays($days);
        $to = now()->subDays($days - 1);

        $latest = VehicleReadinessCheck::orderByDesc('checked_at')->get()->unique('vehicle_id');
        $byBarangay = [];
        foreach ($latest as $check) {
            if (!$check->all_passed || $check->checked_at->lt($from) || $check->checked_at->gt($to)) continue;
            $vehicle = Vehicle::find($check->vehicle_id);
            if (!$vehicle || in_array($vehicle->status, ['Inactive', 'Decommissioned'], true)) continue;
            if ($vehicle->readiness_invalidated_at && $check->checked_at->lte($vehicle->readiness_invalidated_at)) continue;
            $byBarangay[$vehicle->barangay_id][] = $vehicle->vehicle_name;
        }

        $sent = 0;
        foreach ($byBarangay as $barangayId => $names) {
            $list = implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? ' and '.(count($names) - 5).' more' : '');
            foreach (User::havingRole('Custodian')->where('is_active', true)->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId), fn ($q) => $q->whereNull('barangay_id'))->get() as $custodian) {
                Notification::create([
                    'user_id' => $custodian->id,
                    'title'   => 'Readiness checks expire soon',
                    'message' => count($names).' vehicle(s) need a new readiness check within a day: '.$list.'.',
                    'type'    => 'readiness_expiring',
                ]);
                $sent++;
            }
        }
        $this->info("Sent {$sent} notice(s).");

        return self::SUCCESS;
    }
}
