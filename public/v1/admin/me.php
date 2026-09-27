<?php
// GET — returns the authenticated admin profile from session cookie or header
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$admin = requireAdmin();
unset($admin['password']);

sendSuccess(['user' => $admin]);
