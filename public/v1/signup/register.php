<?php
// New owner + first gym, with the free trial (multipart/form-data).
// Email signup:  gym_name, owner_name, email, password, phone?, address?, logo? (file)
// Google signup: gym_name, owner_name?, id_token, phone?, address?, logo?   (email comes from Google)
// → the same payload as a login (user, gyms, token), so the app is signed in straight away.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');
RateLimit::hit('register', RateLimit::REGISTER);

$data = validate(input(), [
    'gym_name' => 'required|string|maxlen:100',
    'owner_name' => 'string|maxlen:100',
    'email' => 'email',
    'password' => 'string',
    'phone' => 'string|maxlen:20',
    'address' => 'string',
    'id_token' => 'string',
]);

$googleId = null;
if ($data['id_token']) {
    $google = GoogleAuth::verify($data['id_token']);
    $data['email'] = $google['email'];
    $data['owner_name'] = $data['owner_name'] ?: $google['name'];
    $googleId = $google['sub'];
} elseif (!$data['email'] || strlen((string)$data['password']) < 6) {
    throw new ValidationException("Email and a password of at least 6 characters are required");
}
if (!$data['owner_name']) {
    throw new ValidationException("Owner name is required");
}
if (UserService::emailTaken($data['email'])) {
    throw new ConflictException("Email already registered. Please log in.");
}

$logo = Uploads::saveImage(uploadedFile('logo'), Uploads::GYM_LOGOS, 'gym_logo_');
try {
    $userId = dbTransaction(function () use ($data, $googleId, $logo) {
        $userId = UserService::create(
            $data['owner_name'],
            $data['email'],
            $data['phone'],
            $data['password'] ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
            $googleId ? 'google' : 'email',
            $googleId
        );
        GymService::create($userId, [
            'gym_name' => $data['gym_name'],
            'owner_name' => $data['owner_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'logo_url' => $logo,
        ]);
        return $userId;
    });
} catch (Throwable $e) {
    Uploads::delete($logo);
    throw $e;
}

sendSuccess(UserService::loginResponse(UserService::findByEmail($data['email'])), "Gym registered successfully");
