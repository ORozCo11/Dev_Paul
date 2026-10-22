<?php

namespace App\Console\Commands;

use Database\Seeders\CitySeeder;
use Database\Seeders\PhilippineBarangaySeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBarangayGeography extends Command
{
    protected $signature = 'geography:sync';
    protected $description = 'Load bundled PSGC barangays while preserving existing tenant IDs, boundaries, and registration codes';

    public function handle(): int
    {
        // Let active registration requests finish before importing reference rows.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 30000');
        }
        foreach ([ProvinceSeeder::class, CitySeeder::class, PhilippineBarangaySeeder::class] as $seeder) {
            if ($this->call('db:seed', ['--class' => $seeder, '--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        return self::SUCCESS;
    }
}
