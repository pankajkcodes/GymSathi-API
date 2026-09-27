<?php
// POST { gym_id, status: active|inactive|trial|deleted }  ('deleted' → hidden; set 'active' to restore)
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$raw = input();
$gymId = $raw['gym_id'] ?? $raw['id'] ?? null;
$rawStatus = strtolower(trim((string)($raw['status'] ?? '')));
if ($rawStatus === 'suspended') {
    $rawStatus = 'inactive';
}
$raw['status'] = $rawStatus;

$data = validate($raw, ['status' => 'required|in:active,inactive,trial,deleted']);
$gym = GymService::find($gymId, true);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}

dbRun("UPDATE gyms SET status = ? WHERE id = ?", [$data['status'], $gym['id']]);
sendSuccess(['gym_id' => $gym['gym_id'], 'status' => $data['status']], "Gym status updated to {$data['status']}");
