# Snippet Library Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-user, locally-runnable code-snippet library with tags, language metadata, and ranked search — designed as a workshop demonstration of the superpowers lifecycle.

**Architecture:** PHP 8.1+ JSON API and a vanilla-JS SPA in one repo, served by a single `php -S` process. SQLite via PDO for persistence. Pure-PHP search ranker as the centerpiece TDD target.

**Tech Stack:** PHP 8.1+, PDO+SQLite, Composer, PHPUnit 10. Vanilla JavaScript (ES modules, no build step), Node 20+ for `node --test`, Prism.js loaded lazily from CDN.

**Related spec:** `docs/superpowers/specs/2026-05-20-snippet-library-design.md`

---

## File Structure

### Backend (`backend/`)
- `composer.json` — Composer config: PHPUnit dev dep, PSR-4 autoload `App\` → `src/`, autoload-dev for tests
- `phpunit.xml` — PHPUnit suite config (Unit + Integration)
- `public/index.php` — Front controller; dispatches `/api/*`, returns `false` for everything else
- `src/Database.php` — PDO factory, `migrate()` schema bootstrap
- `src/Languages.php` — Static list of supported language identifiers
- `src/Models/Snippet.php` — Readonly value object
- `src/Repositories/SnippetRepository.php` — CRUD + filtered candidate query
- `src/Repositories/TagRepository.php` — find-or-create, list-with-counts, names-for-snippet
- `src/Search/Tokenizer.php` — Lowercase, split, drop short tokens, dedupe
- `src/Search/SearchRanker.php` — Pure-PHP weighted ranker
- `src/Validation/SnippetValidator.php` — Validate POST/PUT payloads
- `src/Http/Router.php` — `(METHOD, /api/path)` dispatch
- `src/Http/JsonResponse.php` — JSON output helpers (status + body)
- `src/Http/Controllers/SnippetsController.php` — `/api/snippets` endpoints
- `src/Http/Controllers/TagsController.php` — `/api/tags`
- `src/Http/Controllers/LanguagesController.php` — `/api/languages`
- `tests/Unit/TokenizerTest.php`
- `tests/Unit/SearchRankerTest.php` — TDD star
- `tests/Unit/DatabaseTest.php`
- `tests/Unit/TagRepositoryTest.php`
- `tests/Unit/SnippetRepositoryTest.php`
- `tests/Unit/SnippetValidatorTest.php`
- `tests/Unit/RouterTest.php`
- `tests/Integration/IntegrationCase.php` — Spawns `php -S` per test, returns HTTP helper
- `tests/Integration/SnippetsApiTest.php` — End-to-end CRUD + search round-trips

### Frontend (`frontend/`)
- `index.html` — Single-page shell
- `styles.css` — Minimal styling
- `src/api.js` — fetch wrapper, one function per endpoint
- `src/state.js` — Pub/sub state store
- `src/debounce.js` — Debounce helper (isolated for testability)
- `src/highlight.js` — Lazy Prism loader with CDN-failure fallback
- `src/results.js` — Result list view
- `src/search.js` — Search bar, tag chips, language dropdown view
- `src/editor.js` — Create/edit modal view
- `src/main.js` — Wires modules + mounts on `DOMContentLoaded`
- `tests/api.test.js`
- `tests/state.test.js`
- `tests/debounce.test.js`
- `tests/highlight.test.js`

### Root
- `Makefile` — `dev`, `test`, `test-backend`, `test-frontend`, `clean`
- `README.md` — Quickstart + architecture pointer
- `.gitignore` — (already exists)

---

## Tasks

### Task 1: Repo scaffold (Makefile, README, directory tree, frontend shell)

**Files:**
- Create: `Makefile`
- Create: `README.md`
- Create: `frontend/index.html`
- Create: `frontend/styles.css`
- Create: `backend/data/.gitkeep`
- Create: `backend/public/index.php`

- [ ] **Step 1: Create the Makefile**

`Makefile`:
```make
.PHONY: dev test test-backend test-frontend clean

dev:
	php -S localhost:8080 -t backend/public backend/public/index.php

test: test-backend test-frontend

test-backend:
	cd backend && composer test

test-frontend:
	cd frontend && node --test tests/

clean:
	rm -f backend/data/*.sqlite backend/data/*.sqlite-journal
```

- [ ] **Step 2: Create the README**

`README.md`:
```markdown
# Snippet Library

A single-user, locally-runnable code-snippet library. Workshop demo for the
`superpowers` plugin.

## Quickstart

    make dev       # Starts the app at http://localhost:8080
    make test      # Runs backend + frontend test suites

## Architecture

- PHP 8.1+ JSON API at `/api/*`
- Vanilla-JS SPA served from the same origin
- SQLite via PDO

See `docs/superpowers/specs/2026-05-20-snippet-library-design.md` for the design spec.
```

- [ ] **Step 3: Create the frontend shell**

`frontend/index.html`:
```html
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Snippet Library</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
  <header>
    <h1>Snippet Library</h1>
  </header>
  <main id="app">Loading…</main>
  <script type="module" src="/src/main.js"></script>
</body>
</html>
```

`frontend/styles.css`:
```css
body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
header { display: flex; align-items: center; justify-content: space-between; }
.snippet { border: 1px solid #ddd; border-radius: 6px; padding: 1rem; margin-bottom: 1rem; }
.snippet h2 { margin: 0 0 .5rem; font-size: 1.1rem; }
.tags { display: flex; gap: .3rem; flex-wrap: wrap; margin-top: .5rem; }
.tag { background: #eef; padding: .15rem .5rem; border-radius: 999px; font-size: .8rem; }
.search-bar { display: flex; gap: .5rem; margin: 1rem 0; }
.search-bar input, .search-bar select { padding: .4rem; font-size: 1rem; }
.modal { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; padding: 1.5rem; border-radius: 8px; min-width: 500px; }
.modal-content input, .modal-content textarea, .modal-content select { width: 100%; padding: .4rem; margin-top: .25rem; font-size: 1rem; }
.modal-content label { display: block; margin-top: .75rem; font-weight: 600; }
.modal-actions { display: flex; gap: .5rem; justify-content: flex-end; margin-top: 1rem; }
pre code { display: block; padding: .5rem; background: #f6f8fa; border-radius: 4px; overflow-x: auto; }
```

- [ ] **Step 4: Create the front controller stub**

`backend/public/index.php`:
```php
<?php
declare(strict_types=1);

// Static-file fallthrough: PHP's built-in dev server handles non-API paths.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if (!str_starts_with($path, '/api/')) {
    $docRoot = __DIR__;
    $candidate = $docRoot . $path;
    if ($path !== '/' && is_file($candidate)) {
        return false; // let PHP serve the static file
    }
    // Default: serve the SPA shell.
    $frontendRoot = dirname(__DIR__, 2) . '/frontend';
    $requested = $path === '/' ? '/index.html' : $path;
    $candidate = $frontendRoot . $requested;
    if (is_file($candidate)) {
        $mime = match (pathinfo($candidate, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// Temporary health endpoint — real router wired in Task 21.
header('Content-Type: application/json');
if ($path === '/api/health') {
    echo json_encode(['status' => 'ok']);
    return true;
}
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
return true;
```

- [ ] **Step 5: Create data dir placeholder**

`backend/data/.gitkeep`: (empty file)

- [ ] **Step 6: Verify `make dev` serves the app**

Run: `make dev` in one terminal, then in another: `curl -s http://localhost:8080/api/health`
Expected: `{"status":"ok"}`

Then: `curl -s http://localhost:8080/ | head -5`
Expected: HTML starting with `<!doctype html>` and the title "Snippet Library".

Stop the dev server (Ctrl-C in the first terminal).

- [ ] **Step 7: Commit**

```bash
git add Makefile README.md frontend/ backend/public/ backend/data/.gitkeep
git commit -m "Scaffold repo: Makefile, README, frontend shell, PHP front controller"
```

---

### Task 2: Composer config + PHPUnit setup

**Files:**
- Create: `backend/composer.json`
- Create: `backend/phpunit.xml`
- Create: `backend/tests/Unit/.gitkeep`
- Create: `backend/tests/Integration/.gitkeep`

- [ ] **Step 1: Create composer.json**

`backend/composer.json`:
```json
{
    "name": "snippet-library/backend",
    "description": "Snippet library backend (workshop demo)",
    "type": "project",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Create phpunit.xml**

`backend/phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 3: Install dependencies**

Run: `cd backend && composer install`
Expected: PHPUnit installed under `backend/vendor/`. No errors.

- [ ] **Step 4: Verify PHPUnit runs (empty suite)**

Create placeholder files so PHPUnit has an empty but valid suite:

`backend/tests/Unit/.gitkeep`: (empty)
`backend/tests/Integration/.gitkeep`: (empty)

Run: `cd backend && vendor/bin/phpunit`
Expected: "No tests executed!" (and exit code 0).

- [ ] **Step 5: Commit**

```bash
git add backend/composer.json backend/composer.lock backend/phpunit.xml backend/tests/
git commit -m "Add Composer + PHPUnit setup"
```

---

### Task 3: `App\Languages` constants

**Files:**
- Create: `backend/src/Languages.php`
- Test: `backend/tests/Unit/LanguagesTest.php`

- [ ] **Step 1: Write the failing test**

`backend/tests/Unit/LanguagesTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Languages;
use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase
{
    public function testAllReturnsTheTenSupportedLanguages(): void
    {
        $expected = ['php', 'javascript', 'python', 'sql', 'bash', 'html', 'css', 'json', 'markdown', 'other'];
        self::assertSame($expected, Languages::all());
    }

    public function testIsSupportedReturnsTrueForKnownLanguage(): void
    {
        self::assertTrue(Languages::isSupported('php'));
        self::assertTrue(Languages::isSupported('markdown'));
    }

    public function testIsSupportedReturnsFalseForUnknown(): void
    {
        self::assertFalse(Languages::isSupported('rust'));
        self::assertFalse(Languages::isSupported('PHP')); // case-sensitive
        self::assertFalse(Languages::isSupported(''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && vendor/bin/phpunit --filter LanguagesTest`
Expected: FAIL with "Class App\Languages does not exist".

- [ ] **Step 3: Implement**

`backend/src/Languages.php`:
```php
<?php
declare(strict_types=1);

namespace App;

final class Languages
{
    /** @var list<string> */
    private const SUPPORTED = ['php', 'javascript', 'python', 'sql', 'bash', 'html', 'css', 'json', 'markdown', 'other'];

    /** @return list<string> */
    public static function all(): array
    {
        return self::SUPPORTED;
    }

    public static function isSupported(string $language): bool
    {
        return in_array($language, self::SUPPORTED, true);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd backend && vendor/bin/phpunit --filter LanguagesTest`
Expected: PASS (3 tests, 4 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Languages.php backend/tests/Unit/LanguagesTest.php
git commit -m "Add App\\Languages constants + tests"
```

---

### Task 4: `App\Models\Snippet` value object

**Files:**
- Create: `backend/src/Models/Snippet.php`
- Test: `backend/tests/Unit/SnippetTest.php`

- [ ] **Step 1: Write the failing test**

`backend/tests/Unit/SnippetTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Models\Snippet;
use PHPUnit\Framework\TestCase;

final class SnippetTest extends TestCase
{
    public function testCanBeConstructedWithAllFields(): void
    {
        $s = new Snippet(
            id: 7,
            title: 'PHPUnit setup',
            language: 'php',
            body: '<?php echo "hi";',
            tags: ['php', 'testing'],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T11:00:00Z',
        );

        self::assertSame(7, $s->id);
        self::assertSame('PHPUnit setup', $s->title);
        self::assertSame(['php', 'testing'], $s->tags);
    }

    public function testIdCanBeNullForUnsavedSnippets(): void
    {
        $s = new Snippet(
            id: null,
            title: 'New',
            language: 'php',
            body: 'x',
            tags: [],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T10:00:00Z',
        );
        self::assertNull($s->id);
    }

    public function testToArrayProducesJsonSafeShape(): void
    {
        $s = new Snippet(
            id: 1,
            title: 't',
            language: 'php',
            body: 'b',
            tags: ['x'],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T10:00:00Z',
        );

        self::assertSame([
            'id' => 1,
            'title' => 't',
            'language' => 'php',
            'body' => 'b',
            'tags' => ['x'],
            'created_at' => '2026-05-20T10:00:00Z',
            'updated_at' => '2026-05-20T10:00:00Z',
        ], $s->toArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd backend && vendor/bin/phpunit --filter SnippetTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

`backend/src/Models/Snippet.php`:
```php
<?php
declare(strict_types=1);

namespace App\Models;

final class Snippet
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $language,
        public readonly string $body,
        public readonly array $tags,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * @return array{id: ?int, title: string, language: string, body: string, tags: list<string>, created_at: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'language' => $this->language,
            'body' => $this->body,
            'tags' => $this->tags,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `cd backend && vendor/bin/phpunit --filter SnippetTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Models/Snippet.php backend/tests/Unit/SnippetTest.php
git commit -m "Add Snippet value object"
```

---

### Task 5: `App\Search\Tokenizer`

**Files:**
- Create: `backend/src/Search/Tokenizer.php`
- Test: `backend/tests/Unit/TokenizerTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/TokenizerTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Search\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    private Tokenizer $t;
    protected function setUp(): void { $this->t = new Tokenizer(); }

    public function testEmptyStringYieldsNoTokens(): void
    {
        self::assertSame([], $this->t->tokenize(''));
        self::assertSame([], $this->t->tokenize('   '));
    }

    public function testLowercasesAndSplitsOnWhitespace(): void
    {
        self::assertSame(['foo', 'bar'], $this->t->tokenize('Foo BAR'));
        self::assertSame(['foo', 'bar'], $this->t->tokenize("Foo\tBAR\n"));
    }

    public function testDropsTokensShorterThanTwoCharacters(): void
    {
        self::assertSame(['foo'], $this->t->tokenize('a foo'));
        self::assertSame(['foo'], $this->t->tokenize('foo i'));
    }

    public function testDedupesRepeatedTokensPreservingOrder(): void
    {
        self::assertSame(['foo', 'bar'], $this->t->tokenize('foo bar foo'));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && vendor/bin/phpunit --filter TokenizerTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

`backend/src/Search/Tokenizer.php`:
```php
<?php
declare(strict_types=1);

namespace App\Search;

final class Tokenizer
{
    /** @return list<string> */
    public function tokenize(string $query): array
    {
        $lower = mb_strtolower(trim($query), 'UTF-8');
        if ($lower === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $lower);
        if ($parts === false) {
            return [];
        }
        $kept = [];
        foreach ($parts as $p) {
            if ($p === '' || mb_strlen($p, 'UTF-8') < 2) {
                continue;
            }
            if (!in_array($p, $kept, true)) {
                $kept[] = $p;
            }
        }
        return $kept;
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `cd backend && vendor/bin/phpunit --filter TokenizerTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Search/Tokenizer.php backend/tests/Unit/TokenizerTest.php
git commit -m "Add Tokenizer + tests"
```

---

### Task 6: `SearchRanker` — empty-query branch (no tokens)

This task and the next three TDD the ranker incrementally. Each adds one cluster of behaviors.

**Files:**
- Create: `backend/src/Search/SearchRanker.php`
- Test: `backend/tests/Unit/SearchRankerTest.php`

- [ ] **Step 1: Write failing tests for the empty-tokens path**

`backend/tests/Unit/SearchRankerTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Models\Snippet;
use App\Search\SearchRanker;
use PHPUnit\Framework\TestCase;

final class SearchRankerTest extends TestCase
{
    private SearchRanker $ranker;
    protected function setUp(): void { $this->ranker = new SearchRanker(); }

    /** @return list<Snippet> */
    private function snippets(Snippet ...$s): array { return array_values($s); }

    private function make(int $id, string $title = 't', string $body = 'b', array $tags = [], string $updatedAt = '2026-05-20T00:00:00Z'): Snippet
    {
        return new Snippet(
            id: $id,
            title: $title,
            language: 'php',
            body: $body,
            tags: $tags,
            createdAt: $updatedAt,
            updatedAt: $updatedAt,
        );
    }

    public function testEmptyTokensSortsByUpdatedAtDesc(): void
    {
        $older = $this->make(1, updatedAt: '2026-05-01T00:00:00Z');
        $newer = $this->make(2, updatedAt: '2026-05-19T00:00:00Z');
        $newest = $this->make(3, updatedAt: '2026-05-20T00:00:00Z');

        $ranked = $this->ranker->rank($this->snippets($older, $newer, $newest), []);

        self::assertSame([3, 2, 1], array_map(fn ($r) => $r['snippet']->id, $ranked));
    }

    public function testEmptyTokensReturnsScoreZeroForAll(): void
    {
        $only = $this->make(1);
        $ranked = $this->ranker->rank([$only], []);
        self::assertSame(0.0, $ranked[0]['score']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement minimal ranker (empty-tokens only)**

`backend/src/Search/SearchRanker.php`:
```php
<?php
declare(strict_types=1);

namespace App\Search;

use App\Models\Snippet;

final class SearchRanker
{
    /**
     * @param list<Snippet> $candidates
     * @param list<string>  $tokens   already lowercased
     * @return list<array{snippet: Snippet, score: float}>
     */
    public function rank(array $candidates, array $tokens): array
    {
        if ($tokens === []) {
            usort($candidates, fn (Snippet $a, Snippet $b) => $b->updatedAt <=> $a->updatedAt);
            return array_map(fn (Snippet $s) => ['snippet' => $s, 'score' => 0.0], $candidates);
        }
        // Scoring path: implemented in Task 7.
        return [];
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Search/SearchRanker.php backend/tests/Unit/SearchRankerTest.php
git commit -m "SearchRanker: empty-tokens path returns snippets sorted by updated_at desc"
```

---

### Task 7: `SearchRanker` — single-token scoring across title, tags, body

- [ ] **Step 1: Append failing tests**

Append to `backend/tests/Unit/SearchRankerTest.php` (inside the class):
```php
    public function testSingleTokenTitleMatchScoresFive(): void
    {
        $s = $this->make(1, title: 'PHPUnit setup');
        $ranked = $this->ranker->rank([$s], ['phpunit']);
        self::assertSame(1, $ranked[0]['snippet']->id);
        // 5.0 (title) + 2.0 (whole-word) + tiny recency contribution
        self::assertEqualsWithDelta(7.0, $ranked[0]['score'], 0.1);
    }

    public function testSingleTokenTagExactMatchScoresFour(): void
    {
        $s = $this->make(1, title: 'untitled', body: 'nope', tags: ['php']);
        $ranked = $this->ranker->rank([$s], ['php']);
        self::assertEqualsWithDelta(4.0, $ranked[0]['score'], 0.1);
    }

    public function testSingleTokenTagSubstringMatchScoresOneFive(): void
    {
        $s = $this->make(1, title: 'untitled', body: 'nope', tags: ['phpunit-config']);
        $ranked = $this->ranker->rank([$s], ['php']);
        self::assertEqualsWithDelta(1.5, $ranked[0]['score'], 0.1);
    }

    public function testSingleTokenBodyMatchScoresOnePerUniqueLine(): void
    {
        $body = "foo()\nbar()\nfoo()\nfoo()"; // 3 occurrences but only 1 unique line
        $s = $this->make(1, title: 'untitled', body: $body, tags: []);
        $ranked = $this->ranker->rank([$s], ['foo']);
        self::assertEqualsWithDelta(1.0, $ranked[0]['score'], 0.1);
    }

    public function testBodyScoreSumsAcrossDistinctMatchingLines(): void
    {
        $body = "alpha foo\nbeta foo\nno match here";
        $s = $this->make(1, title: 'untitled', body: $body, tags: []);
        $ranked = $this->ranker->rank([$s], ['foo']);
        // Two distinct lines contain "foo"
        self::assertEqualsWithDelta(2.0, $ranked[0]['score'], 0.1);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: FAIL — the empty-tokens path returns `[]` for any non-empty tokens, so all 5 new tests fail.

- [ ] **Step 3: Implement scoring path**

Replace the body of `rank()` in `backend/src/Search/SearchRanker.php`:
```php
<?php
declare(strict_types=1);

namespace App\Search;

use App\Models\Snippet;

final class SearchRanker
{
    private const WEIGHT_TITLE = 5.0;
    private const WEIGHT_TITLE_WHOLE_WORD_BONUS = 2.0;
    private const WEIGHT_TAG_EXACT = 4.0;
    private const WEIGHT_TAG_SUBSTRING = 1.5;
    private const WEIGHT_BODY_PER_LINE = 1.0;
    private const RECENCY_PER_DAY = 0.001;

    /**
     * @param list<Snippet> $candidates
     * @param list<string>  $tokens
     * @return list<array{snippet: Snippet, score: float}>
     */
    public function rank(array $candidates, array $tokens): array
    {
        if ($tokens === []) {
            usort($candidates, fn (Snippet $a, Snippet $b) => $b->updatedAt <=> $a->updatedAt);
            return array_map(fn (Snippet $s) => ['snippet' => $s, 'score' => 0.0], $candidates);
        }

        $scored = [];
        foreach ($candidates as $s) {
            $score = $this->scoreSnippet($s, $tokens);
            if ($score === null) {
                continue;
            }
            $scored[] = ['snippet' => $s, 'score' => $score];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * @param list<string> $tokens
     */
    private function scoreSnippet(Snippet $s, array $tokens): ?float
    {
        $title = mb_strtolower($s->title, 'UTF-8');
        $body = mb_strtolower($s->body, 'UTF-8');
        $tags = array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $s->tags);

        $score = 0.0;
        foreach ($tokens as $tok) {
            $contribution = 0.0;
            $matched = false;

            if (str_contains($title, $tok)) {
                $contribution += self::WEIGHT_TITLE;
                if (preg_match('/(?<![a-z0-9_])' . preg_quote($tok, '/') . '(?![a-z0-9_])/u', $title) === 1) {
                    $contribution += self::WEIGHT_TITLE_WHOLE_WORD_BONUS;
                }
                $matched = true;
            }

            if (in_array($tok, $tags, true)) {
                $contribution += self::WEIGHT_TAG_EXACT;
                $matched = true;
            } else {
                foreach ($tags as $tag) {
                    if (str_contains($tag, $tok)) {
                        $contribution += self::WEIGHT_TAG_SUBSTRING;
                        $matched = true;
                        break;
                    }
                }
            }

            $bodyHits = 0;
            $seenLines = [];
            foreach (explode("\n", $body) as $line) {
                if (str_contains($line, $tok) && !isset($seenLines[$line])) {
                    $seenLines[$line] = true;
                    $bodyHits++;
                }
            }
            if ($bodyHits > 0) {
                $contribution += $bodyHits * self::WEIGHT_BODY_PER_LINE;
                $matched = true;
            }

            if (!$matched) {
                return null;
            }
            $score += $contribution;
        }

        $ts = strtotime($s->updatedAt);
        if ($ts !== false) {
            $daysSinceEpoch = intdiv($ts, 86400);
            $score += $daysSinceEpoch * self::RECENCY_PER_DAY;
        }
        return $score;
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: PASS (all 7 tests so far).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Search/SearchRanker.php backend/tests/Unit/SearchRankerTest.php
git commit -m "SearchRanker: single-token scoring across title, tags, body"
```

---

### Task 8: `SearchRanker` — multi-token AND filtering + missing-token exclusion

- [ ] **Step 1: Append failing tests**

Append to `backend/tests/Unit/SearchRankerTest.php` (inside the class):
```php
    public function testMultiTokenAllPresentScoresSumOfContributions(): void
    {
        $s = $this->make(1, title: 'phpunit testing setup', body: 'install phpunit and run');
        $ranked = $this->ranker->rank([$s], ['phpunit', 'setup']);
        self::assertCount(1, $ranked);
        // Each token contributes: title 5+2=7 for whole-word matches; body adds 1 for phpunit line
        // phpunit token: title 5+2 + body 1 = 8
        // setup token: title 5+2 = 7
        // Total = 15 (+ tiny recency)
        self::assertGreaterThan(14.0, $ranked[0]['score']);
        self::assertLessThan(16.0, $ranked[0]['score']);
    }

    public function testMissingTokenExcludesSnippet(): void
    {
        $hasOne = $this->make(1, title: 'phpunit setup', body: 'install', tags: []);
        $hasBoth = $this->make(2, title: 'phpunit setup', body: 'composer install phpunit', tags: ['composer']);

        $ranked = $this->ranker->rank([$hasOne, $hasBoth], ['phpunit', 'composer']);

        self::assertCount(1, $ranked);
        self::assertSame(2, $ranked[0]['snippet']->id);
    }

    public function testRanksHigherScoresFirst(): void
    {
        $weak = $this->make(1, title: 'foo bar baz', body: 'nothing else');           // title hit only
        $strong = $this->make(2, title: 'foo', body: 'foo\nfoo other', tags: ['foo']); // exact tag + title + body

        $ranked = $this->ranker->rank([$weak, $strong], ['foo']);
        self::assertSame([2, 1], array_map(fn ($r) => $r['snippet']->id, $ranked));
    }
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: PASS — these behaviors are already implicit in the current impl. The `testRanksHigherScoresFirst` and AND-exclusion already work. Re-running just verifies.

Actually expect: PASS (all 10 tests). If any fail, debug before continuing.

- [ ] **Step 3: Implementation (none needed if all pass)**

The Task 7 implementation already handles multi-token AND and exclusion via `$matched`/`return null`. If a test fails, fix the implementation accordingly.

- [ ] **Step 4: Commit the additional tests**

```bash
git add backend/tests/Unit/SearchRankerTest.php
git commit -m "SearchRanker: lock in multi-token AND exclusion + ranking order"
```

---

### Task 9: `SearchRanker` — whole-word bonus, recency tiebreak, unique-line edge case

- [ ] **Step 1: Append failing tests**

Append to `backend/tests/Unit/SearchRankerTest.php`:
```php
    public function testTitleWholeWordOutranksTitleSubstring(): void
    {
        $whole = $this->make(1, title: 'foo example');
        $partial = $this->make(2, title: 'foobar example');

        $ranked = $this->ranker->rank([$partial, $whole], ['foo']);
        self::assertSame([1, 2], array_map(fn ($r) => $r['snippet']->id, $ranked));
    }

    public function testRecencyOnlyBreaksTiesNeverFlipsRealRankings(): void
    {
        // Identical scoring content, different updated_at → newer wins
        $older = $this->make(1, title: 'foo', updatedAt: '2026-05-01T00:00:00Z');
        $newer = $this->make(2, title: 'foo', updatedAt: '2026-05-20T00:00:00Z');

        $ranked = $this->ranker->rank([$older, $newer], ['foo']);
        self::assertSame([2, 1], array_map(fn ($r) => $r['snippet']->id, $ranked));

        // But recency must never beat a real scoring difference.
        // weakOldest has lower base score than strongOldest, so newer-but-weak shouldn't win.
        $weakNew = $this->make(3, title: 'no match here, foobar mentions', body: 'foo bar baz', updatedAt: '2030-01-01T00:00:00Z');
        $strongOld = $this->make(4, title: 'foo', tags: ['foo'], body: 'foo', updatedAt: '2020-01-01T00:00:00Z');
        $ranked2 = $this->ranker->rank([$weakNew, $strongOld], ['foo']);
        self::assertSame(4, $ranked2[0]['snippet']->id, 'Recency tiebreak must not outrank a real scoring difference');
    }

    public function testTwoHundredIdenticalMatchingLinesScoreSameAsOne(): void
    {
        $line = 'console.log(target)';
        $oneLine = $this->make(1, title: 'small', body: $line);
        $manyLines = $this->make(2, title: 'spammed', body: implode("\n", array_fill(0, 200, $line)));

        $rankedOne = $this->ranker->rank([$oneLine], ['target']);
        $rankedMany = $this->ranker->rank([$manyLines], ['target']);

        // Both score 1.0 from the body contribution (titles don't match)
        self::assertEqualsWithDelta($rankedOne[0]['score'], $rankedMany[0]['score'], 0.001);
    }
```

- [ ] **Step 2: Verify**

Run: `cd backend && vendor/bin/phpunit --filter SearchRankerTest`
Expected: PASS — the whole-word regex, recency arithmetic, and `$seenLines` dedup already implement these in Task 7. If anything fails, fix before continuing.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Unit/SearchRankerTest.php
git commit -m "SearchRanker: lock in whole-word bonus, recency tiebreak, unique-line dedup"
```

---

### Task 10: `App\Database` + `migrate()`

**Files:**
- Create: `backend/src/Database.php`
- Test: `backend/tests/Unit/DatabaseTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/DatabaseTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testMigrateCreatesAllExpectedTables(): void
    {
        $db = new Database(':memory:');
        $db->migrate();

        $tables = $db->pdo()->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
            ->fetchAll(\PDO::FETCH_COLUMN);

        self::assertContains('snippets', $tables);
        self::assertContains('tags', $tables);
        self::assertContains('snippet_tags', $tables);
    }

    public function testMigrateIsIdempotent(): void
    {
        $db = new Database(':memory:');
        $db->migrate();
        $db->migrate();

        $count = $db->pdo()->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")
            ->fetchColumn();
        self::assertSame(3, (int) $count);
    }

    public function testForeignKeysAreEnforced(): void
    {
        $db = new Database(':memory:');
        $db->migrate();
        $pdo = $db->pdo();

        $this->expectException(\PDOException::class);
        // No snippet with id=999 exists, so this snippet_tags insert must fail.
        $pdo->exec('INSERT INTO snippet_tags (snippet_id, tag_id) VALUES (999, 1)');
    }
}
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter DatabaseTest`
Expected: FAIL — `App\Database` does not exist.

- [ ] **Step 3: Implement**

`backend/src/Database.php`:
```php
<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $dsn = $path === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $path;
        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
        $statements = [
            'CREATE TABLE IF NOT EXISTS snippets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT    NOT NULL,
                language    TEXT    NOT NULL,
                body        TEXT    NOT NULL,
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS tags (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT    NOT NULL UNIQUE COLLATE NOCASE
            )',
            'CREATE TABLE IF NOT EXISTS snippet_tags (
                snippet_id INTEGER NOT NULL REFERENCES snippets(id) ON DELETE CASCADE,
                tag_id     INTEGER NOT NULL REFERENCES tags(id)     ON DELETE CASCADE,
                PRIMARY KEY (snippet_id, tag_id)
            )',
            'CREATE INDEX IF NOT EXISTS idx_snippet_tags_tag_id ON snippet_tags(tag_id)',
        ];
        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter DatabaseTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Database.php backend/tests/Unit/DatabaseTest.php
git commit -m "Add Database with idempotent migrate()"
```

---

### Task 11: `TagRepository` — upsertNames + listWithCounts + namesForSnippet

**Files:**
- Create: `backend/src/Repositories/TagRepository.php`
- Test: `backend/tests/Unit/TagRepositoryTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/TagRepositoryTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database;
use App\Repositories\TagRepository;
use PHPUnit\Framework\TestCase;

final class TagRepositoryTest extends TestCase
{
    private TagRepository $repo;
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->db->migrate();
        $this->repo = new TagRepository($this->db->pdo());
    }

    public function testUpsertNamesCreatesNewTags(): void
    {
        $ids = $this->repo->upsertNames(['php', 'testing']);
        self::assertCount(2, $ids);

        $count = $this->db->pdo()->query('SELECT COUNT(*) FROM tags')->fetchColumn();
        self::assertSame(2, (int) $count);
    }

    public function testUpsertNamesReusesExistingTagsCaseInsensitively(): void
    {
        $first = $this->repo->upsertNames(['php']);
        $second = $this->repo->upsertNames(['PHP']);
        self::assertSame($first, $second);

        $count = $this->db->pdo()->query('SELECT COUNT(*) FROM tags')->fetchColumn();
        self::assertSame(1, (int) $count);
    }

    public function testUpsertNamesNormalizesAndDedupesInput(): void
    {
        $ids = $this->repo->upsertNames(['  PHP  ', 'php', 'Testing', 'testing']);
        // Two distinct tags after trim/lowercase/dedupe
        self::assertCount(2, $ids);

        $names = $this->db->pdo()->query('SELECT name FROM tags ORDER BY name')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['php', 'testing'], $names);
    }

    public function testListWithCountsReturnsOnlyUsedTagsWithCounts(): void
    {
        $pdo = $this->db->pdo();
        // Snippet 1 with tags php, testing
        $pdo->exec("INSERT INTO snippets (id, title, language, body, created_at, updated_at) VALUES (1, 't', 'php', 'b', '2026-05-20T00:00:00Z', '2026-05-20T00:00:00Z')");
        $pdo->exec("INSERT INTO snippets (id, title, language, body, created_at, updated_at) VALUES (2, 't2', 'php', 'b2', '2026-05-20T00:00:00Z', '2026-05-20T00:00:00Z')");
        $ids = $this->repo->upsertNames(['php', 'testing', 'orphan']);
        // Link tags to snippets
        $pdo->exec("INSERT INTO snippet_tags (snippet_id, tag_id) VALUES (1, {$ids[0]})"); // php
        $pdo->exec("INSERT INTO snippet_tags (snippet_id, tag_id) VALUES (1, {$ids[1]})"); // testing
        $pdo->exec("INSERT INTO snippet_tags (snippet_id, tag_id) VALUES (2, {$ids[0]})"); // php again

        $result = $this->repo->listWithCounts();

        self::assertSame([
            ['name' => 'php', 'count' => 2],
            ['name' => 'testing', 'count' => 1],
        ], $result);
        // 'orphan' tag exists but is not linked to any snippet — must NOT appear.
    }

    public function testNamesForSnippetReturnsTagNamesSortedAlphabetically(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec("INSERT INTO snippets (id, title, language, body, created_at, updated_at) VALUES (1, 't', 'php', 'b', '2026-05-20T00:00:00Z', '2026-05-20T00:00:00Z')");
        $ids = $this->repo->upsertNames(['zoo', 'apple', 'middle']);
        foreach ($ids as $tagId) {
            $pdo->exec("INSERT INTO snippet_tags (snippet_id, tag_id) VALUES (1, $tagId)");
        }

        self::assertSame(['apple', 'middle', 'zoo'], $this->repo->namesForSnippet(1));
    }

    public function testNamesForSnippetReturnsEmptyArrayForUnknownSnippet(): void
    {
        self::assertSame([], $this->repo->namesForSnippet(999));
    }
}
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter TagRepositoryTest`
Expected: FAIL — `TagRepository` missing.

- [ ] **Step 3: Implement**

`backend/src/Repositories/TagRepository.php`:
```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TagRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param list<string> $names
     * @return list<int>   Tag IDs, one per unique normalized input name, in the order they were first seen.
     */
    public function upsertNames(array $names): array
    {
        $normalized = [];
        foreach ($names as $n) {
            $clean = mb_strtolower(trim($n), 'UTF-8');
            if ($clean === '' || in_array($clean, $normalized, true)) {
                continue;
            }
            $normalized[] = $clean;
        }
        if ($normalized === []) {
            return [];
        }

        $select = $this->pdo->prepare('SELECT id FROM tags WHERE name = :name');
        $insert = $this->pdo->prepare('INSERT INTO tags (name) VALUES (:name)');

        $ids = [];
        foreach ($normalized as $name) {
            $select->execute(['name' => $name]);
            $id = $select->fetchColumn();
            if ($id === false) {
                $insert->execute(['name' => $name]);
                $id = (int) $this->pdo->lastInsertId();
            }
            $ids[] = (int) $id;
        }
        return $ids;
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    public function listWithCounts(): array
    {
        $sql = '
            SELECT t.name AS name, COUNT(*) AS count
            FROM tags t
            JOIN snippet_tags st ON st.tag_id = t.id
            GROUP BY t.id
            ORDER BY count DESC, t.name ASC
        ';
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map(fn ($r) => ['name' => (string) $r['name'], 'count' => (int) $r['count']], $rows);
    }

    /**
     * @return list<string>
     */
    public function namesForSnippet(int $snippetId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.name FROM tags t
            JOIN snippet_tags st ON st.tag_id = t.id
            WHERE st.snippet_id = :id
            ORDER BY t.name ASC
        ');
        $stmt->execute(['id' => $snippetId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter TagRepositoryTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Repositories/TagRepository.php backend/tests/Unit/TagRepositoryTest.php
git commit -m "Add TagRepository with upsert, list-with-counts, names-for-snippet"
```

---

### Task 12: `SnippetRepository` — create/findById/findFiltered/update/delete

**Files:**
- Create: `backend/src/Repositories/SnippetRepository.php`
- Test: `backend/tests/Unit/SnippetRepositoryTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/SnippetRepositoryTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Database;
use App\Repositories\SnippetRepository;
use App\Repositories\TagRepository;
use PHPUnit\Framework\TestCase;

final class SnippetRepositoryTest extends TestCase
{
    private SnippetRepository $repo;
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->db->migrate();
        $tags = new TagRepository($this->db->pdo());
        $this->repo = new SnippetRepository($this->db->pdo(), $tags);
    }

    public function testCreatePersistsSnippetAndReturnsItWithIdAndTimestamps(): void
    {
        $s = $this->repo->create(['title' => 'PHPUnit', 'language' => 'php', 'body' => 'echo;', 'tags' => ['php', 'testing']]);

        self::assertNotNull($s->id);
        self::assertSame('PHPUnit', $s->title);
        self::assertSame(['php', 'testing'], $s->tags);
        self::assertNotSame('', $s->createdAt);
        self::assertSame($s->createdAt, $s->updatedAt);
    }

    public function testFindByIdReturnsSnippetWithTags(): void
    {
        $created = $this->repo->create(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => ['php']]);
        $found = $this->repo->findById($created->id);
        self::assertNotNull($found);
        self::assertSame($created->id, $found->id);
        self::assertSame(['php'], $found->tags);
    }

    public function testFindByIdReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->findById(999));
    }

    public function testFindFilteredWithoutFiltersReturnsAllSortedByUpdatedAtDesc(): void
    {
        $this->repo->create(['title' => 'a', 'language' => 'php', 'body' => 'b', 'tags' => []]);
        usleep(10_000); // ensure distinct timestamps
        $this->repo->create(['title' => 'b', 'language' => 'php', 'body' => 'b', 'tags' => []]);

        $results = $this->repo->findFiltered(language: null, tags: []);
        self::assertCount(2, $results);
        self::assertSame('b', $results[0]->title);
        self::assertSame('a', $results[1]->title);
    }

    public function testFindFilteredByLanguageReturnsOnlyMatchingLanguage(): void
    {
        $this->repo->create(['title' => 'p', 'language' => 'php', 'body' => 'b', 'tags' => []]);
        $this->repo->create(['title' => 'j', 'language' => 'javascript', 'body' => 'b', 'tags' => []]);

        $results = $this->repo->findFiltered(language: 'php', tags: []);
        self::assertCount(1, $results);
        self::assertSame('p', $results[0]->title);
    }

    public function testFindFilteredByTagsAndsAcrossTagList(): void
    {
        $this->repo->create(['title' => 'a', 'language' => 'php', 'body' => 'b', 'tags' => ['x']]);
        $this->repo->create(['title' => 'b', 'language' => 'php', 'body' => 'b', 'tags' => ['x', 'y']]);
        $this->repo->create(['title' => 'c', 'language' => 'php', 'body' => 'b', 'tags' => ['y']]);

        $results = $this->repo->findFiltered(language: null, tags: ['x', 'y']);
        self::assertCount(1, $results);
        self::assertSame('b', $results[0]->title);
    }

    public function testUpdateReplacesFieldsAndTagsAndBumpsUpdatedAt(): void
    {
        $created = $this->repo->create(['title' => 'old', 'language' => 'php', 'body' => 'old', 'tags' => ['a']]);
        usleep(10_000);
        $updated = $this->repo->update($created->id, ['title' => 'new', 'language' => 'sql', 'body' => 'SELECT 1', 'tags' => ['b']]);

        self::assertSame('new', $updated->title);
        self::assertSame('sql', $updated->language);
        self::assertSame(['b'], $updated->tags);
        self::assertNotSame($created->updatedAt, $updated->updatedAt);
        self::assertSame($created->createdAt, $updated->createdAt);
    }

    public function testDeleteRemovesSnippetAndItsTagLinks(): void
    {
        $created = $this->repo->create(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => ['x']]);
        $this->repo->delete($created->id);

        self::assertNull($this->repo->findById($created->id));
        $links = $this->db->pdo()->query('SELECT COUNT(*) FROM snippet_tags')->fetchColumn();
        self::assertSame(0, (int) $links);
    }
}
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter SnippetRepositoryTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

`backend/src/Repositories/SnippetRepository.php`:
```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Snippet;
use PDO;

final class SnippetRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TagRepository $tags,
    ) {}

    /**
     * @param array{title: string, language: string, body: string, tags: list<string>} $data
     */
    public function create(array $data): Snippet
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO snippets (title, language, body, created_at, updated_at) VALUES (:title, :language, :body, :created_at, :updated_at)');
        $stmt->execute([
            'title' => $data['title'],
            'language' => $data['language'],
            'body' => $data['body'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->syncTags($id, $data['tags']);
        return $this->findById($id);
    }

    public function findById(int $id): ?Snippet
    {
        $stmt = $this->pdo->prepare('SELECT * FROM snippets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->hydrate($row);
    }

    /**
     * @param list<string> $tags    AND-filter on all of these tag names (lowercased)
     * @return list<Snippet>
     */
    public function findFiltered(?string $language, array $tags): array
    {
        $where = [];
        $params = [];
        if ($language !== null) {
            $where[] = 'language = :language';
            $params['language'] = $language;
        }
        if ($tags !== []) {
            $placeholders = [];
            foreach ($tags as $i => $tag) {
                $key = ":tag_$i";
                $placeholders[] = $key;
                $params[$key] = mb_strtolower($tag, 'UTF-8');
            }
            $where[] = 'id IN (
                SELECT st.snippet_id FROM snippet_tags st
                JOIN tags t ON t.id = st.tag_id
                WHERE t.name IN (' . implode(',', $placeholders) . ')
                GROUP BY st.snippet_id
                HAVING COUNT(DISTINCT t.id) = ' . count($tags) . '
            )';
        }
        $sql = 'SELECT * FROM snippets';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY updated_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->hydrate($r), $rows);
    }

    /**
     * @param array{title: string, language: string, body: string, tags: list<string>} $data
     */
    public function update(int $id, array $data): Snippet
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE snippets SET title=:title, language=:language, body=:body, updated_at=:updated_at WHERE id=:id');
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'language' => $data['language'],
            'body' => $data['body'],
            'updated_at' => $now,
        ]);
        $this->pdo->prepare('DELETE FROM snippet_tags WHERE snippet_id = :id')->execute(['id' => $id]);
        $this->syncTags($id, $data['tags']);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM snippets WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @param list<string> $tagNames
     */
    private function syncTags(int $snippetId, array $tagNames): void
    {
        $tagIds = $this->tags->upsertNames($tagNames);
        if ($tagIds === []) {
            return;
        }
        $insert = $this->pdo->prepare('INSERT OR IGNORE INTO snippet_tags (snippet_id, tag_id) VALUES (:snippet_id, :tag_id)');
        foreach ($tagIds as $tagId) {
            $insert->execute(['snippet_id' => $snippetId, 'tag_id' => $tagId]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Snippet
    {
        return new Snippet(
            id: (int) $row['id'],
            title: (string) $row['title'],
            language: (string) $row['language'],
            body: (string) $row['body'],
            tags: $this->tags->namesForSnippet((int) $row['id']),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter SnippetRepositoryTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Repositories/SnippetRepository.php backend/tests/Unit/SnippetRepositoryTest.php
git commit -m "Add SnippetRepository with filtered queries"
```

---

### Task 13: `SnippetValidator`

**Files:**
- Create: `backend/src/Validation/SnippetValidator.php`
- Test: `backend/tests/Unit/SnippetValidatorTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/SnippetValidatorTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Validation\SnippetValidator;
use PHPUnit\Framework\TestCase;

final class SnippetValidatorTest extends TestCase
{
    private SnippetValidator $v;
    protected function setUp(): void { $this->v = new SnippetValidator(); }

    public function testValidPayloadReturnsNoErrors(): void
    {
        $errors = $this->v->validate(['title' => 'T', 'language' => 'php', 'body' => 'b', 'tags' => ['php']]);
        self::assertSame([], $errors);
    }

    public function testMissingTitleIsAnError(): void
    {
        $errors = $this->v->validate(['language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }

    public function testEmptyTitleAfterTrimIsAnError(): void
    {
        $errors = $this->v->validate(['title' => '   ', 'language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }

    public function testEmptyBodyIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => '', 'tags' => []]);
        self::assertContains('body is required', $errors);
    }

    public function testUnknownLanguageIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'rust', 'body' => 'b', 'tags' => []]);
        self::assertContains('language must be one of: php, javascript, python, sql, bash, html, css, json, markdown, other', $errors);
    }

    public function testTagsMustBeArray(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => 'php']);
        self::assertContains('tags must be an array of strings', $errors);
    }

    public function testTagsArrayElementsMustBeStrings(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => ['ok', 123]]);
        self::assertContains('tags must be an array of strings', $errors);
    }

    public function testNonStringTitleIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 123, 'language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }
}
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter SnippetValidatorTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

`backend/src/Validation/SnippetValidator.php`:
```php
<?php
declare(strict_types=1);

namespace App\Validation;

use App\Languages;

final class SnippetValidator
{
    /**
     * @param array<string, mixed> $data
     * @return list<string> errors; empty list means valid
     */
    public function validate(array $data): array
    {
        $errors = [];

        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors[] = 'title is required';
        }

        $body = $data['body'] ?? null;
        if (!is_string($body) || $body === '') {
            $errors[] = 'body is required';
        }

        $language = $data['language'] ?? null;
        if (!is_string($language) || !Languages::isSupported($language)) {
            $errors[] = 'language must be one of: ' . implode(', ', Languages::all());
        }

        $tags = $data['tags'] ?? [];
        $tagsValid = is_array($tags);
        if ($tagsValid) {
            foreach ($tags as $t) {
                if (!is_string($t)) { $tagsValid = false; break; }
            }
        }
        if (!$tagsValid) {
            $errors[] = 'tags must be an array of strings';
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter SnippetValidatorTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Validation/SnippetValidator.php backend/tests/Unit/SnippetValidatorTest.php
git commit -m "Add SnippetValidator"
```

---

### Task 14: `Router`

**Files:**
- Create: `backend/src/Http/Router.php`
- Test: `backend/tests/Unit/RouterTest.php`

- [ ] **Step 1: Write failing tests**

`backend/tests/Unit/RouterTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesExactPathMatch(): void
    {
        $router = new Router();
        $router->get('/api/tags', fn () => 'tags-listed');

        $result = $router->dispatch('GET', '/api/tags');
        self::assertSame(['status' => 200, 'result' => 'tags-listed'], [
            'status' => 200,
            'result' => $result,
        ]);
    }

    public function testCapturesIntegerPlaceholder(): void
    {
        $router = new Router();
        $router->get('/api/snippets/{id}', fn ($id) => "snippet:$id");

        self::assertSame('snippet:42', $router->dispatch('GET', '/api/snippets/42'));
    }

    public function testNonIntegerInIntPlaceholderDoesNotMatch(): void
    {
        $router = new Router();
        $router->get('/api/snippets/{id}', fn ($id) => "hit:$id");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not Found');
        $router->dispatch('GET', '/api/snippets/abc');
    }

    public function testUnknownPathThrowsNotFound(): void
    {
        $router = new Router();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not Found');
        $router->dispatch('GET', '/api/missing');
    }

    public function testKnownPathWithWrongMethodThrowsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/api/tags', fn () => 'x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Method Not Allowed');
        $router->dispatch('POST', '/api/tags');
    }

    public function testRegistersAllFourVerbs(): void
    {
        $router = new Router();
        $router->get('/x', fn () => 'g');
        $router->post('/x', fn () => 'p');
        $router->put('/x', fn () => 'u');
        $router->delete('/x', fn () => 'd');

        self::assertSame('g', $router->dispatch('GET', '/x'));
        self::assertSame('p', $router->dispatch('POST', '/x'));
        self::assertSame('u', $router->dispatch('PUT', '/x'));
        self::assertSame('d', $router->dispatch('DELETE', '/x'));
    }
}
```

- [ ] **Step 2: Verify failure**

Run: `cd backend && vendor/bin/phpunit --filter RouterTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

`backend/src/Http/Router.php`:
```php
<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void    { $this->routes[] = ['method' => 'GET',    'pattern' => $pattern, 'handler' => $handler]; }
    public function post(string $pattern, callable $handler): void   { $this->routes[] = ['method' => 'POST',   'pattern' => $pattern, 'handler' => $handler]; }
    public function put(string $pattern, callable $handler): void    { $this->routes[] = ['method' => 'PUT',    'pattern' => $pattern, 'handler' => $handler]; }
    public function delete(string $pattern, callable $handler): void { $this->routes[] = ['method' => 'DELETE', 'pattern' => $pattern, 'handler' => $handler]; }

    public function dispatch(string $method, string $path): mixed
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $m) === 1) {
                $pathMatched = true;
                if ($route['method'] === $method) {
                    array_shift($m); // drop the full match
                    $args = array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, $m);
                    return ($route['handler'])(...$args);
                }
            }
        }
        if ($pathMatched) {
            throw new RuntimeException('Method Not Allowed', 405);
        }
        throw new RuntimeException('Not Found', 404);
    }

    private function compile(string $pattern): string
    {
        // Replace {id} with integer-only capture
        $regex = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $m[1] === 'id' ? '(\d+)' : '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
```

- [ ] **Step 4: Verify pass**

Run: `cd backend && vendor/bin/phpunit --filter RouterTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/src/Http/Router.php backend/tests/Unit/RouterTest.php
git commit -m "Add Router with int placeholder and 404/405 semantics"
```

---

### Task 15: `JsonResponse` helper

**Files:**
- Create: `backend/src/Http/JsonResponse.php`

(Trivial helper. No unit test — its behavior is exercised by every integration test in Task 21.)

- [ ] **Step 1: Implement**

`backend/src/Http/JsonResponse.php`:
```php
<?php
declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function ok(mixed $data): void
    {
        self::emit(200, $data);
    }

    public static function created(mixed $data): void
    {
        self::emit(201, $data);
    }

    public static function noContent(): void
    {
        http_response_code(204);
    }

    public static function error(string $message, int $status): void
    {
        self::emit($status, ['error' => $message]);
    }

    private static function emit(int $status, mixed $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/src/Http/JsonResponse.php
git commit -m "Add JsonResponse helper"
```

---

### Task 16: `LanguagesController` + wire `/api/languages` route

**Files:**
- Create: `backend/src/Http/Controllers/LanguagesController.php`
- Modify: `backend/public/index.php`

- [ ] **Step 1: Implement the controller**

`backend/src/Http/Controllers/LanguagesController.php`:
```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Languages;

final class LanguagesController
{
    public function index(): void
    {
        JsonResponse::ok(['languages' => Languages::all()]);
    }
}
```

- [ ] **Step 2: Update `public/index.php` to use the router**

Replace `backend/public/index.php` entirely:
```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database;
use App\Http\Controllers\LanguagesController;
use App\Http\JsonResponse;
use App\Http\Router;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Static-file fallthrough for non-API paths.
if (!str_starts_with($path, '/api/')) {
    $publicCandidate = __DIR__ . $path;
    if ($path !== '/' && is_file($publicCandidate)) {
        return false; // serve from public/ via built-in server
    }
    $frontendRoot = dirname(__DIR__, 2) . '/frontend';
    $requested = $path === '/' ? '/index.html' : $path;
    $candidate = $frontendRoot . $requested;
    if (is_file($candidate)) {
        $mime = match (pathinfo($candidate, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// API dispatch.
$dbPath = $_ENV['SNIPPET_DB'] ?? dirname(__DIR__) . '/data/snippets.sqlite';
$db = new Database($dbPath);
$db->migrate();
$pdo = $db->pdo();

$router = new Router();
$router->get('/api/health', fn () => JsonResponse::ok(['status' => 'ok']));
$router->get('/api/languages', fn () => (new LanguagesController())->index());

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (\RuntimeException $e) {
    JsonResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
return true;
```

- [ ] **Step 3: Verify by hand**

Run: `make dev` in one terminal.
In another: `curl -s http://localhost:8080/api/languages`
Expected: `{"languages":["php","javascript","python","sql","bash","html","css","json","markdown","other"]}`

Stop the dev server.

- [ ] **Step 4: Commit**

```bash
git add backend/src/Http/Controllers/LanguagesController.php backend/public/index.php
git commit -m "Wire LanguagesController + router + DB bootstrap"
```

---

### Task 17: `TagsController` + wire `/api/tags` route

**Files:**
- Create: `backend/src/Http/Controllers/TagsController.php`
- Modify: `backend/public/index.php`

- [ ] **Step 1: Implement the controller**

`backend/src/Http/Controllers/TagsController.php`:
```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Repositories\TagRepository;

final class TagsController
{
    public function __construct(private readonly TagRepository $tags) {}

    public function index(): void
    {
        JsonResponse::ok(['tags' => $this->tags->listWithCounts()]);
    }
}
```

- [ ] **Step 2: Wire the route in `public/index.php`**

Locate the block in `backend/public/index.php` after `$router = new Router();` and add:
```php
use App\Repositories\TagRepository;
use App\Http\Controllers\TagsController;
```
near the existing `use` statements at the top.

Then in the route registration block, add (just below the `/api/languages` line):
```php
$tagRepo = new TagRepository($pdo);
$router->get('/api/tags', fn () => (new TagsController($tagRepo))->index());
```

The full file should now look like this. **Replace `backend/public/index.php`:**
```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\TagsController;
use App\Http\JsonResponse;
use App\Http\Router;
use App\Repositories\TagRepository;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (!str_starts_with($path, '/api/')) {
    $publicCandidate = __DIR__ . $path;
    if ($path !== '/' && is_file($publicCandidate)) {
        return false;
    }
    $frontendRoot = dirname(__DIR__, 2) . '/frontend';
    $requested = $path === '/' ? '/index.html' : $path;
    $candidate = $frontendRoot . $requested;
    if (is_file($candidate)) {
        $mime = match (pathinfo($candidate, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

$dbPath = $_ENV['SNIPPET_DB'] ?? dirname(__DIR__) . '/data/snippets.sqlite';
$db = new Database($dbPath);
$db->migrate();
$pdo = $db->pdo();

$tagRepo = new TagRepository($pdo);

$router = new Router();
$router->get('/api/health', fn () => JsonResponse::ok(['status' => 'ok']));
$router->get('/api/languages', fn () => (new LanguagesController())->index());
$router->get('/api/tags', fn () => (new TagsController($tagRepo))->index());

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (\RuntimeException $e) {
    JsonResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
return true;
```

- [ ] **Step 3: Manual smoke**

Run: `make dev`, then `curl -s http://localhost:8080/api/tags`
Expected: `{"tags":[]}` (no snippets yet → no in-use tags).

Stop the server.

- [ ] **Step 4: Commit**

```bash
git add backend/src/Http/Controllers/TagsController.php backend/public/index.php
git commit -m "Wire TagsController + /api/tags route"
```

---

### Task 18: `SnippetsController` — full CRUD + search wired

**Files:**
- Create: `backend/src/Http/Controllers/SnippetsController.php`
- Modify: `backend/public/index.php`

- [ ] **Step 1: Implement the controller**

`backend/src/Http/Controllers/SnippetsController.php`:
```php
<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Models\Snippet;
use App\Repositories\SnippetRepository;
use App\Search\SearchRanker;
use App\Search\Tokenizer;
use App\Validation\SnippetValidator;

final class SnippetsController
{
    public function __construct(
        private readonly SnippetRepository $snippets,
        private readonly SnippetValidator $validator,
        private readonly Tokenizer $tokenizer,
        private readonly SearchRanker $ranker,
    ) {}

    public function index(): void
    {
        $q = (string) ($_GET['q'] ?? '');
        $language = $_GET['language'] ?? null;
        if ($language === '' || !is_string($language)) {
            $language = null;
        }
        $rawTags = $_GET['tag'] ?? [];
        if (is_string($rawTags)) {
            $rawTags = [$rawTags];
        }
        if (!is_array($rawTags)) {
            $rawTags = [];
        }
        $tags = array_values(array_filter(array_map(fn ($t) => is_string($t) ? mb_strtolower(trim($t), 'UTF-8') : '', $rawTags), fn ($t) => $t !== ''));

        $limit = (int) ($_GET['limit'] ?? 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 200) $limit = 200;

        $candidates = $this->snippets->findFiltered($language, $tags);
        $tokens = $this->tokenizer->tokenize($q);

        $ranked = $this->ranker->rank($candidates, $tokens);
        $sliced = array_slice($ranked, 0, $limit);

        $results = array_map(function (array $row) use ($tokens) {
            $payload = $row['snippet']->toArray();
            if ($tokens !== []) {
                $payload['score'] = round($row['score'], 4);
            }
            return $payload;
        }, $sliced);

        JsonResponse::ok([
            'results' => $results,
            'total' => count($ranked),
        ]);
    }

    public function show(int $id): void
    {
        $s = $this->snippets->findById($id);
        if ($s === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        JsonResponse::ok($s->toArray());
    }

    public function store(): void
    {
        $data = $this->parseBody();
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            JsonResponse::error(implode('; ', $errors), 400);
            return;
        }
        $created = $this->snippets->create([
            'title' => trim((string) $data['title']),
            'language' => (string) $data['language'],
            'body' => (string) $data['body'],
            'tags' => array_values(array_map('strval', (array) ($data['tags'] ?? []))),
        ]);
        JsonResponse::created($created->toArray());
    }

    public function update(int $id): void
    {
        if ($this->snippets->findById($id) === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        $data = $this->parseBody();
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            JsonResponse::error(implode('; ', $errors), 400);
            return;
        }
        $updated = $this->snippets->update($id, [
            'title' => trim((string) $data['title']),
            'language' => (string) $data['language'],
            'body' => (string) $data['body'],
            'tags' => array_values(array_map('strval', (array) ($data['tags'] ?? []))),
        ]);
        JsonResponse::ok($updated->toArray());
    }

    public function destroy(int $id): void
    {
        if ($this->snippets->findById($id) === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        $this->snippets->delete($id);
        JsonResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
```

- [ ] **Step 2: Wire all five `/api/snippets` routes**

**Replace `backend/public/index.php`:**
```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database;
use App\Http\Controllers\LanguagesController;
use App\Http\Controllers\SnippetsController;
use App\Http\Controllers\TagsController;
use App\Http\JsonResponse;
use App\Http\Router;
use App\Repositories\SnippetRepository;
use App\Repositories\TagRepository;
use App\Search\SearchRanker;
use App\Search\Tokenizer;
use App\Validation\SnippetValidator;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (!str_starts_with($path, '/api/')) {
    $publicCandidate = __DIR__ . $path;
    if ($path !== '/' && is_file($publicCandidate)) {
        return false;
    }
    $frontendRoot = dirname(__DIR__, 2) . '/frontend';
    $requested = $path === '/' ? '/index.html' : $path;
    $candidate = $frontendRoot . $requested;
    if (is_file($candidate)) {
        $mime = match (pathinfo($candidate, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

$dbPath = $_ENV['SNIPPET_DB'] ?? dirname(__DIR__) . '/data/snippets.sqlite';
$db = new Database($dbPath);
$db->migrate();
$pdo = $db->pdo();

$tagRepo = new TagRepository($pdo);
$snippetRepo = new SnippetRepository($pdo, $tagRepo);
$snippetsController = new SnippetsController(
    $snippetRepo,
    new SnippetValidator(),
    new Tokenizer(),
    new SearchRanker(),
);

$router = new Router();
$router->get('/api/health', fn () => JsonResponse::ok(['status' => 'ok']));
$router->get('/api/languages', fn () => (new LanguagesController())->index());
$router->get('/api/tags', fn () => (new TagsController($tagRepo))->index());

$router->get   ('/api/snippets',         fn ()       => $snippetsController->index());
$router->post  ('/api/snippets',         fn ()       => $snippetsController->store());
$router->get   ('/api/snippets/{id}',    fn (int $id) => $snippetsController->show($id));
$router->put   ('/api/snippets/{id}',    fn (int $id) => $snippetsController->update($id));
$router->delete('/api/snippets/{id}',    fn (int $id) => $snippetsController->destroy($id));

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (\RuntimeException $e) {
    JsonResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
return true;
```

- [ ] **Step 3: Manual smoke for create + list**

Run: `make dev`. Then in another terminal:
```bash
curl -s -X POST http://localhost:8080/api/snippets \
  -H 'Content-Type: application/json' \
  -d '{"title":"Echo hi","language":"php","body":"<?php echo \"hi\";","tags":["php"]}'
```
Expected: A JSON object with `id`, the supplied fields, and timestamps.

```bash
curl -s 'http://localhost:8080/api/snippets?q=hi'
```
Expected: `{"results":[{...id:1, score: number}],"total":1}`.

Stop the server. Clean up: `make clean`.

- [ ] **Step 4: Commit**

```bash
git add backend/src/Http/Controllers/SnippetsController.php backend/public/index.php
git commit -m "Wire SnippetsController CRUD + search"
```

---

### Task 19: Integration test base case

**Files:**
- Create: `backend/tests/Integration/IntegrationCase.php`

(No standalone tests yet — this is the harness Task 20 uses.)

- [ ] **Step 1: Implement the test base**

`backend/tests/Integration/IntegrationCase.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class IntegrationCase extends TestCase
{
    /** @var resource|null */
    private $process = null;
    private string $dbPath = '';
    private int $port = 0;

    protected function setUp(): void
    {
        $this->port = $this->findFreePort();
        $this->dbPath = sys_get_temp_dir() . '/snippet-it-' . uniqid('', true) . '.sqlite';
        $docRoot = dirname(__DIR__, 2) . '/public';
        $router = $docRoot . '/index.php';

        $cmd = sprintf(
            'SNIPPET_DB=%s php -S 127.0.0.1:%d -t %s %s',
            escapeshellarg($this->dbPath),
            $this->port,
            escapeshellarg($docRoot),
            escapeshellarg($router),
        );
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($this->process)) {
            self::fail('Failed to start php -S');
        }

        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 0.2);
            if ($sock) { fclose($sock); return; }
            usleep(50_000);
        }
        self::fail('php -S did not become ready');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['pid']) {
                @posix_kill($status['pid'], SIGTERM);
            }
            proc_terminate($this->process);
            proc_close($this->process);
        }
        if ($this->dbPath !== '' && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: array<mixed>|null, raw: string}
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init("http://127.0.0.1:{$this->port}{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = $raw === '' ? null : json_decode($raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'raw' => (string) $raw];
    }

    private function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
```

- [ ] **Step 2: Smoke run (suite remains empty)**

Run: `cd backend && vendor/bin/phpunit --testsuite Integration`
Expected: "No tests executed!" (exit 0). The harness has no test methods of its own.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Integration/IntegrationCase.php
git commit -m "Add IntegrationCase: spawns php -S per test, returns HTTP helper"
```

---

### Task 20: End-to-end integration test for snippet CRUD + search

**Files:**
- Create: `backend/tests/Integration/SnippetsApiTest.php`

- [ ] **Step 1: Write the failing test**

`backend/tests/Integration/SnippetsApiTest.php`:
```php
<?php
declare(strict_types=1);

namespace App\Tests\Integration;

final class SnippetsApiTest extends IntegrationCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $r = $this->request('GET', '/api/health');
        self::assertSame(200, $r['status']);
        self::assertSame(['status' => 'ok'], $r['body']);
    }

    public function testLanguagesEndpointListsTenLanguages(): void
    {
        $r = $this->request('GET', '/api/languages');
        self::assertSame(200, $r['status']);
        self::assertCount(10, $r['body']['languages']);
        self::assertContains('php', $r['body']['languages']);
    }

    public function testFullCrudRoundTrip(): void
    {
        // Create
        $create = $this->request('POST', '/api/snippets', [
            'title' => 'Echo hi',
            'language' => 'php',
            'body' => '<?php echo "hi";',
            'tags' => ['php', 'demo'],
        ]);
        self::assertSame(201, $create['status']);
        $id = (int) $create['body']['id'];
        self::assertGreaterThan(0, $id);
        self::assertSame(['demo', 'php'], $create['body']['tags']);

        // Read
        $read = $this->request('GET', "/api/snippets/$id");
        self::assertSame(200, $read['status']);
        self::assertSame('Echo hi', $read['body']['title']);

        // List (no filters)
        $list = $this->request('GET', '/api/snippets');
        self::assertSame(200, $list['status']);
        self::assertSame(1, $list['body']['total']);

        // Search
        $search = $this->request('GET', '/api/snippets?q=hi');
        self::assertSame(200, $search['status']);
        self::assertSame(1, $search['body']['total']);
        self::assertArrayHasKey('score', $search['body']['results'][0]);

        // Update
        $update = $this->request('PUT', "/api/snippets/$id", [
            'title' => 'Echo bye',
            'language' => 'php',
            'body' => '<?php echo "bye";',
            'tags' => ['php'],
        ]);
        self::assertSame(200, $update['status']);
        self::assertSame('Echo bye', $update['body']['title']);
        self::assertSame(['php'], $update['body']['tags']);

        // Tags endpoint reflects current state (no orphan 'demo')
        $tags = $this->request('GET', '/api/tags');
        self::assertSame(200, $tags['status']);
        self::assertSame([['name' => 'php', 'count' => 1]], $tags['body']['tags']);

        // Delete
        $del = $this->request('DELETE', "/api/snippets/$id");
        self::assertSame(204, $del['status']);

        $gone = $this->request('GET', "/api/snippets/$id");
        self::assertSame(404, $gone['status']);
    }

    public function testCreateWithInvalidPayloadReturns400(): void
    {
        $r = $this->request('POST', '/api/snippets', ['title' => '', 'language' => 'rust', 'body' => '', 'tags' => []]);
        self::assertSame(400, $r['status']);
        self::assertStringContainsString('title is required', $r['body']['error']);
        self::assertStringContainsString('body is required', $r['body']['error']);
        self::assertStringContainsString('language must be one of', $r['body']['error']);
    }

    public function testSearchAndsAcrossTagFilters(): void
    {
        $this->request('POST', '/api/snippets', ['title' => 'only x', 'language' => 'php', 'body' => 'b', 'tags' => ['x']]);
        $this->request('POST', '/api/snippets', ['title' => 'x and y', 'language' => 'php', 'body' => 'b', 'tags' => ['x', 'y']]);

        $r = $this->request('GET', '/api/snippets?tag=x&tag=y');
        self::assertSame(200, $r['status']);
        self::assertSame(1, $r['body']['total']);
        self::assertSame('x and y', $r['body']['results'][0]['title']);
    }
}
```

- [ ] **Step 2: Verify pass**

Run: `cd backend && vendor/bin/phpunit --testsuite Integration`
Expected: PASS (5 tests). Each test spins its own server + DB.

- [ ] **Step 3: Run the full suite**

Run: `cd backend && vendor/bin/phpunit`
Expected: All Unit + Integration tests pass.

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Integration/SnippetsApiTest.php
git commit -m "Add end-to-end CRUD + search integration tests"
```

---

### Task 21: Frontend `api.js` + tests

**Files:**
- Create: `frontend/src/api.js`
- Create: `frontend/tests/api.test.js`

- [ ] **Step 1: Write failing tests**

`frontend/tests/api.test.js`:
```js
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
```

- [ ] **Step 2: Verify failure**

Run: `cd frontend && node --test tests/api.test.js`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement**

`frontend/src/api.js`:
```js
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
```

- [ ] **Step 4: Verify pass**

Run: `cd frontend && node --test tests/api.test.js`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/api.js frontend/tests/api.test.js
git commit -m "Add frontend api.js + tests"
```

---

### Task 22: Frontend `state.js` + tests

**Files:**
- Create: `frontend/src/state.js`
- Create: `frontend/tests/state.test.js`

- [ ] **Step 1: Write failing tests**

`frontend/tests/state.test.js`:
```js
import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

let store;
beforeEach(async () => {
  // Re-import a fresh module each test (cache-bust via query string)
  store = await import('../src/state.js?bust=' + Math.random());
});

test('get returns the initial state', () => {
  const s = store.get();
  assert.equal(s.query, '');
  assert.ok(s.selectedTags instanceof Set);
  assert.deepEqual(s.results, []);
  assert.equal(s.editing, null);
});

test('update merges a patch and notifies subscribers', () => {
  let received = null;
  store.subscribe((s) => { received = { ...s }; });
  store.update({ query: 'hi' });
  assert.equal(received.query, 'hi');
  assert.equal(store.get().query, 'hi');
});

test('subscribe returns an unsubscribe function', () => {
  let calls = 0;
  const unsub = store.subscribe(() => calls++);
  store.update({ query: 'a' });
  unsub();
  store.update({ query: 'b' });
  assert.equal(calls, 1);
});

test('multiple subscribers are all notified', () => {
  let a = 0, b = 0;
  store.subscribe(() => a++);
  store.subscribe(() => b++);
  store.update({ query: 'x' });
  assert.equal(a, 1);
  assert.equal(b, 1);
});
```

- [ ] **Step 2: Verify failure**

Run: `cd frontend && node --test tests/state.test.js`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement**

`frontend/src/state.js`:
```js
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
```

- [ ] **Step 4: Verify pass**

Run: `cd frontend && node --test tests/state.test.js`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/state.js frontend/tests/state.test.js
git commit -m "Add frontend state pub/sub store"
```

---

### Task 23: Frontend `debounce.js` + tests

**Files:**
- Create: `frontend/src/debounce.js`
- Create: `frontend/tests/debounce.test.js`

- [ ] **Step 1: Write failing tests**

`frontend/tests/debounce.test.js`:
```js
import { test, mock } from 'node:test';
import assert from 'node:assert/strict';

import { debounce } from '../src/debounce.js';

test('calls the function once after the quiet period', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    let calls = 0;
    const fn = debounce(() => calls++, 200);
    fn(); fn(); fn();
    assert.equal(calls, 0);
    mock.timers.tick(199);
    assert.equal(calls, 0);
    mock.timers.tick(1);
    assert.equal(calls, 1);
  } finally {
    mock.timers.reset();
  }
});

test('passes the last call arguments through', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const received = [];
    const fn = debounce((...args) => received.push(args), 100);
    fn(1, 'a');
    fn(2, 'b');
    mock.timers.tick(100);
    assert.deepEqual(received, [[2, 'b']]);
  } finally {
    mock.timers.reset();
  }
});

test('a later call after a flush starts a new debounce window', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    let calls = 0;
    const fn = debounce(() => calls++, 50);
    fn();
    mock.timers.tick(50);
    assert.equal(calls, 1);
    fn();
    mock.timers.tick(50);
    assert.equal(calls, 2);
  } finally {
    mock.timers.reset();
  }
});
```

- [ ] **Step 2: Verify failure**

Run: `cd frontend && node --test tests/debounce.test.js`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement**

`frontend/src/debounce.js`:
```js
export function debounce(fn, ms) {
  let handle = null;
  return function debounced(...args) {
    if (handle !== null) clearTimeout(handle);
    handle = setTimeout(() => {
      handle = null;
      fn(...args);
    }, ms);
  };
}
```

- [ ] **Step 4: Verify pass**

Run: `cd frontend && node --test tests/debounce.test.js`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/debounce.js frontend/tests/debounce.test.js
git commit -m "Add debounce helper + tests"
```

---

### Task 24: Frontend `highlight.js` + tests (lazy Prism + CDN failure fallback)

**Files:**
- Create: `frontend/src/highlight.js`
- Create: `frontend/tests/highlight.test.js`

- [ ] **Step 1: Write failing tests**

`frontend/tests/highlight.test.js`:
```js
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
```

- [ ] **Step 2: Verify failure**

Run: `cd frontend && node --test tests/highlight.test.js`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement**

`frontend/src/highlight.js`:
```js
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
```

- [ ] **Step 4: Verify pass**

Run: `cd frontend && node --test tests/highlight.test.js`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add frontend/src/highlight.js frontend/tests/highlight.test.js
git commit -m "Add highlight.js with lazy Prism + CDN-failure fallback"
```

---

### Task 25: Frontend `results.js` (view, no unit test)

The view modules are DOM-render functions; per the spec we don't unit-test the DOM. They will be exercised end-to-end via the smoke test in Task 28.

**Files:**
- Create: `frontend/src/results.js`

- [ ] **Step 1: Implement**

`frontend/src/results.js`:
```js
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
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/results.js
git commit -m "Add results.js view"
```

---

### Task 26: Frontend `search.js` (view)

**Files:**
- Create: `frontend/src/search.js`

- [ ] **Step 1: Implement**

`frontend/src/search.js`:
```js
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
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/search.js
git commit -m "Add search.js view"
```

---

### Task 27: Frontend `editor.js` (modal view)

**Files:**
- Create: `frontend/src/editor.js`

- [ ] **Step 1: Implement**

`frontend/src/editor.js`:
```js
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
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/editor.js
git commit -m "Add editor.js modal view"
```

---

### Task 28: Frontend `main.js` wiring

**Files:**
- Create: `frontend/src/main.js`
- Modify: `frontend/index.html`

- [ ] **Step 1: Update `index.html` to provide mount points**

Replace `frontend/index.html`:
```html
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Snippet Library</title>
  <link rel="stylesheet" href="/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css">
</head>
<body>
  <header><h1>Snippet Library</h1></header>
  <section id="search-mount"></section>
  <main id="results-mount">Loading…</main>
  <div id="editor-mount"></div>
  <script type="module" src="/src/main.js"></script>
</body>
</html>
```

- [ ] **Step 2: Implement `main.js`**

`frontend/src/main.js`:
```js
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
```

- [ ] **Step 3: Manual smoke**

Run: `make dev`. Open `http://localhost:8080/` in a browser.
- The page should load with "No snippets yet" message and a "New snippet" button.
- Click "New snippet", fill in a title/language/body, save.
- The snippet should appear in the list with syntax highlighting (if CDN reachable).
- Type a substring of the title in the search box — list should filter (after the 200ms debounce).
- Refresh the page — snippet persists.
- Edit and delete also work.

Stop the dev server.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/main.js frontend/index.html
git commit -m "Wire frontend: state, views, debounce, API"
```

---

### Task 29: End-to-end smoke checklist + README polish

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update the README with a smoke checklist**

Replace `README.md`:
```markdown
# Snippet Library

A single-user, locally-runnable code-snippet library. Workshop demo for the
`superpowers` plugin.

## Quickstart

    make dev       # Starts the app at http://localhost:8080
    make test      # Runs backend + frontend test suites
    make clean     # Wipes the local SQLite database

Requires PHP 8.1+, Composer, and Node 20+.

First-time setup:

    cd backend && composer install
    cd ..

## Smoke checklist (manual, in browser)

1. `make dev` → open `http://localhost:8080/`.
2. Click "New snippet" → fill in title, pick a language, paste some code, add comma-separated tags → Save.
3. The new card appears with syntax-highlighted code (if the CDN is reachable).
4. Type a substring of the title in the search box. After ~200ms the list filters.
5. Click a tag chip — list narrows to that tag. Click again to remove.
6. Pick a language from the dropdown — list narrows further.
7. Click "Edit" on a card, change the title, save — change reflects immediately.
8. Click "Delete" — confirm — card disappears.
9. Refresh the page — remaining snippets persist (SQLite in `backend/data/`).

## Architecture

- PHP 8.1+ JSON API at `/api/*` (PSR-4 in `backend/src/App\*`)
- Vanilla-JS SPA in `frontend/src/`, served by the same `php -S` process
- SQLite via PDO; schema created on first request

See `docs/superpowers/specs/2026-05-20-snippet-library-design.md` for design
context and `docs/superpowers/plans/2026-05-20-snippet-library.md` for the
implementation plan.
```

- [ ] **Step 2: Run the full test suite end-to-end**

```bash
make test
```
Expected: All backend (Unit + Integration) and frontend tests pass.

- [ ] **Step 3: Run through the smoke checklist in a browser**

Tick through every step in the README's "Smoke checklist" section. If anything fails, file a bug to fix.

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "Add smoke checklist; polish README"
```

---

## Self-Review

The following review was performed inline; issues found were fixed during writing.

**1. Spec coverage:**
- Section 1 (Goals): Covered by overall plan.
- Section 2 (Architecture): Tasks 1-2 establish the structure.
- Section 3 (Data model): Task 10 (`Database::migrate()`); tag normalization in Task 11.
- Section 4 (API surface): Tasks 16-18 plus router (14) and JsonResponse (15).
- Section 5 (Search ranking): Tasks 5-9 (Tokenizer + four-cycle TDD on SearchRanker).
- Section 6 (Frontend structure): Tasks 21-28.
- Section 7 (Testing strategy): Unit tests inline with implementation tasks; Integration in Tasks 19-20; frontend tests in Tasks 21-24.
- Section 8 (Dev workflow): Task 1 (Makefile) and Task 29 (README).
- Section 9 (Implementation slicing): Tasks map to spec's seven proposed slices.

**2. Placeholder scan:** No TBDs, no "similar to Task N", no "implement error handling here". Every code block is complete.

**3. Type consistency:**
- `Snippet` constructor signature is identical wherever instantiated (id, title, language, body, tags, createdAt, updatedAt).
- `SearchRanker::rank(array $candidates, array $tokens)` signature consistent across Tasks 6-9.
- `TagRepository::upsertNames(array): array` — same signature in repo, callsite (`SnippetRepository::syncTags`), and tests.
- `SnippetRepository::create/update` take an array with the same keys (`title`, `language`, `body`, `tags`).
- Frontend `state.update(patch)` and `state.subscribe(fn)` — identical signatures across uses.

**4. Ambiguity check:** Search ranker tests cover both "AND across tokens" and "OR across fields" behaviors explicitly; the unique-line counting rule is pinned by the 200-identical-lines test; the recency tiebreak is constrained ("never strong enough to flip a real ranking") by a dedicated test.

No issues remain.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-20-snippet-library.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Best fit if you want to step through the lifecycle live during the workshop and inspect the work at each checkpoint.

**2. Inline Execution** — Execute tasks in this session using `executing-plans`, batch execution with checkpoints for review. Best fit if you'd rather drive the workshop yourself and use this Claude session as a single executor.

**Which approach?**
