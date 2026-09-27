<?php
// POST — log out by clearing the admin_token HttpOnly cookie
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

setcookie('admin_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $isLocal ? '' : '.gymsathi.in',
    'secure' => !$isLocal,
    'httponly' => true,
    'samesite' => 'Lax',
]);

sendSuccess(null, "Logged out successfully");
