<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database;
use App\Http\Controllers\LanguagesController;
use App\Http\JsonResponse;
use App\Http\Router;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Static-file fallthrough for non-API paths.
if (!str_starts_with($path, '/api/')) {
    $publicCandidate = __DIR__ . $path;
    if ($path !== '/' && is_file($publicCandidate)) {
        return false; // serve from public/ via built-in server
    }
    // Frontend-folder lookup with path-traversal protection (from Task 1 review).
    $frontendRoot = realpath(dirname(__DIR__, 2) . '/frontend');
    $requested    = $path === '/' ? '/index.html' : $path;
    $resolved     = $frontendRoot !== false ? realpath($frontendRoot . $requested) : false;
    if ($resolved !== false && str_starts_with($resolved, $frontendRoot . DIRECTORY_SEPARATOR)) {
        $mime = match (pathinfo($resolved, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($resolved);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// API dispatch.
$dbPath = $_ENV['SNIPPET_DB'] ?? dirname(__DIR__) . '/data/snippets.sqlite';
$db = new Database($dbPath);
$db->migrate();
$pdo = $db->pdo();

$router = new Router();
$router->get('/api/health', fn () => JsonResponse::ok(['status' => 'ok']));
$router->get('/api/languages', fn () => (new LanguagesController())->index());

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (\RuntimeException $e) {
    JsonResponse::error($e->getMessage(), $e->getCode() ?: 500);
}
return true;
