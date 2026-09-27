<?php
// POST { date?, gym_ids?: [numeric ids] } — push each active gym its summary for the date.
// Admin token or cron secret.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdminOrCron();

$d = validate(input(), ['date' => 'date', 'gym_ids' => 'array']);
$results = CronService::broadcastDailyReports($d['date'] ?? today(), $d['gym_ids']);

sendSuccess(['date' => $d['date'] ?? today(), 'gyms' => count($results), 'results' => $results], "Daily reports sent");
