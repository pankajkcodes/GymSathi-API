<?php
// GET ?gym_id=&member_id?&page=&limit= — payments received by the gym. Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');
[$page, $limit, $offset] = page(50, 500);

$where = "p.gym_id = ?";
$params = [$gym['gym_id']];
if (query('member_id')) {
    $where .= " AND (p.member_id = ? OR m.member_code = ?)";
    $mid = query('member_id');
    $params[] = $mid;
    $params[] = $mid;
}

$from = "FROM payments p LEFT JOIN members m ON p.member_id = m.id WHERE $where";
$total = (int)dbValue("SELECT COUNT(*) $from", $params);
$rows = dbAll("
    SELECT p.id, p.member_id, m.member_number, m.member_code, m.name AS member_name, m.profile_image, p.amount, p.payment_method, p.transaction_id, p.payment_date
    $from
    ORDER BY p.payment_date DESC
    LIMIT $limit OFFSET $offset
", $params);

sendSuccess(paginated($rows, $total, $page, $limit));
