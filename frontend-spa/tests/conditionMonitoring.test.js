import test from 'node:test';
import assert from 'node:assert/strict';
import { currentCondition } from '../src/utils/conditionMonitoring.js';

test('linked condition checks display the authoritative vehicle condition throughout the ticket lifecycle', () => {
  for (const [status, condition] of [
    ['Pending Approval', 'Needs Inspection'],
    ['Active', 'Needs Repair'],
    ['For Verification', 'Needs Repair'],
    ['Closed', 'Good'],
    ['Cancelled', 'Good'],
  ]) {
    const row = {
      condition_result: 'Needs Repair',
      resulting_ticket: { ticket_id: 12, status },
      vehicle: { condition },
    };
    const original = structuredClone(row);
    assert.equal(currentCondition(row), condition, status);
    assert.deepEqual(row, original, 'Displaying a current condition must preserve the recorded check.');
  }
});

test('a closed ticket does not imply Good while another repair still blocks the vehicle', () => {
  assert.equal(currentCondition({
    condition_result: 'Needs Inspection',
    resulting_ticket: { ticket_id: 12, status: 'Closed' },
    vehicle: { condition: 'Needs Repair', status: 'Under Maintenance' },
  }), 'Needs Repair');
});

test('unlinked checks preserve their historical result when the vehicle has changed', () => {
  for (const resulting_ticket of [undefined, null]) {
    assert.equal(currentCondition({
      condition_result: 'Needs Repair',
      resulting_ticket,
      vehicle: { condition: 'Good' },
    }), 'Needs Repair');
  }
});

test('a linked check falls back to its recorded result when the live condition is unavailable', () => {
  for (const vehicle of [undefined, null, {}, { condition: '' }]) {
    assert.equal(currentCondition({
      condition_result: 'Needs Inspection',
      resulting_ticket: { ticket_id: 12, status: 'Closed' },
      vehicle,
    }), 'Needs Inspection');
  }
});

test('missing condition values display Not Checked', () => {
  for (const row of [
    {},
    { condition_result: null },
    { condition_result: '' },
    { resulting_ticket: { ticket_id: 12, status: 'Active' }, vehicle: {} },
  ]) {
    assert.equal(currentCondition(row), 'Not Checked');
  }
});
