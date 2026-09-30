<?php
// Attendance. Timestamps are UTC; the "date" of a visit is its IST date (see app/Support/dates.php).
// A visit's date comes from check_in, or created_at for old rows without check_in.

class AttendanceService
{
    const DAILY_FILTERS = ['all', 'present', 'absent', 'active', 'expired'];

    const ON_DATE_SQL = "(DATE(CONVERT_TZ(a.check_in, '+00:00', '" . ATTENDANCE_UTC_OFFSET . "')) = :date OR (a.check_in IS NULL AND a.created_at = :date))";

    /**
     * Members of the gym with their attendance for one date.
     *
     * @return array [rows, total]
     */
    public static function daily(array $gym, $date, $filter, $search, $limit, $offset)
    {
        if (!in_array($filter, self::DAILY_FILTERS, true)) {
            throw new ValidationException("Filter must be one of: " . implode(', ', self::DAILY_FILTERS));
        }

        // One row per member present that day (first check-in, latest check-out).
        $dayVisits = "
            SELECT a.member_id,
                   MIN(COALESCE(a.check_in, a.created_at)) AS check_in,
                   MAX(a.check_out) AS check_out
            FROM attendance a
            WHERE a.gym_id = :gym AND " . self::ON_DATE_SQL . "
            GROUP BY a.member_id";

        $where = "m.gym_id = :gym AND m.status != 'deleted'";
        $params = [':gym' => $gym['gym_id'], ':date' => $date];

        if ($filter === 'present') {
            $where .= " AND d.member_id IS NOT NULL";
        } elseif ($filter === 'absent') {
            $where .= " AND d.member_id IS NULL";
        } elseif ($filter === 'active' || $filter === 'expired') {
            $where .= " AND " . str_replace(':today', ':date', MemberService::FILTERS[$filter]);
        }
        if ($search) {
            $where .= " AND (m.name LIKE :search OR m.phone LIKE :search OR m.member_code LIKE :search OR CAST(m.member_number AS CHAR) = :search_exact OR b.batch_name LIKE :search)";
            $params[':search'] = "%$search%";
            $params[':search_exact'] = trim($search);
        }

        $from = "FROM members m
            LEFT JOIN ($dayVisits) d ON d.member_id = m.id
            LEFT JOIN batches b ON m.batch_id = b.id
            LEFT JOIN plans p ON m.plan_id = p.id
            WHERE $where";

        $total = (int)dbValue("SELECT COUNT(*) $from", $params);
        $rows = dbAll("
            SELECT m.id, m.member_number, m.member_code, m.name, m.phone, m.email, m.profile_image, m.status, m.start_date, m.expiry_date,
                   (d.member_id IS NOT NULL) AS is_present, d.check_in, d.check_out,
                   b.batch_name, b.start_time, b.end_time,
                   p.plan_name, p.duration_months, p.price
            $from
            ORDER BY " . ($filter === 'present' ? "d.check_in DESC" : "m.name ASC") . "
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            $params
        );

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['member_number'] = (int)($r['member_number'] ?? 0);
            $r['is_present'] = (int)$r['is_present'];
            $r['check_in_time'] = isoUtc($r['check_in']);
            $r['check_out_time'] = isoUtc($r['check_out']);
            unset($r['check_in'], $r['check_out']);
            $r['duration_months'] = $r['duration_months'] !== null ? (int)$r['duration_months'] : null;
            $r['price'] = $r['price'] !== null ? (float)$r['price'] : null;
        }
        return [$rows, $total];
    }

    /**
     * All visits of one member (newest first). Pass $gym to limit to that gym.
     */
    public static function history($memberId, $gym = null)
    {
        $sql = "SELECT id, check_in, check_out, COALESCE(" . attendanceDaySql('check_in') . ", DATE(created_at)) AS date FROM attendance WHERE member_id = ?";
        $params = [$memberId];
        if ($gym) {
            $sql .= " AND gym_id = ?";
            $params[] = $gym['gym_id'];
        }
        $rows = dbAll($sql . " ORDER BY COALESCE(check_in, created_at) DESC", $params);

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['check_in_time'] = isoUtc($r['check_in']);
            $r['check_out_time'] = isoUtc($r['check_out']);
        }
        return $rows;
    }

    /**
     * First scan of the day checks in, second checks out, third just reports.
     *
     * @return array [response data, message]
     */
    public static function scan(array $gym, $memberId, $date)
    {
        if ($date > attendanceToday()) {
            throw new ValidationException("Cannot mark attendance for future dates");
        }
        $member = MemberService::find($gym, $memberId);
        if ($member['status'] === 'blocked') {
            throw new ForbiddenException("This member is blocked. Cannot mark attendance.");
        }

        $visit = dbOne("
            SELECT id, check_in, check_out FROM attendance a
            WHERE a.gym_id = :gym AND a.member_id = :member AND " . self::ON_DATE_SQL . "
            ORDER BY a.id DESC LIMIT 1
        ", [':gym' => $gym['gym_id'], ':member' => $memberId, ':date' => $date]);

        $now = utcNow();

        if (!$visit) {
            $checkIn = $date === attendanceToday() ? $now : attendanceUtcAt($date);
            dbRun("INSERT INTO attendance (gym_id, member_id, check_in, created_at) VALUES (?, ?, ?, ?)", [$gym['gym_id'], $memberId, $checkIn, $date]);
            return [['is_present' => true, 'check_in_time' => isoUtc($checkIn), 'check_out_time' => null], 'Checked in successfully'];
        }

        if (empty($visit['check_out'])) {
            dbRun("UPDATE attendance SET check_out = ? WHERE id = ?", [$now, $visit['id']]);
            return [['is_present' => true, 'check_in_time' => isoUtc($visit['check_in']), 'check_out_time' => isoUtc($now)], 'Checked out successfully'];
        }

        return [
            ['is_present' => true, 'check_in_time' => isoUtc($visit['check_in']), 'check_out_time' => isoUtc($visit['check_out'])],
            'Already checked in and checked out today',
        ];
    }
}
