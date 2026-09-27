<?php
// GET ?gym_id=&filter=paid|trial|active|expired|revenue&period=&from=&to=&page=&limit=
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

$where = "1 = 1";
$params = [];

if (query('gym_id')) {
    $gym = GymService::find(query('gym_id'), true);
    $gymCode = $gym ? $gym['gym_id'] : query('gym_id');
    $where .= " AND s.gym_id = ?";
    $params[] = $gymCode;
}

$filter = query('filter', '');
if ($filter === 'paid' || $filter === 'purchased') {
    $where .= " AND (s.amount > 0 OR (s.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))";
} elseif ($filter === 'trial') {
    $where .= " AND (s.amount = 0 AND (s.plan_id IN (4, 'Trial', 'trial') OR COALESCE(ap.plan_name, '') = 'Trial'))";
} elseif ($filter === 'active') {
    $where .= " AND s.status = 'active'";
} elseif ($filter === 'expired') {
    $where .= " AND s.status != 'active'";
} elseif ($filter === 'revenue') {
    [$from, $to] = Period::fromQuery();
    if ($from) {
        $where .= " AND DATE(s.created_at) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
    }
}
[$page, $limit, $offset] = page(10);

$total = (int)dbValue("
    SELECT COUNT(*) 
    FROM gym_subscriptions s 
    LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id)
    WHERE $where
", $params);

$rows = dbAll("
    SELECT s.*, g.gym_name, g.logo_url, COALESCE(ap.plan_name, s.plan_id, 'Custom') AS plan_name,
           COALESCE(NULLIF(s.amount, 0), ap.price, 0) AS amount,
           CASE 
               WHEN (s.amount > 0 OR (s.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial')) THEN 1 
               ELSE 0 
           END AS is_paid
    FROM gym_subscriptions s
    LEFT JOIN gyms g ON s.gym_id = g.gym_id
    LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id)
    WHERE $where
    ORDER BY s.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

$counts = [
    'all' => (int)dbValue("SELECT COUNT(*) FROM gym_subscriptions s"),
    'paid' => (int)dbValue("SELECT COUNT(*) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id) WHERE (s.amount > 0 OR (s.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))"),
    'trial' => (int)dbValue("SELECT COUNT(*) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id) WHERE (s.amount = 0 AND (s.plan_id IN (4, 'Trial', 'trial') OR COALESCE(ap.plan_name, '') = 'Trial'))"),
    'active' => (int)dbValue("SELECT COUNT(*) FROM gym_subscriptions s WHERE s.status = 'active'"),
    'expired' => (int)dbValue("SELECT COUNT(*) FROM gym_subscriptions s WHERE s.status != 'active'"),
];

$res = paginated($rows, $total, $page, $limit);
$res['filter_counts'] = $counts;

sendSuccess($res);
