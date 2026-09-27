<?php
// POST { email } — email a code to confirm the address before registering.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), ['email' => 'required|email']);
RateLimit::hit('otp_send', RateLimit::OTP_SEND, $data['email']);

if (UserService::emailTaken($data['email'])) {
    throw new ConflictException("Email already registered. Please log in.");
}

$debug = OtpService::send($data['email'], OtpService::VERIFY_EMAIL, "Verify Your Email - Gym Sathi");
sendSuccess($debug, "OTP sent to your email");
