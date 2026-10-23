<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Compatibility entry point: the five-vehicle sample fleet replaces the old demo. */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SampleFleetSeeder::class);
    }
}
