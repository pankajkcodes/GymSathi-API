<?php
// POST (multipart) { gym_id, gym_name?, phone?, address?, logo? } — owner or manager.
// Owner's own name/email/password: auth/update-profile.php and auth/change-password.php.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();
$gym = requireGym($user, input('gym_id'), 'manage', false);

$data = validate(input(), [
    'gym_name' => 'string|maxlen:100',
    'phone' => 'string|maxlen:20',
    'address' => 'string',
]);
$changes = array_filter($data, function ($v) { return $v !== null; });

$logo = Uploads::saveImage(uploadedFile('logo'), Uploads::GYM_LOGOS, 'gym_logo_');
if ($logo) {
    $changes['logo_url'] = $logo;
}
if (empty($changes)) {
    throw new ValidationException("Nothing to update");
}

try {
    $set = implode(', ', array_map(function ($col) { return "$col = ?"; }, array_keys($changes)));
    dbRun("UPDATE gyms SET $set WHERE id = ?", array_merge(array_values($changes), [$gym['id']]));
} catch (Throwable $e) {
    Uploads::delete($logo);
    throw $e;
}
if ($logo) {
    Uploads::delete($gym['logo_url']);
}

sendSuccess(GymService::profile(GymService::find($gym['id'])), "Gym updated");
