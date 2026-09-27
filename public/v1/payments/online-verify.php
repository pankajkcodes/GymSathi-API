<?php
// POST { razorpay_order_id, razorpay_payment_id, razorpay_signature } — after Razorpay checkout.
// Gym, member and plan are read from the order, not from the request.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$data = validate(input(), [
    'razorpay_order_id' => 'required|string',
    'razorpay_payment_id' => 'required|string',
    'razorpay_signature' => 'required|string',
]);

$paid = PaymentService::paidOrder($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], PaymentService::GYM_PLAN);
$gym = requireGym($user, $paid['notes']['gym_id'], 'members.write');
$member = MemberService::find($gym, (int)$paid['notes']['member_id']);

$expiry = PaymentService::applyGymPlan($gym, $member, $paid);
sendSuccess(['new_expiry' => $expiry], "Member plan activated");
