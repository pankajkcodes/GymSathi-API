<?php
// POST { current_password, new_password } — logged-in user changes their own password.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$data = validate(input(), [
    'current_password' => 'string',
    'new_password' => 'required|string',
]);
if (strlen($data['new_password']) < 6) {
    throw new ValidationException("Password must be at least 6 characters long");
}

$hash = dbValue("SELECT password FROM users WHERE id = ?", [$user['id']]);
// Google-only accounts have no password yet and may set one.
if ($hash && !password_verify((string)$data['current_password'], $hash)) {
    throw new ValidationException("Current password is incorrect");
}

dbRun("UPDATE users SET password = ?, registration_type = IF(registration_type = 'google', 'both', registration_type) WHERE id = ?",
    [password_hash($data['new_password'], PASSWORD_DEFAULT), $user['id']]);
sendSuccess(null, "Password changed");
