<?php
// GET ?gym_id= — one gym's profile. Starts the free trial for gyms that never had a subscription.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), null, false);

sendSuccess(GymService::profile(GymService::ensureTrial($gym)) + ['your_role' => $gym['role']]);
