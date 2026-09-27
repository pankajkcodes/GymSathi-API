<?php
// POST { gym_id, member_id, status } — status: active | blocked | inactive
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');
$data = validate(input(), [
    'member_id' => 'required|int',
    'status' => 'required|in:active,blocked,inactive',
]);

MemberService::setStatus($gym, $data['member_id'], $data['status']);
sendSuccess(['member_id' => $data['member_id'], 'status' => $data['status']], "Member status updated");
