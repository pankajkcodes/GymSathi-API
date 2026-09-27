<?php
// POST (JSON or multipart) { gym_id, member_id, ...any member field } + profile_image (file)
// Only the fields you send are changed.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');

$memberId = validate(input(), ['member_id' => 'required|int'])['member_id'];
$sent = array_intersect_key(input(), MemberService::FIELD_RULES);
$data = array_intersect_key(validate(input(), MemberService::FIELD_RULES), $sent);

MemberService::update($gym, $memberId, $data, uploadedFile('profile_image'));
sendSuccess(MemberService::find($gym, $memberId), "Member updated");
