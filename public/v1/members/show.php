<?php
// GET ?gym_id=&member_id=
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'members.read');

$memberId = query('member_id');
sendSuccess(MemberService::find($gym, $memberId));
