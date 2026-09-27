<?php
// POST { email } — step 1 of password reset: email a 6-digit code.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), ['email' => 'required|email']);
RateLimit::hit('otp_send', RateLimit::OTP_SEND, $data['email']);

$user = UserService::findOrAdoptByEmail($data['email']);
if (!$user) {
    throw new NotFoundException("No account found with this email");
}

$debug = OtpService::send($data['email'], OtpService::RESET_PASSWORD, "Password Reset OTP - Gym Sathi", $user['name']);
sendSuccess($debug, "OTP sent to your email");
