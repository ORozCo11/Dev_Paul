<?php

namespace App\Console\Commands;

use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One simple rule for every stage: a ticket that has not changed for 3 days
 * reminds whoever it is waiting on; after 7 days the Admin is told as well.
 *
 *   Pending Approval  -> waiting on the Admin
 *   Active            -> waiting on the mechanic(s) with work still Under Repair
 *   For Verification  -> waiting on the assigned Custodian
 *
 * Work items put On Hold (waiting for parts, etc.) are not counted as stalled.
 * Each reminder goes out at most once per 3 days, and the escalation once per
 * stalled period.
 */
class RemindStalledTickets extends Command
{
    protected $signature = 'tickets:remind-stalled';

    protected $description = 'Remind whoever a ticket is waiting on after 3 days, and tell the Admin after 7';

    public const REMIND_AFTER_DAYS = 3;
    public const ESCALATE_AFTER_DAYS = 7;

    public function handle(): int
    {
        $now = now();
        $reminded = 0;
        $escalated = 0;

        $tickets = MaintenanceTicket::whereIn('status', ['Pending Approval', 'Active', 'For Verification'])
            ->with(['vehicle', 'subIssues'])
            ->get();

        foreach ($tickets as $ticket) {
            $lastChange = collect([$ticket->updated_at])
                ->merge($ticket->subIssues->pluck('updated_at'))
                ->filter()->max();
            if (!$lastChange) {
                continue;
            }
            $idleDays = $lastChange->diffInDays($now);
            if ($idleDays < self::REMIND_AFTER_DAYS) {
                continue;
            }

            $open = $ticket->subIssues->where('status', 'Under Repair');
            if ($ticket->status === 'Active' && $open->isNotEmpty() && $open->every(fn ($i) => $i->on_hold_since)) {
                continue; // everything left is knowingly on hold
            }

            $barangayId = $ticket->vehicle?->barangay_id;
            $waitingOn = $this->waitingOn($ticket, $open, $barangayId);
            $label = "Ticket #{$ticket->display_number} ({$ticket->vehicle?->vehicle_name}) has had no update for {$idleDays} days (status: {$ticket->status}).";

            $due = !$ticket->stall_reminded_at
                || $ticket->stall_reminded_at->lte($lastChange)
                || $ticket->stall_reminded_at->lte($now->copy()->subDays(self::REMIND_AFTER_DAYS));
            if ($due) {
                foreach ($waitingOn as $userId) {
                    $this->notify($userId, 'Ticket Waiting On You', $label . ' Please take the next step.', $ticket);
                }
                $ticket->forceFill(['stall_reminded_at' => $now])->saveQuietly();
                $reminded++;
            }

            if ($idleDays >= self::ESCALATE_AFTER_DAYS
                && (!$ticket->stall_escalated_at || $ticket->stall_escalated_at->lte($lastChange))) {
                $admins = User::where('barangay_id', $barangayId)->havingRole('Admin')->where('is_active', true)->pluck('id');
                foreach ($admins->diff($waitingOn) as $adminId) {
                    $this->notify($adminId, 'Ticket Stalled', $label . ' It may need reassigning.', $ticket);
                }
                $ticket->forceFill(['stall_escalated_at' => $now])->saveQuietly();
                $escalated++;
            }
        }

        $this->info("Reminded on {$reminded} ticket(s); escalated {$escalated}.");

        return self::SUCCESS;
    }

    /** @return array<int> user ids the ticket is waiting on */
    private function waitingOn(MaintenanceTicket $ticket, $openItems, ?int $barangayId): array
    {
        $ids = match ($ticket->status) {
            'For Verification' => collect([$ticket->assigned_custodian_id]),
            'Active' => $openItems->whereNull('on_hold_since')->flatMap(fn ($i) => [$i->assigned_mechanic_id ?: $i->external_tester_id])->unique(),
            default => collect(),
        };
        $ids = $ids->filter()->values();

        if ($ids->isEmpty()) {
            // Pending Approval, or nobody assigned: the Admin has to act.
            $ids = User::where('barangay_id', $barangayId)->havingRole('Admin')->where('is_active', true)->pluck('id');
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function notify(int $userId, string $title, string $message, MaintenanceTicket $ticket): void
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => 'ticket_stalled',
            'ticket_id' => $ticket->ticket_id,
        ]);
    }
}
