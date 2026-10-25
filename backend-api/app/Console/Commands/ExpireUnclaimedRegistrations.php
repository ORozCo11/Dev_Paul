<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * A first Administrator who never enters the emailed staff code within
 * User::STAFF_CODE_VALID_DAYS has their registration cancelled and is
 * emailed to register again.
 */
class ExpireUnclaimedRegistrations extends Command
{
    protected $signature = 'registrations:expire-unclaimed';

    protected $description = 'Cancel first-Administrator registrations whose emailed staff code expired unused';

    public function handle(): int
    {
        $expired = User::where('awaiting_staff_code', true)
            ->where('is_active', false)
            ->where('staff_code_sent_at', '<', now()->subDays(User::STAFF_CODE_VALID_DAYS))
            ->get();

        $expired->each->expireRegistration();
        $this->info("Expired {$expired->count()} unclaimed registration(s).");

        return self::SUCCESS;
    }
}
