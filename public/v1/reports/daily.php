<?php
// GET ?gym_id=&date=YYYY-MM-DD — one day's numbers. Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');
$q = validate($_GET, ['date' => 'date']);

sendSuccess(ReportService::daily($gym['gym_id'], $q['date'] ?? today()));
