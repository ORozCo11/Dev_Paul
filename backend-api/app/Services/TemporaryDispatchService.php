<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReadinessCheck;
use App\Models\VehicleUsageLog;
use Illuminate\Support\Facades\DB;

class TemporaryDispatchService
{
    public function tickets(Vehicle $vehicle, bool $lock = false)
    {
        $query = MaintenanceTicket::where('vehicle_id', $vehicle->vehicle_id)
            ->whereNotIn('status', ['Closed', 'Cancelled', 'Declined'])->orderBy('ticket_id');
        if ($lock) $query->lockForUpdate();
        $tickets = $query->get();
        foreach ($tickets as $ticket) {
            $lines = $ticket->subIssues()->orderBy('sub_issue_id');
            if ($lock) $lines->lockForUpdate();
            $ticket->setRelation('subIssues', $lines->get());
        }
        return $tickets;
    }

    public function contextHash(MaintenanceTicket $ticket): string
    {
        return hash('sha256', json_encode([
            $ticket->only(['status', 'assigned_custodian_id', 'assigned_mechanic_id', 'ticket_title', 'ticket_description', 'priority']),
            $ticket->subIssues->map(fn ($line) => [$line->getAttributes(), $line->donorParts()->orderBy('part_id')->get()->map(fn ($part) => $part->getAttributes())->all()])->all(),
            $ticket->vehicle->only(['status', 'condition']),
        ], JSON_THROW_ON_ERROR));
    }

    public function requirements(Vehicle $vehicle, $tickets): array
    {
        $problems = [];
        if (in_array($vehicle->status, ['Inactive', 'Decommissioned'], true)) $problems[] = 'Retired vehicles cannot be released.';
        $latest = VehicleReadinessCheck::where('vehicle_id', $vehicle->vehicle_id)->orderByDesc('checked_at')->orderByDesc('readiness_check_id')->first();
        if ($latest && !$latest->all_passed) $problems[] = 'The latest readiness check failed. Record a new passing readiness check before release.';
        foreach ($tickets as $ticket) {
            if ($ticket->subIssues->contains(fn ($line) => $line->external_sent_at && !$line->external_returned_at)) {
                $problems[] = "Ticket #{$ticket->display_number}: the vehicle is still at an external shop.";
            }
            $ids = $ticket->subIssues->pluck('assigned_mechanic_id')->filter()->push($ticket->assigned_mechanic_id)->filter()->unique();
            if ($ticket->subIssues->contains(fn ($line) => $line->repair_type !== 'external' && !$line->assigned_mechanic_id && !$ticket->assigned_mechanic_id && (in_array($ticket->status, ['Active', 'For Verification'], true) || in_array($line->status, ['Under Repair', 'For Inspection'], true)))) {
                $problems[] = "Ticket #{$ticket->display_number}: assign a mechanic to assess the ongoing repair first.";
            }
            foreach ($ids as $id) {
                $assessment = DB::table('ticket_dispatch_assessments')->where('ticket_id', $ticket->ticket_id)->where('assessed_by', $id)->orderByDesc('id')->first();
                $mechanic = User::find($id);
                if (!$mechanic?->is_active || !$mechanic->hasRole('Maintenance Personnel') || !$assessment || !$assessment->safe_to_operate || !$assessment->assembled || !$assessment->no_safety_critical_fault || $assessment->context_hash !== $this->contextHash($ticket) || \Carbon\Carbon::parse($assessment->assessed_at)->lt(now()->subDay())) {
                    $problems[] = "Ticket #{$ticket->display_number}: a current safe-operation assessment is required from ".($mechanic?->name ?? 'the assigned mechanic').'.';
                }
            }
        }
        return $problems;
    }

    public function assessments($tickets): array
    {
        return DB::table('ticket_dispatch_assessments')->join('users', 'users.id', '=', 'ticket_dispatch_assessments.assessed_by')
            ->whereIn('ticket_id', $tickets->pluck('ticket_id'))->orderByDesc('ticket_dispatch_assessments.id')
            ->get(['ticket_dispatch_assessments.*', 'users.name'])->unique(fn ($row) => $row->ticket_id.':'.$row->assessed_by)
            ->filter(function ($row) use ($tickets) {
                $ticket = $tickets->firstWhere('ticket_id', $row->ticket_id);
                return $ticket->subIssues->pluck('assigned_mechanic_id')->push($ticket->assigned_mechanic_id)->contains($row->assessed_by);
            })
            ->map(function ($row) use ($tickets) {
                $row->current = $row->context_hash === $this->contextHash($tickets->firstWhere('ticket_id', $row->ticket_id)) && \Carbon\Carbon::parse($row->assessed_at)->gte(now()->subDay());
                $row->ticket_number = $tickets->firstWhere('ticket_id', $row->ticket_id)->display_number;
                $row->safe_to_operate = (bool) $row->safe_to_operate;
                $row->assembled = (bool) $row->assembled;
                $row->no_safety_critical_fault = (bool) $row->no_safety_critical_fault;
                return $row;
            })->values()->all();
    }

    public function assertRepairIsInStation(MaintenanceTicket $ticket): void
    {
        abort_if(VehicleUsageLog::where('vehicle_id', $ticket->vehicle_id)->whereNull('ended_at')->whereNotNull('temporary_release')->exists(), 422,
            'This vehicle is on a temporary dispatch. Record its return before continuing repair work or verification.');
    }
}
