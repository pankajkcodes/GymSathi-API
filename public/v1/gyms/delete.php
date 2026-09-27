<?php
// POST { gym_id } — owner only. Soft delete: hidden from everyone, data kept (support can restore).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'owner', false);

if (GymService::ownedCount($user['id']) <= 1) {
    throw new ValidationException("You can't delete your only gym");
}
dbRun("UPDATE gyms SET status = 'deleted' WHERE id = ?", [$gym['id']]);

sendSuccess(['gyms' => GymService::forUser($user['id'])], "Gym deleted");
