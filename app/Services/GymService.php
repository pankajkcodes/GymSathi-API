<?php
// Gyms: lookup, profile, creation (the ONLY place gyms are created), trial.

class GymService
{
    const TRIAL_PLAN_ID = 4;

    /**
     * Gym by numeric id or code ("12" or "GYM007"). Soft-deleted gyms count as not found.
     * Legacy login columns (password, google_id) are removed from the result.
     */
    public static function find($rawId, $includeDeleted = false)
    {
        if ($rawId === null || $rawId === '') {
            return null;
        }
        $rawId = (string)$rawId;
        $gym = ctype_digit($rawId)
            ? dbOne("SELECT * FROM gyms WHERE id = ?", [$rawId])
            : dbOne("SELECT * FROM gyms WHERE gym_id = ?", [$rawId]);

        if (!$gym || (!$includeDeleted && $gym['status'] === 'deleted')) {
            return null;
        }
        unset($gym['password'], $gym['google_id']);
        $gym['id'] = (int)$gym['id'];
        return $gym;
    }

    /**
     * Gyms a user can open, for the gym switcher.
     */
    public static function forUser($userId)
    {
        $gyms = dbAll("
            SELECT g.id, g.gym_id, g.gym_name, g.logo_url, g.status, g.address, ugr.role, ugr.permissions
            FROM user_gym_roles ugr
            JOIN gyms g ON ugr.gym_id = g.id
            WHERE ugr.user_id = ? AND g.status != 'deleted'
            ORDER BY ugr.id ASC
        ", [$userId]);

        foreach ($gyms as &$g) {
            $g['id'] = (int)$g['id'];
            $g['permissions'] = $g['permissions'] !== null ? json_decode($g['permissions']) : null;
        }
        return $gyms;
    }

    public static function owner($gymPk)
    {
        return dbOne("
            SELECT u.id, u.name, u.email, u.phone
            FROM user_gym_roles ugr
            JOIN users u ON u.id = ugr.user_id
            WHERE ugr.gym_id = ? AND ugr.role = 'owner'
            ORDER BY ugr.id ASC LIMIT 1
        ", [$gymPk]);
    }

    /**
     * Public gym fields. Owner name/email come from users; the legacy columns on gyms are only
     * a fallback for rows not migrated yet.
     */
    public static function profile(array $gym, ?array $owner = null)
    {
        if ($owner === null) {
            $owner = self::owner($gym['id']) ?: [];
        }
        return [
            'id' => (int)$gym['id'],
            'gym_id' => $gym['gym_id'],
            'gym_name' => $gym['gym_name'],
            'owner_name' => $owner['name'] ?? $gym['owner_name'],
            'email' => $owner['email'] ?? $gym['email'],
            'phone' => $gym['phone'],
            'address' => $gym['address'],
            'logo_url' => $gym['logo_url'],
            'status' => $gym['status'],
            'created_at' => $gym['created_at'],
            'subscription_expiry' => $gym['subscription_expiry'],
        ];
    }

    /**
     * Create a gym owned by $ownerUserId (joins the caller's transaction if one is open).
     *
     * @param array $data gym_name, owner_name, phone, address, logo_url
     * @return array ['id', 'gym_id', 'trial_end']
     */
    public static function create($ownerUserId, array $data, $withTrial = true)
    {
        return dbTransaction(function () use ($ownerUserId, $data, $withTrial) {
            $owner = dbOne("SELECT name, email, phone FROM users WHERE id = ?", [$ownerUserId]);
            $contactEmail = $data['email'] ?? ($owner['email'] ?? null);
            $ownerName = $data['owner_name'] ?? ($owner['name'] ?? null);
            $phone = $data['phone'] ?? ($owner['phone'] ?? null);

            dbRun("
                INSERT INTO gyms (gym_id, gym_name, owner_name, email, password, phone, address, logo_url, registration_type, google_id, status)
                VALUES (NULL, ?, ?, ?, NULL, ?, ?, ?, 'email', NULL, 'active')
            ", [$data['gym_name'], $ownerName, $contactEmail, $phone, $data['address'] ?? null, $data['logo_url'] ?? null]);
            $gymPk = (int)db()->lastInsertId();

            $code = self::generateCode($gymPk);
            dbRun("UPDATE gyms SET gym_id = ? WHERE id = ?", [$code, $gymPk]);

            $giveTrial = $withTrial && !self::ownerHadTrial($ownerUserId);
            dbRun("INSERT INTO user_gym_roles (user_id, gym_id, role) VALUES (?, ?, 'owner')", [$ownerUserId, $gymPk]);

            return [
                'id' => $gymPk,
                'gym_id' => $code,
                'trial_end' => $giveTrial ? self::activateTrial($code, $gymPk, $ownerUserId) : null,
            ];
        });
    }

    /**
     * Sequential GYM code (GYM001, GYM002, ...), strictly based on the highest existing code.
     */
    private static function generateCode($gymPk)
    {
        $max = (int)dbValue("SELECT MAX(CAST(SUBSTRING(gym_id, 4) AS UNSIGNED)) FROM gyms WHERE gym_id REGEXP '^GYM[0-9]+$'");
        $next = max($max + 1, (int)$gymPk);
        $code = 'GYM' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        while (dbValue("SELECT 1 FROM gyms WHERE gym_id = ?", [$code])) {
            $next++;
            $code = 'GYM' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * One free trial per owner/account, not per gym.
     */
    public static function ownerHadTrial($userId)
    {
        $hasUsed = dbValue("SELECT has_used_trial FROM users WHERE id = ?", [$userId]);
        if (!empty($hasUsed)) {
            return true;
        }

        // Check if any owned gym currently or previously had a trial subscription
        $hadInSubs = (bool)dbValue("
            SELECT 1
            FROM user_gym_roles ugr
            JOIN gyms g ON g.id = ugr.gym_id
            JOIN gym_subscriptions gs ON gs.gym_id = g.gym_id
            WHERE ugr.user_id = ? AND ugr.role = 'owner' AND gs.plan_id IN (?, 'Trial', 'trial')
            LIMIT 1
        ", [$userId, (string)self::TRIAL_PLAN_ID]);

        if ($hadInSubs) {
            dbRun("UPDATE users SET has_used_trial = 1 WHERE id = ?", [$userId]);
            return true;
        }

        return false;
    }

    /**
     * @return string|null trial end date
     */
    public static function activateTrial($gymCode, $gymPk, $ownerUserId = null)
    {
        $months = dbValue("SELECT duration_months FROM app_plans WHERE id = ?", [self::TRIAL_PLAN_ID]);
        if ($months === null) {
            return null;
        }
        $endDate = addMonths(today(), $months);

        dbRun("
            INSERT INTO gym_subscriptions (gym_id, plan_id, amount, start_date, end_date, status)
            VALUES (?, ?, 0.00, ?, ?, 'active')
        ", [$gymCode, (string)self::TRIAL_PLAN_ID, today(), $endDate]);
        dbRun("UPDATE gyms SET subscription_expiry = ? WHERE id = ?", [$endDate, $gymPk]);

        if ($ownerUserId) {
            dbRun("UPDATE users SET has_used_trial = 1 WHERE id = ?", [$ownerUserId]);
        } else {
            $owner = self::owner($gymPk);
            if ($owner) {
                dbRun("UPDATE users SET has_used_trial = 1 WHERE id = ?", [$owner['id']]);
            }
        }

        return $endDate;
    }

    /**
     * Gyms that never had any subscription get the trial on first open — once per owner.
     * Returns the (possibly updated) gym.
     */
    public static function ensureTrial(array $gym)
    {
        if (!empty($gym['subscription_expiry']) && $gym['subscription_expiry'] !== '0000-00-00') {
            return $gym;
        }
        $hasSubscription = dbValue("SELECT COUNT(*) FROM gym_subscriptions WHERE gym_id = ?", [$gym['gym_id']]) > 0;
        $owner = self::owner($gym['id']);
        if (!$hasSubscription && $owner && !self::ownerHadTrial($owner['id'])) {
            $gym['subscription_expiry'] = self::activateTrial($gym['gym_id'], $gym['id'], $owner['id']) ?? $gym['subscription_expiry'];
        }
        return $gym;
    }

    /**
     * Number of (not deleted) gyms this user owns.
     */
    public static function ownedCount($userId)
    {
        return (int)dbValue("
            SELECT COUNT(*) FROM user_gym_roles ugr JOIN gyms g ON g.id = ugr.gym_id
            WHERE ugr.user_id = ? AND ugr.role = 'owner' AND g.status != 'deleted'
        ", [$userId]);
    }
}
