<?php
// POST { token } — stop push notifications to this phone. Call on logout.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), ['token' => 'required|string']);
$payload = currentTokenPayload();
if (!$payload) {
    throw new UnauthorizedException("Authentication required");
}

$column = $payload['typ'] === 'member' ? 'member_id' : 'user_id';
dbRun("DELETE FROM fcm_tokens WHERE token = ? AND $column = ?", [$data['token'], (int)$payload['sub']]);
sendSuccess(null, "Device unregistered");
