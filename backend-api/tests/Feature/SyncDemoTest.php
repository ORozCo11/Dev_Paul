<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class SyncDemoTest extends TestCase
{
    public function test_production_is_refused_before_connecting_to_the_database(): void
    {
        $this->app['env'] = 'production';
        DB::shouldReceive('connection')->never();
        $this->artisan('demo:sync')->expectsOutputToContain('no changes made')->assertFailed();
    }

    public function test_non_sqlite_configuration_is_refused_without_connecting_to_a_remote_database(): void
    {
        config(['database.default' => 'mysql']);
        $this->artisan('demo:sync')->expectsOutputToContain('no changes made')->assertFailed();
        $this->assertInstanceOf(\Closure::class, DB::connection()->getRawPdo());
    }

    public function test_a_backup_failure_stops_before_migrating_or_seeding(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'vms-demo-test-');
        config(['database.connections.sqlite.database' => $database]);
        DB::purge('sqlite');
        File::partialMock()->shouldReceive('ensureDirectoryExists')->once()->andThrow(new RuntimeException('Backup directory unavailable.'));
        try {
            $this->artisan('demo:sync')->expectsOutputToContain('Backup directory unavailable')->assertFailed();
            $this->assertSame([], DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"));
        } finally {
            DB::disconnect('sqlite');
            unlink($database);
        }
    }
}
