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
