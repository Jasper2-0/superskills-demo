export function render(container, state, handlers) {
  container.innerHTML = '';
  if (!state.editing) return;

  const overlay = document.createElement('div');
  overlay.className = 'modal';

  const content = document.createElement('div');
  content.className = 'modal-content';
  overlay.appendChild(content);

  const heading = document.createElement('h2');
  heading.textContent = state.editing.id ? 'Edit snippet' : 'New snippet';
  content.appendChild(heading);

  const titleLabel = document.createElement('label');
  titleLabel.textContent = 'Title';
  const title = document.createElement('input');
  title.value = state.editing.title || '';
  titleLabel.appendChild(title);
  content.appendChild(titleLabel);

  const langLabel = document.createElement('label');
  langLabel.textContent = 'Language';
  const lang = document.createElement('select');
  for (const l of state.languages) {
    const opt = document.createElement('option');
    opt.value = l;
    opt.textContent = l;
    if (state.editing.language === l) opt.selected = true;
    lang.appendChild(opt);
  }
  langLabel.appendChild(lang);
  content.appendChild(langLabel);

  const tagsLabel = document.createElement('label');
  tagsLabel.textContent = 'Tags (comma-separated)';
  const tags = document.createElement('input');
  tags.value = (state.editing.tags || []).join(', ');
  tagsLabel.appendChild(tags);
  content.appendChild(tagsLabel);

  const bodyLabel = document.createElement('label');
  bodyLabel.textContent = 'Body';
  const body = document.createElement('textarea');
  body.rows = 14;
  body.value = state.editing.body || '';
  bodyLabel.appendChild(body);
  content.appendChild(bodyLabel);

  const actions = document.createElement('div');
  actions.className = 'modal-actions';
  const cancel = document.createElement('button');
  cancel.textContent = 'Cancel';
  cancel.addEventListener('click', () => handlers.onCancel());
  const save = document.createElement('button');
  save.textContent = 'Save';
  save.addEventListener('click', () => handlers.onSave({
    id: state.editing.id,
    title: title.value,
    language: lang.value,
    body: body.value,
    tags: tags.value.split(',').map((t) => t.trim()).filter(Boolean),
  }));
  actions.appendChild(cancel);
  actions.appendChild(save);
  content.appendChild(actions);

  container.appendChild(overlay);
}
