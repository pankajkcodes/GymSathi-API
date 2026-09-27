<?php
// GET ?period=today|yesterday|last_7_days|last_30_days|this_year|custom|2025&from=&to=
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

[$from, $to] = Period::fromQuery();
if (!$from) {
    throw new ValidationException("Invalid period");
}
$count = function ($sql) use ($from, $to) {
    return dbValue($sql, [':from' => $from, ':to' => $to]);
};

sendSuccess([
    'from' => $from,
    'to' => $to,
    'new_owners' => (int)$count("SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN :from AND :to"),
    'new_gyms' => (int)$count("SELECT COUNT(*) FROM gyms WHERE status != 'deleted' AND DATE(created_at) BETWEEN :from AND :to"),
    'new_members' => (int)$count("SELECT COUNT(*) FROM members WHERE DATE(created_at) BETWEEN :from AND :to"),
    'revenue' => (float)$count("SELECT COALESCE(SUM(CASE WHEN s.amount > 0 THEN s.amount ELSE ap.price END), 0) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id) WHERE DATE(s.created_at) BETWEEN :from AND :to"),
    'subscriptions_sold' => (int)$count("SELECT COUNT(*) FROM gym_subscriptions WHERE DATE(created_at) BETWEEN :from AND :to"),
    'active_plans' => (int)$count("SELECT COUNT(*) FROM gym_subscriptions WHERE DATE(created_at) BETWEEN :from AND :to"),
    'ended_trials' => (int)$count("SELECT COUNT(*) FROM gyms WHERE subscription_expiry BETWEEN :from AND :to"),
]);
