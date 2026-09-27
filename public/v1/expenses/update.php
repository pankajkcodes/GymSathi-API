<?php
// POST { gym_id, expense_id, title, amount, expense_date, category?, description? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage');
$data = validate(input(), ['expense_id' => 'required|int'] + [
    'title' => 'required|string|maxlen:255',
    'amount' => 'required|numeric|min:0.01',
    'expense_date' => 'required|date',
    'category' => 'string|maxlen:100',
    'description' => 'string',
]);

if (!dbValue("SELECT 1 FROM expenses WHERE id = ? AND gym_id = ?", [$data['expense_id'], $gym['gym_id']])) {
    throw new NotFoundException("Expense not found");
}
dbRun("UPDATE expenses SET title = ?, amount = ?, category = ?, description = ?, expense_date = ? WHERE id = ?",
    [$data['title'], $data['amount'], $data['category'] ?? 'General', $data['description'], $data['expense_date'], $data['expense_id']]);
sendSuccess(null, "Expense updated");
