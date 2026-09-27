<?php
// POST { username, password } → admin token (also set as an HttpOnly cookie). Sessions last 1 day.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'username' => 'required|string',
    'password' => 'required|string',
]);
RateLimit::hit('admin_login', RateLimit::ADMIN_LOGIN, $data['username']);

$admin = dbOne("SELECT * FROM admins WHERE username = ? AND role = 'super_admin'", [$data['username']]);
if (!$admin || !password_verify($data['password'], $admin['password'])) {
    throw new UnauthorizedException("Invalid username or password");
}
RateLimit::clear('admin_login', $data['username']);
unset($admin['password']);

$admin['token'] = createToken('admin', $admin['id'], 1);
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
setcookie('admin_token', $admin['token'], [
    'expires' => time() + 86400,
    'path' => '/',
    'domain' => $isLocal ? '' : '.gymsathi.in',
    'secure' => !$isLocal,
    'httponly' => true,
    'samesite' => 'Lax',
]);

sendSuccess(['user' => $admin], "Welcome, {$admin['name']}!");
