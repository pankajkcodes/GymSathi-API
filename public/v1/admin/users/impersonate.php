<?php
// POST /v1/admin/users/impersonate.php
// Super Admin only: generates a temporary owner token to troubleshoot an owner's account
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$data = validate(input(), [
    'user_id' => 'required|int',
]);

$user = dbOne("SELECT id, name, email, phone, status, registration_type, created_at FROM users WHERE id = ?", [(int)$data['user_id']]);
if (!$user) {
    throw new NotFoundException("Owner account not found");
}

if ($user['status'] !== 'active') {
    throw new ForbiddenException("Cannot impersonate a {$user['status']} owner account");
}

// Generate authentication token for user role
$token = createToken('user', (int)$user['id'], 1); // 1-day temporary token

// Also fetch their owned gyms for context
$gyms = dbAll("SELECT g.id, g.gym_id, g.gym_name, g.status 
               FROM gyms g 
               JOIN user_gym_roles r ON r.gym_id = g.id 
               WHERE r.user_id = ? AND r.role = 'owner'", [(int)$user['id']]);

sendSuccess([
    'user' => $user,
    'token' => $token,
    'gyms' => $gyms,
    'expires_in_hours' => 24
], "Temporary session token generated for {$user['name']}");
