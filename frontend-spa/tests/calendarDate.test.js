import test from 'node:test';
import assert from 'node:assert/strict';
import { calendarDate } from '../src/utils/adminDate.js';

test('calendar fields follow the Philippine date on both sides of midnight', () => {
  assert.equal(calendarDate(new Date('2026-10-21T15:59:59Z')), '2026-10-21');
  assert.equal(calendarDate(new Date('2026-10-21T16:00:00Z')), '2026-10-22');
  assert.equal(calendarDate(new Date('2026-12-31T16:00:00Z')), '2027-01-01');
});
