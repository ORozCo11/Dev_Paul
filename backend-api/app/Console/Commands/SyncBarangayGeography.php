<?php

namespace App\Console\Commands;

use Database\Seeders\CitySeeder;
use Database\Seeders\PhilippineBarangaySeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Console\Command;

class SyncBarangayGeography extends Command
{
    protected $signature = 'geography:sync';
    protected $description = 'Load bundled PSGC barangays while preserving existing tenant IDs, boundaries, and registration codes';

    public function handle(): int
    {
        foreach ([ProvinceSeeder::class, CitySeeder::class, PhilippineBarangaySeeder::class] as $seeder) {
            if ($this->call('db:seed', ['--class' => $seeder, '--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        return self::SUCCESS;
    }
}
