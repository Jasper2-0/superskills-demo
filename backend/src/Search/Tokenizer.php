<?php
declare(strict_types=1);

namespace App\Search;

final class Tokenizer
{
    /** @return list<string> */
    public function tokenize(string $query): array
    {
        $lower = mb_strtolower(trim($query), 'UTF-8');
        if ($lower === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $lower);
        if ($parts === false) {
            return [];
        }
        $kept = [];
        foreach ($parts as $p) {
            if ($p === '' || mb_strlen($p, 'UTF-8') < 2) {
                continue;
            }
            if (!in_array($p, $kept, true)) {
                $kept[] = $p;
            }
        }
        return $kept;
    }
}
