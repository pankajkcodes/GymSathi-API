<?php
// GET — platform totals.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

$stats = dbOne("SELECT 
    (SELECT COUNT(*) FROM gyms WHERE status != 'deleted') AS total_gyms,
    (SELECT COUNT(*) FROM members WHERE status != 'deleted') AS total_members,
    (SELECT COUNT(*) FROM users) AS total_users,
    (SELECT COUNT(DISTINCT user_id) FROM user_gym_roles WHERE role = 'owner') AS total_owners,
    (SELECT COUNT(*) FROM (SELECT user_id FROM user_gym_roles WHERE role = 'owner' GROUP BY user_id HAVING COUNT(*) > 1) t) AS multi_gym_owners,
    (SELECT COUNT(DISTINCT user_id) FROM user_gym_roles WHERE role != 'owner') AS total_staff,
    (SELECT COALESCE(SUM(CASE WHEN s.amount > 0 THEN s.amount ELSE ap.price END), 0) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id)) AS total_revenue,
    (SELECT COUNT(DISTINCT g.gym_id) FROM gyms g WHERE g.status != 'deleted' AND g.gym_id IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial')) AND (g.subscription_expiry IS NULL OR g.subscription_expiry >= CURDATE())) AS active_plans,
    (SELECT COUNT(*) FROM gyms WHERE status != 'deleted' AND subscription_expiry < CURDATE()) AS expired_plans,
    (SELECT COUNT(DISTINCT g.gym_id) FROM gyms g WHERE g.status != 'deleted' AND g.gym_id NOT IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))) AS free_gyms,
    (SELECT COUNT(DISTINCT g.gym_id) FROM gyms g WHERE g.status != 'deleted' AND g.gym_id IN (SELECT gs.gym_id FROM gym_subscriptions gs LEFT JOIN app_plans ap ON (ap.id = gs.plan_id OR ap.plan_name = gs.plan_id) WHERE gs.amount > 0 OR gs.plan_id IN (5,6,7) OR (gs.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))) AS total_paid_gyms,
    (SELECT COUNT(*) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id) WHERE (s.amount > 0 OR s.plan_id IN (5,6,7) OR (s.plan_id NOT IN (4, 'Trial', 'trial') AND COALESCE(ap.plan_name, '') != 'Trial'))) AS total_paid_subscriptions,
    (SELECT COUNT(*) FROM gyms g WHERE g.status != 'deleted' AND NOT EXISTS (SELECT 1 FROM user_gym_roles r WHERE r.gym_id = g.id AND r.role = 'owner')) AS gyms_without_owner,
    (SELECT COUNT(DISTINCT gym_id) FROM attendance WHERE DATE(created_at) = CURDATE()) AS active_gyms_today,
    (SELECT COUNT(*) FROM gyms WHERE status != 'deleted' AND subscription_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS expiring_next_7_days,
    (SELECT COALESCE(SUM(CASE WHEN s.amount > 0 THEN s.amount ELSE ap.price END), 0) FROM gym_subscriptions s LEFT JOIN app_plans ap ON (ap.id = s.plan_id OR ap.plan_name = s.plan_id) WHERE s.status = 'active' AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS mrr_last_30_days,
    (SELECT COUNT(*) FROM gyms WHERE status != 'deleted' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS new_gyms_this_month
") ?: [];

sendSuccess([
    'total_gyms' => (int)($stats['total_gyms'] ?? 0),
    'total_members' => (int)($stats['total_members'] ?? 0),
    'total_users' => (int)($stats['total_users'] ?? 0),
    'total_owners' => (int)($stats['total_owners'] ?? 0),
    'multi_gym_owners' => (int)($stats['multi_gym_owners'] ?? 0),
    'total_staff' => (int)($stats['total_staff'] ?? 0),
    'total_revenue' => (float)($stats['total_revenue'] ?? 0),
    'active_plans' => (int)($stats['active_plans'] ?? 0),
    'total_paid_gyms' => (int)($stats['total_paid_gyms'] ?? 0),
    'total_paid_subscriptions' => (int)($stats['total_paid_subscriptions'] ?? 0),
    'expired_plans' => (int)($stats['expired_plans'] ?? 0),
    'free_gyms' => (int)($stats['free_gyms'] ?? 0),
    'gyms_without_owner' => (int)($stats['gyms_without_owner'] ?? 0),
    'active_gyms_today' => (int)($stats['active_gyms_today'] ?? 0),
    'expiring_next_7_days' => (int)($stats['expiring_next_7_days'] ?? 0),
    'mrr_last_30_days' => (float)($stats['mrr_last_30_days'] ?? 0),
    'new_gyms_this_month' => (int)($stats['new_gyms_this_month'] ?? 0),
]);
