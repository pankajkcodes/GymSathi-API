<?php
// POST { gym_id, plan_id?, plan_name?, duration_months, amount, payment_method?, transaction_id? }
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$raw = input();
if (!isset($raw['gym_id']) && isset($raw['id'])) {
    $raw['gym_id'] = $raw['id'];
}
$data = validate($raw, [
    'gym_id' => 'required',
    'duration_months' => 'required|int',
    'amount' => 'required|numeric',
]);

$gym = GymService::find($data['gym_id'], true);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}

$months = max(1, (int)$data['duration_months']);
$amount = (float)$data['amount'];
$gymCode = $gym['gym_id'];

// Resolve plan_id
$planId = null;
if (!empty($raw['plan_id'])) {
    $planId = (int)$raw['plan_id'];
} elseif (!empty($raw['plan_name'])) {
    $matchedPlan = dbOne("SELECT id FROM app_plans WHERE plan_name LIKE ? LIMIT 1", ['%' . trim($raw['plan_name']) . '%']);
    if ($matchedPlan) {
        $planId = (int)$matchedPlan['id'];
    }
}
if (!$planId) {
    // Default to matching duration or trial
    $matchedPlan = dbOne("SELECT id FROM app_plans WHERE duration_months = ? LIMIT 1", [$months]);
    $planId = $matchedPlan ? (int)$matchedPlan['id'] : GymService::TRIAL_PLAN_ID;
}

// Transaction reference or offline payment tag
$paymentMethod = trim((string)($raw['payment_method'] ?? ''));
$txnId = trim((string)($raw['transaction_id'] ?? $raw['payment_id'] ?? ''));
if (!$txnId && $paymentMethod) {
    $txnId = strtoupper($paymentMethod) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
} elseif (!$txnId) {
    $txnId = 'ADMIN-OFFLINE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// If current expiry is in the future, extend from that date; otherwise extend from today
$currentExpiry = $gym['subscription_expiry'];
$baseTime = ($currentExpiry && strtotime($currentExpiry) > time()) 
    ? strtotime($currentExpiry) 
    : time();

$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime("+{$months} months", $baseTime));

dbTransaction(function () use ($gym, $gymCode, $planId, $amount, $txnId, $startDate, $endDate) {
    dbRun("
        INSERT INTO gym_subscriptions (gym_id, plan_id, amount, payment_id, status, start_date, end_date, created_at)
        VALUES (?, ?, ?, ?, 'active', ?, ?, NOW())
    ", [$gymCode, $planId, $amount, $txnId, $startDate, $endDate]);

    // Update gym expiry date and restore active operational status if inactive
    dbRun("
        UPDATE gyms 
        SET subscription_expiry = ?,
            status = CASE WHEN status = 'inactive' THEN 'active' ELSE status END
        WHERE id = ?
    ", [$endDate, $gym['id']]);
});

sendSuccess([
    'gym_id' => $gymCode,
    'plan_id' => $planId,
    'payment_id' => $txnId,
    'start_date' => $startDate,
    'subscription_expiry' => $endDate,
], "Subscription activated successfully until " . date('d M Y', strtotime($endDate)));

