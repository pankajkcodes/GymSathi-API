<?php
// POST { email, password } → user + gyms + token
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'email' => 'required|email',
    'password' => 'required|string',
]);
RateLimit::hit('login', RateLimit::LOGIN, $data['email']);

$user = UserService::findOrAdoptByEmail($data['email']);
if (!$user || empty($user['password']) || !password_verify($data['password'], $user['password'])) {
    throw new UnauthorizedException("Invalid email or password");
}
RateLimit::clear('login', $data['email']);
UserService::assertActive($user);

if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    dbRun("UPDATE users SET password = ? WHERE id = ?", [password_hash($data['password'], PASSWORD_DEFAULT), $user['id']]);
}

sendSuccess(UserService::loginResponse($user), "Welcome back, {$user['name']}!");
