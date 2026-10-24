import test from 'node:test';
import assert from 'node:assert/strict';
import { ticketTimeline } from './ticketTimeline.js';

test('audit events retain repeated actions and actors in chronological order', () => {
  const events = ticketTimeline({ activity: [
    { log_id: 3, action: 'Work Order Reassigned', created_at: '2026-10-22T02:00:00Z', user: { name: 'Admin B' } },
    { log_id: 1, action: 'Propose Ticket', created_at: '2026-10-21T23:00:00Z', user: { name: 'Custodian' } },
    { log_id: 2, action: 'Work Order Reassigned', created_at: '2026-10-22T01:00:00Z', user: { name: 'Admin A' } },
  ] });
  assert.deepEqual(events.map((event) => event.id), ['activity-1', 'activity-2', 'activity-3']);
  assert.equal(events[2].by.name, 'Admin B');
});

test('legacy records use recorded repair and verification dates, not updated_at', () => {
  const events = ticketTimeline({ created_at: '2026-10-20T00:00:00Z', updated_at: '2026-10-25T00:00:00Z', status: 'Closed', sub_issues: [
    { sub_issue_id: 5, repair_completed_at: '2026-10-21T00:00:00Z', verified_at: '2026-10-22T00:00:00Z' },
  ] });
  assert.deepEqual(events.map((event) => event.label), ['Created', 'Repair Completed', 'Repair Verified']);
});

test('pre-diagnosis is identified by the recorded workflow, even for dual-role users', () => {
  const events = ticketTimeline({ inspected_at: '2026-10-22T00:00:00Z', inspected_by: { id: 1 }, assigned_custodian_id: 1, inspection_notes: 'Pre-diagnosed at creation — inspection skipped.' });
  assert.equal(events[0].label, 'Pre-diagnosed');
});

test('invalid dates are excluded and equal timestamps preserve audit order', () => {
  const events = ticketTimeline({ activity: [
    { log_id: 1, action: 'Create Ticket', created_at: 'invalid' },
    { log_id: 2, action: 'Repairs Logged', created_at: '2026-10-22T00:00:00Z' },
    { log_id: 3, action: 'Ticket Verified & Closed', created_at: '2026-10-22T00:00:00Z' },
  ] });
  assert.deepEqual(events.map((event) => event.id), ['activity-2', 'activity-3']);
});

test('historical references display the local ticket number without changing other tickets', () => {
  const [event] = ticketTimeline({ ticket_id: 15, barangay_number: 2, activity: [
    { log_id: 1, action: 'Create Ticket', created_at: '2026-10-22T00:00:00Z', details: 'Ticket #15 created; see Ticket #150.' },
  ] });
  assert.equal(event.details, 'Ticket #2 created; see Ticket #150.');
});

test('reassignment timeline preserves work item, both mechanics, reason and accountable actor', () => {
  const details = 'Ticket #1: Pump seal reassigned from Mechanic A to Mechanic B. Reason: Original mechanic is on leave.';
  const [event] = ticketTimeline({ ticket_id: 1, activity: [{ log_id: 9, action: 'Ticket Mechanic Reassigned', details, created_at: '2026-10-25T03:00:00Z', user: { name: 'Admin' } }] });
  assert.equal(event.details, details);
  assert.equal(event.by.name, 'Admin');
  assert.equal(event.label, 'Ticket Mechanic Reassigned');
});
