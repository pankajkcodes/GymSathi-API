<?php
// GET ?gym_id=
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'batches.read');

sendSuccess(dbAll("SELECT * FROM batches WHERE gym_id = ? ORDER BY start_time", [$gym['gym_id']]));
