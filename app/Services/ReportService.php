<?php
// Gym numbers: dashboard summary, one day's report, charts, exports.

class ReportService
{
    const EXPORT_TYPES = ['members', 'payments', 'expenses', 'attendance', 'expiring'];
    const CHART_TYPES = ['revenue', 'attendance'];
    const FINANCIAL_KEYS = ['monthly_revenue', 'total_expense'];

    /**
     * Dashboard: this month's money + member counts (same definitions as the member list filters).
     * Staff without full access don't get the money figures.
     */
    public static function summary(array $gym, $includeFinancial)
    {
        $code = $gym['gym_id'];
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');
        $expenseStart = date('Y-m-01');
        $expenseEnd = date('Y-m-t');

        $f = MemberService::FILTERS;
        $financialSelect = $includeFinancial
            ? ", (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE gym_id = :gym AND payment_date BETWEEN :monthStart AND :monthEnd) AS monthly_revenue,
                 (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE gym_id = :gym AND expense_date BETWEEN :expenseStart AND :expenseEnd) AS total_expense"
            : "";

        $params = [
            ':gym' => $code,
            ':today' => today(),
            ':date' => utcToday(),
        ];
        if ($includeFinancial) {
            $params[':monthStart'] = $monthStart;
            $params[':monthEnd'] = $monthEnd;
            $params[':expenseStart'] = $expenseStart;
            $params[':expenseEnd'] = $expenseEnd;
        }

        $res = dbOne("
            SELECT 
                (SELECT COUNT(*) FROM members m WHERE m.gym_id = :gym AND m.status != 'deleted') AS total_members,
                (SELECT COALESCE(SUM({$f['active']}), 0) FROM members m LEFT JOIN plans p ON m.plan_id = p.id WHERE m.gym_id = :gym AND m.status != 'deleted') AS active_members,
                (SELECT COALESCE(SUM({$f['expired']}), 0) FROM members m LEFT JOIN plans p ON m.plan_id = p.id WHERE m.gym_id = :gym AND m.status != 'deleted') AS expired_members,
                (SELECT COALESCE(SUM({$f['expiring_today']}), 0) FROM members m WHERE m.gym_id = :gym AND m.status != 'deleted') AS expiring_today,
                (SELECT COALESCE(SUM({$f['blocked']}), 0) FROM members m WHERE m.gym_id = :gym AND m.status != 'deleted') AS blocked_members,
                (SELECT COALESCE(SUM({$f['inactive']}), 0) FROM members m WHERE m.gym_id = :gym AND m.status != 'deleted') AS inactive_members,
                (SELECT COALESCE(SUM({$f['no_plan']}), 0) FROM members m WHERE m.gym_id = :gym AND m.status != 'deleted') AS no_plan_members,
                (SELECT COALESCE(SUM({$f['unpaid']}), 0) FROM members m LEFT JOIN plans p ON m.plan_id = p.id WHERE m.gym_id = :gym AND m.status != 'deleted') AS unpaid_members,
                (SELECT COUNT(DISTINCT a.member_id) FROM attendance a WHERE a.gym_id = :gym AND (DATE(a.check_in) = :date OR (a.check_in IS NULL AND a.created_at = :date))) AS today_attendance
                $financialSelect
        ", $params) ?: [];

        $summary = [
            'total_members' => (int)($res['total_members'] ?? 0),
            'active_members' => (int)($res['active_members'] ?? 0),
            'expired_members' => (int)($res['expired_members'] ?? 0),
            'expiring_today' => (int)($res['expiring_today'] ?? 0),
            'blocked_members' => (int)($res['blocked_members'] ?? 0),
            'inactive_members' => (int)($res['inactive_members'] ?? 0),
            'no_plan_members' => (int)($res['no_plan_members'] ?? 0),
            'unpaid_members' => (int)($res['unpaid_members'] ?? 0),
            'today_attendance' => (int)($res['today_attendance'] ?? 0),
        ];

        if ($includeFinancial) {
            $summary['monthly_revenue'] = (float)($res['monthly_revenue'] ?? 0);
            $summary['total_expense'] = (float)($res['total_expense'] ?? 0);
        }
        return $summary;
    }

    /**
     * One day's numbers (dashboard "today" card and the daily push report).
     */
    public static function daily($gymCode, $date)
    {
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days', strtotime($date)));

        $row = dbOne("
            SELECT 
                (SELECT COUNT(*) FROM members WHERE gym_id = :gym AND DATE(created_at) = :date) AS members_added,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE gym_id = :gym AND payment_date BETWEEN :dayStart AND :dayEnd) AS revenue,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE gym_id = :gym AND expense_date = :date) AS expenses,
                (SELECT COUNT(*) FROM attendance WHERE gym_id = :gym AND (DATE(check_in) = :date OR (check_in IS NULL AND created_at = :date))) AS attendance_count,
                (SELECT COUNT(DISTINCT member_id) FROM attendance WHERE gym_id = :gym AND (DATE(check_in) = :date OR (check_in IS NULL AND created_at = :date))) AS unique_members_present,
                (SELECT COUNT(*) FROM members WHERE gym_id = :gym AND status != 'deleted') AS total_members,
                (SELECT COUNT(*) FROM members WHERE gym_id = :gym AND status = 'active') AS active_members,
                (SELECT COUNT(*) FROM members WHERE gym_id = :gym AND status = 'expired') AS expired_members,
                (SELECT COUNT(*) FROM members m WHERE m.gym_id = :gym AND m.status = 'active' AND m.created_at < :weekAgo AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.member_id = m.id AND a.check_in >= :weekAgo)) AS inactive_members
        ", [
            ':gym' => $gymCode,
            ':date' => $date,
            ':dayStart' => $date . ' 00:00:00',
            ':dayEnd' => $date . ' 23:59:59',
            ':weekAgo' => $weekAgo,
        ]) ?: [];

        $rev = (float)($row['revenue'] ?? 0);
        $exp = (float)($row['expenses'] ?? 0);

        return [
            'date' => $date,
            'members_added' => (int)($row['members_added'] ?? 0),
            'revenue' => $rev,
            'expenses' => $exp,
            'attendance_count' => (int)($row['attendance_count'] ?? 0),
            'unique_members_present' => (int)($row['unique_members_present'] ?? 0),
            'total_members' => (int)($row['total_members'] ?? 0),
            'active_members' => (int)($row['active_members'] ?? 0),
            'expired_members' => (int)($row['expired_members'] ?? 0),
            'inactive_members' => (int)($row['inactive_members'] ?? 0),
            'net_profit' => $rev - $exp,
        ];
    }

    /**
     * Chart series: revenue per month (last 6 months) or check-ins per day (last 30 days).
     *
     * @return array [['label' => …, 'value' => …], …]
     */
    public static function chart(array $gym, $type)
    {
        if ($type === 'revenue') {
            return dbAll("
                SELECT DATE_FORMAT(payment_date, '%b %Y') AS label, SUM(amount) AS value
                FROM payments
                WHERE gym_id = ? AND payment_date >= DATE_SUB(?, INTERVAL 6 MONTH)
                GROUP BY YEAR(payment_date), MONTH(payment_date), label
                ORDER BY YEAR(payment_date), MONTH(payment_date)
            ", [$gym['gym_id'], today()]);
        }
        return dbAll("
            SELECT DATE(check_in) AS label, COUNT(*) AS value
            FROM attendance
            WHERE gym_id = ? AND check_in >= DATE_SUB(?, INTERVAL 30 DAY)
            GROUP BY DATE(check_in)
            ORDER BY DATE(check_in)
        ", [$gym['gym_id'], utcToday()]);
    }

    /**
     * Rows for an export. Dates are optional (all time when missing).
     */
    public static function exportRows(array $gym, $type, $from, $to)
    {
        $code = $gym['gym_id'];
        $range = function ($column) use ($from, $to) {
            return ($from && $to) ? [" AND DATE($column) BETWEEN ? AND ?", [$from, $to]] : ['', []];
        };

        switch ($type) {
            case 'members':
                [$sql, $p] = $range('created_at');
                return dbAll("SELECT id, member_number, member_code, name, phone, email, status, start_date, expiry_date, created_at FROM members WHERE gym_id = ? AND status != 'deleted' $sql ORDER BY member_number ASC", array_merge([$code], $p));
            case 'payments':
                [$sql, $p] = $range('p.payment_date');
                return dbAll("SELECT p.id, m.member_number, m.member_code, m.name AS member_name, p.amount, p.payment_method, p.transaction_id, p.payment_date FROM payments p LEFT JOIN members m ON p.member_id = m.id WHERE p.gym_id = ? $sql ORDER BY p.payment_date DESC", array_merge([$code], $p));
            case 'expenses':
                [$sql, $p] = $range('expense_date');
                return dbAll("SELECT id, title, amount, category, description, expense_date FROM expenses WHERE gym_id = ? $sql ORDER BY expense_date DESC", array_merge([$code], $p));
            case 'attendance':
                [$sql, $p] = $range('a.check_in');
                return dbAll("SELECT a.id, m.member_number, m.member_code, m.name AS member_name, a.check_in, a.check_out FROM attendance a LEFT JOIN members m ON a.member_id = m.id WHERE a.gym_id = ? $sql ORDER BY a.check_in DESC", array_merge([$code], $p));
            case 'expiring':
                // Default: active members expiring in the next 7 days
                $from = $from ?: today();
                $to = $to ?: date('Y-m-d', strtotime('+7 days'));
                return dbAll("SELECT id, member_number, member_code, name, phone, status, expiry_date, profile_image FROM members WHERE gym_id = ? AND status != 'deleted' AND expiry_date BETWEEN ? AND ? ORDER BY expiry_date", [$code, $from, $to]);
        }
        throw new ValidationException("Type must be one of: " . implode(', ', self::EXPORT_TYPES));
    }

    /**
     * Stream rows as a CSV download and stop.
     */
    public static function streamCsv(array $rows, $filename)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '', $filename) . '"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                // Stop spreadsheet formula injection (a member named "=HYPERLINK(...)").
                fputcsv($out, array_map(function ($v) {
                    return (is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
                }, $row));
            }
        }
        fclose($out);
        exit;
    }
}
