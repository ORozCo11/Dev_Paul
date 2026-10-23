<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class SyncDemo extends Command
{
    protected $signature = 'demo:sync';
    protected $description = 'Back up local SQLite, migrate, and reset the shared five-vehicle demo';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('Demo sync requires local/testing with SQLite; no changes made.');
            return self::FAILURE;
        }

        try {
            if (DB::connection()->getDatabaseName() !== ':memory:') {
                $directory = storage_path('app/backups');
                File::ensureDirectoryExists($directory);
                $backup = $directory.'/before-demo-sync-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';
                // SQLite creates a consistent snapshot while the dev API may be running.
                // Refuse to migrate/reset if that snapshot cannot be written.
                DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($backup));
                $this->info('Database backup: '.$backup);
            }
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) return self::FAILURE;
            if ($this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]) !== self::SUCCESS) return self::FAILURE;
        } catch (Throwable $error) {
            $this->error('Demo sync stopped: '.$error->getMessage());
            return self::FAILURE;
        }

        $this->info('Shared demo loaded: 5 vehicles, 6 staff profiles, 3 tickets and 4 schedules.');
        $this->line('Other accounts/vehicles are preserved. Log out and sign in again to refresh your profile.');
        return self::SUCCESS;
    }
}
