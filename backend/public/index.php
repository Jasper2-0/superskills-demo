<?php
declare(strict_types=1);

// Static-file fallthrough: PHP's built-in dev server handles non-API paths.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if (!str_starts_with($path, '/api/')) {
    $docRoot = __DIR__;
    $candidate = $docRoot . $path;
    if ($path !== '/' && is_file($candidate)) {
        return false; // let PHP serve the static file
    }
    // Default: serve the SPA shell.
    $frontendRoot = dirname(__DIR__, 2) . '/frontend';
    $requested = $path === '/' ? '/index.html' : $path;
    $candidate = $frontendRoot . $requested;
    if (is_file($candidate)) {
        $mime = match (pathinfo($candidate, PATHINFO_EXTENSION)) {
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            default => 'text/plain',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        return true;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// Temporary health endpoint — real router wired in Task 21.
header('Content-Type: application/json');
if ($path === '/api/health') {
    echo json_encode(['status' => 'ok']);
    return true;
}
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
return true;
