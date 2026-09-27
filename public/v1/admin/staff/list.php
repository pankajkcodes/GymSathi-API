<?php
// GET ?gym_id=  → staff of one gym.   GET (no gym_id) → every staff member with their gyms.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

if (query('gym_id')) {
    $gym = GymService::find(query('gym_id'), true);
    if (!$gym) {
        throw new NotFoundException("Gym not found");
    }
    sendSuccess(StaffService::listForGym($gym['id']));
}

$staff = [];
foreach (dbAll("
    SELECT u.id AS user_id, u.name, u.email, u.phone, g.id AS gym_id, g.gym_name, ugr.role, ugr.permissions
    FROM user_gym_roles ugr
    JOIN users u ON u.id = ugr.user_id
    JOIN gyms g ON g.id = ugr.gym_id
    WHERE ugr.role != 'owner' AND g.status != 'deleted'
    ORDER BY u.name
") as $row) {
    $id = (int)$row['user_id'];
    $staff[$id] = $staff[$id] ?? ['user_id' => $id, 'name' => $row['name'], 'email' => $row['email'], 'phone' => $row['phone'], 'gym_assignments' => []];
    $staff[$id]['gym_assignments'][] = [
        'gym_id' => (int)$row['gym_id'],
        'gym_name' => $row['gym_name'],
        'role' => $row['role'],
        'permissions' => $row['permissions'] !== null ? json_decode($row['permissions'], true) : null,
    ];
}
sendSuccess(array_values($staff));
