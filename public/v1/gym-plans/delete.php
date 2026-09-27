<?php
// POST { gym_id, plan_id } — refused while members are on this plan.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'plans.write');
$data = validate(input(), ['plan_id' => 'required|int']);

$inUse = (int)dbValue("SELECT COUNT(*) FROM members WHERE plan_id = ? AND gym_id = ? AND status != 'deleted'", [$data['plan_id'], $gym['gym_id']]);
if ($inUse > 0) {
    throw new ConflictException("$inUse member(s) are on this plan. Move them to another plan first.");
}
if (dbRun("DELETE FROM plans WHERE id = ? AND gym_id = ?", [$data['plan_id'], $gym['gym_id']])->rowCount() === 0) {
    throw new NotFoundException("Plan not found");
}
sendSuccess(null, "Plan deleted");
