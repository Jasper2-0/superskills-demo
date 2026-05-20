import { test, mock } from 'node:test';
import assert from 'node:assert/strict';

import { debounce } from '../src/debounce.js';

test('calls the function once after the quiet period', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    let calls = 0;
    const fn = debounce(() => calls++, 200);
    fn(); fn(); fn();
    assert.equal(calls, 0);
    mock.timers.tick(199);
    assert.equal(calls, 0);
    mock.timers.tick(1);
    assert.equal(calls, 1);
  } finally {
    mock.timers.reset();
  }
});

test('passes the last call arguments through', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const received = [];
    const fn = debounce((...args) => received.push(args), 100);
    fn(1, 'a');
    fn(2, 'b');
    mock.timers.tick(100);
    assert.deepEqual(received, [[2, 'b']]);
  } finally {
    mock.timers.reset();
  }
});

test('a later call after a flush starts a new debounce window', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    let calls = 0;
    const fn = debounce(() => calls++, 50);
    fn();
    mock.timers.tick(50);
    assert.equal(calls, 1);
    fn();
    mock.timers.tick(50);
    assert.equal(calls, 2);
  } finally {
    mock.timers.reset();
  }
});
