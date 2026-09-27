<?php
// Command-line cron runner. Hostinger → Advanced → Cron Jobs, e.g.:
//   /usr/bin/php /home/<user>/domains/gymsathi.in/api/cron/run.php checkMemberExpiry
// Tasks: all, checkMemberExpiry, checkSubscriptions, dailyRevenueReport,
//        inactiveMemberAlert, systemCleanup, databaseBackup

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

$task = $argv[1] ?? 'all';
$results = $task === 'all' ? CronService::run('all') : [$task => CronService::run($task)];

$failed = false;
foreach ($results as $name => $result) {
    echo "== $name: " . ($result['success'] ? 'OK' : 'FAILED') . PHP_EOL;
    foreach ($result['logs'] as $line) {
        echo "   $line" . PHP_EOL;
    }
    $failed = $failed || !$result['success'];
}
exit($failed ? 1 : 0);
