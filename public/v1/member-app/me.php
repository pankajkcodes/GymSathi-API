<?php
// GET — the logged-in member's profile with plan, batch and gym.
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('GET');

$me = requireMember();
$gym = GymService::find($me['gym_id']);
if (!$gym) {
    throw new NotFoundException("Gym not found");
}

$member = MemberService::find($gym, $me['id']);
$member['gym_details'] = GymService::profile($gym);
sendSuccess($member);
