<?php
// POST { name?, phone?, email? } — logged-in user edits their own account.
// (Owner name/email used to be edited on the gym; they belong to the person.)
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$data = validate(input(), [
    'name' => 'string|maxlen:100',
    'phone' => 'string|maxlen:20',
    'email' => 'email',
]);

$changes = array_filter($data, function ($v) { return $v !== null; });
if (isset($changes['email']) && $changes['email'] !== strtolower($user['email']) && UserService::emailTaken($changes['email'])) {
    throw new ConflictException("Email already in use");
}
if (empty($changes)) {
    throw new ValidationException("Nothing to update");
}

$set = implode(', ', array_map(function ($col) { return "$col = ?"; }, array_keys($changes)));
dbRun("UPDATE users SET $set WHERE id = ?", array_merge(array_values($changes), [$user['id']]));

if (isset($changes['name'])) {
    // Keep the legacy display column on the user's own gyms in sync (the old API still reads it).
    dbRun("UPDATE gyms g JOIN user_gym_roles r ON r.gym_id = g.id AND r.user_id = ? AND r.role = 'owner' SET g.owner_name = ?", [$user['id'], $changes['name']]);
}

sendSuccess(UserService::loginResponse(UserService::findByEmail($changes['email'] ?? $user['email'])), "Profile updated");
