import { highlight } from './highlight.js';

export function render(container, state, handlers) {
  container.innerHTML = '';
  if (state.results.length === 0) {
    const empty = document.createElement('p');
    empty.textContent = state.query || state.selectedTags.size || state.language
      ? 'No snippets match your filters.'
      : 'No snippets yet. Click "New snippet" to add one.';
    container.appendChild(empty);
    return;
  }
  for (const snippet of state.results) {
    container.appendChild(renderCard(snippet, handlers));
  }
}

function renderCard(snippet, handlers) {
  const card = document.createElement('article');
  card.className = 'snippet';

  const title = document.createElement('h2');
  title.textContent = snippet.title;
  card.appendChild(title);

  const meta = document.createElement('p');
  meta.textContent = `${snippet.language} · updated ${formatDate(snippet.updated_at)}`;
  card.appendChild(meta);

  const pre = document.createElement('pre');
  const code = document.createElement('code');
  code.textContent = snippet.body;
  pre.appendChild(code);
  card.appendChild(pre);
  highlight(code, snippet.language);

  if (snippet.tags && snippet.tags.length) {
    const tags = document.createElement('div');
    tags.className = 'tags';
    for (const t of snippet.tags) {
      const span = document.createElement('span');
      span.className = 'tag';
      span.textContent = t;
      tags.appendChild(span);
    }
    card.appendChild(tags);
  }

  const actions = document.createElement('div');
  actions.className = 'snippet-actions';
  const edit = document.createElement('button');
  edit.textContent = 'Edit';
  edit.addEventListener('click', () => handlers.onEdit(snippet));
  const del = document.createElement('button');
  del.textContent = 'Delete';
  del.addEventListener('click', () => handlers.onDelete(snippet));
  actions.appendChild(edit);
  actions.appendChild(del);
  card.appendChild(actions);

  return card;
}

function formatDate(iso) {
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}
