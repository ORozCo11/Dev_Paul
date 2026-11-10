import test from 'node:test';
import assert from 'node:assert/strict';
import { reconcileDonorSections } from './donorSections.js';

test('saving a newly added donor does not reorder or renumber earlier donors', () => {
  let state = reconcileDonorSections(null, ['10', '20']);
  state = reconcileDonorSections(state, ['20', '10']);
  assert.deepEqual(state.order, ['10', '20']);
  assert.deepEqual(state.numbers, { 10: 1, 20: 2 });
});

test('removing the first donor and adding another never reuses donor number one', () => {
  let state = reconcileDonorSections(null, ['10', '20']);
  state = reconcileDonorSections(state, ['20']);
  state = reconcileDonorSections(state, ['20', '30']);
  assert.deepEqual(state.order, ['20', '30']);
  assert.equal(state.numbers['20'], 2);
  assert.equal(state.numbers['30'], 3);
});

test('removing the only donor keeps the next label available after restoring saved state', () => {
  let state = reconcileDonorSections(null, [10]);
  state = reconcileDonorSections(state, []);
  state = reconcileDonorSections(JSON.parse(JSON.stringify(state)), [20, '20']);
  assert.deepEqual(state.order, ['20']);
  assert.equal(state.numbers['20'], 2);
  assert.equal(reconcileDonorSections(state, [20]), state);
});
