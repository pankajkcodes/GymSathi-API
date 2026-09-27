<?php
// GET — the logged-in owner/staff user and the gyms they can open.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$user['gyms'] = GymService::forUser($user['id']);
sendSuccess($user);
