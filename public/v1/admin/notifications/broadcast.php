<?php
// POST { title, message, target?: 'all'|'active'|'trial', type?: 'announcement'|'alert'|'system' }
// Broadcast an in-app and push announcement to gym owners.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$raw = input();
$data = validate($raw, [
    'title' => 'required|string|maxlen:255',
    'message' => 'required|string',
    'target' => 'string|in:all,active,trial',
    'type' => 'string|maxlen:50',
]);

$target = $data['target'] ?? 'all';
$type = $data['type'] ?? 'system';

$where = ["status != 'deleted'"];
if ($target === 'active') {
    $where[] = "subscription_expiry >= CURDATE()";
} elseif ($target === 'trial') {
    $where[] = "(subscription_expiry IS NULL OR subscription_expiry < CURDATE())";
}

$gyms = dbAll("SELECT id, gym_id, gym_name FROM gyms WHERE " . implode(' AND ', $where));

$sentGyms = 0;
$totalPushed = 0;

foreach ($gyms as $g) {
    try {
        $res = NotificationService::notifyGym($g['id'], $data['title'], $data['message'], $type, null, 'notifications');
        if (!empty($res['saved'])) {
            $sentGyms++;
            $totalPushed += ($res['sent'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Broadcast failed for gym {$g['gym_id']}: " . $e->getMessage());
    }
}

sendSuccess([
    'target' => $target,
    'total_gyms_targeted' => count($gyms),
    'gyms_notified' => $sentGyms,
    'devices_pushed' => $totalPushed,
], "Announcement broadcasted to {$sentGyms} gyms ({$totalPushed} device pushes sent)");
