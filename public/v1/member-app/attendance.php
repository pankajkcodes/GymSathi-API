<?php
// GET — the logged-in member's visits, newest first.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$member = requireMember();
sendSuccess(AttendanceService::history($member['id']));
