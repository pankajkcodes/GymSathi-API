<?php
// POST { plan_id } — refused for the trial plan and for plans that were ever sold.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();
$raw = input();
if (!isset($raw['plan_id']) && isset($raw['id'])) {
    $raw['plan_id'] = $raw['id'];
}
$d = validate($raw, ['plan_id' => 'required|int']);

if ($d['plan_id'] === GymService::TRIAL_PLAN_ID) {
    throw new ValidationException("The trial plan can't be deleted");
}
if (dbValue("SELECT 1 FROM gym_subscriptions WHERE plan_id = ?", [(string)$d['plan_id']])) {
    throw new ConflictException("This plan has subscriptions; deleting it would break their history");
}
dbRun("DELETE FROM app_plans WHERE id = ?", [$d['plan_id']]);
sendSuccess(null, "Plan deleted");
