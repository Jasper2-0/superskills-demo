<?php
declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function ok(mixed $data): void
    {
        self::emit(200, $data);
    }

    public static function created(mixed $data): void
    {
        self::emit(201, $data);
    }

    public static function noContent(): void
    {
        http_response_code(204);
    }

    public static function error(string $message, int $status): void
    {
        self::emit($status, ['error' => $message]);
    }

    private static function emit(int $status, mixed $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
