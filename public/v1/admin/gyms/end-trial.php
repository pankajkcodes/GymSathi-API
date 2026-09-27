<?php
// POST { gym_id } — end the gym's free period now (expiry = yesterday).
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$lookup = input('gym_id') ?? input('id');
$gym = GymService::find($lookup, true);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}

dbRun("UPDATE gyms SET subscription_expiry = ? WHERE id = ?", [date('Y-m-d', strtotime('-1 day')), $gym['id']]);
sendSuccess(null, "Free trial ended");
