<?php

namespace App\Console\Commands;

use App\Models\TicketSubIssue;
use App\Models\MaintenanceTicket;
use Illuminate\Console\Command;

class BackfillVerificationAssignedTo extends Command
{
    protected $signature = 'backfill:verification-assigned-to';
    protected $description = 'Backfill verification_assigned_to for existing sub-issues';

    public function handle()
    {
        \DB::statement('PRAGMA foreign_keys=OFF');

        $subIssues = TicketSubIssue::whereIn('status', ['For Inspection', 'For Confirmation', 'Done'])
            ->whereNull('verification_assigned_to')
            ->get();

        $count = 0;
        foreach ($subIssues as $subIssue) {
            $ticket = MaintenanceTicket::find($subIssue->ticket_id);
            if ($ticket && $ticket->assigned_custodian_id) {
                $subIssue->update(['verification_assigned_to' => $ticket->assigned_custodian_id]);
                $count++;
            }
        }

        \DB::statement('PRAGMA foreign_keys=ON');

        $this->info("Backfilled $count sub-issues with verification_assigned_to.");
    }
}
