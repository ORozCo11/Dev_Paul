<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Services still booked on vehicles that were already decommissioned: they can never be done and only show as overdue.
        DB::table('vehicle_maintenance_schedules')
            ->whereIn('vehicle_id', DB::table('vehicles')->where('status', 'Decommissioned')->select('vehicle_id'))
            ->whereIn('status', ['Scheduled', 'Pending Approval'])
            ->whereNull('resulting_ticket_id')
            ->update(['status' => 'Cancelled', 'hold_reason' => null]);
    }

    public function down(): void
    {
        // Data clean-up only; not reversible.
    }
};
