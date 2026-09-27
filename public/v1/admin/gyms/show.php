<?php
// GET ?gym_id= (numeric id or code)
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

$lookup = query('gym_id') ?? query('id');
$gym = GymService::find($lookup, true);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}
$code = $gym['gym_id'];

$owner = GymService::owner($gym['id']);
$profile = GymService::profile($gym, $owner ?: []);
$ownerGyms = [];
if ($owner && !empty($owner['id'])) {
    $ownerGyms = GymService::forUser((int)$owner['id']);
}

sendSuccess([
    'gym' => $profile,
    'gym_info' => $profile, // for backwards-compatibility with frontend
    'owner' => $owner,
    'owner_gyms' => $ownerGyms,
    'staff' => StaffService::listForGym($gym['id']),
    'subscription' => (function() use ($code) {
        $sub = dbOne("
            SELECT gs.*, ap.plan_name, ap.price AS catalog_price
            FROM gym_subscriptions gs
            JOIN app_plans ap ON gs.plan_id = ap.id
            WHERE gs.gym_id = ? AND gs.status = 'active'
            ORDER BY gs.end_date DESC LIMIT 1
        ", [$code]);
        if ($sub) {
            $sub['price'] = ((float)$sub['amount'] > 0) ? $sub['amount'] : ($sub['catalog_price'] ?? $sub['amount']);
        }
        return $sub;
    })(),
    'membership_plans' => dbAll("
        SELECT p.id, p.plan_name, p.duration_months, p.price, p.description,
               (SELECT COUNT(*) FROM members m WHERE m.plan_id = p.id AND m.status != 'deleted') AS member_count
        FROM plans p
        WHERE p.gym_id = ?
        ORDER BY p.price
    ", [$code]),
    'payments' => dbAll("
        SELECT p.id, p.member_id, m.name AS member_name, m.phone AS member_phone,
               p.amount, p.payment_method, p.transaction_id, p.payment_date
        FROM payments p
        LEFT JOIN members m ON p.member_id = m.id
        WHERE p.gym_id = ?
        ORDER BY p.payment_date DESC, p.id DESC
        LIMIT 100
    ", [$code]),
    'payment_stats' => (function() use ($code) {
        $row = dbOne("
            SELECT COALESCE(SUM(amount), 0) AS total_collected,
                   COUNT(*) AS total_transactions,
                   COALESCE(SUM(CASE WHEN payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN amount ELSE 0 END), 0) AS this_month_collected
            FROM payments WHERE gym_id = ?
        ", [$code]);
        return [
            'total_collected' => (float)($row['total_collected'] ?? 0),
            'total_transactions' => (int)($row['total_transactions'] ?? 0),
            'this_month_collected' => (float)($row['this_month_collected'] ?? 0),
        ];
    })(),
    'recent_members' => dbAll("
        SELECT m.id, m.member_number, m.member_code, m.name, m.phone, m.status, m.start_date, m.expiry_date, m.created_at, p.plan_name
        FROM members m
        LEFT JOIN plans p ON m.plan_id = p.id
        WHERE m.gym_id = ? AND m.status != 'deleted'
        ORDER BY m.id DESC
        LIMIT 20
    ", [$code]),
    'stats' => (function() use ($code) {
        $row = dbOne("
            SELECT COUNT(*) AS total_members,
                   COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_members
            FROM members WHERE gym_id = ? AND status != 'deleted'
        ", [$code]);
        return [
            'total_members' => (int)($row['total_members'] ?? 0),
            'active_members' => (int)($row['active_members'] ?? 0),
        ];
    })(),
]);
