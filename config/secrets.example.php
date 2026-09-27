<?php
// Copy this file to secrets.php and fill in real values.
// secrets.php is git-ignored and lives OUTSIDE the public web root.
return [
    // "production" hides error details. "local" includes OTPs in responses when mail() fails.
    'app_env' => 'production',

    'db' => [
        'host' => 'localhost',
        'name' => 'database_name',
        'user' => 'database_user',
        'pass' => 'database_password',
    ],

    // Long random strings. Generate with: php -r "echo bin2hex(random_bytes(32));"
    // Changing jwt_secret logs every user out.
    'jwt_secret'  => 'change-me',
    'cron_secret' => 'change-me',

    'razorpay' => [
        'key_id'     => 'rzp_live_xxx',
        'key_secret' => 'xxx',
    ],

    // OAuth client IDs whose Google ID tokens we accept (Android, iOS and web client IDs).
    // Found in Firebase console / Google Cloud → Credentials.
    'google_client_ids' => [
        // 'xxxxxxxx.apps.googleusercontent.com',
    ],

    // Browser origins allowed to call the API (mobile apps don't need CORS).
    'cors_origins' => [
        'https://gymsathi.in',
        'https://www.gymsathi.in',
        'https://admin.gymsathi.in',
    ],

    'mail_from'     => 'no-reply@gymsathi.in',
    'mail_reply_to' => 'gymsathi.app@gmail.com',

    // Optional, e.g. 'Asia/Kolkata'. Empty keeps the server default (same as the old API).
    'timezone' => '',
];
