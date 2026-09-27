<?php
// GET ?gym_id= — the gym's GymSathi subscriptions. Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage', false);

sendSuccess(dbAll("
    SELECT gs.id, gs.plan_id, ap.plan_name, gs.amount, gs.payment_id, gs.start_date, gs.end_date, gs.status, gs.created_at
    FROM gym_subscriptions gs
    JOIN app_plans ap ON gs.plan_id = ap.id
    WHERE gs.gym_id = ?
    ORDER BY gs.created_at DESC
", [$gym['gym_id']]));
