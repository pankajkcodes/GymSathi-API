<?php
// POST { token, platform? } — register this phone for push notifications (owner/staff or member token).
// Call after every login and when Firebase gives a new token.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'token' => 'required|string|maxlen:4096',
    'platform' => 'in:android,ios,web',
]);

$payload = currentTokenPayload();
if ($payload && $payload['typ'] === 'member') {
    $member = requireMember();
    [$userId, $memberId, $gymRef] = [null, $member['id'], (string)$member['gym_id']];
} else {
    $user = requireUser();
    [$userId, $memberId, $gymRef] = [$user['id'], null, ''];
}

// Same phone may now be logged into a different account: move the token.
dbRun("DELETE FROM fcm_tokens WHERE token = ?", [$data['token']]);
dbRun("INSERT INTO fcm_tokens (user_id, member_id, gym_id, token, platform) VALUES (?, ?, ?, ?, ?)",
    [$userId, $memberId, $gymRef, $data['token'], $data['platform'] ?? 'android']);

sendSuccess(null, "Device registered");
