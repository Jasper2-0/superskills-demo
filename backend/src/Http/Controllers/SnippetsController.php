<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Models\Snippet;
use App\Repositories\SnippetRepository;
use App\Search\SearchRanker;
use App\Search\Tokenizer;
use App\Validation\SnippetValidator;

final class SnippetsController
{
    public function __construct(
        private readonly SnippetRepository $snippets,
        private readonly SnippetValidator $validator,
        private readonly Tokenizer $tokenizer,
        private readonly SearchRanker $ranker,
    ) {}

    public function index(): void
    {
        $q = (string) ($_GET['q'] ?? '');
        $language = $_GET['language'] ?? null;
        if ($language === '' || !is_string($language)) {
            $language = null;
        }
        $rawTags = $_GET['tag'] ?? [];
        if (is_string($rawTags)) {
            $rawTags = [$rawTags];
        }
        if (!is_array($rawTags)) {
            $rawTags = [];
        }
        $tags = array_values(array_filter(array_map(fn ($t) => is_string($t) ? mb_strtolower(trim($t), 'UTF-8') : '', $rawTags), fn ($t) => $t !== ''));

        $limit = (int) ($_GET['limit'] ?? 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 200) $limit = 200;

        $candidates = $this->snippets->findFiltered($language, $tags);
        $tokens = $this->tokenizer->tokenize($q);

        $ranked = $this->ranker->rank($candidates, $tokens);
        $sliced = array_slice($ranked, 0, $limit);

        $results = array_map(function (array $row) use ($tokens) {
            $payload = $row['snippet']->toArray();
            if ($tokens !== []) {
                $payload['score'] = round($row['score'], 4);
            }
            return $payload;
        }, $sliced);

        JsonResponse::ok([
            'results' => $results,
            'total' => count($ranked),
        ]);
    }

    public function show(int $id): void
    {
        $s = $this->snippets->findById($id);
        if ($s === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        JsonResponse::ok($s->toArray());
    }

    public function store(): void
    {
        $data = $this->parseBody();
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            JsonResponse::error(implode('; ', $errors), 400);
            return;
        }
        $created = $this->snippets->create([
            'title' => trim((string) $data['title']),
            'language' => (string) $data['language'],
            'body' => (string) $data['body'],
            'tags' => array_values(array_map('strval', (array) ($data['tags'] ?? []))),
        ]);
        JsonResponse::created($created->toArray());
    }

    public function update(int $id): void
    {
        if ($this->snippets->findById($id) === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        $data = $this->parseBody();
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            JsonResponse::error(implode('; ', $errors), 400);
            return;
        }
        $updated = $this->snippets->update($id, [
            'title' => trim((string) $data['title']),
            'language' => (string) $data['language'],
            'body' => (string) $data['body'],
            'tags' => array_values(array_map('strval', (array) ($data['tags'] ?? []))),
        ]);
        JsonResponse::ok($updated->toArray());
    }

    public function destroy(int $id): void
    {
        if ($this->snippets->findById($id) === null) {
            JsonResponse::error('Snippet not found', 404);
            return;
        }
        $this->snippets->delete($id);
        JsonResponse::noContent();
    }

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
