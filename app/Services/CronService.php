<?php
// Scheduled jobs. Run with: php cron/run.php <task>   (or POST /v1/cron/trigger.php?task=<task>)
// Every task returns ['success' => bool, 'logs' => string[], 'error' => ?string].

class CronService
{
    const TASKS = [
        'checkMemberExpiry',
        'checkSubscriptions',
        'dailyRevenueReport',
        'inactiveMemberAlert',
        'systemCleanup',
        'databaseBackup',
    ];

    public static function run($task)
    {
        if ($task === 'all') {
            $results = [];
            foreach (self::TASKS as $t) {
                $results[$t] = self::run($t);
            }
            return $results;
        }
        if (!in_array($task, self::TASKS, true)) {
            throw new ValidationException("Unknown task. Use one of: all, " . implode(', ', self::TASKS));
        }

        $logs = [];
        $log = function ($line) use (&$logs) {
            $logs[] = '[' . date('Y-m-d H:i:s') . "] $line";
        };
        try {
            self::$task($log);
            return ['success' => true, 'logs' => $logs, 'error' => null];
        } catch (Throwable $e) {
            error_log("Cron $task failed: " . $e->getMessage());
            $log("FAILED: " . $e->getMessage());
            return ['success' => false, 'logs' => $logs, 'error' => $e->getMessage()];
        }
    }

    /** Active members past their expiry date → 'expired', and tell the gym. */
    private static function checkMemberExpiry(callable $log)
    {
        $expired = dbAll("SELECT id, gym_id, name FROM members WHERE expiry_date < ? AND status = 'active'", [today()]);
        dbRun("UPDATE members SET status = 'expired' WHERE expiry_date < ? AND status = 'active'", [today()]);
        $log(count($expired) . " members marked expired.");

        foreach ($expired as $m) {
            NotificationService::notifyGym($m['gym_id'], "Member Plan Expired",
                "The membership for {$m['name']} has expired.", 'expiry', $m['id']);
        }
    }

    /** GymSathi subscriptions past their end date → 'expired', and tell the gym. */
    private static function checkSubscriptions(callable $log)
    {
        $expired = dbAll("SELECT DISTINCT gym_id FROM gym_subscriptions WHERE end_date < ? AND status = 'active'", [today()]);
        dbRun("UPDATE gym_subscriptions SET status = 'expired' WHERE end_date < ? AND status = 'active'", [today()]);
        $log(count($expired) . " gyms had a subscription expire.");

        foreach ($expired as $row) {
            // Only tell gyms that have no other active subscription left.
            if (!dbValue("SELECT 1 FROM gym_subscriptions WHERE gym_id = ? AND status = 'active'", [$row['gym_id']])) {
                NotificationService::notifyGym($row['gym_id'], "App Subscription Expired",
                    "Your GymSathi subscription has expired. Please renew to continue using the services.", 'subscription_plans');
            }
        }
    }

    /** Yesterday's income/expense summary to every gym that had money movement. */
    private static function dailyRevenueReport(callable $log)
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $gyms = dbRun("
            SELECT gym_id FROM payments WHERE DATE(payment_date) = :d
            UNION
            SELECT gym_id FROM expenses WHERE expense_date = :d
        ", [':d' => $yesterday])->fetchAll(PDO::FETCH_COLUMN);

        foreach ($gyms as $code) {
            $r = ReportService::daily($code, $yesterday);
            NotificationService::notifyGym($code, "Daily Financial Summary ($yesterday)",
                "Yesterday's Summary: Total Income: ₹{$r['revenue']}, Total Expenses: ₹{$r['expenses']}, Net Profit: ₹{$r['net_profit']}", 'reports');
        }
        $log("Reports sent to " . count($gyms) . " gyms.");
    }

    /** Tell gyms about active members who haven't visited for 7+ days. */
    private static function inactiveMemberAlert(callable $log)
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-7 days'));
        $members = dbAll("
            SELECT m.id, m.gym_id, m.name, MAX(a.check_in) AS last_visit
            FROM members m
            LEFT JOIN attendance a ON a.member_id = m.id
            WHERE m.status = 'active' AND m.created_at < :t
            GROUP BY m.id, m.gym_id, m.name
            HAVING last_visit IS NULL OR last_visit < :t
        ", [':t' => $threshold]);

        foreach ($members as $m) {
            $last = $m['last_visit'] ? date('Y-m-d', strtotime($m['last_visit'])) : 'Never';
            NotificationService::notifyGym($m['gym_id'], "Inactive Member Alert",
                "{$m['name']} has not visited the gym for over 7 days. (Last seen: $last).", 'member_details', $m['id']);
        }
        $log(count($members) . " inactive-member alerts sent.");
    }

    /** Push each active gym its summary for $date. Used by admin/notifications/broadcast-daily. */
    public static function broadcastDailyReports($date, $gymPks = null)
    {
        if ($gymPks) {
            $in = implode(',', array_fill(0, count($gymPks), '?'));
            $gyms = dbAll("SELECT id, gym_id FROM gyms WHERE status = 'active' AND id IN ($in)", array_map('intval', $gymPks));
        } else {
            $gyms = dbAll("SELECT id, gym_id FROM gyms WHERE status = 'active'");
        }

        $results = [];
        foreach ($gyms as $gym) {
            $r = ReportService::daily($gym['gym_id'], $date);
            $message = "📊 TODAY'S SUMMARY\n━━━━━━━━━━━━━\n" .
                "💰 Net Profit: ₹" . number_format($r['net_profit']) . "\n" .
                "👥 New Joinees: {$r['members_added']}\n" .
                "📈 Revenue: ₹" . number_format($r['revenue']) . "\n" .
                "📉 Expenses: ₹" . number_format($r['expenses']) . "\n" .
                "✅ Attendance: {$r['unique_members_present']} Present";
            $push = NotificationService::notifyGym($gym['id'],
                "Gym Report (" . date('d M', strtotime($date)) . "): Profit ₹" . number_format($r['net_profit']), $message, 'financial');
            $results[] = ['gym_id' => (int)$gym['id'], 'sent' => $push['sent'], 'failed' => $push['failed'], 'report' => $r];
        }
        return $results;
    }

    /** Expired OTPs, stale device tokens, old rate-limit files. */
    private static function systemCleanup(callable $log)
    {
        $log(dbRun("DELETE FROM password_resets WHERE expires_at < NOW()")->rowCount() . " expired OTPs removed.");
        $log(dbRun("DELETE FROM fcm_tokens WHERE updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)")->rowCount() . " stale device tokens removed.");

        $removed = 0;
        foreach (glob(APP_ROOT . '/storage/ratelimit/*.json') ?: [] as $file) {
            if (filemtime($file) < time() - 86400 && @unlink($file)) {
                $removed++;
            }
        }
        $log("$removed old rate-limit files removed.");
    }

    /**
     * mysqldump to storage/backups (needs exec() and mysqldump on the server; keeps 30 days).
     * Credentials go through a temporary option file so the password isn't in the process list.
     */
    private static function databaseBackup(callable $log)
    {
        if (!function_exists('exec')) {
            throw new RuntimeException("exec() is disabled on this server. Use Hostinger's built-in backups.");
        }
        $dir = APP_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $file = $dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

        $optionFile = tempnam(sys_get_temp_dir(), 'gsdb');
        chmod($optionFile, 0600);
        file_put_contents($optionFile, sprintf("[client]\nuser=\"%s\"\npassword=\"%s\"\nhost=\"%s\"\n", config('db.user'), config('db.pass'), config('db.host')));

        try {
            exec(sprintf('mysqldump --defaults-extra-file=%s --single-transaction --no-tablespaces %s > %s 2>&1',
                escapeshellarg($optionFile), escapeshellarg(config('db.name')), escapeshellarg($file)), $output, $exitCode);
        } finally {
            @unlink($optionFile);
        }

        if ($exitCode !== 0) {
            error_log("mysqldump failed: " . trim(@file_get_contents($file)));
            @unlink($file);
            throw new RuntimeException("mysqldump failed (see error log)");
        }
        chmod($file, 0600);
        $log("Backup saved: " . basename($file));

        foreach (glob("$dir/*.sql") ?: [] as $old) {
            if (time() - filemtime($old) >= 30 * 86400) {
                unlink($old);
                $log("Deleted old backup: " . basename($old));
            }
        }
    }
}
