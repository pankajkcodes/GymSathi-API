<?php
// File-based rate limiting (works on shared hosting without Redis).

class RateLimit
{
    // [max attempts, window seconds]
    const LOGIN = [10, 900];
    const OTP_SEND = [5, 3600];
    const OTP_VERIFY = [10, 900];
    const REGISTER = [5, 3600];
    const ADMIN_LOGIN = [5, 900];
    const GOOGLE_LOGIN = [20, 900];
    const PAYMENT_ORDER = [30, 3600];
    const GYM_CREATE = [10, 3600];

    /**
     * Stop with 429 when $action was tried more than allowed, per IP and (optionally) per $key.
     *
     *   RateLimit::hit('login', RateLimit::LOGIN, $email);
     */
    public static function hit($action, array $limit, $key = null)
    {
        [$max, $window] = $limit;

        // In local development, relax rate limits to prevent locking out the developer
        if (isLocalEnv()) {
            $max = max($max * 10, 100);
        }

        $buckets = [$action . '|ip|' . clientIp()];
        if ($key !== null && $key !== '') {
            $buckets[] = $action . '|key|' . strtolower((string)$key);
        }

        foreach ($buckets as $bucket) {
            if (self::exceeded(APP_ROOT . '/storage/ratelimit/' . sha1($bucket) . '.json', $max, $window)) {
                throw new HttpException("Too many attempts. Please try again later.", 429);
            }
        }
    }

    /**
     * Clear rate limit buckets for this action and key upon successful action (e.g. login).
     */
    public static function clear($action, $key = null)
    {
        $buckets = [$action . '|ip|' . clientIp()];
        if ($key !== null && $key !== '') {
            $buckets[] = $action . '|key|' . strtolower((string)$key);
        }

        foreach ($buckets as $bucket) {
            $file = APP_ROOT . '/storage/ratelimit/' . sha1($bucket) . '.json';
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function exceeded($file, $max, $window)
    {
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false; // never block users because the limiter itself failed
        }
        flock($fp, LOCK_EX);
        $now = time();
        $hits = json_decode(stream_get_contents($fp), true);
        $hits = array_values(array_filter(is_array($hits) ? $hits : [], function ($t) use ($now, $window) {
            return $t > $now - $window;
        }));

        $blocked = count($hits) >= $max;
        if (!$blocked) {
            $hits[] = $now;
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        flock($fp, LOCK_UN);
        fclose($fp);

        return $blocked;
    }
}
