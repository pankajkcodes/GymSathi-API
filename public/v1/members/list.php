<?php
// GET ?gym_id=&filter=&search=&page=&limit=
// filter: all | active | expired | expiring_today | blocked | inactive | unpaid | no_plan
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'members.read');
[$page, $limit, $offset] = page(20, 500);

[$rows, $total] = MemberService::search($gym, query('filter', 'all'), query('search'), $limit, $offset);
sendSuccess(paginated($rows, $total, $page, $limit));
