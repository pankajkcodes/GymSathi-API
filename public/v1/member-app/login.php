<?php
// POST { email, otp } — member login step 2 → member profile + token.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'email' => 'required|email',
    'otp' => 'required|string',
]);
RateLimit::hit('member_otp_verify', RateLimit::OTP_VERIFY, $data['email']);
OtpService::requireValid($data['email'], $data['otp'], OtpService::MEMBER_LOGIN);

$member = dbOne("SELECT * FROM members WHERE email = ? AND status != 'deleted' ORDER BY (status = 'active') DESC, id DESC LIMIT 1", [$data['email']]);
if (!$member) {
    throw new UnauthorizedException("Invalid or expired OTP");
}
if (in_array($member['status'], ['blocked', 'inactive'], true)) {
    throw new ForbiddenException("Your account is {$member['status']}. Please contact your gym.");
}

$gym = GymService::find($member['gym_id']);
$member['gym_details'] = $gym ? GymService::profile($gym) : null;
$member['role'] = 'member';
$member['token'] = createToken('member', $member['id']);

sendSuccess($member, "Welcome, {$member['name']}!");
