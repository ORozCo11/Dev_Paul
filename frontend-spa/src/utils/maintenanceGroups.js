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
