<?php
declare(strict_types=1);

namespace App\Models;

final class Snippet
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $language,
        public readonly string $body,
        public readonly array $tags,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * @return array{id: ?int, title: string, language: string, body: string, tags: list<string>, created_at: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'language' => $this->language,
            'body' => $this->body,
            'tags' => $this->tags,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
