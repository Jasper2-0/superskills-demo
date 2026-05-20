let loadPromise = null;

function loadPrism() {
  if (loadPromise) return loadPromise;
  loadPromise = new Promise((resolve) => {
    if (typeof document === 'undefined') return resolve(false);
    if (globalThis.Prism) return resolve(true);
    const script = document.createElement('script');
    script.addEventListener('load', () => resolve(!!globalThis.Prism));
    script.addEventListener('error', () => resolve(false));
    script.src = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js';
    document.head.appendChild(script);
  });
  return loadPromise;
}

export async function highlight(codeEl, language) {
  if (typeof document === 'undefined' || !codeEl) return;
  codeEl.className = `language-${language || 'plain'}`;
  const ok = await loadPrism();
  if (!ok) return; // graceful fallback: unstyled <pre>
  try {
    globalThis.Prism.highlightElement(codeEl);
  } catch { /* swallow — unstyled is still readable */ }
}
