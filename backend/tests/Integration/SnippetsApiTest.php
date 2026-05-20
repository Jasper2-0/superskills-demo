<?php
declare(strict_types=1);

namespace App\Tests\Integration;

final class SnippetsApiTest extends IntegrationCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $r = $this->request('GET', '/api/health');
        self::assertSame(200, $r['status']);
        self::assertSame(['status' => 'ok'], $r['body']);
    }

    public function testLanguagesEndpointListsTenLanguages(): void
    {
        $r = $this->request('GET', '/api/languages');
        self::assertSame(200, $r['status']);
        self::assertCount(10, $r['body']['languages']);
        self::assertContains('php', $r['body']['languages']);
    }

    public function testFullCrudRoundTrip(): void
    {
        // Create
        $create = $this->request('POST', '/api/snippets', [
            'title' => 'Echo hi',
            'language' => 'php',
            'body' => '<?php echo "hi";',
            'tags' => ['php', 'demo'],
        ]);
        self::assertSame(201, $create['status']);
        $id = (int) $create['body']['id'];
        self::assertGreaterThan(0, $id);
        self::assertSame(['demo', 'php'], $create['body']['tags']);

        // Read
        $read = $this->request('GET', "/api/snippets/$id");
        self::assertSame(200, $read['status']);
        self::assertSame('Echo hi', $read['body']['title']);

        // List (no filters)
        $list = $this->request('GET', '/api/snippets');
        self::assertSame(200, $list['status']);
        self::assertSame(1, $list['body']['total']);

        // Search
        $search = $this->request('GET', '/api/snippets?q=hi');
        self::assertSame(200, $search['status']);
        self::assertSame(1, $search['body']['total']);
        self::assertArrayHasKey('score', $search['body']['results'][0]);

        // Update
        $update = $this->request('PUT', "/api/snippets/$id", [
            'title' => 'Echo bye',
            'language' => 'php',
            'body' => '<?php echo "bye";',
            'tags' => ['php'],
        ]);
        self::assertSame(200, $update['status']);
        self::assertSame('Echo bye', $update['body']['title']);
        self::assertSame(['php'], $update['body']['tags']);

        // Tags endpoint reflects current state (no orphan 'demo')
        $tags = $this->request('GET', '/api/tags');
        self::assertSame(200, $tags['status']);
        self::assertSame([['name' => 'php', 'count' => 1]], $tags['body']['tags']);

        // Delete
        $del = $this->request('DELETE', "/api/snippets/$id");
        self::assertSame(204, $del['status']);

        $gone = $this->request('GET', "/api/snippets/$id");
        self::assertSame(404, $gone['status']);
    }

    public function testCreateWithInvalidPayloadReturns400(): void
    {
        $r = $this->request('POST', '/api/snippets', ['title' => '', 'language' => 'rust', 'body' => '', 'tags' => []]);
        self::assertSame(400, $r['status']);
        self::assertStringContainsString('title is required', $r['body']['error']);
        self::assertStringContainsString('body is required', $r['body']['error']);
        self::assertStringContainsString('language must be one of', $r['body']['error']);
    }

    public function testSearchAndsAcrossTagFilters(): void
    {
        $this->request('POST', '/api/snippets', ['title' => 'only x', 'language' => 'php', 'body' => 'b', 'tags' => ['x']]);
        $this->request('POST', '/api/snippets', ['title' => 'x and y', 'language' => 'php', 'body' => 'b', 'tags' => ['x', 'y']]);

        $r = $this->request('GET', '/api/snippets?tag=x&tag=y');
        self::assertSame(200, $r['status']);
        self::assertSame(1, $r['body']['total']);
        self::assertSame('x and y', $r['body']['results'][0]['title']);
    }
}
