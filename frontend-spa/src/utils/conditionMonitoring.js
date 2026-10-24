// A linked check follows the vehicle's live condition without rewriting the
// inspection result that was recorded before the ticket was created.
export function currentCondition(row) {
  return (row.resulting_ticket && row.vehicle?.condition) || row.condition_result || 'Not Checked';
}
