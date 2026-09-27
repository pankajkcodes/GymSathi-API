<?php
// Health & Info check: GET /v1
require_once __DIR__ . '/../../app/bootstrap.php';

sendSuccess([
    'service' => 'gymsathi-api',
    'version' => 'v1',
    'status' => 'online',
    'time' => date('Y-m-d H:i:s')
], 'GymSathi API v1 active');
