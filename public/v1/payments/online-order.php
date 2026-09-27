<?php
// POST { gym_id, member_id, plan_id } — staff starts an online (Razorpay) payment of a gym plan
// for a member. Members paying themselves use member-app/online-order.php.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');
$data = validate(input(), [
    'member_id' => 'required|int',
    'plan_id' => 'required|int',
]);
RateLimit::hit('payment_order', RateLimit::PAYMENT_ORDER, $gym['id']);

$member = MemberService::find($gym, $data['member_id']);
sendSuccess(PaymentService::createGymPlanOrder($gym, $member, $data['plan_id']), "Order created");
