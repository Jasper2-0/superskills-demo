<?php
declare(strict_types=1);

namespace App;

final class Languages
{
    /** @var list<string> */
    private const SUPPORTED = ['php', 'javascript', 'python', 'sql', 'bash', 'html', 'css', 'json', 'markdown', 'other'];

    /** @return list<string> */
    public static function all(): array
    {
        return self::SUPPORTED;
    }

    public static function isSupported(string $language): bool
    {
        return in_array($language, self::SUPPORTED, true);
    }
}
