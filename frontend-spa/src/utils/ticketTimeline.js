import { ticketNumber } from './recordNumber.js';

// Prefer the immutable audit trail: mutable status and updated_at cannot tell
// when an earlier approval, reassignment, rejection, or repair actually happened.
export function ticketTimeline(ticket) {
  const events = (ticket.activity ?? []).map((entry) => ({
    id: `activity-${entry.log_id}`,
    label: entry.action,
    at: entry.created_at,
    by: entry.user,
    // Historical audit copy used the internal ID. Translate only this ticket's
    // reference for display, preserving the stored audit and its route identity.
    details: ticket.ticket_id == null ? entry.details : entry.details?.replace(new RegExp(`Ticket #${ticket.ticket_id}(?!\\d)`, 'gi'), `Ticket #${ticketNumber(ticket)}`),
    icon: /closed|verified/i.test(entry.action) ? 'checkCircle' : 'clipboard',
  }));
  const add = (id, label, at, by, icon = 'clipboard', dateOnly = false) => {
    if (at) events.push({ id, label, at, by, icon, dateOnly });
  };
  if (!events.some((event) => /^(create|propose|auto-propose) ticket$/i.test(event.label))) {
    add('created', 'Created', ticket.created_at, ticket.created_by);
  }
  // Older/seeded records may predate audit logging. Only show dates actually
  // recorded on those records; never infer an event date from current status.
  if (!(ticket.activity ?? []).length) {
    add('custodian', 'Assigned to Custodian', ticket.assigned_at, ticket.assigned_custodian);
    add('inspection', ticket.inspection_notes?.startsWith('Pre-diagnosed') ? 'Pre-diagnosed' : 'Inspected', ticket.inspected_at, ticket.inspected_by, 'search');
    for (const line of ticket.sub_issues ?? []) {
      const prefix = `line-${line.sub_issue_id}`;
      add(`${prefix}-assigned`, 'Mechanic Assigned', line.mechanic_assigned_at, line.mechanic_assigned_by);
      add(`${prefix}-started`, 'Repair Started', line.repair_started_at, line.assigned_mechanic, 'wrench', true);
      add(`${prefix}-completed`, 'Repair Completed', line.repair_completed_at, line.assigned_mechanic, 'wrench', true);
      add(`${prefix}-verified`, 'Repair Verified', line.verified_at, line.verified_by, 'checkCircle');
      add(`${prefix}-confirmed`, 'Repair Confirmed', line.confirmed_at, line.confirmed_by, 'checkCircle');
      add(`${prefix}-reopened`, 'Repair Reopened', line.reopened_at, line.reopened_by);
      add(`${prefix}-deferred`, 'Repair Deferred', line.deferred_at, line.deferred_by);
    }
    add('closed', 'Closed', ticket.closed_at, ticket.closed_by, 'checkCircle');
  }
  return events.filter((event) => event.at && Number.isFinite(Date.parse(event.at)))
    .sort((a, b) => Date.parse(a.at) - Date.parse(b.at));
}
