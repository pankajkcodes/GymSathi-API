<?php
// Owner/staff accounts (users table) and the login response.

class UserService
{
    public static function findByEmail($email)
    {
        return dbOne("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    }

    /**
     * Taken if it's a user, or a legacy owner stored only on a gyms row.
     */
    public static function emailTaken($email, $exceptGymPk = null)
    {
        if (self::findByEmail($email)) {
            return true;
        }
        $sql = "SELECT 1 FROM gyms WHERE email = ?" . ($exceptGymPk ? " AND id != ?" : "") . " LIMIT 1";
        return (bool)dbValue($sql, $exceptGymPk ? [$email, $exceptGymPk] : [$email]);
    }

    /**
     * @return int new user id
     */
    public static function create($name, $email, $phone, $passwordHash, $registrationType = 'email', $googleId = null, $hasUsedTrial = 0)
    {
        dbRun("
            INSERT INTO users (name, email, password, phone, registration_type, google_id, status, has_used_trial)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ", [$name, $email, $passwordHash, $phone, $registrationType, $googleId, (int)$hasUsedTrial]);
        return (int)db()->lastInsertId();
    }

    /**
     * Find a user by email, creating one for a legacy owner if needed.
     *
     * Owners who signed up through the old API exist only on the gyms row (email + password
     * hash). On first contact (login, OTP, Google) we create their users row and owner link.
     * migrations/002 does this for everyone at once; this covers old-API signups made later.
     */
    public static function findOrAdoptByEmail($email)
    {
        $user = self::findByEmail($email);
        if ($user) {
            return $user;
        }

        $gym = dbOne("SELECT * FROM gyms WHERE email = ? LIMIT 1", [$email]);
        if (!$gym) {
            return null;
        }

        dbTransaction(function () use ($gym, $email) {
            $hasTrial = (bool)dbValue("
                SELECT 1 FROM gym_subscriptions 
                WHERE gym_id = ? AND plan_id IN (4, '4', 'Trial', 'trial') 
                LIMIT 1
            ", [$gym['gym_id']]);

            $userId = self::create(
                $gym['owner_name'] ?: $gym['gym_name'],
                $email,
                $gym['phone'],
                $gym['password'],
                $gym['registration_type'] ?: 'email',
                $gym['google_id'],
                $hasTrial ? 1 : 0
            );
            if (!GymService::owner($gym['id'])) {
                dbRun("INSERT INTO user_gym_roles (user_id, gym_id, role) VALUES (?, ?, 'owner')", [$userId, $gym['id']]);
            }
        });

        return self::findByEmail($email);
    }

    public static function assertActive(array $user)
    {
        if ($user['status'] !== 'active') {
            throw new ForbiddenException("Your account is " . $user['status'] . ". Please contact support.");
        }
    }

    /**
     * What the app gets after any login: user, first gym (for older screens), all gyms, token.
     */
    public static function loginResponse(array $user)
    {
        $gyms = GymService::forUser((int)$user['id']);
        $first = $gyms[0] ?? null;

        return [
            "id" => (int)$user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "phone" => $user['phone'],
            "has_used_trial" => (bool)($user['has_used_trial'] ?? 0),
            "gym_id" => $first['gym_id'] ?? null,
            "role" => $first['role'] ?? 'owner',
            "gym_name" => $first['gym_name'] ?? '',
            "logo_url" => $first['logo_url'] ?? null,
            "registration_type" => $user['registration_type'],
            "status" => $user['status'],
            "created_at" => $user['created_at'],
            "token" => createToken('user', $user['id']),
            "gyms" => $gyms,
        ];
    }
}
