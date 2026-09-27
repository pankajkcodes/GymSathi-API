-- 001: columns and indexes the new API needs.
-- ADDITIVE ONLY: nothing is dropped or renamed, so the old API keeps working.
-- Run once, BEFORE deploying the new API. Safe to re-run (IF NOT EXISTS).
-- Take a full backup first.

-- OTPs: one table, several purposes, with a wrong-guess counter.
ALTER TABLE password_resets
    ADD COLUMN IF NOT EXISTS purpose VARCHAR(20) NOT NULL DEFAULT 'reset' AFTER otp,
    ADD COLUMN IF NOT EXISTS attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER purpose;
CREATE INDEX IF NOT EXISTS idx_password_resets_email_purpose ON password_resets (email, purpose);

-- Push tokens belong to a person (user or member), not just a gym.
ALTER TABLE fcm_tokens
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS member_id INT NULL AFTER user_id;
CREATE INDEX IF NOT EXISTS idx_fcm_tokens_user ON fcm_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_fcm_tokens_member ON fcm_tokens (member_id);
CREATE INDEX IF NOT EXISTS idx_fcm_tokens_token ON fcm_tokens (token(255));

-- Deep-link fields the notification code already tries to write.
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS screen VARCHAR(50) NULL AFTER type,
    ADD COLUMN IF NOT EXISTS reference_id VARCHAR(50) NULL AFTER screen;

-- Soft delete for gyms.
ALTER TABLE gyms
    MODIFY status ENUM('active','inactive','trial','deleted') DEFAULT 'active';

-- Gym codes must be unique (verified: 0 duplicates today).
CREATE UNIQUE INDEX IF NOT EXISTS uniq_gyms_gym_id ON gyms (gym_id);

-- Lookups by gym code / payment id used on almost every request.
CREATE INDEX IF NOT EXISTS idx_members_gym ON members (gym_id(50));
CREATE INDEX IF NOT EXISTS idx_members_email ON members (email);
CREATE INDEX IF NOT EXISTS idx_payments_gym ON payments (gym_id);
CREATE INDEX IF NOT EXISTS idx_payments_member ON payments (member_id);
CREATE INDEX IF NOT EXISTS idx_payments_txn ON payments (transaction_id);
CREATE INDEX IF NOT EXISTS idx_attendance_gym_date ON attendance (gym_id, check_in);
CREATE INDEX IF NOT EXISTS idx_expenses_gym_date ON expenses (gym_id, expense_date);
CREATE INDEX IF NOT EXISTS idx_plans_gym ON plans (gym_id);
CREATE INDEX IF NOT EXISTS idx_batches_gym ON batches (gym_id);
CREATE INDEX IF NOT EXISTS idx_gym_subs_gym ON gym_subscriptions (gym_id(50));
CREATE INDEX IF NOT EXISTS idx_gym_subs_payment ON gym_subscriptions (payment_id);
CREATE INDEX IF NOT EXISTS idx_users_google ON users (google_id);
