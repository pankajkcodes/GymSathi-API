<?php
// POST { gym_id, plan_id, plan_name, duration_months, price, description? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'plans.write');
$data = validate(input(), ['plan_id' => 'required|int'] + [
    'plan_name' => 'required|string|maxlen:100',
    'duration_months' => 'required|int|min:1',
    'price' => 'required|numeric|min:0',
    'description' => 'string',
]);

if (!dbValue("SELECT 1 FROM plans WHERE id = ? AND gym_id = ?", [$data['plan_id'], $gym['gym_id']])) {
    throw new NotFoundException("Plan not found");
}
dbRun("UPDATE plans SET plan_name = ?, duration_months = ?, price = ?, description = ? WHERE id = ?",
    [$data['plan_name'], $data['duration_months'], $data['price'], $data['description'], $data['plan_id']]);
sendSuccess(null, "Plan updated");
