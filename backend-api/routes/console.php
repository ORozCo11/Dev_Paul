<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the notifications table from growing unbounded — see
// App\Console\Commands\PruneOldNotifications for the retention rule.
Schedule::command('notifications:prune')->daily();

// Tells each Custodian, once a day, which readiness checks lapse within a day — see App\Console\Commands\WarnExpiringReadiness.
Schedule::command('readiness:warn-expiring')->dailyAt('07:00')->timezone('Asia/Manila');

// Turns a due Maintenance Schedule entry into a real ticket automatically —
// see App\Console\Commands\ConvertDueSchedulesToTickets.
Schedule::command('schedules:convert-due-to-tickets')->everyMinute()->timezone('Asia/Manila')->withoutOverlapping();

// One reminder rule for every ticket stage — see
// App\Console\Commands\RemindStalledTickets.
Schedule::command('tickets:remind-stalled')->dailyAt('08:00')->timezone('Asia/Manila')->withoutOverlapping();
