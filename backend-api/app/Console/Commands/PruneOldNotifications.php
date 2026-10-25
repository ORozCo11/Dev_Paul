<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * The notifications table has no retention policy — every ticket/schedule
 * event stamps a permanent row, so it grows forever. This prunes it on a
 * schedule (see routes/console.php) with two thresholds:
 *
 *  - a READ notification is already-seen, low-value history — gone after
 *    30 days by default.
 *  - ANY notification, read or not, is gone after 90 days regardless —
 *    an unread notification that old is realistically never going to be
 *    acted on, and it shouldn't be allowed to live forever just because
 *    nobody clicked it.
 */
class PruneOldNotifications extends Command
{
    protected $signature = 'notifications:prune
        {--read-days=30 : Delete READ notifications older than this many days}
        {--max-days=90 : Delete ANY notification (read or not) older than this many days}';

    protected $description = 'Delete old notifications to keep the table from growing unbounded';

    public function handle(): int
    {
        $readDays = (int) $this->option('read-days');
        $maxDays = (int) $this->option('max-days');

        $readCutoff = now()->subDays($readDays);
        $maxCutoff = now()->subDays($maxDays);

        $count = Notification::where(function ($query) use ($readCutoff) {
            $query->whereNotNull('read_at')->where('created_at', '<', $readCutoff);
        })->orWhere('created_at', '<', $maxCutoff)->delete();

        $this->info("Pruned {$count} old notification(s) (read > {$readDays}d, or any > {$maxDays}d).");

        return self::SUCCESS;
    }
}
