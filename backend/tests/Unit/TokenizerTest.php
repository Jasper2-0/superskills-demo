<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Search\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    private Tokenizer $t;
    protected function setUp(): void { $this->t = new Tokenizer(); }

    public function testEmptyStringYieldsNoTokens(): void
    {
        self::assertSame([], $this->t->tokenize(''));
        self::assertSame([], $this->t->tokenize('   '));
    }

    public function testLowercasesAndSplitsOnWhitespace(): void
    {
        self::assertSame(['foo', 'bar'], $this->t->tokenize('Foo BAR'));
        self::assertSame(['foo', 'bar'], $this->t->tokenize("Foo\tBAR\n"));
    }

    public function testDropsTokensShorterThanTwoCharacters(): void
    {
        self::assertSame(['foo'], $this->t->tokenize('a foo'));
        self::assertSame(['foo'], $this->t->tokenize('foo i'));
    }

    public function testDedupesRepeatedTokensPreservingOrder(): void
    {
        self::assertSame(['foo', 'bar'], $this->t->tokenize('foo bar foo'));
    }
}
