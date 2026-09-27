<?php
// POST { gym_id, title, amount, expense_date, category?, description? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage');
$data = validate(input(), [
    'title' => 'required|string|maxlen:255',
    'amount' => 'required|numeric|min:0.01',
    'expense_date' => 'required|date',
    'category' => 'string|maxlen:100',
    'description' => 'string',
]);

dbRun("INSERT INTO expenses (gym_id, title, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?, ?)",
    [$gym['gym_id'], $data['title'], $data['amount'], $data['category'] ?? 'General', $data['description'], $data['expense_date']]);
sendSuccess(['id' => (int)db()->lastInsertId()], "Expense added");
