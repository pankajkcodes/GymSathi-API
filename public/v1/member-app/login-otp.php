<?php
// POST { email } — member login step 1: email a code.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), ['email' => 'required|email']);
RateLimit::hit('member_otp_send', RateLimit::OTP_SEND, $data['email']);

$member = dbOne("SELECT * FROM members WHERE email = ? AND status != 'deleted' ORDER BY (status = 'active') DESC, id DESC LIMIT 1", [$data['email']]);
if (!$member) {
    throw new NotFoundException("No member found with this email");
}

$debug = OtpService::send($data['email'], OtpService::MEMBER_LOGIN, "Login OTP - Gym Sathi Member", $member['name']);
sendSuccess($debug, "OTP sent to your email");
