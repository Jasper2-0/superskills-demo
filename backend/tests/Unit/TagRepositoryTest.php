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
