import test from 'node:test';
import assert from 'node:assert/strict';
import { parseTimestamp, scheduleStage, scheduleIsOverdue, relatedRecordLabel } from '../src/utils/qaWorkflow.js';

test('legacy UTC repair logs and ISO timeline refer to the same instant', () => {
  assert.equal(parseTimestamp('2026-10-24 08:30').getTime(), parseTimestamp('2026-10-24T08:30:00Z').getTime());
  assert.equal(parseTimestamp('2026-10-24T16:30:00+08:00').getTime(), parseTimestamp('2026-10-24T08:30:00Z').getTime());
});
test('calendar dates retain their Philippine day independently of browser timezone', () => {
  assert.equal(parseTimestamp('2026-10-24').toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' }), '2026-10-24');
});
test('a finished schedule waiting for verification occupies one distinct bucket', () => {
  const stages = [{ status: 'Scheduled' }, { status: 'Completed', resulting_maintenance: { progress_status: 'For Verification' } }, { status: 'Completed', resulting_maintenance: { progress_status: 'Completed' } }].map(scheduleStage);
  assert.deepEqual(stages, ['Scheduled', 'AwaitingVerification', 'Completed']);
});
test('converted tickets are not overdue standalone schedules and today is not overdue', () => {
  assert.equal(scheduleIsOverdue({ status: 'Scheduled', scheduled_date: '2026-10-23' }, '2026-10-24'), true);
  assert.equal(scheduleIsOverdue({ status: 'Scheduled', scheduled_date: '2026-10-23', resulting_ticket_id: 10 }, '2026-10-24'), false);
  assert.equal(scheduleIsOverdue({ status: 'Scheduled', scheduled_date: '2026-10-24' }, '2026-10-24'), false);
});
test('equal numeric identifiers are explicitly distinguished by record type', () => {
  assert.equal(relatedRecordLabel({ related_table: 'maintenance_tickets', related_record_id: 17 }), 'Ticket #17');
  assert.equal(relatedRecordLabel({ related_table: 'vehicle_issue_reports', related_record_id: 17 }), 'Issue #17');
});
