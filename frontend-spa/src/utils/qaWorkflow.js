import { calendarDate } from './adminDate.js';

// Offset-free persisted repair timestamps were written in the API's UTC zone.
export function parseTimestamp(value) {
  if (!value) return new Date(NaN);
  const text = String(value);
  if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return new Date(`${text}T12:00:00+08:00`);
  const normalized = text.replace(' ', 'T');
  return new Date(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/.test(normalized) ? `${normalized}Z` : normalized);
}
export function scheduleStage(row) {
  return row.status === 'Completed' && row.resulting_maintenance && row.resulting_maintenance.progress_status !== 'Completed' ? 'AwaitingVerification' : row.status;
}
export function scheduleIsOverdue(row, today = calendarDate()) {
  return row.status === 'Scheduled' && !row.resulting_ticket_id && !!row.scheduled_date && row.scheduled_date.slice(0, 10) < today;
}
export function relatedRecordLabel(row) {
  const labels = { vehicles: 'Vehicle', maintenance_tickets: 'Ticket', ticket_sub_issues: 'Work item', vehicle_issue_reports: 'Issue', vehicle_condition_checks: 'Condition check', vehicle_readiness_checks: 'Readiness check', vehicle_maintenance_records: 'Maintenance record', vehicle_maintenance_schedules: 'Schedule', vehicle_documents: 'Document' };
  return row.related_record_id == null ? '-' : `${labels[row.related_table] ?? row.related_table ?? 'Record'} #${row.related_record_id}`;
}
