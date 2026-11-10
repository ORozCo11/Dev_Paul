// Mirrors TicketController::verifyTicket()'s self-verification guard: anyone
// who did repair work on a ticket (its mechanic, or a mechanic it was moved
// away from) can't verify it. The Custodian who handled the external-shop
// hand-off may verify it: they receive the vehicle back, test it, then close.
export function didRepairWorkOn(ticket, userId) {
  const ids = [
    ticket?.assigned_mechanic_id,
    ...(ticket?.sub_issues ?? []).flatMap((s) => s.prior_mechanic_ids ?? []),
  ];
  return ids.some((id) => id != null && String(id) === String(userId));
}
