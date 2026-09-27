<?php
// GET              → GymSathi plans for sale (public, used on the website too)
// GET ?gym_id=     → plans + that gym's current subscription (login required)
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$plans = dbAll("SELECT * FROM app_plans WHERE id != ? ORDER BY price", [GymService::TRIAL_PLAN_ID]);
$current = null;

if (query('gym_id')) {
    $gym = requireGym(requireUser(), query('gym_id'), null, false);
    $current = dbOne("
        SELECT gs.plan_id, ap.plan_name, gs.start_date, gs.end_date, gs.amount
        FROM gym_subscriptions gs
        JOIN app_plans ap ON gs.plan_id = ap.id
        WHERE gs.gym_id = ? AND gs.status = 'active' AND gs.end_date >= ?
        ORDER BY gs.end_date DESC LIMIT 1
    ", [$gym['gym_id'], today()]);
}

sendSuccess(['plans' => $plans, 'current_subscription' => $current]);
