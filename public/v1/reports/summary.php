<?php
// GET ?gym_id= — dashboard: member counts (+ this month's revenue/expenses for owner/manager).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'));

sendSuccess(ReportService::summary($gym, hasFullGymAccess($gym)));
