<?php

class Mailer
{
    public static function send($to, $subject, $body)
    {
        // Strip line breaks so user input can't inject extra mail headers.
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);

        $headers = "From: " . config('mail_from') . "\r\n" .
            "Reply-To: " . config('mail_reply_to') . "\r\n" .
            "Content-Type: text/plain; charset=UTF-8";

        $sent = @mail($to, $subject, $body, $headers);
        if (!$sent) {
            error_log("mail() failed: $subject");
        }
        return $sent;
    }

    public static function sendTemporaryPassword($email, $name, $role, $password)
    {
        return self::send($email, "Your Gym Sathi account",
            "Hello $name,\n\nYou have been added as $role on Gym Sathi.\n\n" .
            "Login email: $email\nTemporary password: $password\n\n" .
            "Please change it after your first login.\n\nRegards,\nGym Sathi Team");
    }
}
