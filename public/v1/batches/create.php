<?php
// POST { gym_id, batch_name, start_time, end_time }   (times as HH:MM)
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'batches.write');
$data = validate(input(), [
    'batch_name' => 'required|string|maxlen:100',
    'start_time' => 'required|time',
    'end_time' => 'required|time',
]);

dbRun("INSERT INTO batches (gym_id, batch_name, start_time, end_time) VALUES (?, ?, ?, ?)",
    [$gym['gym_id'], $data['batch_name'], $data['start_time'], $data['end_time']]);
sendSuccess(['id' => (int)db()->lastInsertId()], "Batch added");
