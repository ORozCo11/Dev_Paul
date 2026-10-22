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
        // Call your custom UserSeeder class
        $this->call([
            ProvinceSeeder::class,
            CitySeeder::class,
            CityBoundarySeeder::class,
            BarangaySeeder::class,
            PhilippineBarangaySeeder::class,
            BarangayBoundarySeeder::class,
            UserSeeder::class,
            ReferenceBarangayRegistrationSeeder::class,
            FleetReferenceSeeder::class,
            MockDataSeeder::class,
            DemoActivitySeeder::class,
        ]);
    }
}
