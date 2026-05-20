<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesExactPathMatch(): void
    {
        $router = new Router();
        $router->get('/api/tags', fn () => 'tags-listed');

        $result = $router->dispatch('GET', '/api/tags');
        self::assertSame(['status' => 200, 'result' => 'tags-listed'], [
            'status' => 200,
            'result' => $result,
        ]);
    }

    public function testCapturesIntegerPlaceholder(): void
    {
        $router = new Router();
        $router->get('/api/snippets/{id}', fn ($id) => "snippet:$id");

        self::assertSame('snippet:42', $router->dispatch('GET', '/api/snippets/42'));
    }

    public function testNonIntegerInIntPlaceholderDoesNotMatch(): void
    {
        $router = new Router();
        $router->get('/api/snippets/{id}', fn ($id) => "hit:$id");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not Found');
        $router->dispatch('GET', '/api/snippets/abc');
    }

    public function testUnknownPathThrowsNotFound(): void
    {
        $router = new Router();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not Found');
        $router->dispatch('GET', '/api/missing');
    }

    public function testKnownPathWithWrongMethodThrowsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/api/tags', fn () => 'x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Method Not Allowed');
        $router->dispatch('POST', '/api/tags');
    }

    public function testRegistersAllFourVerbs(): void
    {
        $router = new Router();
        $router->get('/x', fn () => 'g');
        $router->post('/x', fn () => 'p');
        $router->put('/x', fn () => 'u');
        $router->delete('/x', fn () => 'd');

        self::assertSame('g', $router->dispatch('GET', '/x'));
        self::assertSame('p', $router->dispatch('POST', '/x'));
        self::assertSame('u', $router->dispatch('PUT', '/x'));
        self::assertSame('d', $router->dispatch('DELETE', '/x'));
    }
}
