<?php
// GET — all GymSathi plans (including the trial).
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('GET');
requireAdmin();

sendSuccess(dbAll("SELECT * FROM app_plans ORDER BY price"));
