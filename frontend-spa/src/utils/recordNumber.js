// Database IDs remain opaque route keys. Numbers shown to users are allocated
// separately for each barangay and module, and never depend on table sorting.
export const recordNumber = (record, primaryKey = 'id') => record?.barangay_number ?? record?.[primaryKey] ?? '—';
export const ticketNumber = (ticket) => ticket?.ticket_number ?? recordNumber(ticket, 'ticket_id');

// Ticket title without the sub-issue summary (the API's `display_title`);
// falls back to the stored title for payloads that don't carry it.
export const ticketTitle = (ticket) => ticket?.display_title ?? ticket?.ticket_title;
