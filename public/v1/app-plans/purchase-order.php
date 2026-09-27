<?php
// POST { gym_id, plan_id } — start buying a GymSathi plan (Razorpay order). Owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage', false);
$data = validate(input(), ['plan_id' => 'required|int']);
RateLimit::hit('payment_order', RateLimit::PAYMENT_ORDER, $gym['id']);

sendSuccess(PaymentService::createAppPlanOrder($gym, $data['plan_id']), "Order created");
