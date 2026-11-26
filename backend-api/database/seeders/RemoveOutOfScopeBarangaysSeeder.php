<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off cleanup (local/testing only): permanently deletes the Paknaan and Ticad (Pob.) barangays
 * together with their users, vehicles, tickets, schedules, records, notifications and logs.
 * Only barangays OUTSIDE Cebu City are touched; Guadalupe and accounts with no barangay are kept.
 *
 *   php artisan db:seed --class=RemoveOutOfScopeBarangaysSeeder
 */
class RemoveOutOfScopeBarangaysSeeder extends Seeder
{
    private const NAMES = ['Paknaan', 'Ticad (Pob.)'];

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Cleanup skipped: local/testing only.');
            return;
        }

        $cebuCityId = DB::table('cities')->where('code', MunicipalityScopeSeeder::CITY_CODE)->value('id');
        $barangayIds = DB::table('barangays')->whereIn('name', self::NAMES)
            ->when($cebuCityId, fn ($q) => $q->where('city_id', '!=', $cebuCityId))->pluck('id')->all();
        if (!$barangayIds) {
            $this->command?->info('Nothing to remove.');
            return;
        }

        $userIds = DB::table('users')->whereIn('barangay_id', $barangayIds)->pluck('id')->all();
        $vehicleIds = DB::table('vehicles')->whereIn('barangay_id', $barangayIds)->pluck('vehicle_id')->all();
        $ticketIds = DB::table('maintenance_tickets')->whereIn('vehicle_id', $vehicleIds)->pluck('ticket_id')->all();
        $issueIds = DB::table('vehicle_issue_reports')->whereIn('vehicle_id', $vehicleIds)->pluck('issue_report_id')->all();
        $scheduleIds = DB::table('vehicle_maintenance_schedules')->whereIn('vehicle_id', $vehicleIds)->pluck('schedule_id')->all();

        $scopes = [
            'vehicle_id' => $vehicleIds, 'source_vehicle_id' => $vehicleIds, 'destination_vehicle_id' => $vehicleIds,
            'ticket_id' => $ticketIds, 'issue_report_id' => $issueIds, 'schedule_id' => $scheduleIds,
            'barangay_id' => $barangayIds,
        ];
        $skip = ['users', 'barangays', 'migrations'];

        Schema::disableForeignKeyConstraints();
        DB::transaction(function () use ($scopes, $skip, $userIds, $barangayIds) {
            foreach (Schema::getTableListing() as $table) {
                $table = preg_replace('/^.*\./', '', $table);
                if (in_array($table, $skip, true)) continue;
                foreach ($scopes as $column => $ids) {
                    if ($ids && Schema::hasColumn($table, $column)) {
                        DB::table($table)->whereIn($column, $ids)->delete();
                    }
                }
                // Rows that only belong to the removed users (notifications, tokens, ...).
                if ($userIds && Schema::hasColumn($table, 'user_id')) {
                    DB::table($table)->whereIn('user_id', $userIds)->delete();
                }
            }
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', 'like', '%User')->whereIn('tokenable_id', $userIds)->delete();
            }
            DB::table('users')->whereIn('id', $userIds)->delete();
            DB::table('barangays')->whereIn('id', $barangayIds)->delete();
        });
        Schema::enableForeignKeyConstraints();

        $this->command?->info('Removed '.count($barangayIds).' barangays, '.count($userIds).' users and '.count($vehicleIds).' vehicles with their related records.');
    }
}
