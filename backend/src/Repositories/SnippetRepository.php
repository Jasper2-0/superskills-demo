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
        $t = microtime(true);
        $sec = (int) $t;
        $usec = (int) round(($t - $sec) * 1_000_000);
        return gmdate('Y-m-d\TH:i:s', $sec) . sprintf('.%06dZ', $usec);
    }
}
