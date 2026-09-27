<?php
// POST { gym_id, notification_id }  or  { gym_id, all: true }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), null, false);
$data = validate(input(), ['notification_id' => 'int', 'all' => 'bool']);

if ($data['all']) {
    dbRun("UPDATE notifications SET is_read = 1 WHERE gym_id = ?", [$gym['id']]);
} elseif ($data['notification_id']) {
    dbRun("UPDATE notifications SET is_read = 1 WHERE id = ? AND gym_id = ?", [$data['notification_id'], $gym['id']]);
} else {
    throw new ValidationException("Send notification_id or all: true");
}
sendSuccess(null, "Marked as read");
