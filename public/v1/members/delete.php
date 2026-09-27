<?php
// POST { gym_id, member_id } — soft delete (payments and attendance history are kept).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');
$data = validate(input(), ['member_id' => 'required|int']);

MemberService::delete($gym, $data['member_id']);
sendSuccess(null, "Member deleted");
