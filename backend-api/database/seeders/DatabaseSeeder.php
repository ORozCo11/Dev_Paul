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
            BarangaySeeder::class,
            PhilippineBarangaySeeder::class,
            BarangayBoundarySeeder::class,
            FleetReferenceSeeder::class,
            SampleFleetSeeder::class,
        ]);
    }
}
