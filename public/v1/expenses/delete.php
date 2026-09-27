<?php
// POST { gym_id, expense_id }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage');
$data = validate(input(), ['expense_id' => 'required|int']);

if (dbRun("DELETE FROM expenses WHERE id = ? AND gym_id = ?", [$data['expense_id'], $gym['gym_id']])->rowCount() === 0) {
    throw new NotFoundException("Expense not found");
}
sendSuccess(null, "Expense deleted");
