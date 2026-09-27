<?php
// POST { token, title, body } — test push to one device.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$input = input();

// If target or message is provided without a single device token, treat as broadcast
if (!empty($input['target']) || (!empty($input['message']) && empty($input['token']))) {
    require __DIR__ . '/broadcast.php';
    exit;
}

$d = validate($input, [
    'token' => 'required|string',
    'title' => 'required|string',
    'body' => 'string',
    'message' => 'string',
]);

$body = $d['body'] ?? $d['message'] ?? '';
$results = FcmClient::sendNotification([$d['token']], $d['title'], $body, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK', 'screen' => 'notifications']);
$response = $results ? json_decode($results[0], true) : null;
if (!isset($response['name'])) {
    throw new HttpException("Push failed", 502, $response);
}
sendSuccess($response, "Push sent");

