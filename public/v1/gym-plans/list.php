<?php
// GET ?gym_id= — membership plans the gym sells (table: plans).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'plans.read');

sendSuccess(dbAll("SELECT * FROM plans WHERE gym_id = ? ORDER BY price", [$gym['gym_id']]));
