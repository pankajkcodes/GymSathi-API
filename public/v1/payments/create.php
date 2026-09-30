<?php
// POST { gym_id, member_id, amount, payment_method?, transaction_id?, payment_date? } — record a cash/UPI/card payment.
// payment_date (YYYY-MM-DD) backdates the payment; omitted or today means now.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');
$data = validate(input(), [
    'member_id' => 'required|int',
    'amount' => 'required|numeric|min:0.01',
    'payment_method' => 'string|maxlen:50',
    'transaction_id' => 'string|maxlen:100',
    'payment_date' => 'date',
]);
// One day of leeway: the phone's local date can be ahead of the server's timezone.
if ($data['payment_date'] && $data['payment_date'] > date('Y-m-d', strtotime('+1 day'))) {
    throw new ValidationException("Payment date can't be in the future");
}
$paidAt = ($data['payment_date'] && $data['payment_date'] !== date('Y-m-d'))
    ? $data['payment_date'] . ' 00:00:00'
    : date('Y-m-d H:i:s');
MemberService::find($gym, $data['member_id']);

dbRun("INSERT INTO payments (gym_id, member_id, amount, payment_method, transaction_id, payment_date) VALUES (?, ?, ?, ?, ?, ?)",
    [$gym['gym_id'], $data['member_id'], $data['amount'], $data['payment_method'] ?? 'Cash', $data['transaction_id'], $paidAt]);
sendSuccess(['id' => (int)db()->lastInsertId()], "Payment recorded");
