async function request(method, url, body) {
  const init = { method, headers: { 'Content-Type': 'application/json' } };
  if (body !== undefined) init.body = JSON.stringify(body);
  const res = await fetch(url, init);
  if (!res.ok) {
    let message = `HTTP ${res.status}`;
    try {
      const data = await res.json();
      if (data && data.error) message = data.error;
    } catch { /* keep generic message */ }
    throw new Error(message);
  }
  if (res.status === 204) return null;
  return res.json();
}

export async function searchSnippets({ q, tags = [], language, limit } = {}) {
  const params = new URLSearchParams();
  if (q) params.set('q', q);
  for (const t of tags) params.append('tag', t);
  if (language) params.set('language', language);
  if (limit) params.set('limit', String(limit));
  const qs = params.toString();
  return request('GET', '/api/snippets' + (qs ? `?${qs}` : ''));
}

export async function getSnippet(id) {
  return request('GET', `/api/snippets/${id}`);
}

export async function createSnippet(payload) {
  return request('POST', '/api/snippets', payload);
}

export async function updateSnippet(id, payload) {
  return request('PUT', `/api/snippets/${id}`, payload);
}

export async function deleteSnippet(id) {
  return request('DELETE', `/api/snippets/${id}`);
}

export async function listTags() {
  return request('GET', '/api/tags');
}

export async function listLanguages() {
  return request('GET', '/api/languages');
}
