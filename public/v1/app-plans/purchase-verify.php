<?php
// POST { razorpay_order_id, razorpay_payment_id, razorpay_signature } — after Razorpay checkout.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$data = validate(input(), [
    'razorpay_order_id' => 'required|string',
    'razorpay_payment_id' => 'required|string',
    'razorpay_signature' => 'required|string',
]);

$paid = PaymentService::paidOrder($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], PaymentService::APP_PLAN);
$gym = requireGym($user, $paid['notes']['gym_id'], 'manage', false);

$expiry = PaymentService::applyAppPlan($gym, $paid);
sendSuccess(['new_expiry' => $expiry], "Subscription activated");
