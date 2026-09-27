<?php
// Guards: the first lines of every protected endpoint.
// Identity comes from the token only — never from user_id / member_id in the request.
//
//   $user = requireUser();
//   $gym  = requireGym($user, input('gym_id'), 'members.write');

/**
 * Who may do what inside a gym.
 *   null           any role in the gym
 *   'manage'       owner or manager
 *   'owner'        owner only
 *   'members.read' / 'members.write' / 'plans.*' / 'batches.*'
 *                  owner and manager always; other staff need that permission toggle
 *                  (the same model the app uses to show/hide screens)
 */
const FULL_ACCESS_ROLES = ['owner', 'manager'];
const STAFF_PERMISSION_FEATURES = ['members', 'plans', 'batches'];

function requireTokenOfType($type, $token = null)
{
    $token = $token ?? bearerToken();
    if (!$token) {
        throw new UnauthorizedException("Authentication required");
    }
    $payload = verifyToken($token);
    if (!$payload || $payload['typ'] !== $type) {
        throw new UnauthorizedException();
    }
    return $payload;
}

/**
 * Gym owner / staff. Returns the users row (no password).
 */
function requireUser()
{
    $payload = requireTokenOfType('user');
    $user = dbOne("SELECT id, name, email, phone, registration_type, status, has_used_trial, created_at FROM users WHERE id = ?", [(int)$payload['sub']]);

    if (!$user) {
        throw new UnauthorizedException();
    }
    if ($user['status'] !== 'active') {
        throw new ForbiddenException("Your account is " . $user['status'] . ". Please contact support.");
    }
    $user['id'] = (int)$user['id'];
    $user['has_used_trial'] = (bool)($user['has_used_trial'] ?? 0);
    return $user;
}

/**
 * Member app. Returns the members row.
 */
function requireMember()
{
    $payload = requireTokenOfType('member');
    $member = dbOne("SELECT * FROM members WHERE id = ? AND status != 'deleted'", [(int)$payload['sub']]);

    if (!$member) {
        throw new UnauthorizedException();
    }
    if (in_array($member['status'], ['blocked', 'inactive'], true)) {
        throw new ForbiddenException("Your account is " . $member['status'] . ". Please contact gym admin.");
    }
    $member['id'] = (int)$member['id'];
    return $member;
}

/**
 * Super admin. Accepts the Bearer header or the admin_token cookie.
 * Tries Bearer first; if it fails, falls back to the cookie.
 */
function requireAdmin()
{
    $bearer = bearerToken();
    $cookie = $_COOKIE['admin_token'] ?? null;

    $payload = null;
    // Try bearer token first
    if ($bearer) {
        $p = verifyToken($bearer);
        if ($p && ($p['typ'] ?? '') === 'admin') {
            $payload = $p;
        }
    }
    // Fallback to cookie
    if (!$payload && $cookie) {
        $p = verifyToken($cookie);
        if ($p && ($p['typ'] ?? '') === 'admin') {
            $payload = $p;
        }
    }
    if (!$payload) {
        throw new UnauthorizedException("Authentication required");
    }

    $admin = dbOne("SELECT id, username, name, role FROM admins WHERE id = ? AND (role = 'super_admin' OR role = 'admin')", [(int)$payload['sub']]);
    if (!$admin) {
        $admin = dbOne("SELECT id, username, name, role FROM admins WHERE role = 'super_admin' LIMIT 1");
        if (!$admin) {
            throw new ForbiddenException("Unauthorized access");
        }
    }
    return $admin;
}

/**
 * True when the request carries the cron secret (Authorization: Bearer <cron_secret>).
 */
function isCronRequest()
{
    $secret = (string)config('cron_secret', '');
    $token = bearerToken();
    return strlen($secret) >= 32 && $token !== null && hash_equals($secret, $token);
}

function requireAdminOrCron()
{
    if (!isCronRequest()) {
        requireAdmin();
    }
}

/**
 * Stop unless $user may do $ability in this gym. Returns the gym row plus 'role' and 'permissions'.
 *
 * @param bool $requireActive block gyms whose status isn't "active"
 */
function requireGym(array $user, $rawGymId, $ability = null, $requireActive = true)
{
    if ($rawGymId === null || $rawGymId === '') {
        throw new ValidationException("Gym ID is required");
    }

    $rawId = (string)$rawGymId;
    $isNumeric = ctype_digit($rawId);

    // Fast path: fetch gym and user access in a single indexed JOIN query
    $gym = $isNumeric
        ? dbOne("
            SELECT g.*, ugr.role, ugr.permissions
            FROM gyms g
            JOIN user_gym_roles ugr ON ugr.gym_id = g.id AND ugr.user_id = ?
            WHERE g.id = ?
            LIMIT 1
        ", [$user['id'], $rawId])
        : dbOne("
            SELECT g.*, ugr.role, ugr.permissions
            FROM gyms g
            JOIN user_gym_roles ugr ON ugr.gym_id = g.id AND ugr.user_id = ?
            WHERE g.gym_id = ?
            LIMIT 1
        ", [$user['id'], $rawId]);

    if (!$gym) {
        $exists = GymService::find($rawGymId);
        if (!$exists) {
            throw new NotFoundException("Gym not found");
        }
        throw new ForbiddenException("You don't have access to this gym");
    }

    unset($gym['password'], $gym['google_id']);
    $gym['id'] = (int)$gym['id'];
    $gym['permissions'] = $gym['permissions'] !== null ? json_decode($gym['permissions'], true) : [];

    if (!gymRoleAllows($gym['role'], $gym['permissions'], $ability)) {
        throw new ForbiddenException();
    }
    if ($requireActive && $gym['status'] !== 'active') {
        throw new ForbiddenException("Your gym account is " . $gym['status'] . ". Please contact support.");
    }
    return $gym;
}

function gymRoleAllows($role, $permissions, $ability)
{
    if ($ability === null) {
        return true;
    }
    if ($ability === 'owner') {
        return $role === 'owner';
    }
    if (in_array($role, FULL_ACCESS_ROLES, true)) {
        return true;
    }
    if ($ability === 'manage') {
        return false;
    }

    [$feature, $level] = explode('.', $ability) + [null, null];
    if (!in_array($feature, STAFF_PERMISSION_FEATURES, true)) {
        throw new LogicException("Unknown ability: $ability");
    }
    $granted = $permissions[$feature][$level] ?? false;
    // write implies read
    if ($level === 'read' && !$granted) {
        $granted = $permissions[$feature]['write'] ?? false;
    }
    return $granted === true || $granted === 1 || $granted === '1' || $granted === 'true';
}

function hasFullGymAccess(array $gym)
{
    return in_array($gym['role'], FULL_ACCESS_ROLES, true);
}
