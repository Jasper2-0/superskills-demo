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
