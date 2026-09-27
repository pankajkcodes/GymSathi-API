<?php
// POST { gym_id, members: [{ name, phone, email?, joining_date?, expiry_date?, plan_id? | plan_name?,
//                           batch_id?, paid_amount?, payment_mode? }] }  — max 1000 rows. Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage');
$data = validate(input(), ['members' => 'required|array']);

$result = MemberService::import($gym, $data['members']);
sendSuccess($result, "Import finished: {$result['success_count']} added, {$result['failed_count']} skipped.");
