<?php
// A gym's members. All tables link to a gym by its code (members.gym_id = 'GYM007').

class MemberService
{
    const STATUSES = ['active', 'inactive', 'expired', 'blocked', 'unpaid'];
    const IMPORT_MAX_ROWS = 1000;

    // Validation for member fields (create adds 'required' to name + phone).
    const FIELD_RULES = [
        'name' => 'string|maxlen:100',
        'phone' => 'string|maxlen:20',
        'email' => 'email',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'batch_id' => 'int',
        'plan_id' => 'int',
        'status' => 'in:active,inactive,expired,blocked,unpaid',
    ];

    // Total paid by a member (all time), used for "unpaid".
    const PAID_SQL = "(SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.member_id = m.id)";

    /**
     * List filters. :today is bound by the caller. Shared with ReportService so the dashboard
     * counts always match the lists.
     */
    const FILTERS = [
        'all'            => "1 = 1",
        'active'         => "(m.status = 'active' OR m.status IS NULL OR m.status = '') AND (m.expiry_date IS NULL OR m.expiry_date >= :today)",
        'expired'        => "(m.status = 'expired' OR (m.expiry_date IS NOT NULL AND m.expiry_date < :today))",
        'expiring_today' => "m.expiry_date = :today",
        'blocked'        => "m.status = 'blocked'",
        'inactive'       => "m.status = 'inactive'",
        'unpaid'         => "(m.status = 'unpaid' OR (p.price > 0 AND " . self::PAID_SQL . " < p.price))",
        'no_plan'        => "(m.plan_id IS NULL OR m.plan_id = 0)",
    ];

    /**
     * Paginated, filtered member list.
     *
     * @return array [rows, total]
     */
    public static function search(array $gym, $filter, $search, $limit, $offset)
    {
        if ($filter === 'no_plans' || $filter === 'no_purchase') {
            $filter = 'no_plan'; // old names
        }
        if (!isset(self::FILTERS[$filter])) {
            throw new ValidationException("Filter must be one of: " . implode(', ', array_keys(self::FILTERS)));
        }

        $where = "m.gym_id = :gym AND m.status != 'deleted' AND " . self::FILTERS[$filter];
        $params = [':gym' => $gym['gym_id']];
        if (strpos($where, ':today') !== false) {
            $params[':today'] = today();
        }
        if ($search) {
            $where .= " AND (m.name LIKE :search OR m.phone LIKE :search OR m.member_code LIKE :search OR CAST(m.member_number AS CHAR) = :search_exact OR b.batch_name LIKE :search)";
            $params[':search'] = "%$search%";
            $params[':search_exact'] = trim($search);
        }

        $from = "FROM members m
            LEFT JOIN batches b ON m.batch_id = b.id
            LEFT JOIN plans p ON m.plan_id = p.id
            WHERE $where";

        $total = (int)dbValue("SELECT COUNT(*) $from", $params);
        $rows = dbAll("
            SELECT m.id, m.member_number, m.member_code, m.name, m.phone, m.email, m.start_date, m.expiry_date, m.status, m.profile_image,
                   m.batch_id, b.batch_name, b.start_time, b.end_time,
                   m.plan_id, p.plan_name, p.duration_months, p.price,
                   GREATEST(0, COALESCE(p.price, 0) - " . self::PAID_SQL . ") AS unpaid_amount
            $from
            ORDER BY m.created_at DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            $params
        );

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['member_number'] = (int)($r['member_number'] ?? 0);
            $r['duration_months'] = $r['duration_months'] !== null ? (int)$r['duration_months'] : null;
            $r['price'] = $r['price'] !== null ? (float)$r['price'] : null;
            $r['unpaid_amount'] = (float)$r['unpaid_amount'];
        }
        return [$rows, $total];
    }

    public static function find(array $gym, $memberId)
    {
        $member = dbOne("
            SELECT m.*, b.batch_name, b.start_time, b.end_time, p.plan_name, p.duration_months, p.price
            FROM members m
            LEFT JOIN batches b ON m.batch_id = b.id
            LEFT JOIN plans p ON m.plan_id = p.id
            WHERE (m.id = ? OR m.member_code = ?) AND m.gym_id = ? AND m.status != 'deleted'
        ", [$memberId, $memberId, $gym['gym_id']]);
        if (!$member) {
            throw new NotFoundException("Member not found");
        }
        $member['id'] = (int)$member['id'];
        $member['member_number'] = (int)($member['member_number'] ?? 0);
        return $member;
    }

    /**
     * Next sequential number starting from 1 for this specific gym.
     */
    public static function nextMemberNumber($gymCode)
    {
        $max = (int)dbValue("SELECT MAX(member_number) FROM members WHERE gym_id = ?", [$gymCode]);
        return $max + 1;
    }

    /**
     * Sequential member code scoped to the gym (e.g. GYM003-M001, GYM003-M002).
     */
    public static function generateMemberCode($gymCode, $memberNumber = null)
    {
        $seq = $memberNumber ?? self::nextMemberNumber($gymCode);
        $prefix = $gymCode . '-M';
        $code = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        while (dbValue("SELECT 1 FROM members WHERE gym_id = ? AND member_code = ?", [$gymCode, $code])) {
            $seq++;
            $code = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * @param array $data validated: name, phone, email, start_date, expiry_date, batch_id, plan_id, status
     * @return array ['id' => int, 'member_number' => int, 'member_code' => string]
     */
    public static function create(array $gym, array $data, $photo = null)
    {
        self::assertPhoneFree($gym, $data['phone']);
        self::assertPlanAndBatch($gym, $data);

        $photoPath = Uploads::saveImage($photo, Uploads::MEMBER_PHOTOS, 'member_');
        $memberNumber = self::nextMemberNumber($gym['gym_id']);
        $memberCode = self::generateMemberCode($gym['gym_id'], $memberNumber);
        try {
            dbRun("
                INSERT INTO members (gym_id, member_code, member_number, name, phone, email, start_date, expiry_date, status, batch_id, plan_id, profile_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $gym['gym_id'],
                $memberCode,
                $memberNumber,
                $data['name'],
                $data['phone'],
                $data['email'],
                $data['start_date'] ?? today(),
                $data['expiry_date'],
                $data['status'] ?? 'active',
                $data['batch_id'],
                $data['plan_id'] ?? 0,
                $photoPath,
            ]);
        } catch (Throwable $e) {
            Uploads::delete($photoPath);
            throw $e;
        }
        return ['id' => (int)db()->lastInsertId(), 'member_number' => $memberNumber, 'member_code' => $memberCode];
    }

    /**
     * Partial update: only keys present in $data are changed.
     */
    public static function update(array $gym, $memberId, array $data, $photo = null)
    {
        $current = self::find($gym, $memberId);

        if (isset($data['phone'])) {
            self::assertPhoneFree($gym, $data['phone'], $memberId);
        }
        self::assertPlanAndBatch($gym, $data);

        $columns = ['name', 'phone', 'email', 'start_date', 'expiry_date', 'batch_id', 'plan_id', 'status'];
        $set = [];
        $params = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $data)) {
                $set[] = "$col = ?";
                $params[] = $data[$col];
            }
        }

        $photoPath = Uploads::saveImage($photo, Uploads::MEMBER_PHOTOS, 'member_');
        if ($photoPath) {
            $set[] = "profile_image = ?";
            $params[] = $photoPath;
        }
        if (empty($set)) {
            throw new ValidationException("No fields provided to update");
        }

        try {
            dbRun("UPDATE members SET " . implode(', ', $set) . " WHERE id = ? AND gym_id = ?", array_merge($params, [$memberId, $gym['gym_id']]));
        } catch (Throwable $e) {
            Uploads::delete($photoPath);
            throw $e;
        }
        if ($photoPath) {
            Uploads::delete($current['profile_image']);
        }
    }

    public static function setStatus(array $gym, $memberId, $status)
    {
        self::find($gym, $memberId);
        dbRun("UPDATE members SET status = ? WHERE id = ? AND gym_id = ?", [$status, $memberId, $gym['gym_id']]);
    }

    /**
     * Soft delete: payments and attendance history are kept.
     */
    public static function delete(array $gym, $memberId)
    {
        self::find($gym, $memberId);
        dbRun("UPDATE members SET status = 'deleted' WHERE id = ? AND gym_id = ?", [$memberId, $gym['gym_id']]);
    }

    /**
     * Bulk import. Bad rows are skipped and reported; good rows are saved in one transaction.
     * A plan_name that doesn't exist yet is created (1 month, price = paid amount).
     *
     * @return array ['total_rows', 'success_count', 'failed_count', 'errors']
     */
    public static function import(array $gym, array $rows)
    {
        if (count($rows) > self::IMPORT_MAX_ROWS) {
            throw new ValidationException("Maximum " . self::IMPORT_MAX_ROWS . " members per upload");
        }
        $code = $gym['gym_id'];

        $phones = array_fill_keys(dbRun("SELECT phone FROM members WHERE gym_id = ? AND status != 'deleted'", [$code])->fetchAll(PDO::FETCH_COLUMN), true);
        $plansByName = [];
        foreach (dbAll("SELECT id, LOWER(TRIM(plan_name)) AS name FROM plans WHERE gym_id = ?", [$code]) as $p) {
            $plansByName[$p['name']] = (int)$p['id'];
        }
        $planIds = array_fill_keys(array_values($plansByName), true);
        $batchIds = array_fill_keys(array_map('intval', dbRun("SELECT id FROM batches WHERE gym_id = ?", [$code])->fetchAll(PDO::FETCH_COLUMN)), true);

        $errors = [];
        $added = 0;

        dbTransaction(function () use ($rows, $code, &$phones, &$plansByName, &$planIds, $batchIds, &$errors, &$added) {
            foreach ($rows as $index => $row) {
                $rowNo = $index + 1;
                try {
                    $m = validate((array)$row, [
                        'name' => 'required|string|maxlen:100',
                        'phone' => 'required|string|maxlen:20',
                        'email' => 'string',
                        'joining_date' => 'date',
                        'start_date' => 'date',
                        'expiry_date' => 'date',
                        'plan_id' => 'int',
                        'plan_name' => 'string|maxlen:100',
                        'batch_id' => 'int',
                        'paid_amount' => 'numeric|min:0',
                        'payment_mode' => 'string|maxlen:50',
                    ]);
                } catch (ValidationException $e) {
                    $errors[] = ['row' => $rowNo, 'name' => $row['name'] ?? '', 'reason' => $e->getMessage()];
                    continue;
                }

                if (isset($phones[$m['phone']])) {
                    $errors[] = ['row' => $rowNo, 'name' => $m['name'], 'phone' => $m['phone'], 'reason' => "Phone number ({$m['phone']}) already registered in this gym"];
                    continue;
                }

                $planId = ($m['plan_id'] && isset($planIds[$m['plan_id']])) ? $m['plan_id'] : 0;
                if (!$planId && $m['plan_name']) {
                    $key = strtolower($m['plan_name']);
                    if (!isset($plansByName[$key])) {
                        dbRun("INSERT INTO plans (gym_id, plan_name, duration_months, price) VALUES (?, ?, 1, ?)", [$code, $m['plan_name'], $m['paid_amount'] ?? 0]);
                        $plansByName[$key] = (int)db()->lastInsertId();
                        $planIds[$plansByName[$key]] = true;
                    }
                    $planId = $plansByName[$key];
                }

                $joined = $m['joining_date'] ?? $m['start_date'] ?? today();
                $email = ($m['email'] && filter_var($m['email'], FILTER_VALIDATE_EMAIL)) ? strtolower($m['email']) : null;
                $batchId = ($m['batch_id'] && isset($batchIds[$m['batch_id']])) ? $m['batch_id'] : null;

                $memberNumber = self::nextMemberNumber($code);
                $memberCode = self::generateMemberCode($code, $memberNumber);
                dbRun("
                    INSERT INTO members (gym_id, member_code, member_number, name, phone, email, start_date, expiry_date, status, batch_id, plan_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                ", [$code, $memberCode, $memberNumber, $m['name'], $m['phone'], $email, $joined, $m['expiry_date'], $batchId, $planId]);
                $memberId = (int)db()->lastInsertId();
                $phones[$m['phone']] = true;

                if ($m['paid_amount'] > 0) {
                    dbRun("INSERT INTO payments (gym_id, member_id, amount, payment_method, payment_date) VALUES (?, ?, ?, ?, ?)",
                        [$code, $memberId, $m['paid_amount'], $m['payment_mode'] ?? 'Cash', $joined . ' 00:00:00']);
                }
                $added++;
            }
        });

        return [
            'total_rows' => count($rows),
            'success_count' => $added,
            'failed_count' => count($errors),
            'errors' => $errors,
        ];
    }

    public static function assertPhoneFree(array $gym, $phone, $exceptMemberId = null)
    {
        $sql = "SELECT 1 FROM members WHERE phone = ? AND gym_id = ? AND status != 'deleted'";
        $params = [$phone, $gym['gym_id']];
        if ($exceptMemberId) {
            $sql .= " AND id != ?";
            $params[] = $exceptMemberId;
        }
        if (dbValue($sql, $params)) {
            throw new ConflictException("A member with this phone already exists");
        }
    }

    /**
     * plan_id / batch_id (when given) must belong to this gym.
     */
    private static function assertPlanAndBatch(array $gym, array $data)
    {
        if (!empty($data['plan_id']) && !dbValue("SELECT 1 FROM plans WHERE id = ? AND gym_id = ?", [$data['plan_id'], $gym['gym_id']])) {
            throw new ValidationException("Plan not found in this gym");
        }
        if (!empty($data['batch_id']) && !dbValue("SELECT 1 FROM batches WHERE id = ? AND gym_id = ?", [$data['batch_id'], $gym['gym_id']])) {
            throw new ValidationException("Batch not found in this gym");
        }
    }
}
