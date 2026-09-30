<?php
// POST { gym_id, member_id, date? } — first scan checks in, second checks out.
// date (YYYY-MM-DD, IST day) lets staff mark a past day; defaults to today.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.read');
$data = validate(input(), [
    'member_id' => 'required|int',
    'date' => 'date',
]);

[$result, $message] = AttendanceService::scan($gym, $data['member_id'], $data['date'] ?? attendanceToday());
sendSuccess($result, $message);
