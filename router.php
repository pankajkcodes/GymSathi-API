<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip optional /api prefix if present
if (strpos($uri, '/api') === 0) {
    $uri = substr($uri, 4);
}

// Canonical file in public/
$file = __DIR__ . '/public' . $uri;

// If it's a file directly under public/
if (is_file($file)) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        exit;
    }
    return false; // static asset
}

// Support endpoints called without /v1 prefix (e.g. /admin/dashboard.php -> public/v1/admin/dashboard.php)
$v1File = __DIR__ . '/public/v1' . $uri;
if (is_file($v1File)) {
    if (pathinfo($v1File, PATHINFO_EXTENSION) === 'php') {
        require $v1File;
        exit;
    }
    return false;
}

// Fallback to index.php
require __DIR__ . '/public/index.php';
