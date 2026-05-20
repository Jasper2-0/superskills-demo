<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Models\Snippet;
use PHPUnit\Framework\TestCase;

final class SnippetTest extends TestCase
{
    public function testCanBeConstructedWithAllFields(): void
    {
        $s = new Snippet(
            id: 7,
            title: 'PHPUnit setup',
            language: 'php',
            body: '<?php echo "hi";',
            tags: ['php', 'testing'],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T11:00:00Z',
        );

        self::assertSame(7, $s->id);
        self::assertSame('PHPUnit setup', $s->title);
        self::assertSame(['php', 'testing'], $s->tags);
    }

    public function testIdCanBeNullForUnsavedSnippets(): void
    {
        $s = new Snippet(
            id: null,
            title: 'New',
            language: 'php',
            body: 'x',
            tags: [],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T10:00:00Z',
        );
        self::assertNull($s->id);
    }

    public function testToArrayProducesJsonSafeShape(): void
    {
        $s = new Snippet(
            id: 1,
            title: 't',
            language: 'php',
            body: 'b',
            tags: ['x'],
            createdAt: '2026-05-20T10:00:00Z',
            updatedAt: '2026-05-20T10:00:00Z',
        );

        self::assertSame([
            'id' => 1,
            'title' => 't',
            'language' => 'php',
            'body' => 'b',
            'tags' => ['x'],
            'created_at' => '2026-05-20T10:00:00Z',
            'updated_at' => '2026-05-20T10:00:00Z',
        ], $s->toArray());
    }
}
