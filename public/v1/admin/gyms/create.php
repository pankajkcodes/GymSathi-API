<?php
// POST { gym_name, owner_name, email, phone?, address? }
// Creates the gym for an existing owner account (by email) or a new one; new owners get a
// temporary password by email.
require __DIR__ . '/../../../../app/bootstrap.php';
requireMethod('POST');
requireAdmin();

$data = validate(input(), [
    'gym_name' => 'required|string|maxlen:100',
    'owner_name' => 'required|string|maxlen:100',
    'email' => 'required|email',
    'phone' => 'string|maxlen:20',
    'address' => 'string',
]);

$temporaryPassword = null;
$gym = dbTransaction(function () use ($data, &$temporaryPassword) {
    $owner = UserService::findByEmail($data['email']);
    if ($owner) {
        $ownerId = (int)$owner['id'];
    } else {
        $temporaryPassword = bin2hex(random_bytes(5));
        $ownerId = UserService::create($data['owner_name'], $data['email'], $data['phone'], password_hash($temporaryPassword, PASSWORD_DEFAULT));
    }
    return GymService::create($ownerId, $data);
});

if ($temporaryPassword) {
    Mailer::sendTemporaryPassword($data['email'], $data['owner_name'], 'owner', $temporaryPassword);
}
sendSuccess([
    'gym_id' => $gym['gym_id'],
    'email' => $data['email'],
    'temporary_password' => $temporaryPassword,
    'new_owner_account' => $temporaryPassword !== null,
], $temporaryPassword ? "Gym created. Login details were emailed to the owner." : "Gym added to the existing owner account.");
