-- 004: Add member_code to members and has_used_trial to users.
-- ADDITIVE ONLY: adds member_code and has_used_trial with backfill.
-- Safe to re-run.

-- 1. Track trial usage at the user/account level (not just per-gym)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS has_used_trial TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

CREATE INDEX IF NOT EXISTS idx_users_has_used_trial ON users (has_used_trial);

-- 2. Add sequential gym-scoped member code to members table
ALTER TABLE members
    ADD COLUMN IF NOT EXISTS member_code VARCHAR(50) NULL AFTER gym_id;

CREATE INDEX IF NOT EXISTS idx_members_member_code ON members (gym_id(50), member_code);

-- 3. Backfill has_used_trial for all users who have ever had a gym trial subscription
UPDATE users u
SET u.has_used_trial = 1
WHERE u.id IN (
    SELECT DISTINCT ugr.user_id
    FROM user_gym_roles ugr
    JOIN gyms g ON g.id = ugr.gym_id
    JOIN gym_subscriptions gs ON gs.gym_id = g.gym_id
    WHERE ugr.role = 'owner' AND gs.plan_id IN (4, '4', 'Trial', 'trial')
);
