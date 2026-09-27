<?php
// POST { email, otp }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'email' => 'required|email',
    'otp' => 'required|string',
]);
RateLimit::hit('otp_verify', RateLimit::OTP_VERIFY, $data['email']);

OtpService::requireValid($data['email'], $data['otp'], OtpService::VERIFY_EMAIL);
sendSuccess(null, "Email verified");
