<?php
// POST { gym_id, user_id } — remove a staff member from a gym (their account stays). Owner only.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'owner', false);
$data = validate(input(), ['user_id' => 'required|int']);

StaffService::remove($data['user_id'], $gym['id']);
sendSuccess(null, "Staff member removed");
