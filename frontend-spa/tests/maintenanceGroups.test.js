import test from 'node:test';
import assert from 'node:assert/strict';
import { groupMaintenanceByVehicle, maintenanceSourceBucket, completedMaintenanceRecords, maintenanceSourceLabel } from '../src/utils/maintenanceGroups.js';

test('two repaired sub-issues share one vehicle row without losing either repair', () => {
  const repairs = [
    { maintenance_id: 12, vehicle_id: 4, maintenance_type: 'Brakes', progress_status: 'Completed' },
    { maintenance_id: 11, vehicle_id: 4, maintenance_type: 'Engine', progress_status: 'Completed' },
    { maintenance_id: 10, vehicle_id: 5, maintenance_type: 'Tires', progress_status: 'Completed' },
  ];
  const groups = groupMaintenanceByVehicle(repairs);
  assert.equal(groups.length, 2);
  assert.equal(groups[0].maintenance_id, 12);
  assert.deepEqual(groups[0].repair_records, repairs.slice(0, 2));
  assert.equal(groups[1].vehicle_id, 5);
  assert.equal(repairs[0].repair_records, undefined);
});

test('grouping filtered repairs retains matches from any sub-issue', () => {
  const records = [
    { maintenance_id: 3, vehicle_id: 1, maintenance_type: 'Engine' },
    { maintenance_id: 2, vehicle_id: 1, maintenance_type: 'Brakes' },
    { maintenance_id: 1, vehicle_id: 2, maintenance_type: 'Engine' },
  ];
  const groups = groupMaintenanceByVehicle(records.filter((r) => r.maintenance_type === 'Brakes'));
  assert.equal(groups.length, 1);
  assert.equal(groups[0].vehicle_id, 1);
  assert.equal(groups[0].repair_records[0].maintenance_id, 2);
});

test('normalizes vehicle identities and never merges records with unknown vehicles', () => {
  const groups = groupMaintenanceByVehicle([
    { maintenance_id: 4, vehicle_id: '1' },
    { maintenance_id: 3, vehicle: { vehicle_id: 1 } },
    { maintenance_id: 2 },
    { maintenance_id: 1 },
  ]);
  assert.deepEqual(groups.map((g) => g.repair_records.length), [2, 1, 1]);
  assert.deepEqual(groupMaintenanceByVehicle(), []);
});

test('maintenance source totals are exclusive and retain separate records for the same vehicle', () => {
  const records = [
    { maintenance_id: 1, vehicle_id: 1, source: 'Field Repair' },
    { maintenance_id: 2, vehicle_id: 1, source: 'From Issue Report' },
    { maintenance_id: 3, vehicle_id: 2, is_external: true, source: 'External Shop' },
    { maintenance_id: 4, vehicle_id: 3, is_external: true, source: 'Scheduled Maintenance' },
    { maintenance_id: 5, vehicle_id: 3, originating_schedule: { schedule_id: 7 } },
  ];
  assert.deepEqual(records.map(maintenanceSourceBucket), ['In-House Repair', 'In-House Repair', 'External Shop', 'Scheduled Maintenance', 'Scheduled Maintenance']);
  assert.equal(groupMaintenanceByVehicle(records).length, 3);
  assert.equal(records.filter((record) => maintenanceSourceBucket(record) === 'In-House Repair').length, 2);
});


test('completed history excludes ongoing, submitted and reopened work even when a finish date is recorded', () => {
  const records = ['Assigned', 'Under Repair', 'On Hold - Awaiting Parts', 'For Verification', 'Completed'].map((progress_status, i) => ({ maintenance_id: i + 1, progress_status, date_completed: '2026-10-25' }));
  assert.deepEqual(completedMaintenanceRecords(records), [records[4]]);
  assert.deepEqual(completedMaintenanceRecords(), []);
});

test('completed repairs on the same vehicle remain separate with exclusive source labels', () => {
  const records = [
    { maintenance_id: 1, vehicle_id: 9, progress_status: 'Completed', source: 'From Issue Report' },
    { maintenance_id: 2, vehicle_id: 9, progress_status: 'Completed', is_external: true, source: 'External Shop' },
    { maintenance_id: 3, vehicle_id: 9, progress_status: 'Completed', is_external: true, originating_schedule: { schedule_id: 1 } },
    { maintenance_id: 4, vehicle_id: 9, progress_status: 'Under Repair', source: 'External Shop' },
  ];
  const completed = completedMaintenanceRecords(records);
  assert.equal(completed.length, 3);
  assert.deepEqual(completed.map(maintenanceSourceLabel), ['In-House Repair', 'Sent to External Shop', 'Maintenance Schedule']);
  assert.deepEqual(records.map((record) => record.maintenance_id), [1, 2, 3, 4]);
});
