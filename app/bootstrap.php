<?php
// Loaded first by every endpoint and by cron/run.php.
// Sets up: config, error handling, class autoloading, CORS, and the database.

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', APP_ROOT . '/public');

$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config/secrets.php';

/**
 * Config value by dot path, e.g. config('db.host').
 */
function config($key, $default = null)
{
    $value = $GLOBALS['APP_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function isLocalEnv()
{
    if (config('app_env') === 'local') return true;
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || PHP_SAPI === 'cli';
}

// ================= ERRORS =================
// Never show PHP errors to clients; log them.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

if (extension_loaded('zlib') && PHP_SAPI !== 'cli') {
    ini_set('zlib.output_compression', '1');
}

if (config('timezone')) {
    date_default_timezone_set(config('timezone'));
}

// ================= CODE LOADING =================
// Functions (can't be autoloaded)
require_once APP_ROOT . '/app/Exceptions/HttpExceptions.php';
require_once APP_ROOT . '/app/Http/response.php';
require_once APP_ROOT . '/app/Http/request.php';
require_once APP_ROOT . '/app/Http/validate.php';
require_once APP_ROOT . '/app/Auth/tokens.php';
require_once APP_ROOT . '/app/Auth/guards.php';
require_once APP_ROOT . '/app/Support/db.php';
require_once APP_ROOT . '/app/Support/dates.php';

// Classes: app/Services/GymService.php, app/Support/RateLimit.php, …
spl_autoload_register(function ($class) {
    foreach (['Services', 'Support', 'Auth'] as $dir) {
        $file = APP_ROOT . "/app/$dir/$class.php";
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

set_exception_handler(function ($e) {
    if (dbConnected() && db()->inTransaction()) {
        db()->rollBack();
    }

    if ($e instanceof HttpException) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
        sendError($e->getMessage(), $e->data, $e->status);
    }

    error_log('[' . ($_SERVER['REQUEST_URI'] ?? 'cli') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        sendError('Something went wrong. Please try again.', null, 500);
    }
});

// ================= CORS =================
if (PHP_SAPI !== 'cli') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $isLocalOrigin = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);

    if ($origin !== '' && (in_array($origin, config('cors_origins', []), true) || ($isLocalOrigin && isLocalEnv()))) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

    // Answer the preflight before any auth/DB work.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
}
