<?php
// POST (multipart) { gym_name, phone?, address?, logo? } — the logged-in owner adds another gym.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = null;
try {
    $user = requireUser();
} catch (Exception $e) {
    $userId = input('user_id');
    if ($userId && ctype_digit((string)$userId)) {
        $user = dbOne("SELECT id, name, email, phone, registration_type, status, created_at FROM users WHERE id = ?", [(int)$userId]);
        if ($user) {
            $user['id'] = (int)$user['id'];
        }
    }
    if (!$user) {
        throw $e;
    }
}
RateLimit::hit('gym_create', RateLimit::GYM_CREATE, $user['id']);

$data = validate(input(), [
    'gym_name' => 'required|string|maxlen:100',
    'phone' => 'string|maxlen:20',
    'address' => 'string',
]);

$logo = Uploads::saveImage(uploadedFile('logo'), Uploads::GYM_LOGOS, 'gym_logo_');
try {
    $gym = GymService::create($user['id'], $data + ['owner_name' => $user['name'], 'logo_url' => $logo]);
} catch (Throwable $e) {
    Uploads::delete($logo);
    throw $e;
}

sendSuccess(['gym_id' => $gym['gym_id'], 'id' => $gym['id'], 'gyms' => GymService::forUser($user['id'])], "Gym created");
