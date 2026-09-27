<?php
// Staff access = rows in user_gym_roles (role + permissions JSON). Owners are never modified here.

class StaffService
{
    const ROLES = ['manager', 'trainer', 'receptionist', 'staff'];

    /**
     * Staff of a gym (owners excluded).
     */
    public static function listForGym($gymPk)
    {
        $staff = dbAll("
            SELECT u.id, u.name, u.email, u.phone, ugr.role, ugr.permissions
            FROM user_gym_roles ugr
            JOIN users u ON ugr.user_id = u.id
            WHERE ugr.gym_id = ? AND ugr.role != 'owner'
        ", [$gymPk]);
        foreach ($staff as &$s) {
            $s['permissions'] = $s['permissions'] !== null ? json_decode($s['permissions']) : null;
        }
        return $staff;
    }

    /**
     * Give a person (found or created by email) a staff role on each gym.
     *
     * @param int[]    $gymPks       gyms the caller is allowed to manage
     * @param int|null $scopeOwnerId owner app: also remove the person from this owner's OTHER gyms.
     *                               admin: null — remove from every gym not listed.
     * @return int staff user id
     */
    public static function assign(array $gymPks, array $person, $role, $permissions, $scopeOwnerId = null)
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new ValidationException("Role must be one of: " . implode(', ', self::ROLES));
        }
        $gymPks = array_values(array_unique(array_map('intval', $gymPks)));
        if (empty($gymPks)) {
            throw new ValidationException("Select at least one gym");
        }

        $existing = UserService::findByEmail($person['email']);
        if ($existing && $scopeOwnerId !== null && (int)$existing['id'] === (int)$scopeOwnerId) {
            throw new ValidationException("You can't add yourself as staff");
        }
        if (!$existing && !empty($person['password']) && strlen($person['password']) < 6) {
            throw new ValidationException("Password must be at least 6 characters");
        }

        $temporaryPassword = null;
        $staffId = dbTransaction(function () use ($existing, $person, $gymPks, $role, $permissions, $scopeOwnerId, &$temporaryPassword) {
            if ($existing) {
                $staffId = (int)$existing['id'];
            } else {
                $password = $person['password'] ?? '';
                if ($password === '') {
                    $temporaryPassword = $password = bin2hex(random_bytes(5));
                }
                $staffId = UserService::create($person['name'], $person['email'], $person['phone'] ?? null, password_hash($password, PASSWORD_DEFAULT));
            }

            foreach ($gymPks as $gymPk) {
                self::setRole($staffId, $gymPk, $role, $permissions);
            }
            self::pruneOtherGyms($staffId, $gymPks, $scopeOwnerId);
            return $staffId;
        });

        if ($temporaryPassword !== null) {
            Mailer::sendTemporaryPassword($person['email'], $person['name'], $role, $temporaryPassword);
        }
        return $staffId;
    }

    /**
     * Insert or update one staff role. Refuses to touch an owner row.
     */
    public static function setRole($staffId, $gymPk, $role, $permissions)
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new ValidationException("Role must be one of: " . implode(', ', self::ROLES));
        }
        if (dbValue("SELECT 1 FROM user_gym_roles WHERE user_id = ? AND gym_id = ? AND role = 'owner'", [$staffId, $gymPk])) {
            throw new ValidationException("This person is the owner of this gym");
        }
        dbRun("
            INSERT INTO user_gym_roles (user_id, gym_id, role, permissions) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE role = VALUES(role), permissions = VALUES(permissions)
        ", [$staffId, $gymPk, $role, $permissions ? json_encode($permissions) : null]);
    }

    public static function remove($staffId, $gymPk)
    {
        $role = dbValue("SELECT role FROM user_gym_roles WHERE user_id = ? AND gym_id = ?", [$staffId, $gymPk]);
        if ($role === null) {
            throw new NotFoundException("Staff member not found in this gym");
        }
        if ($role === 'owner') {
            throw new ValidationException("The gym owner can't be removed");
        }
        dbRun("DELETE FROM user_gym_roles WHERE user_id = ? AND gym_id = ?", [$staffId, $gymPk]);
    }

    private static function pruneOtherGyms($staffId, array $keepGymPks, $scopeOwnerId)
    {
        $placeholders = implode(',', array_fill(0, count($keepGymPks), '?'));
        if ($scopeOwnerId !== null) {
            // Only gyms this owner owns — never other owners' gyms.
            dbRun("
                DELETE ugr FROM user_gym_roles ugr
                JOIN user_gym_roles mine ON mine.gym_id = ugr.gym_id AND mine.user_id = ? AND mine.role = 'owner'
                WHERE ugr.user_id = ? AND ugr.role != 'owner' AND ugr.gym_id NOT IN ($placeholders)
            ", array_merge([$scopeOwnerId, $staffId], $keepGymPks));
        } else {
            dbRun("DELETE FROM user_gym_roles WHERE user_id = ? AND role != 'owner' AND gym_id NOT IN ($placeholders)",
                array_merge([$staffId], $keepGymPks));
        }
    }
}
