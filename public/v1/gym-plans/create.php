<?php
// POST { gym_id, plan_name, duration_months, price, description? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'plans.write');
$data = validate(input(), [
    'plan_name' => 'required|string|maxlen:100',
    'duration_months' => 'required|int|min:1',
    'price' => 'required|numeric|min:0',
    'description' => 'string',
]);

dbRun("INSERT INTO plans (gym_id, plan_name, duration_months, price, description) VALUES (?, ?, ?, ?, ?)",
    [$gym['gym_id'], $data['plan_name'], $data['duration_months'], $data['price'], $data['description']]);
sendSuccess(['id' => (int)db()->lastInsertId()], "Plan added");
