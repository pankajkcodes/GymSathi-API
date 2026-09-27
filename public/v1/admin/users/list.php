<?php
// GET ?search=&role=&page=&limit= — list users (owners, managers, trainers) with their assigned gyms.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

$where = ["1=1"];
$params = [];

if (query('search')) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    array_push($params, ...array_fill(0, 3, '%' . query('search') . '%'));
}

if (query('role')) {
    $where[] = "EXISTS (SELECT 1 FROM user_gym_roles r WHERE r.user_id = u.id AND r.role = ?)";
    $params[] = query('role');
}

if (query('filter') === 'multi') {
    $where[] = "(SELECT COUNT(*) FROM user_gym_roles r WHERE r.user_id = u.id AND r.role = 'owner') > 1";
} elseif (query('filter') === 'single') {
    $where[] = "(SELECT COUNT(*) FROM user_gym_roles r WHERE r.user_id = u.id AND r.role = 'owner') = 1";
}

$whereSql = implode(' AND ', $where);
[$page, $limit, $offset] = page(15);

$total = (int)dbValue("SELECT COUNT(*) FROM users u WHERE $whereSql", $params);

$users = dbAll("
    SELECT u.id, u.name, u.email, u.phone, u.registration_type, u.status, u.created_at,
           (SELECT COUNT(*) FROM user_gym_roles r WHERE r.user_id = u.id AND r.role = 'owner') AS owned_gyms_count,
           (SELECT COUNT(*) FROM user_gym_roles r WHERE r.user_id = u.id) AS total_gyms_count
    FROM users u
    WHERE $whereSql
    ORDER BY owned_gyms_count DESC, u.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Attach gyms array to each user efficiently in one batch query
$userIds = array_map(function($u) { return (int)$u['id']; }, $users);
$gymsByUser = [];

if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $allGyms = dbAll("
        SELECT g.id, g.gym_id, g.gym_name, g.logo_url, g.status, g.address, ugr.user_id, ugr.role, ugr.permissions
        FROM user_gym_roles ugr
        JOIN gyms g ON ugr.gym_id = g.id
        WHERE ugr.user_id IN ($placeholders) AND g.status != 'deleted'
        ORDER BY ugr.id ASC
    ", $userIds);

    foreach ($allGyms as $g) {
        $uid = (int)$g['user_id'];
        unset($g['user_id']);
        $g['id'] = (int)$g['id'];
        $g['permissions'] = $g['permissions'] !== null ? json_decode($g['permissions']) : null;
        $gymsByUser[$uid][] = $g;
    }
}

foreach ($users as &$u) {
    $u['id'] = (int)$u['id'];
    $u['owned_gyms_count'] = (int)$u['owned_gyms_count'];
    $u['total_gyms_count'] = (int)$u['total_gyms_count'];
    $u['gyms'] = $gymsByUser[$u['id']] ?? [];
}

sendSuccess(paginated($users, $total, $page, $limit));
