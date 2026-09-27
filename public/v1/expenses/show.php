<?php
// GET ?gym_id=&expense_id=
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');

$expense = dbOne("SELECT * FROM expenses WHERE id = ? AND gym_id = ?", [(int)query('expense_id'), $gym['gym_id']]);
if (!$expense) {
    throw new NotFoundException("Expense not found");
}
sendSuccess($expense);
