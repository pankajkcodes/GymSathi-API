<?php
// GET — gyms the logged-in user can open (for the gym switcher).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
sendSuccess(GymService::forUser($user['id']));
