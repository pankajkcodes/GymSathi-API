<?php
// GET — the logged-in member's payments.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$member = requireMember();
sendSuccess(dbAll("SELECT id, amount, payment_date, payment_method, transaction_id FROM payments WHERE member_id = ? ORDER BY payment_date DESC", [$member['id']]));
