// Keep donor labels tied to vehicles, rather than their current array position.
export function reconcileDonorSections(previous, donorIds) {
  const state = previous ?? { order: [], numbers: {}, nextNumber: 1 };
  const ids = [...new Set(donorIds.filter((id) => id != null && id !== '').map(String))];
  const order = [...state.order.filter((id) => ids.includes(id)), ...ids.filter((id) => !state.order.includes(id))];
  let numbers = state.numbers;
  let nextNumber = state.nextNumber;
  for (const id of order) {
    if (!Object.hasOwn(numbers, id)) {
      numbers = { ...numbers, [id]: nextNumber++ };
    }
  }
  if (numbers === state.numbers && order.length === state.order.length && order.every((id, i) => id === state.order[i])) return state;
  return { order, numbers, nextNumber };
}
