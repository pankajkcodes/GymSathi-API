<?php
// Health check: GET https://api.gymsathi.in/
require_once __DIR__ . '/../app/bootstrap.php';

sendSuccess(['service' => 'gymsathi-api', 'version' => 'v1'], 'ok');
