// Condition Monitoring displays the two inspection outcomes accepted by its form.
// A linked ticket may temporarily hold the vehicle at Needs Inspection; keep
// the recorded outcome visible until a concrete Good/Needs Repair state exists.
// Historical data remains unchanged, including legacy inconclusive results.
const inspectionOutcomes = ['Good', 'Needs Repair'];

export function currentCondition(row) {
  const liveCondition = row.resulting_ticket && row.vehicle?.condition;
  if (inspectionOutcomes.includes(liveCondition)) return liveCondition;
  return inspectionOutcomes.includes(row.condition_result) ? row.condition_result : 'Not Checked';
}
