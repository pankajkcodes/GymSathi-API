<?php
// POST { gym_id, batch_id } — members in the batch stay, just without a batch.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'batches.write');
$data = validate(input(), ['batch_id' => 'required|int']);

dbTransaction(function () use ($gym, $data) {
    if (dbRun("DELETE FROM batches WHERE id = ? AND gym_id = ?", [$data['batch_id'], $gym['gym_id']])->rowCount() === 0) {
        throw new NotFoundException("Batch not found");
    }
    dbRun("UPDATE members SET batch_id = NULL WHERE batch_id = ? AND gym_id = ?", [$data['batch_id'], $gym['gym_id']]);
});
sendSuccess(null, "Batch deleted");
