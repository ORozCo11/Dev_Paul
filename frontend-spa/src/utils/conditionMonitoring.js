// Condition Monitoring displays the two inspection outcomes accepted by its form.
// A linked ticket may temporarily hold the vehicle at Needs Inspection; keep
// the recorded outcome visible until a concrete Good/Needs Repair state exists.
// Historical data remains unchanged, including legacy inconclusive results.
const inspectionOutcomes = ['Good', 'Needs Repair'];

export function currentCondition(row) {
  // A vehicle that has a ticket raised against it is flagged Needs Inspection
  // (proposal) or Needs Repair (approved) on the vehicle itself — show that
  // right away rather than the older check result, whichever way the ticket
  // was opened. Condition Monitoring only has the two outcomes, so both map
  // to Needs Repair.
  if (['Needs Inspection', 'Needs Repair'].includes(row.vehicle?.condition)) return 'Needs Repair';
  const liveCondition = row.resulting_ticket && row.vehicle?.condition;
  if (inspectionOutcomes.includes(liveCondition)) return liveCondition;
  if (inspectionOutcomes.includes(row.condition_result)) return row.condition_result;
  // A newly registered vehicle starts Good/Available, so with no check on
  // file it reads Good instead of Not Checked.
  return row.vehicle?.condition === 'Good' ? 'Good' : 'Not Checked';
}
