<?php
// The only ways endpoints read the request:
//   input('field')  → JSON body or form field (POST)
//   query('field')  → URL query string (GET)
//   uploadedFile('logo')
//   page()          → [page, limit, offset]

function requestBody()
{
    static $body = null;
    if ($body === null) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        $body = is_array($decoded) ? $decoded : $_POST;
    }
    return $body;
}

/**
 * POST data: JSON body or multipart/form fields. Call with no key for everything.
 */
function input($key = null, $default = null)
{
    $body = requestBody();
    if ($key === null) {
        return $body;
    }
    return array_key_exists($key, $body) ? $body[$key] : $default;
}

/**
 * GET query parameter.
 */
function query($key, $default = null)
{
    return isset($_GET[$key]) && $_GET[$key] !== '' ? $_GET[$key] : $default;
}

/**
 * An uploaded file entry, or null when none was sent.
 */
function uploadedFile($key)
{
    if (!isset($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return $_FILES[$key];
}

/**
 * Stop unless the request uses this HTTP method. Every endpoint handles exactly one.
 */
function requireMethod($method)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        sendError("Method not allowed. Use $method.", null, 405);
    }
}

/**
 * ?page=&limit= → [page, limit, offset]. limit is capped.
 */
function page($defaultLimit = 20, $maxLimit = 100)
{
    $page = max(1, (int)query('page', 1));
    $limit = min($maxLimit, max(1, (int)query('limit', $defaultLimit)));
    return [$page, $limit, ($page - 1) * $limit];
}

function clientIp()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
