<?php
// GET ?gym_id= — latest 200 notifications of the gym.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), null, false);

$rows = dbAll("SELECT * FROM notifications WHERE gym_id = ? ORDER BY created_at DESC LIMIT 200", [$gym['id']]);
foreach ($rows as &$n) {
    $n['screen'] = $n['screen'] ?: NotificationService::screenForType($n['type']);
}
sendSuccess($rows);
