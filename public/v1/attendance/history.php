<?php
// GET ?gym_id=&member_id= — one member's visits, newest first.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'members.read');
$member = MemberService::find($gym, (int)query('member_id'));

sendSuccess(AttendanceService::history($member['id'], $gym));
