<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Languages;
use PHPUnit\Framework\TestCase;

final class LanguagesTest extends TestCase
{
    public function testAllReturnsTheTenSupportedLanguages(): void
    {
        $expected = ['php', 'javascript', 'python', 'sql', 'bash', 'html', 'css', 'json', 'markdown', 'other'];
        self::assertSame($expected, Languages::all());
    }

    public function testIsSupportedReturnsTrueForKnownLanguage(): void
    {
        self::assertTrue(Languages::isSupported('php'));
        self::assertTrue(Languages::isSupported('markdown'));
    }

    public function testIsSupportedReturnsFalseForUnknown(): void
    {
        self::assertFalse(Languages::isSupported('rust'));
        self::assertFalse(Languages::isSupported('PHP')); // case-sensitive
        self::assertFalse(Languages::isSupported(''));
    }
}
