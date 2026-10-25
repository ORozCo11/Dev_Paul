// Mirrors TicketController::verifyTicket()'s self-verification guard: anyone
// who did repair work on a ticket (its mechanic, a mechanic it was moved away
// from, or whoever handled an external-shop hand-off) can't verify it. Lets
// the UI hide Verify instead of letting a dual-role Custodian hit a 403.
export function didRepairWorkOn(ticket, userId) {
  const ids = [
    ticket?.assigned_mechanic_id,
    ...(ticket?.sub_issues ?? []).flatMap((s) => [...(s.prior_mechanic_ids ?? []), s.external_handled_by, s.external_received_by]),
  ];
  return ids.some((id) => id != null && String(id) === String(userId));
}
