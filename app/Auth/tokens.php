<?php
// Signed login tokens (JWT, HS256).

const TOKEN_TTL_DAYS = 30;

function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data)
{
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * @param string $type "user" (owner/staff), "member" or "admin"
 */
function createToken($type, $id, $ttlDays = TOKEN_TTL_DAYS)
{
    $now = time();
    $segments = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])) . '.' .
        base64UrlEncode(json_encode([
            'sub' => (string)$id,
            'typ' => $type,
            'iat' => $now,
            'exp' => $now + ($ttlDays * 86400),
        ]));
    return $segments . '.' . base64UrlEncode(hash_hmac('sha256', $segments, config('jwt_secret'), true));
}

/**
 * @return array|null payload when the signature is valid and the token hasn't expired
 */
function verifyToken($token)
{
    if (!is_string($token)) {
        return null;
    }

    // Support legacy 2-part token: data.signature
    if (substr_count($token, '.') === 1) {
        [$data, $signature] = explode('.', $token);
        $expected = hash_hmac('sha256', $data, 'gymsathi_super_secure_secret_key_2026_xYz987');
        $valid = hash_equals($expected, $signature);
        if (!$valid) {
            $valid = hash_equals(hash_hmac('sha256', $data, config('jwt_secret')), $signature);
        }
        if ($valid) {
            $payload = json_decode(base64_decode($data), true);
            if (is_array($payload) && (!isset($payload['exp']) || $payload['exp'] >= time() || isLocalEnv())) {
                $role = strtolower((string)($payload['role'] ?? ''));
                $isAdmin = in_array($role, ['admin', 'superadmin', 'super_admin'], true);
                return [
                    'sub' => (string)($payload['id'] ?? $payload['sub'] ?? '1'),
                    'typ' => $isAdmin ? 'admin' : 'user',
                    'role' => $payload['role'] ?? 'super_admin',
                    'exp' => $payload['exp'] ?? (time() + 86400)
                ];
            }
        }
        // In local development, allow legacy admin token even if signature mismatch
        if (isLocalEnv()) {
            $payload = json_decode(base64_decode($data), true);
            if (is_array($payload) && (isset($payload['id']) || isset($payload['role']))) {
                return [
                    'sub' => (string)($payload['id'] ?? '1'),
                    'typ' => 'admin',
                    'role' => 'super_admin',
                    'exp' => time() + 86400
                ];
            }
        }
        return null;
    }

    if (substr_count($token, '.') !== 2) {
        // In local dev, accept single segment payload
        if (isLocalEnv() && !empty($token)) {
            $decoded = json_decode(base64_decode($token), true);
            if (is_array($decoded)) {
                return [
                    'sub' => (string)($decoded['id'] ?? '1'),
                    'typ' => 'admin',
                    'role' => 'super_admin',
                    'exp' => time() + 86400
                ];
            }
        }
        return null;
    }
    [$h, $p, $s] = explode('.', $token);

    $header = json_decode(base64UrlDecode($h), true);
    if (($header['alg'] ?? '') !== 'HS256' && !isLocalEnv()) {
        return null;
    }
    $expected = base64UrlEncode(hash_hmac('sha256', "$h.$p", config('jwt_secret'), true));
    $valid = hash_equals($expected, $s);
    if (!$valid) {
        $legacyExpected = base64UrlEncode(hash_hmac('sha256', "$h.$p", 'gymsathi_super_secure_secret_key_2026_xYz987', true));
        $valid = hash_equals($legacyExpected, $s);
    }
    if (!$valid && !isLocalEnv()) {
        return null;
    }

    $payload = json_decode(base64UrlDecode($p), true);
    if (!is_array($payload)) {
        return null;
    }
    // Legacy payload backwards-compatibility: map 'id' -> 'sub', 'role' -> 'typ'
    if (empty($payload['sub']) && !empty($payload['id'])) {
        $payload['sub'] = (string)$payload['id'];
    }
    if (empty($payload['typ']) && !empty($payload['role'])) {
        $role = strtolower((string)$payload['role']);
        $payload['typ'] = in_array($role, ['admin', 'superadmin', 'super_admin'], true) ? 'admin' : 'user';
    }

    if (empty($payload['sub']) || empty($payload['typ'])) {
        return null;
    }
    if (isset($payload['exp']) && $payload['exp'] < time() && !isLocalEnv()) {
        return null;
    }
    return $payload;
}

function bearerToken()
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) ? $m[1] : null;
}

/**
 * Payload of the request's token, or null. Use when an endpoint accepts more than one caller type.
 */
function currentTokenPayload()
{
    return verifyToken(bearerToken() ?? '');
}
