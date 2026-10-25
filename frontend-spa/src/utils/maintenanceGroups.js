// Keep the repair records intact; only the ledger's presentation is grouped.
export function groupMaintenanceByVehicle(records = []) {
  const groups = new Map();
  for (const record of records) {
    const vehicleId = record.vehicle_id ?? record.vehicle?.vehicle_id;
    const key = vehicleId == null ? `record:${record.maintenance_id}` : `vehicle:${vehicleId}`;
    if (!groups.has(key)) groups.set(key, { ...record, repair_records: [] });
    groups.get(key).repair_records.push(record);
  }
  return [...groups.values()];
}

// A scheduled job takes precedence over its repair method so totals do not overlap.
export function maintenanceSourceBucket(record) {
  if (record.source === 'Scheduled Maintenance' || record.originating_schedule) return 'Scheduled Maintenance';
  if (record.is_external || record.source === 'External Shop') return 'External Shop';
  return 'In-House Repair';
}

// Completion is a workflow state, not merely a mechanic's recorded finish date.
export function completedMaintenanceRecords(records = []) {
  return records.filter((record) => record.progress_status === 'Completed');
}

export function maintenanceSourceLabel(record) {
  return {
    'In-House Repair': 'In-House Repair',
    'External Shop': 'Sent to External Shop',
    'Scheduled Maintenance': 'Maintenance Schedule',
  }[maintenanceSourceBucket(record)];
}
