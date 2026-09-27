<?php
// GET ?gym_id=&filter=new_members&period=&from=&to=&search=&page=&limit=
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

$where = ["m.status != 'deleted'"];
$params = [];
if (query('gym_id')) {
    $gym = GymService::find(query('gym_id'), true);
    $where[] = "m.gym_id = ?";
    $params[] = $gym ? $gym['gym_id'] : query('gym_id');
}
[$from, $to] = Period::fromQuery();
if (query('filter') === 'new_members' && $from) {
    $where[] = "DATE(m.created_at) BETWEEN ? AND ?";
    array_push($params, $from, $to);
}
if (query('search')) {
    $where[] = "(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)";
    array_push($params, ...array_fill(0, 3, '%' . query('search') . '%'));
}

$whereSql = implode(' AND ', $where);
[$page, $limit, $offset] = page(10);

$total = (int)dbValue("SELECT COUNT(*) FROM members m WHERE $whereSql", $params);
$rows = dbAll("
    SELECT m.*, g.gym_name, p.plan_name
    FROM members m
    LEFT JOIN gyms g ON m.gym_id = g.gym_id
    LEFT JOIN plans p ON m.plan_id = p.id
    WHERE $whereSql
    ORDER BY m.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

sendSuccess(paginated($rows, $total, $page, $limit));
