<?php
// POST (JSON or multipart) { gym_id, name, phone, email?, start_date?, expiry_date?, batch_id?, plan_id?, status? }
// + profile_image (file, multipart only)
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');

$rules = MemberService::FIELD_RULES;
$rules['name'] = 'required|' . $rules['name'];
$rules['phone'] = 'required|' . $rules['phone'];
$data = validate(input(), $rules);

$member = MemberService::create($gym, $data, uploadedFile('profile_image'));
sendSuccess(['id' => $member['id'], 'member_number' => $member['member_number'], 'member_code' => $member['member_code']], "Member added");
