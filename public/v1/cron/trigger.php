<?php
// POST ?task=<task|all>  with  Authorization: Bearer <cron_secret>
// For schedulers that can only call URLs. Prefer cron/run.php (command line).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
if (!isCronRequest()) {
    throw new UnauthorizedException("Unauthorized");
}

sendSuccess(CronService::run(query('task', 'all')), "Cron finished");
