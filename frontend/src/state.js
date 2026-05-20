const state = {
  query: '',
  selectedTags: new Set(),
  language: null,
  results: [],
  tags: [],
  languages: [],
  editing: null,
};

const listeners = new Set();

export function get() {
  return state;
}

export function update(patch) {
  Object.assign(state, patch);
  for (const fn of listeners) fn(state);
}

export function subscribe(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}
