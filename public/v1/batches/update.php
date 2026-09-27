<?php
// POST { gym_id, batch_id, batch_name, start_time, end_time }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'batches.write');
$data = validate(input(), ['batch_id' => 'required|int'] + [
    'batch_name' => 'required|string|maxlen:100',
    'start_time' => 'required|time',
    'end_time' => 'required|time',
]);

if (!dbValue("SELECT 1 FROM batches WHERE id = ? AND gym_id = ?", [$data['batch_id'], $gym['gym_id']])) {
    throw new NotFoundException("Batch not found");
}
dbRun("UPDATE batches SET batch_name = ?, start_time = ?, end_time = ? WHERE id = ?",
    [$data['batch_name'], $data['start_time'], $data['end_time'], $data['batch_id']]);
sendSuccess(null, "Batch updated");
