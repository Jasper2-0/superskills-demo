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
        $strong = $this->make(2, title: 'foo', body: "foo\nfoo other", tags: ['foo']); // exact tag + title + body

        $ranked = $this->ranker->rank([$weak, $strong], ['foo']);
        self::assertSame([2, 1], array_map(fn ($r) => $r['snippet']->id, $ranked));
    }

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
}
