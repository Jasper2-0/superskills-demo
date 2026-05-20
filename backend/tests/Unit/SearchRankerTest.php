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
