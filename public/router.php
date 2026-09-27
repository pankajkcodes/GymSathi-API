<?php
// Document root is public/
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1. Direct file in public/ (e.g. /v1/admin/dashboard.php or images)
if ($uri !== '/' && is_file($file)) {
    return false; // serve as-is
}

// 2. If called without /v1 (e.g. /admin/dashboard.php), map to /v1/...
if (strpos($uri, '/v1/') !== 0) {
    $v1File = __DIR__ . '/v1' . $uri;
    if (is_file($v1File)) {
        require $v1File;
        exit;
    }
}

// 3. Fallback to index.php
require __DIR__ . '/index.php';
