<?php
// POST { plan_id } — member pays their gym for a plan (Razorpay order).
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$member = requireMember();
$data = validate(input(), ['plan_id' => 'required|int']);
RateLimit::hit('payment_order', RateLimit::PAYMENT_ORDER, 'member' . $member['id']);

$gym = GymService::find($member['gym_id']);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}
sendSuccess(PaymentService::createGymPlanOrder($gym, $member, $data['plan_id']), "Order created");
