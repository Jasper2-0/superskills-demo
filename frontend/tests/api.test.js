import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';

import * as api from '../src/api.js';

let calls;
let response;
const originalFetch = globalThis.fetch;

beforeEach(() => {
  calls = [];
  response = { ok: true, status: 200, json: async () => ({}), text: async () => '' };
  globalThis.fetch = async (url, init = {}) => {
    calls.push({ url, init });
    return response;
  };
});

afterEach(() => {
  globalThis.fetch = originalFetch;
});

test('searchSnippets builds repeated tag params', async () => {
  await api.searchSnippets({ q: 'hi', tags: ['php', 'demo'], language: 'php', limit: 25 });
  assert.equal(calls.length, 1);
  const url = new URL(calls[0].url, 'http://localhost');
  assert.equal(url.pathname, '/api/snippets');
  assert.equal(url.searchParams.get('q'), 'hi');
  assert.equal(url.searchParams.get('language'), 'php');
  assert.equal(url.searchParams.get('limit'), '25');
  assert.deepEqual(url.searchParams.getAll('tag'), ['php', 'demo']);
});

test('searchSnippets omits empty optional params', async () => {
  await api.searchSnippets({});
  const url = new URL(calls[0].url, 'http://localhost');
  assert.equal(url.searchParams.has('q'), false);
  assert.equal(url.searchParams.has('language'), false);
  assert.deepEqual(url.searchParams.getAll('tag'), []);
});

test('createSnippet posts JSON body and parses response', async () => {
  response = { ok: true, status: 201, json: async () => ({ id: 7 }), text: async () => '' };
  const result = await api.createSnippet({ title: 'a', language: 'php', body: 'b', tags: ['x'] });
  assert.equal(calls[0].init.method, 'POST');
  assert.equal(calls[0].init.headers['Content-Type'], 'application/json');
  assert.deepEqual(JSON.parse(calls[0].init.body), { title: 'a', language: 'php', body: 'b', tags: ['x'] });
  assert.deepEqual(result, { id: 7 });
});

test('deleteSnippet sends DELETE and resolves on 204', async () => {
  response = { ok: true, status: 204, json: async () => ({}), text: async () => '' };
  await api.deleteSnippet(3);
  assert.equal(calls[0].init.method, 'DELETE');
  const url = new URL(calls[0].url, 'http://localhost');
  assert.equal(url.pathname, '/api/snippets/3');
});

test('non-ok response throws with parsed error message', async () => {
  response = { ok: false, status: 400, json: async () => ({ error: 'title is required' }), text: async () => '' };
  await assert.rejects(
    () => api.createSnippet({ title: '', language: 'php', body: 'b', tags: [] }),
    /title is required/,
  );
});
