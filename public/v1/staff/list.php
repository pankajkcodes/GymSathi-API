<?php
// GET ?gym_id= — staff of a gym (owner excluded). Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage', false);

sendSuccess(StaffService::listForGym($gym['id']));
