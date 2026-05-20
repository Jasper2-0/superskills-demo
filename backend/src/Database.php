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
                id          INTEGER PRIMARY KEY,
                title       TEXT    NOT NULL,
                language    TEXT    NOT NULL,
                body        TEXT    NOT NULL,
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS tags (
                id   INTEGER PRIMARY KEY,
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
