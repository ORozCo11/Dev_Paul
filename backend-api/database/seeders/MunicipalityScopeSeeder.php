<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * This VMS instance serves ONE municipality (Cebu City) and the barangays inside it. The national reference seeders load
 * the whole PSGC list, so this runs right after them and removes every other province, city and barangay.
 *
 * Anything still referenced by real data (a user, vehicle, hub, file, import, log or number sequence) is left alone, so
 * running it on a live database can never delete a barangay somebody is using.
 */
class MunicipalityScopeSeeder extends Seeder
{
    public const CITY_CODE = '072217000'; // Cebu City (PSGC)

    public function run(): void
    {
        $city = DB::table('cities')->where('code', self::CITY_CODE)->first();
        if (!$city) {
            $this->command?->warn('Cebu City is not in the cities table; nothing was removed.');
            return;
        }

        $inUse = collect(['users', 'vehicles', 'vehicle_hubs', 'managed_files', 'vehicle_imports', 'activity_logs', 'barangay_number_sequences'])
            ->filter(fn ($table) => DB::getSchemaBuilder()->hasColumn($table, 'barangay_id'))
            ->flatMap(fn ($table) => DB::table($table)->whereNotNull('barangay_id')->distinct()->pluck('barangay_id'))
            ->unique()->values()->all();

        $removedBarangays = DB::table('barangays')->where('city_id', '!=', $city->id)->whereNotIn('id', $inUse)->delete();
        $usedCities = DB::table('barangays')->distinct()->pluck('city_id')->push($city->id)
            ->merge(DB::table('users')->whereNotNull('city_id')->pluck('city_id'))->unique()->all();
        $removedCities = DB::table('cities')->whereNotIn('id', $usedCities)->delete();
        $usedProvinces = DB::table('cities')->distinct()->pluck('province_id')->all();
        $removedProvinces = DB::table('provinces')->whereNotIn('id', $usedProvinces)->delete();

        $this->command?->info("Municipality scope: removed {$removedBarangays} barangays, {$removedCities} cities, {$removedProvinces} provinces.");
    }
}
