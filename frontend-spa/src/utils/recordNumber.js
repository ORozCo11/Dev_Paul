// Database IDs remain opaque route keys. Numbers shown to users are allocated
// separately for each barangay and module, and never depend on table sorting.
export const recordNumber = (record, primaryKey = 'id') => record?.barangay_number ?? record?.[primaryKey] ?? '—';
export const ticketNumber = (ticket) => ticket?.ticket_number ?? recordNumber(ticket, 'ticket_id');
