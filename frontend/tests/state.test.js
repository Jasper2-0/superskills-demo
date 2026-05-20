import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

let store;
beforeEach(async () => {
  // Re-import a fresh module each test (cache-bust via query string)
  store = await import('../src/state.js?bust=' + Math.random());
});

test('get returns the initial state', () => {
  const s = store.get();
  assert.equal(s.query, '');
  assert.ok(s.selectedTags instanceof Set);
  assert.deepEqual(s.results, []);
  assert.equal(s.editing, null);
});

test('update merges a patch and notifies subscribers', () => {
  let received = null;
  store.subscribe((s) => { received = { ...s }; });
  store.update({ query: 'hi' });
  assert.equal(received.query, 'hi');
  assert.equal(store.get().query, 'hi');
});

test('subscribe returns an unsubscribe function', () => {
  let calls = 0;
  const unsub = store.subscribe(() => calls++);
  store.update({ query: 'a' });
  unsub();
  store.update({ query: 'b' });
  assert.equal(calls, 1);
});

test('multiple subscribers are all notified', () => {
  let a = 0, b = 0;
  store.subscribe(() => a++);
  store.subscribe(() => b++);
  store.update({ query: 'x' });
  assert.equal(a, 1);
  assert.equal(b, 1);
});
