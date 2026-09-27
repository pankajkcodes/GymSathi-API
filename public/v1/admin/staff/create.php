<?php
// POST { gym_ids: [..], email, name, phone?, password?, role?, permissions? }
// The list is the person's complete set of gyms: they're removed from gyms not listed.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$data = validate(input(), [
    'gym_ids' => 'required|array',
    'email' => 'required|email',
    'name' => 'required|string|maxlen:100',
    'phone' => 'string|maxlen:20',
    'password' => 'string',
    'role' => 'string',
    'permissions' => 'array',
]);

$gymPks = array_map(function ($gymId) {
    $gym = GymService::find($gymId);
    if (!$gym) {
        throw new NotFoundException("Gym not found: $gymId");
    }
    return $gym['id'];
}, $data['gym_ids']);

$staffId = StaffService::assign($gymPks, $data, $data['role'] ?? 'staff', $data['permissions']);
sendSuccess(['user_id' => $staffId], "Staff member saved");
