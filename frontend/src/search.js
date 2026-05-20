export function render(container, state, handlers) {
  container.innerHTML = '';

  const bar = document.createElement('div');
  bar.className = 'search-bar';

  const input = document.createElement('input');
  input.type = 'search';
  input.placeholder = 'Search snippets…';
  input.value = state.query;
  input.addEventListener('input', () => handlers.onQuery(input.value));
  bar.appendChild(input);

  const langSelect = document.createElement('select');
  const noneOpt = document.createElement('option');
  noneOpt.value = '';
  noneOpt.textContent = 'All languages';
  langSelect.appendChild(noneOpt);
  for (const lang of state.languages) {
    const opt = document.createElement('option');
    opt.value = lang;
    opt.textContent = lang;
    if (state.language === lang) opt.selected = true;
    langSelect.appendChild(opt);
  }
  langSelect.addEventListener('change', () => handlers.onLanguage(langSelect.value || null));
  bar.appendChild(langSelect);

  const newBtn = document.createElement('button');
  newBtn.textContent = 'New snippet';
  newBtn.addEventListener('click', () => handlers.onNew());
  bar.appendChild(newBtn);

  container.appendChild(bar);

  // Tag chips
  if (state.tags.length) {
    const chips = document.createElement('div');
    chips.className = 'tags';
    for (const { name, count } of state.tags) {
      const chip = document.createElement('button');
      chip.className = 'tag' + (state.selectedTags.has(name) ? ' tag-active' : '');
      chip.textContent = `${name} (${count})`;
      chip.addEventListener('click', () => handlers.onToggleTag(name));
      chips.appendChild(chip);
    }
    container.appendChild(chips);
  }

  // Preserve focus on the input across re-renders
  if (document.activeElement && document.activeElement.tagName === 'INPUT') {
    input.focus();
    input.setSelectionRange(input.value.length, input.value.length);
  }
}
