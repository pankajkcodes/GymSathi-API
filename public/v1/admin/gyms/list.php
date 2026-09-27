<?php
// GET ?filter=new|new_members|free|ended_trials&period=&from=&to=&search=&page=&limit=
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

[$from, $to] = Period::fromQuery();
$filter = query('filter', '');
$where = ["g.status != 'deleted'"];
$params = [];

if ($filter === 'new' && $from) {
    $where[] = "DATE(g.created_at) BETWEEN ? AND ?";
    array_push($params, $from, $to);
} elseif ($filter === 'new_members' && $from) {
    $where[] = "g.gym_id IN (SELECT gym_id FROM members WHERE DATE(created_at) BETWEEN ? AND ?)";
    array_push($params, $from, $to);
} elseif ($filter === 'paid' || $filter === 'buying') {
    $where[] = "g.gym_id IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))";
} elseif ($filter === 'free' || $filter === 'trial') {
    $where[] = "g.gym_id NOT IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))";
} elseif ($filter === 'ended_trials' && $from) {
    $where[] = "g.subscription_expiry BETWEEN ? AND ?";
    array_push($params, $from, $to);
} elseif ($filter === 'multi_branch' || $filter === 'multi_gym') {
    $where[] = "ou.id IN (SELECT user_id FROM user_gym_roles WHERE role = 'owner' GROUP BY user_id HAVING COUNT(*) > 1)";
}
if (query('search')) {
    $where[] = "(g.gym_name LIKE ? OR g.gym_id LIKE ? OR g.phone LIKE ? OR ou.name LIKE ? OR ou.email LIKE ?)";
    array_push($params, ...array_fill(0, 5, '%' . query('search') . '%'));
}

// Owner from users (source of truth), legacy gyms columns as fallback.
$from_sql = "FROM gyms g
    LEFT JOIN (SELECT gym_id, MIN(user_id) AS user_id FROM user_gym_roles WHERE role = 'owner' GROUP BY gym_id) o ON o.gym_id = g.id
    LEFT JOIN users ou ON ou.id = o.user_id
    WHERE " . implode(' AND ', $where);
[$page, $limit, $offset] = page(10);

$total = (int)dbValue("SELECT COUNT(*) $from_sql", $params);
$rows = dbAll("
    SELECT g.id, g.gym_id, g.gym_name, g.phone, g.address, g.logo_url, g.status, g.created_at, g.subscription_expiry,
           ou.id AS owner_user_id,
           COALESCE(ou.name COLLATE utf8mb4_unicode_ci, g.owner_name) AS owner_name,
           COALESCE(ou.email COLLATE utf8mb4_unicode_ci, g.email) AS owner_email,
           (SELECT COUNT(*) FROM user_gym_roles r WHERE r.user_id = ou.id AND r.role = 'owner') AS owner_gyms_count,
           (SELECT COUNT(*) FROM members m WHERE m.gym_id = g.gym_id AND m.status = 'active') AS active_members,
           (SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE p.gym_id = g.gym_id) AS total_revenue,
           (SELECT COALESCE(SUM(CASE WHEN gs.amount > 0 THEN gs.amount ELSE ap.price END), 0) FROM gym_subscriptions gs LEFT JOIN app_plans ap ON ap.id = gs.plan_id WHERE gs.gym_id = g.gym_id) AS saas_revenue,
           (SELECT ap.plan_name FROM gym_subscriptions gs JOIN app_plans ap ON gs.plan_id = ap.id WHERE gs.gym_id = g.gym_id AND gs.status = 'active' ORDER BY gs.end_date DESC LIMIT 1) AS app_plan_name,
           (SELECT COALESCE(NULLIF(gs.amount, 0), ap.price, 0) FROM gym_subscriptions gs JOIN app_plans ap ON gs.plan_id = ap.id WHERE gs.gym_id = g.gym_id AND gs.status = 'active' ORDER BY gs.end_date DESC LIMIT 1) AS app_plan_price,
           (SELECT MAX(created_at) FROM attendance a WHERE a.gym_id = g.gym_id) AS last_active_at
    $from_sql
    ORDER BY g.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

$filter_counts = [
    'all' => (int)dbValue("SELECT COUNT(*) FROM gyms WHERE status != 'deleted'"),
    'paid' => (int)dbValue("SELECT COUNT(DISTINCT g.gym_id) FROM gyms g WHERE g.status != 'deleted' AND g.gym_id IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))"),
    'free' => (int)dbValue("SELECT COUNT(DISTINCT g.gym_id) FROM gyms g WHERE g.status != 'deleted' AND g.gym_id NOT IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))"),
    'multi_branch' => (int)dbValue("SELECT COUNT(DISTINCT g.gym_id) FROM gyms g JOIN user_gym_roles r ON r.gym_id = g.id WHERE g.status != 'deleted' AND r.role = 'owner' AND r.user_id IN (SELECT user_id FROM user_gym_roles WHERE role = 'owner' GROUP BY user_id HAVING COUNT(*) > 1)")
];

$res = paginated($rows, $total, $page, $limit);
$res['filter_counts'] = $filter_counts;
sendSuccess($res);
