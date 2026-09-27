<?php
// POST { razorpay_order_id, razorpay_payment_id, razorpay_signature }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$member = requireMember();
$data = validate(input(), [
    'razorpay_order_id' => 'required|string',
    'razorpay_payment_id' => 'required|string',
    'razorpay_signature' => 'required|string',
]);

$paid = PaymentService::paidOrder($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], PaymentService::GYM_PLAN);
if ((int)($paid['notes']['member_id'] ?? 0) !== $member['id']) {
    throw new ForbiddenException("This payment belongs to another member");
}
$gym = GymService::find($paid['notes']['gym_id']);

$expiry = PaymentService::applyGymPlan($gym, $member, $paid);
sendSuccess(['new_expiry' => $expiry], "Plan activated");
