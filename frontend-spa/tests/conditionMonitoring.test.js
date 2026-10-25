import test from 'node:test';
import assert from 'node:assert/strict';
import { currentCondition } from '../src/utils/conditionMonitoring.js';

test('linked checks show confirmed live outcomes and retain the recorded result during pending inspection', () => {
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
    assert.equal(currentCondition(row), condition === 'Needs Inspection' ? 'Needs Repair' : condition, status);
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

test('a linked check falls back to its recorded outcome when the live condition is inconclusive', () => {
  for (const vehicle of [undefined, null, {}, { condition: '' }, { condition: 'Needs Inspection' }]) {
    for (const result of ['Good', 'Needs Repair']) {
      const row = { condition_result: result, resulting_ticket: { ticket_id: 12, status: 'Pending Approval' }, vehicle };
      const original = structuredClone(row);
      assert.equal(currentCondition(row), result);
      assert.deepEqual(row, original);
    }
  }
});

test('legacy inconclusive checks have no confirmed outcome and retain their source data', () => {
  const row = { condition_result: 'Needs Inspection' };
  assert.equal(currentCondition(row), 'Not Checked');
  assert.equal(row.condition_result, 'Needs Inspection');
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
