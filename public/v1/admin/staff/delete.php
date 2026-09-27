<?php
// POST { gym_id, user_id }
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$data = validate(input(), ['user_id' => 'required|int']);
$gym = GymService::find(input('gym_id'), true);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}

StaffService::remove($data['user_id'], $gym['id']);
sendSuccess(null, "Staff member removed");
