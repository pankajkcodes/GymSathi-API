<?php
// POST { user_id, gym_assignments: [{ gym_id, role, permissions }] } — replaces all of the
// person's staff roles. Owner roles are never touched.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$raw = input();
if (!isset($raw['user_id']) && isset($raw['id'])) {
    $raw['user_id'] = $raw['id'];
}
if (!isset($raw['gym_assignments']) && isset($raw['assignments'])) {
    $raw['gym_assignments'] = $raw['assignments'];
}
$data = validate($raw, [
    'user_id' => 'required|int',
    'gym_assignments' => 'array',
]);
$assignments = $data['gym_assignments'] ?? [];

dbTransaction(function () use ($data, $assignments) {
    $keep = [];
    foreach ($assignments as $a) {
        $gymPk = (int)($a['gym_id'] ?? 0);
        if ($gymPk <= 0) {
            continue;
        }
        StaffService::setRole($data['user_id'], $gymPk, $a['role'] ?? 'staff', $a['permissions'] ?? null);
        $keep[] = $gymPk;
    }
    $sql = "DELETE FROM user_gym_roles WHERE user_id = ? AND role != 'owner'";
    $params = [$data['user_id']];
    if ($keep) {
        $sql .= " AND gym_id NOT IN (" . implode(',', array_fill(0, count($keep), '?')) . ")";
        $params = array_merge($params, $keep);
    }
    dbRun($sql, $params);
});
sendSuccess(null, "Staff permissions updated");
