<?php
// Gym notifications: saved in the notifications table and pushed to the owner/manager devices.

class NotificationService
{
    // Roles whose devices receive gym notifications.
    const RECIPIENT_ROLES = ['owner', 'manager'];

    const SCREEN_BY_TYPE = [
        'expiry' => 'member_details',
        'member' => 'member_details',
        'member_details' => 'member_details',
        'retention' => 'member_details',
        'pending_plans' => 'pending_plans',
        'overdue' => 'pending_plans',
        'members' => 'members',
        'attendance' => 'attendance',
        'payment' => 'payment',
        'financial' => 'payment',
        'expense' => 'expense',
        'reports' => 'reports',
        'report' => 'reports',
        'subscription' => 'subscription_plans',
        'subscription_plans' => 'subscription_plans',
    ];

    public static function screenForType($type)
    {
        return self::SCREEN_BY_TYPE[strtolower((string)$type)] ?? 'notifications';
    }

    /**
     * @param string|int $gymRef gym code or numeric id
     * @return array ['saved' => bool, 'sent' => int, 'failed' => int]
     */
    public static function notifyGym($gymRef, $title, $message, $type = 'system', $referenceId = null, $screen = null)
    {
        $gym = GymService::find($gymRef);
        if (!$gym) {
            throw new NotFoundException("Gym not found");
        }
        $screen = $screen ?: self::screenForType($type);

        dbRun("INSERT INTO notifications (gym_id, title, message, type, screen, reference_id, is_read) VALUES (?, ?, ?, ?, ?, ?, 0)",
            [$gym['id'], $title, $message, $type, $screen, $referenceId !== null ? (string)$referenceId : null]);

        $tokens = self::deviceTokens($gym);
        if (empty($tokens)) {
            return ['saved' => true, 'sent' => 0, 'failed' => 0];
        }

        $results = FcmClient::sendNotification($tokens, $title, $message, [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => $screen,
            'reference_id' => $referenceId !== null ? (string)$referenceId : '',
            'type' => (string)$type,
            'gym_id' => $gym['gym_id'],
        ]);

        $sent = 0;
        foreach ((array)$results as $i => $raw) {
            $res = json_decode($raw, true);
            if (isset($res['name'])) {
                $sent++;
                continue;
            }
            error_log("FCM error for gym {$gym['gym_id']}: " . $raw);
            // Forget devices that uninstalled the app.
            $code = $res['error']['details'][0]['errorCode'] ?? ($res['error']['status'] ?? '');
            if (($code === 'UNREGISTERED' || ($res['error']['code'] ?? 0) === 404) && !empty($tokens[$i])) {
                dbRun("DELETE FROM fcm_tokens WHERE token = ?", [$tokens[$i]]);
            }
        }
        return ['saved' => true, 'sent' => $sent, 'failed' => count($tokens) - $sent];
    }

    /**
     * Devices of the gym's owners/managers, plus devices registered by the old app
     * (those rows only have a gym_id).
     */
    private static function deviceTokens(array $gym)
    {
        $roles = implode(',', array_fill(0, count(self::RECIPIENT_ROLES), '?'));
        $tokens = dbRun("
            SELECT DISTINCT ft.token
            FROM fcm_tokens ft
            JOIN user_gym_roles ugr ON ugr.user_id = ft.user_id
            WHERE ugr.gym_id = ? AND ugr.role IN ($roles)
        ", array_merge([$gym['id']], self::RECIPIENT_ROLES))->fetchAll(PDO::FETCH_COLUMN);

        $legacy = dbRun("
            SELECT DISTINCT token FROM fcm_tokens
            WHERE user_id IS NULL AND member_id IS NULL AND (gym_id = ? OR gym_id = ?)
        ", [$gym['gym_id'], (string)$gym['id']])->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_merge($tokens, $legacy)));
    }
}
