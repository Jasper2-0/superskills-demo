<?php
declare(strict_types=1);

namespace App\Search;

use App\Models\Snippet;

final class SearchRanker
{
    /**
     * @param list<Snippet> $candidates
     * @param list<string>  $tokens   already lowercased
     * @return list<array{snippet: Snippet, score: float}>
     */
    public function rank(array $candidates, array $tokens): array
    {
        if ($tokens === []) {
            usort($candidates, fn (Snippet $a, Snippet $b) => $b->updatedAt <=> $a->updatedAt);
            return array_map(fn (Snippet $s) => ['snippet' => $s, 'score' => 0.0], $candidates);
        }
        // Scoring path: implemented in Task 7.
        return [];
    }
}
