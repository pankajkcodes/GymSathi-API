<?php
// POST { gym_id, member_id, amount, payment_method?, transaction_id? } — record a cash/UPI/card payment.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'members.write');
$data = validate(input(), [
    'member_id' => 'required|int',
    'amount' => 'required|numeric|min:0.01',
    'payment_method' => 'string|maxlen:50',
    'transaction_id' => 'string|maxlen:100',
]);
MemberService::find($gym, $data['member_id']);

dbRun("INSERT INTO payments (gym_id, member_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?)",
    [$gym['gym_id'], $data['member_id'], $data['amount'], $data['payment_method'] ?? 'Cash', $data['transaction_id']]);
sendSuccess(['id' => (int)db()->lastInsertId()], "Payment recorded");
