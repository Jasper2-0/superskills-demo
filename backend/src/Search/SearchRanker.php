<?php
declare(strict_types=1);

namespace App\Search;

use App\Models\Snippet;

final class SearchRanker
{
    private const WEIGHT_TITLE = 5.0;
    private const WEIGHT_TITLE_WHOLE_WORD_BONUS = 2.0;
    private const WEIGHT_TAG_EXACT = 4.0;
    private const WEIGHT_TAG_SUBSTRING = 1.5;
    private const WEIGHT_BODY_PER_LINE = 1.0;
    private const RECENCY_PER_DAY = 0.000001;

    /**
     * @param list<Snippet> $candidates
     * @param list<string>  $tokens
     * @return list<array{snippet: Snippet, score: float}>
     */
    public function rank(array $candidates, array $tokens): array
    {
        if ($tokens === []) {
            usort($candidates, fn (Snippet $a, Snippet $b) => $b->updatedAt <=> $a->updatedAt);
            return array_map(fn (Snippet $s) => ['snippet' => $s, 'score' => 0.0], $candidates);
        }

        $scored = [];
        foreach ($candidates as $s) {
            $score = $this->scoreSnippet($s, $tokens);
            if ($score === null) {
                continue;
            }
            $scored[] = ['snippet' => $s, 'score' => $score];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * @param list<string> $tokens
     */
    private function scoreSnippet(Snippet $s, array $tokens): ?float
    {
        $title = mb_strtolower($s->title, 'UTF-8');
        $body = mb_strtolower($s->body, 'UTF-8');
        $tags = array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $s->tags);

        $score = 0.0;
        foreach ($tokens as $tok) {
            $contribution = 0.0;
            $matched = false;

            if (str_contains($title, $tok)) {
                $contribution += self::WEIGHT_TITLE;
                if (preg_match('/(?<![a-z0-9_])' . preg_quote($tok, '/') . '(?![a-z0-9_])/u', $title) === 1) {
                    $contribution += self::WEIGHT_TITLE_WHOLE_WORD_BONUS;
                }
                $matched = true;
            }

            if (in_array($tok, $tags, true)) {
                $contribution += self::WEIGHT_TAG_EXACT;
                $matched = true;
            } else {
                foreach ($tags as $tag) {
                    if (str_contains($tag, $tok)) {
                        $contribution += self::WEIGHT_TAG_SUBSTRING;
                        $matched = true;
                        break;
                    }
                }
            }

            $bodyHits = 0;
            $seenLines = [];
            foreach (explode("\n", $body) as $line) {
                if (str_contains($line, $tok) && !isset($seenLines[$line])) {
                    $seenLines[$line] = true;
                    $bodyHits++;
                }
            }
            if ($bodyHits > 0) {
                $contribution += $bodyHits * self::WEIGHT_BODY_PER_LINE;
                $matched = true;
            }

            if (!$matched) {
                return null;
            }
            $score += $contribution;
        }

        $ts = strtotime($s->updatedAt);
        if ($ts !== false) {
            $daysSinceEpoch = intdiv($ts, 86400);
            $score += $daysSinceEpoch * self::RECENCY_PER_DAY;
        }
        return $score;
    }
}
