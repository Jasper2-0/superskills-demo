<?php
declare(strict_types=1);

namespace App\Validation;

use App\Languages;

final class SnippetValidator
{
    /**
     * @param array<string, mixed> $data
     * @return list<string> errors; empty list means valid
     */
    public function validate(array $data): array
    {
        $errors = [];

        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors[] = 'title is required';
        }

        $body = $data['body'] ?? null;
        if (!is_string($body) || $body === '') {
            $errors[] = 'body is required';
        }

        $language = $data['language'] ?? null;
        if (!is_string($language) || !Languages::isSupported($language)) {
            $errors[] = 'language must be one of: ' . implode(', ', Languages::all());
        }

        $tags = $data['tags'] ?? [];
        $tagsValid = is_array($tags);
        if ($tagsValid) {
            foreach ($tags as $t) {
                if (!is_string($t)) { $tagsValid = false; break; }
            }
        }
        if (!$tagsValid) {
            $errors[] = 'tags must be an array of strings';
        }

        return $errors;
    }
}
