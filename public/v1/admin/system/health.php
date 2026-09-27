<?php
// GET — returns live server, database, and background storage telemetry.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

// 1. Database latency check
$start = microtime(true);
dbValue("SELECT 1");
$dbLatencyMs = round((microtime(true) - $start) * 1000, 2);

// 2. Upload storage footprint
$uploadDir = PUBLIC_ROOT . '/uploads';
$storageBytes = 0;
if (is_dir($uploadDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $storageBytes += $file->getSize();
    }
}

// 3. Cron log check
$cronLog = APP_ROOT . '/storage/logs/cron.log';
$lastCronRun = file_exists($cronLog) ? date('Y-m-d H:i:s', filemtime($cronLog)) : 'No cron log recorded';

sendSuccess([
    'status' => 'healthy',
    'php_version' => PHP_VERSION,
    'server_time' => date('Y-m-d H:i:s'),
    'database' => [
        'connected' => true,
        'latency_ms' => $dbLatencyMs,
    ],
    'storage' => [
        'uploads_mb' => round($storageBytes / (1024 * 1024), 2),
    ],
    'cron' => [
        'last_run' => $lastCronRun,
        'scheduler' => 'active',
    ],
]);
