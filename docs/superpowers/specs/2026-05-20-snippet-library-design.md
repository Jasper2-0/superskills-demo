# Snippet Library — Design Spec

**Date:** 2026-05-20
**Status:** Approved (brainstorming phase)
**Purpose:** Demo project for an internal team workshop on the `superpowers` plugin. Designed to showcase the full lifecycle: brainstorming → writing-plans → executing-plans, with rich enough material for `test-driven-development` to do real work along the way.

---

## 1. Goals & non-goals

### Goals
- A working, locally-runnable code-snippet library with tags, language metadata, and ranked search.
- A clean two-component split (PHP API + JS SPA) that mirrors how implementation plans naturally slice work.
- At least one piece of non-trivial backend logic (the search ranker) that is a strong TDD target.
- Zero-ceremony dev experience: one command starts the whole app; one command runs all tests.

### Non-goals
- Multi-user features, authentication, sharing, or hosting.
- Production deployment, Docker, CI configuration (mentioned but not built).
- Build tooling: no webpack/vite/babel on the frontend; no migration framework on the backend.
- Pagination beyond a simple `limit` cap.

---

## 2. High-level architecture

```
snippet-library/
├── backend/
│   ├── composer.json    # PHPUnit dev dep, App\ → src/ autoload
│   ├── public/          # PHP front controller (index.php) + doc root
│   ├── src/             # App\ namespace, PSR-4 via Composer
│   ├── tests/
│   │   ├── Unit/        # Pure PHP, no IO
│   │   └── Integration/ # Boots php -S, exercises real HTTP
│   └── data/            # SQLite file lives here (gitignored)
├── frontend/
│   ├── index.html
│   ├── src/             # ES modules, no build step
│   └── tests/           # node --test
├── docs/superpowers/specs/
│   └── 2026-05-20-snippet-library-design.md   # this file
├── Makefile             # dev, test, clean
└── README.md
```

**Runtime model.** A single `php -S localhost:8080 -t backend/public backend/public/index.php` process serves both the JSON API (`/api/*`) and the static frontend (everything else). The front controller dispatches `/api/*` to PHP handlers and returns `false` for any other path, letting PHP's built-in static file server handle it. No Apache, no nginx, no CORS.

**Boundary.** Frontend and backend communicate exclusively through JSON over HTTP at `/api/*`. The two slices can be developed, tested, and reviewed independently.

---

## 3. Data model

```sql
CREATE TABLE snippets (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  title       TEXT    NOT NULL,
  language    TEXT    NOT NULL,
  body        TEXT    NOT NULL,
  created_at  TEXT    NOT NULL,   -- ISO 8601 UTC
  updated_at  TEXT    NOT NULL
);

CREATE TABLE tags (
  id   INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT    NOT NULL UNIQUE COLLATE NOCASE
);

CREATE TABLE snippet_tags (
  snippet_id INTEGER NOT NULL REFERENCES snippets(id) ON DELETE CASCADE,
  tag_id     INTEGER NOT NULL REFERENCES tags(id)     ON DELETE CASCADE,
  PRIMARY KEY (snippet_id, tag_id)
);

CREATE INDEX idx_snippet_tags_tag_id ON snippet_tags(tag_id);
```

**Notes:**
- Tag names are normalized to lowercased + trimmed before insert. The `UNIQUE … COLLATE NOCASE` is belt-and-braces against accidental case-mismatched duplicates.
- Languages are stored as free-text but validated against a fixed list at write time: `php`, `javascript`, `python`, `sql`, `bash`, `html`, `css`, `json`, `markdown`, `other`.
- Schema bootstrap via `Database::migrate()` runs `CREATE TABLE IF NOT EXISTS …` on startup. No migration framework.
- **Orphan tags** are left in place when their last linked snippet is deleted. `GET /api/tags` expresses "tags currently in use" via a join, so orphans are invisible to the UI but cheap and don't require trigger/cron cleanup.

---

## 4. API surface

All endpoints are JSON in/out, rooted at `/api`. Errors return `{"error": "message"}` with the appropriate HTTP status.

| Method | Path                  | Purpose                                   |
|--------|-----------------------|-------------------------------------------|
| GET    | `/api/snippets`       | List / search snippets                    |
| GET    | `/api/snippets/{id}`  | Get one snippet                           |
| POST   | `/api/snippets`       | Create a snippet                          |
| PUT    | `/api/snippets/{id}`  | Update (full replace)                     |
| DELETE | `/api/snippets/{id}`  | Delete a snippet                          |
| GET    | `/api/tags`           | List tags currently in use, with counts   |
| GET    | `/api/languages`      | Static list of supported language ids     |

### Search endpoint

`GET /api/snippets?q=<query>&tag=<tag>&tag=<tag>&language=<lang>&limit=50`

| Param      | Behavior                                                                      |
|------------|-------------------------------------------------------------------------------|
| `q`        | Free-text query. Tokenized, ranked (see Section 5). Omitted → no text filter. |
| `tag`      | Repeatable. Multiple tags = AND.                                              |
| `language` | Exact match.                                                                  |
| `limit`    | Optional, default 50, max 200. No pagination cursor.                          |

**Response:**

```json
{
  "results": [
    {
      "id": 7,
      "title": "...",
      "language": "php",
      "body": "...",
      "tags": ["testing", "phpunit"],
      "created_at": "2026-05-20T08:31:00Z",
      "updated_at": "2026-05-20T08:31:00Z",
      "score": 4.5
    }
  ],
  "total": 1
}
```

`score` is present only when `q` is set — this signals to the frontend that the list is ranked and should not be re-sorted client-side.

### Create / update body

```json
{ "title": "...", "language": "php", "body": "...", "tags": ["php", "testing"] }
```

- `tags` is an array of strings; API normalizes (trim + lowercase) and upserts.
- `language` must be in the `/api/languages` list. Unknown → 400.
- Empty `title` or `body` → 400.

### Front controller

A single `public/index.php` (~40 lines) parses `(METHOD, /api/path)` and dispatches to a controller class method. Non-`/api/*` paths return `false`, deferring to PHP's static file server.

---

## 5. Search ranking algorithm

The only piece of non-trivial logic. Designed to be deterministic, pure (no DB inside the ranker), and rich enough to be a worthy TDD target.

### Pipeline

1. **Hard filters in SQL.** `language` and required `tag`s are applied via `WHERE` / `JOIN` on the indexed tables. Candidate set is hydrated into PHP objects.
2. **Tokenize `q`.** Lowercase, split on whitespace, drop tokens shorter than 2 chars, dedupe. Empty after tokenization → skip ranking, return candidates ordered by `updated_at DESC`.
3. **Match requirement.** Each token must appear in *at least one* of: title, body, or any tag name of the snippet. Tokens AND across tokens, OR across fields.
4. **Score** each surviving snippet — sum contributions per token across fields:

| Field                              | Weight     | Notes                                                                  |
|------------------------------------|------------|------------------------------------------------------------------------|
| Title match                        | 5.0        | Whole-word match adds +2.0 bonus on top                                |
| Tag exact match (token == tag name) | 4.0       | One hit per token                                                      |
| Tag substring match                | 1.5        | Capped at one tag per token                                            |
| Body match                         | 1.0        | Count *unique line* occurrences (not raw `substr_count`)               |
| Recency tiebreak                   | +0.000001/day | Days-since-epoch on `updated_at` × 0.000001 — max contribution ~0.02 in 2026, well below the smallest real score (1.0 body match) |

5. **Sort by score DESC**, truncate to `limit`.

### Why these rules

- **Filter then rank** keeps SQL boring and PHP testable. The ranker is `function rank(array $candidates, array $tokens): array`.
- **Whole-word title bonus** addresses the common ranking complaint where partial matches outrank exact ones.
- **Unique-line counting** prevents a single 200-line file from dominating just because its match line appears in a long file. Naive `substr_count` fails the obvious test ("200 identical lines should not score 200× a single line").
- **Recency tiebreak as +0.000001/day** keeps the sort one-stage and trivially testable.

### Test surface (the TDD playground)

- Empty query, no filters → all snippets, sorted by `updated_at DESC`.
- Single-token body match → score = 1.0 × unique-line count.
- Token in title + body → title contribution dominates.
- Multi-token, one token absent → snippet excluded.
- Token equals tag name → exact-tag bonus applied once.
- Two snippets, equal score, different `updated_at` → newer wins.
- 200-line body, all matching → score == 1 matching line.
- Whole-word vs. substring in title → whole-word ranks higher.

Defaults: case-insensitive everywhere, no accent folding, no stemming, no synonyms.

---

## 6. Frontend structure

Vanilla JS, ES modules, no build step. Single HTML page; everything mounts into `<main>`.

### Module layout

```
frontend/src/
├── api.js          # fetch wrapper, one function per endpoint
├── state.js        # pub/sub store (~30 lines)
├── search.js       # search bar, tag chips, language dropdown
├── results.js      # result list rendering
├── editor.js       # create / edit snippet modal
├── highlight.js    # lazy-loads Prism.js from CDN
└── main.js         # wiring + debounce + mount
```

### State store

```js
const state = {
  query: '',
  selectedTags: new Set(),
  language: null,
  results: [],
  tags: [],          // all known in-use tags with counts
  languages: [],
  editing: null,     // null | { id?, title, language, body, tags }
};
```

`get()`, `update(patch)`, `subscribe(fn)`. No diffing — each view module owns its DOM subtree and rebuilds it on state change. Inputs preserve focus by checking `document.activeElement` before re-render.

### Data flow

```
keystroke in search box
  → search.js calls update({ query })
  → main.js subscriber debounces 200ms
  → calls api.searchSnippets({ q, tags, language })
  → calls update({ results })
  → results.js re-renders the list
```

The 200ms debounce is the one non-trivial helper; it lives in `main.js` and is unit-tested with `node --test`'s `mock.timers`.

### Syntax highlighting

`highlight.js` (the module, not the library) is the only file that knows Prism exists. It lazy-loads Prism from a CDN on first render. CDN failure → snippets render in unstyled `<pre>` (tested with mocked failed import).

### Out of scope for workshop

- Routing / shareable snippet URLs.
- Optimistic updates.
- Drag-and-drop, keyboard navigation beyond browser defaults.
- Virtual scrolling (workshop dataset stays small).

---

## 7. Testing strategy

### Backend (PHPUnit)

```
backend/tests/
├── Unit/
│   ├── SearchRankerTest.php       # The TDD star
│   ├── TagUpserterTest.php        # Find-or-create, dedupe, case folding
│   ├── SnippetValidatorTest.php   # Title/body/language/tags validation
│   └── RouterTest.php             # (METHOD, path) → handler
└── Integration/
    ├── SnippetsApiTest.php        # HTTP round-trip vs. real php -S
    └── DatabaseMigrationTest.php  # migrate() is idempotent
```

- Unit tests never touch SQLite. Tag upserter takes an injected `PDO`; tests pass `new PDO('sqlite::memory:')`.
- Integration tests spin up `php -S` in a fixture process and hit it with HTTP. Each test gets its own temp SQLite file, deleted on teardown.

### Frontend (`node --test`, Node 20+)

```
frontend/tests/
├── api.test.js          # URL building (esp. repeated tag=), fetch stubbing, errors
├── state.test.js        # Pub/sub semantics
├── debounce.test.js     # mock.timers
└── highlight.test.js    # Lazy import + CDN-failure fallback
```

No `jsdom`; pure-logic modules cover the meaningful surface for workshop scope.

### Commands

```
make test            # both slices
make test-backend    # cd backend && composer test
make test-frontend   # cd frontend && node --test tests/
```

---

## 8. Dev workflow

```
make dev
```

is

```
php -S localhost:8080 -t backend/public backend/public/index.php
```

Single process. Browse to `http://localhost:8080/`; SPA loads; `fetch('/api/...')` hits the same origin. No CORS.

Other top-level Make targets: `test`, `test-backend`, `test-frontend`, `clean` (wipes `backend/data/*.sqlite`).

`.gitignore`: `backend/data/*.sqlite`, `vendor/`, `node_modules/`.

No top-level `package.json` initially — frontend has no npm deps. `backend/composer.json` declares PHPUnit as a dev dep and autoloads `App\\` → `backend/src/` (relative path `src/` from composer.json's location).

### CI (out of scope to build)

`make test` on a vanilla `php:8.2-cli` + `node:20` container is sufficient. No GitHub Actions yaml in this scope.

---

## 9. Implementation slicing (preview for writing-plans)

For the implementation plan that follows from this spec, the natural slices are:

1. **Repo + dev workflow scaffold.** `composer.json`, `Makefile`, `public/index.php` skeleton, `index.html` skeleton, `make dev` works (serves a "hello" page + a `/api/health` endpoint).
2. **Backend: data layer.** SQLite + `Database::migrate()`, snippet & tag repositories, tag upserter (TDD).
3. **Backend: search ranker.** Pure-PHP ranker module, TDD against the test surface in Section 5.
4. **Backend: HTTP layer.** Router, controllers, validators, `/api/snippets` CRUD + search wiring, `/api/tags`, `/api/languages`. Integration tests.
5. **Frontend: state + api.** `state.js`, `api.js`, debounce helper. Unit tests with `node --test`.
6. **Frontend: views.** `search.js`, `results.js`, `editor.js`, `highlight.js`. Wired in `main.js`.
7. **End-to-end smoke.** Manual checklist in the README + a tiny integration test that does create → search → update → delete via the running server.

Each slice ends with a green test run. Slices 2 and 3 are the strongest TDD demonstrations; slice 4 is the strongest "integration test" demonstration; slice 6 is the strongest "see the change in the browser" demonstration.
