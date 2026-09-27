<?php
// GET ?gym_id=&from_date=&to_date= — owner or manager.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$user = requireUser();
$gym = requireGym($user, query('gym_id'), 'manage');
$q = validate($_GET, ['from_date' => 'date', 'to_date' => 'date']);

$sql = "SELECT * FROM expenses WHERE gym_id = ?";
$params = [$gym['gym_id']];
if ($q['from_date'] && $q['to_date']) {
    $sql .= " AND expense_date BETWEEN ? AND ?";
    array_push($params, $q['from_date'], $q['to_date']);
}
sendSuccess(dbAll("$sql ORDER BY expense_date DESC", $params));
