<?php
// GET ?gym_id=&type=members|payments|expenses|attendance|expiring&format=json|csv&start_date=&end_date=
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');
$q = validate($_GET, [
    'type' => 'required|in:' . implode(',', ReportService::EXPORT_TYPES),
    'format' => 'in:json,csv',
    'start_date' => 'date',
    'end_date' => 'date',
]);

$rows = ReportService::exportRows($gym, $q['type'], $q['start_date'], $q['end_date']);
if ($q['format'] === 'csv') {
    ReportService::streamCsv($rows, "{$q['type']}_report_" . today() . ".csv");
}
sendSuccess($rows);
