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
