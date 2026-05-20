import * as api from './api.js';
import * as store from './state.js';
import * as searchView from './search.js';
import * as resultsView from './results.js';
import * as editorView from './editor.js';
import { debounce } from './debounce.js';

const searchEl = document.getElementById('search-mount');
const resultsEl = document.getElementById('results-mount');
const editorEl = document.getElementById('editor-mount');

const handlers = {
  onQuery(q)        { store.update({ query: q }); refreshDebounced(); },
  onLanguage(l)     { store.update({ language: l }); refreshNow(); },
  onToggleTag(name) {
    const s = store.get();
    if (s.selectedTags.has(name)) s.selectedTags.delete(name);
    else s.selectedTags.add(name);
    store.update({}); // notify
    refreshNow();
  },
  onNew()  { store.update({ editing: { title: '', language: 'php', body: '', tags: [] } }); },
  onEdit(snippet) { store.update({ editing: { ...snippet } }); },
  async onDelete(snippet) {
    if (!confirm(`Delete "${snippet.title}"?`)) return;
    await api.deleteSnippet(snippet.id);
    await refreshNow();
  },
  onCancel() { store.update({ editing: null }); },
  async onSave(payload) {
    try {
      if (payload.id) {
        await api.updateSnippet(payload.id, payload);
      } else {
        await api.createSnippet(payload);
      }
      store.update({ editing: null });
      await refreshNow();
    } catch (e) {
      alert(e.message);
    }
  },
};

async function refreshNow() {
  const s = store.get();
  const [results, tagsResp, langsResp] = await Promise.all([
    api.searchSnippets({
      q: s.query,
      tags: [...s.selectedTags],
      language: s.language,
    }),
    api.listTags(),
    s.languages.length ? Promise.resolve({ languages: s.languages }) : api.listLanguages(),
  ]);
  store.update({
    results: results.results,
    tags: tagsResp.tags,
    languages: langsResp.languages,
  });
}

const refreshDebounced = debounce(refreshNow, 200);

store.subscribe((s) => {
  searchView.render(searchEl, s, handlers);
  resultsView.render(resultsEl, s, handlers);
  editorView.render(editorEl, s, handlers);
});

// Initial load
refreshNow().catch((e) => { resultsEl.textContent = 'Failed to load: ' + e.message; });
