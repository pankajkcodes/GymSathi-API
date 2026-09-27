<?php
// POST { gym_ids: [..], email, name, phone?, password?, role?, permissions? } — owner only.
// Adds the person (or updates them, found by email) on the given gyms.
// No password → a temporary one is emailed. permissions: { members|plans|batches: { read, write } }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$data = validate(input(), [
    'gym_ids' => 'required|array',
    'email' => 'required|email',
    'name' => 'required|string|maxlen:100',
    'phone' => 'string|maxlen:20',
    'password' => 'string',
    'role' => 'string',
    'permissions' => 'array',
]);

$gymPks = array_map(function ($gymId) use ($user) {
    return requireGym($user, $gymId, 'owner', false)['id'];
}, $data['gym_ids']);

$staffId = StaffService::assign($gymPks, $data, $data['role'] ?? 'staff', $data['permissions'], $user['id']);
sendSuccess(['user_id' => $staffId, 'gym_ids' => array_values(array_unique($gymPks))], "Staff member saved");
