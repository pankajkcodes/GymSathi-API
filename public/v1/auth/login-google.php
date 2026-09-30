<?php
// POST { id_token } — Google ID token from the google_sign_in plugin.
// Registered → user + gyms + token.  Not registered → { registered: false, email, name, … }
// and the app continues to signup/register.php with the same id_token.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
RateLimit::hit('login_google', RateLimit::GOOGLE_LOGIN);

$google = GoogleAuth::verify(input('id_token'), input('access_token'));

$user = dbOne("SELECT * FROM users WHERE google_id = ?", [$google['sub']]);
if (!$user) {
    // Google verified this person owns the email, so it's safe to link it to the account.
    $user = UserService::findOrAdoptByEmail($google['email']);
    if ($user) {
        $type = $user['registration_type'] === 'google' ? 'google' : 'both';
        dbRun("UPDATE users SET google_id = ?, registration_type = ? WHERE id = ?", [$google['sub'], $type, $user['id']]);
    }
}

if (!$user) {
    sendSuccess([
        'registered' => false,
        'email' => $google['email'],
        'display_name' => $google['name'],
        'photo_url' => $google['picture'],
    ], 'Google account verified. Please complete your gym registration.');
}

UserService::assertActive($user);
sendSuccess(UserService::loginResponse($user) + ['registered' => true], "Welcome back, {$user['name']}!");
