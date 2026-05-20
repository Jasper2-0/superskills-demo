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
