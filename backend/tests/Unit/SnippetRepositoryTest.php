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
