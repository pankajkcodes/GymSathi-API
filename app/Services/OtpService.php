<?php
// One-time codes (password_resets table; purpose + attempts columns from migration 001).

class OtpService
{
    const TTL_MINUTES = 10;
    const MAX_ATTEMPTS = 5;

    const RESET_PASSWORD = 'reset';       // owner/staff password reset
    const VERIFY_EMAIL = 'verify';        // email check during signup
    const MEMBER_LOGIN = 'member_login';  // member app login

    /**
     * Create a code and email it.
     *
     * @return array|null response data: null normally; ['otp' => …] only when app_env=local and mail failed
     */
    public static function send($email, $purpose, $subject, $greetingName = null)
    {
        $otp = sprintf('%06d', random_int(0, 999999));
        dbRun("DELETE FROM password_resets WHERE email = ? AND purpose = ?", [$email, $purpose]);
        dbRun("INSERT INTO password_resets (email, otp, expires_at, purpose, attempts) VALUES (?, ?, ?, ?, 0)", [
            $email, $otp, date('Y-m-d H:i:s', strtotime('+' . self::TTL_MINUTES . ' minutes')), $purpose,
        ]);

        $body = "Hello" . ($greetingName ? " $greetingName" : "") . ",\n\n" .
            "Your OTP is: $otp\n\n" .
            "It is valid for " . self::TTL_MINUTES . " minutes.\n\n" .
            "If you did not request this, please ignore this email.\n\nRegards,\nGym Sathi Team";

        if (Mailer::send($email, $subject, $body)) {
            return null;
        }
        if (isLocalEnv()) {
            return ['otp' => $otp];
        }
        throw new HttpException("Could not send the OTP email. Please try again later.", 500);
    }

    /**
     * Check a code (single use). Wrong guesses count; after MAX_ATTEMPTS the code stops working.
     */
    public static function verify($email, $otp, $purpose)
    {
        $row = dbOne("
            SELECT id, otp, attempts FROM password_resets
            WHERE email = ? AND purpose = ? AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ", [$email, $purpose]);

        if (!$row || (int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }
        if (!hash_equals((string)$row['otp'], (string)$otp)) {
            dbRun("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?", [$row['id']]);
            return false;
        }
        dbRun("DELETE FROM password_resets WHERE email = ? AND purpose = ?", [$email, $purpose]);
        return true;
    }

    public static function requireValid($email, $otp, $purpose)
    {
        if (!self::verify($email, $otp, $purpose)) {
            throw new UnauthorizedException("Invalid or expired OTP");
        }
    }
}
