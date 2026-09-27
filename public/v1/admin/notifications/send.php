<?php
// POST { gym_id, title, message, type?, reference_id?, screen? } — save + push to the gym's owner/managers.
// Admin token or cron secret.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdminOrCron();

$d = validate(input(), [
    'gym_id' => 'required|string',
    'title' => 'required|string|maxlen:255',
    'message' => 'required|string',
    'type' => 'string|maxlen:50',
    'reference_id' => 'string|maxlen:50',
    'screen' => 'string|maxlen:50',
]);

$result = NotificationService::notifyGym($d['gym_id'], $d['title'], $d['message'], $d['type'] ?? 'system', $d['reference_id'], $d['screen']);
sendSuccess($result, "Notification sent to {$result['sent']} device(s)");
