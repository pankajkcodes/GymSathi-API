<?php
// GET ?gym_id=&date=YYYY-MM-DD&filter=all|present|absent|active|expired&search=&page=&limit=
// Members with is_present / check_in_time / check_out_time for that date (IST date, default today).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'members.read');
$q = validate($_GET, ['date' => 'date', 'filter' => 'string', 'search' => 'string']);
[$page, $limit, $offset] = page(20, 500);

[$rows, $total] = AttendanceService::daily($gym, $q['date'] ?? attendanceToday(), $q['filter'] ?? 'all', $q['search'], $limit, $offset);
sendSuccess(paginated($rows, $total, $page, $limit) + ['date' => $q['date'] ?? attendanceToday()]);
