<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Geographic references, followed by the local/testing sample fleet.
        $this->call([
            ProvinceSeeder::class,
            CitySeeder::class,
            CityBoundarySeeder::class,
            PhilippineBarangaySeeder::class,
            BarangayBoundarySeeder::class,
            // One municipality only (Cebu City): drops every other province, city and barangay.
            MunicipalityScopeSeeder::class,
            FleetReferenceSeeder::class,
            SampleFleetSeeder::class,
            GuadalupeSampleSeeder::class,
            // Demo account-switcher login (superadmin@demotest.com / test123), local/testing only.
            ImpersonationAccountSeeder::class,
        ]);
    }
}
