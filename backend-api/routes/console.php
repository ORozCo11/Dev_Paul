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

// Cancels first-Admin registrations whose emailed staff code went unused for
// a week — see App\Console\Commands\ExpireUnclaimedRegistrations.
Schedule::command('registrations:expire-unclaimed')->hourly();

// Turns a due Maintenance Schedule entry into a real ticket automatically —
// see App\Console\Commands\ConvertDueSchedulesToTickets.
Schedule::command('schedules:convert-due-to-tickets')->everyMinute()->timezone('Asia/Manila')->withoutOverlapping();
