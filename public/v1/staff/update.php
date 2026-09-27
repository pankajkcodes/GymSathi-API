<?php
// POST { gym_id, user_id, role, permissions? } — change one staff member's role/permissions. Owner only.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'owner', false);
$data = validate(input(), [
    'user_id' => 'required|int',
    'role' => 'required|string',
    'permissions' => 'array',
]);

if (!dbValue("SELECT 1 FROM user_gym_roles WHERE user_id = ? AND gym_id = ?", [$data['user_id'], $gym['id']])) {
    throw new NotFoundException("Staff member not found in this gym");
}
StaffService::setRole($data['user_id'], $gym['id'], $data['role'], $data['permissions']);
sendSuccess(null, "Staff member updated");
