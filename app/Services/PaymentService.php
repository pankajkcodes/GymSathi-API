<?php
// Online payments (Razorpay). Three kinds, stored in the order's notes.type:
//   gym_plan     — a member pays the gym for a membership plan
//   app_plan     — a gym buys a GymSathi plan
//   registration — website: new owner pays while signing up
//
// Flow: create*Order() → app opens Razorpay checkout → endpoint calls paidOrder() →
// apply*() inside a lock. Amount and plan always come from the DB / the stored order,
// never from the app.

class PaymentService
{
    const GYM_PLAN = 'gym_plan';
    const APP_PLAN = 'app_plan';
    const REGISTRATION = 'registration';

    // ---------------------------------------------------------------- orders

    public static function createGymPlanOrder(array $gym, array $member, $planId)
    {
        $plan = dbOne("SELECT price, plan_name FROM plans WHERE id = ? AND gym_id = ?", [$planId, $gym['gym_id']]);
        if (!$plan) {
            throw new NotFoundException("Plan not found");
        }
        return self::order($plan, "gym_{$gym['id']}_{$member['id']}_" . time(), [
            'type' => self::GYM_PLAN,
            'gym_id' => $gym['gym_id'],
            'plan_id' => $planId,
            'member_id' => $member['id'],
        ], $member['name'], $member['email'] ?? '');
    }

    public static function createAppPlanOrder(array $gym, $planId)
    {
        $plan = self::sellableAppPlan($planId);
        $owner = GymService::owner($gym['id']);
        return self::order($plan, "app_{$gym['id']}_" . time(), [
            'type' => self::APP_PLAN,
            'gym_id' => $gym['gym_id'],
            'plan_id' => $planId,
        ], $owner['name'] ?? $gym['gym_name'], $owner['email'] ?? '');
    }

    public static function createRegistrationOrder($planId, $email, $name)
    {
        $plan = self::sellableAppPlan($planId);
        return self::order($plan, 'reg_' . time() . '_' . random_int(100, 999), [
            'type' => self::REGISTRATION,
            'plan_id' => $planId,
            'email' => $email,
        ], $name, $email);
    }

    // ---------------------------------------------------------------- verify

    /**
     * Signature valid, order fully paid at Razorpay, of the expected type.
     *
     * @return array ['order_id', 'payment_id', 'amount' (rupees), 'notes']
     */
    public static function paidOrder($orderId, $paymentId, $signature, $expectedType)
    {
        if (!RazorpayClient::signatureValid($orderId, $paymentId, $signature)) {
            throw new ValidationException("Invalid payment signature");
        }
        $order = RazorpayClient::fetchOrder($orderId);
        if (!$order) {
            throw new HttpException("Could not verify payment with Razorpay. Please contact support.", 502);
        }
        if (($order['status'] ?? '') !== 'paid' || (int)($order['amount_paid'] ?? 0) < (int)($order['amount'] ?? PHP_INT_MAX)) {
            throw new ValidationException("Payment not completed");
        }
        $notes = $order['notes'] ?? [];
        if (($notes['type'] ?? '') !== $expectedType || empty($notes['plan_id'])) {
            throw new ValidationException("This payment isn't for this purchase");
        }
        return [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => round(((int)$order['amount_paid']) / 100, 2),
            'notes' => $notes,
        ];
    }

    // ---------------------------------------------------------------- apply

    /**
     * Record the member's payment and extend their membership.
     * @return string new expiry date
     */
    public static function applyGymPlan(array $gym, array $member, array $paid)
    {
        return self::once($paid['payment_id'], function () use ($gym, $member, $paid) {
            $plan = dbOne("SELECT duration_months, price FROM plans WHERE id = ? AND gym_id = ?", [$paid['notes']['plan_id'], $gym['gym_id']]);
            self::assertAmountCovers($plan, $paid);

            $current = dbValue("SELECT expiry_date FROM members WHERE id = ? FOR UPDATE", [$member['id']]);
            $newExpiry = addMonths(self::renewFrom($current), $plan['duration_months']);

            dbRun("INSERT INTO payments (gym_id, member_id, amount, payment_method, transaction_id, payment_date) VALUES (?, ?, ?, 'Online', ?, NOW())",
                [$gym['gym_id'], $member['id'], $paid['amount'], $paid['payment_id']]);
            dbRun("UPDATE members SET expiry_date = ?, plan_id = ?, status = 'active' WHERE id = ?",
                [$newExpiry, $paid['notes']['plan_id'], $member['id']]);
            return $newExpiry;
        });
    }

    /**
     * Add a GymSathi subscription to the gym (stacked after the current one).
     * @return string new expiry date
     */
    public static function applyAppPlan(array $gym, array $paid)
    {
        return self::once($paid['payment_id'], function () use ($gym, $paid) {
            $plan = dbOne("SELECT duration_months, price FROM app_plans WHERE id = ?", [$paid['notes']['plan_id']]);
            self::assertAmountCovers($plan, $paid);

            $current = dbValue("SELECT subscription_expiry FROM gyms WHERE id = ? FOR UPDATE", [$gym['id']]);
            $start = self::renewFrom($current);
            $end = addMonths($start, $plan['duration_months']);

            self::addSubscription($gym, $paid, $start, $end);
            return $end;
        });
    }

    /**
     * Website signup: create owner + gym + paid subscription.
     * @param array $owner validated: owner_name, email, password, phone, gym_name, address
     * @return array the new gym
     */
    public static function applyRegistration(array $owner, array $paid)
    {
        if (strtolower($paid['notes']['email'] ?? '') !== $owner['email']) {
            throw new ValidationException("This payment doesn't match the registration email");
        }
        return self::once($paid['payment_id'], function () use ($owner, $paid) {
            if (UserService::emailTaken($owner['email'])) {
                error_log("Paid signup for an existing email, payment {$paid['payment_id']}");
                throw new ValidationException("Email already registered. Please contact support with payment id {$paid['payment_id']}.");
            }
            $plan = dbOne("SELECT duration_months, price FROM app_plans WHERE id = ?", [$paid['notes']['plan_id']]);
            self::assertAmountCovers($plan, $paid);

            $userId = UserService::create($owner['owner_name'], $owner['email'], $owner['phone'], password_hash($owner['password'], PASSWORD_DEFAULT));
            $gym = GymService::create($userId, [
                'gym_name' => $owner['gym_name'] ?: $owner['owner_name'] . "'s Gym",
                'owner_name' => $owner['owner_name'],
                'phone' => $owner['phone'],
                'address' => $owner['address'],
            ], false);

            self::addSubscription($gym, $paid, today(), addMonths(today(), $plan['duration_months']));
            return $gym;
        });
    }

    // ---------------------------------------------------------------- helpers

    private static function order(array $plan, $receipt, array $notes, $customerName, $customerEmail)
    {
        if ((float)$plan['price'] <= 0) {
            throw new ValidationException("Invalid plan price");
        }
        $order = RazorpayClient::createOrder((float)$plan['price'], $receipt, $notes);
        return [
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'key_id' => config('razorpay.key_id'),
            'plan_name' => $plan['plan_name'],
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'receipt' => $receipt,
            'type' => $notes['type'],
        ];
    }

    private static function sellableAppPlan($planId)
    {
        if ((int)$planId === GymService::TRIAL_PLAN_ID) {
            throw new ValidationException("The trial plan can't be purchased");
        }
        $plan = dbOne("SELECT price, plan_name FROM app_plans WHERE id = ?", [$planId]);
        if (!$plan) {
            throw new NotFoundException("Plan not found");
        }
        return $plan;
    }

    /**
     * Run $apply at most once per payment id: a DB lock stops parallel requests,
     * and a used payment id is rejected. Everything runs in one transaction.
     */
    private static function once($paymentId, callable $apply)
    {
        dbValue("SELECT GET_LOCK(?, 10)", ['rzp_' . $paymentId]);
        try {
            $used = dbValue("SELECT 1 FROM gym_subscriptions WHERE payment_id = ?", [$paymentId])
                || dbValue("SELECT 1 FROM payments WHERE transaction_id = ?", [$paymentId]);
            if ($used) {
                throw new ConflictException("This payment was already processed");
            }
            return dbTransaction($apply);
        } finally {
            dbValue("SELECT RELEASE_LOCK(?)", ['rzp_' . $paymentId]);
        }
    }

    private static function assertAmountCovers($plan, array $paid)
    {
        if (!$plan || $paid['amount'] < (float)$plan['price']) {
            error_log("Payment {$paid['payment_id']} amount {$paid['amount']} doesn't cover plan " . json_encode($plan));
            throw new ValidationException("Paid amount doesn't match the plan. Please contact support with payment id {$paid['payment_id']}.");
        }
    }

    /** Renewals start when the current period ends (or today if already expired). */
    private static function renewFrom($currentExpiry)
    {
        return (!empty($currentExpiry) && $currentExpiry > today()) ? $currentExpiry : today();
    }

    private static function addSubscription(array $gym, array $paid, $start, $end)
    {
        dbRun("
            INSERT INTO gym_subscriptions (gym_id, plan_id, amount, payment_id, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ", [$gym['gym_id'], (string)$paid['notes']['plan_id'], $paid['amount'], $paid['payment_id'], $start, $end]);
        dbRun("UPDATE gyms SET subscription_expiry = ? WHERE id = ?", [$end, $gym['id']]);
    }
}
