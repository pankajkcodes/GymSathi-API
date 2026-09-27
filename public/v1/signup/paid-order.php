<?php
// Website paid signup, step 1. POST { plan_id, email, owner_name } → Razorpay order details
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
RateLimit::hit('payment_order', RateLimit::PAYMENT_ORDER);

$data = validate(input(), [
    'plan_id' => 'required|int',
    'email' => 'required|email',
    'owner_name' => 'required|string|maxlen:100',
]);
if (UserService::emailTaken($data['email'])) {
    throw new ConflictException("Email already registered. Please log in to buy a plan.");
}

sendSuccess(PaymentService::createRegistrationOrder($data['plan_id'], $data['email'], $data['owner_name']), "Order created");
