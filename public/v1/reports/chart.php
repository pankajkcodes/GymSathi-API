<?php
// GET ?gym_id=&type=revenue|attendance — revenue per month (6 months) or check-ins per day (30 days).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');
$q = validate($_GET, ['type' => 'required|in:' . implode(',', ReportService::CHART_TYPES)]);

sendSuccess(ReportService::chart($gym, $q['type']));
