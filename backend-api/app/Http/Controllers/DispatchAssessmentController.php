<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Models\ActivityLog;
use App\Models\MaintenanceTicket;
use App\Models\Notification;
use App\Services\TemporaryDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchAssessmentController extends Controller
{
    use AuthorizesAbilities;

    public function store(Request $request, MaintenanceTicket $ticket, TemporaryDispatchService $service)
    {
        $this->requireAbility($request, 'usage.assess_release');
        $data = $request->validate([
            'safe_to_operate' => ['required', 'boolean'],
            'assembled' => ['required', 'boolean'],
            'no_safety_critical_fault' => ['required', 'boolean'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        return DB::transaction(function () use ($request, $ticket, $service, $data) {
            $ticket = MaintenanceTicket::whereKey($ticket->ticket_id)->lockForUpdate()->firstOrFail();
            $ticket->setRelation('subIssues', $ticket->subIssues()->orderBy('sub_issue_id')->get());
            abort_unless(in_array($ticket->status, ['Active', 'For Verification'], true), 422, 'Only ongoing approved repairs can be assessed.');
            abort_unless((int) $ticket->assigned_mechanic_id === $request->user()->id || $ticket->subIssues->contains(fn ($line) => (int) $line->assigned_mechanic_id === $request->user()->id), 403, 'Only an assigned mechanic may assess this repair.');
            $service->assertRepairIsInStation($ticket);
            $id = DB::table('ticket_dispatch_assessments')->insertGetId($data + [
                'ticket_id' => $ticket->ticket_id, 'assessed_by' => $request->user()->id,
                'context_hash' => $service->contextHash($ticket), 'assessed_at' => now(),
            ]);
            ActivityLog::create(['user_id' => $request->user()->id, 'role' => 'Maintenance Personnel', 'action' => 'Dispatch Assessment', 'module' => 'Maintenance Tickets', 'affected_record_id' => (string) $ticket->ticket_id, 'details' => 'Temporary-dispatch assessment: '.$data['notes']]);
            if ($ticket->assigned_custodian_id) Notification::create([
                'user_id' => $ticket->assigned_custodian_id, 'title' => 'Dispatch Assessment Recorded',
                'message' => "{$request->user()->name} assessed Ticket #{$ticket->display_number}. Review the assessment before temporary dispatch.",
                'type' => 'dispatch_assessment', 'ticket_id' => $ticket->ticket_id, 'vehicle_id' => $ticket->vehicle_id,
            ]);
            return response()->json(DB::table('ticket_dispatch_assessments')->find($id), 201);
        });
    }
}
