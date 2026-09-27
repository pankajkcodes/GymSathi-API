<?php
// Website paid signup, step 2 (after Razorpay checkout).
// POST { razorpay_order_id, razorpay_payment_id, razorpay_signature,
//        owner_name, email, password, phone?, gym_name?, address?, district?, state?, pincode? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$data = validate(input(), [
    'razorpay_order_id' => 'required|string',
    'razorpay_payment_id' => 'required|string',
    'razorpay_signature' => 'required|string',
    'owner_name' => 'required|string|maxlen:100',
    'email' => 'required|email',
    'password' => 'required|string',
    'phone' => 'string|maxlen:20',
    'gym_name' => 'string|maxlen:100',
    'address' => 'string',
    'district' => 'string',
    'state' => 'string',
    'pincode' => 'string|maxlen:10',
]);
if (strlen($data['password']) < 6) {
    throw new ValidationException("Password must be at least 6 characters long");
}

$paid = PaymentService::paidOrder($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], PaymentService::REGISTRATION);

$data['address'] = implode(', ', array_filter([$data['address'], $data['district'], $data['state']]))
    . ($data['pincode'] ? ' - ' . $data['pincode'] : '');
$gym = PaymentService::applyRegistration($data, $paid);

sendSuccess(['gym_id' => $gym['gym_id']], "Registration and payment successful. You can now log in.");
