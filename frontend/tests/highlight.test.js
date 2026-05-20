import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';

let loader;
let originalDocument;

beforeEach(() => {
  originalDocument = globalThis.document;
});
afterEach(() => {
  globalThis.document = originalDocument;
});

test('highlight is a no-op if document is undefined', async () => {
  delete globalThis.document;
  const { highlight } = await import('../src/highlight.js?bust=' + Math.random());
  await highlight({ textContent: 'x' }, 'php'); // must not throw
});

test('a failing CDN load resolves without throwing (graceful fallback)', async () => {
  // Stub document.createElement so the loader's <script> never "loads".
  const scripts = [];
  globalThis.document = {
    createElement: (tag) => {
      const el = { tag, addEventListener(name, cb) { this[name] = cb; }, set src(v) { this._src = v; queueMicrotask(() => this.error && this.error(new Event('error'))); } };
      scripts.push(el);
      return el;
    },
    head: { appendChild() {} },
  };

  const { highlight } = await import('../src/highlight.js?bust=' + Math.random());
  const codeEl = { textContent: '<?php echo 1; ?>', className: '' };
  await highlight(codeEl, 'php'); // expected: returns without throwing; codeEl unmodified beyond class
  assert.equal(codeEl.textContent, '<?php echo 1; ?>');
  assert.match(codeEl.className, /language-php/);
});
