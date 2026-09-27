<?php
// POST { email, otp, new_password } — step 2 of password reset.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'email' => 'required|email',
    'otp' => 'required|string',
    'new_password' => 'required|string',
]);
if (strlen($data['new_password']) < 6) {
    throw new ValidationException("Password must be at least 6 characters long");
}
RateLimit::hit('otp_verify', RateLimit::OTP_VERIFY, $data['email']);

$user = UserService::findOrAdoptByEmail($data['email']);
if (!$user) {
    throw new UnauthorizedException("Invalid or expired OTP");
}
OtpService::requireValid($data['email'], $data['otp'], OtpService::RESET_PASSWORD);

dbRun("UPDATE users SET password = ? WHERE id = ?", [password_hash($data['new_password'], PASSWORD_DEFAULT), $user['id']]);
sendSuccess(null, "Password reset successful. You can now log in with your new password.");
