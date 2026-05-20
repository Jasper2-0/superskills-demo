<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Validation\SnippetValidator;
use PHPUnit\Framework\TestCase;

final class SnippetValidatorTest extends TestCase
{
    private SnippetValidator $v;
    protected function setUp(): void { $this->v = new SnippetValidator(); }

    public function testValidPayloadReturnsNoErrors(): void
    {
        $errors = $this->v->validate(['title' => 'T', 'language' => 'php', 'body' => 'b', 'tags' => ['php']]);
        self::assertSame([], $errors);
    }

    public function testMissingTitleIsAnError(): void
    {
        $errors = $this->v->validate(['language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }

    public function testEmptyTitleAfterTrimIsAnError(): void
    {
        $errors = $this->v->validate(['title' => '   ', 'language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }

    public function testEmptyBodyIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => '', 'tags' => []]);
        self::assertContains('body is required', $errors);
    }

    public function testUnknownLanguageIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'rust', 'body' => 'b', 'tags' => []]);
        self::assertContains('language must be one of: php, javascript, python, sql, bash, html, css, json, markdown, other', $errors);
    }

    public function testTagsMustBeArray(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => 'php']);
        self::assertContains('tags must be an array of strings', $errors);
    }

    public function testTagsArrayElementsMustBeStrings(): void
    {
        $errors = $this->v->validate(['title' => 't', 'language' => 'php', 'body' => 'b', 'tags' => ['ok', 123]]);
        self::assertContains('tags must be an array of strings', $errors);
    }

    public function testNonStringTitleIsAnError(): void
    {
        $errors = $this->v->validate(['title' => 123, 'language' => 'php', 'body' => 'b', 'tags' => []]);
        self::assertContains('title is required', $errors);
    }
}
