-- 005: Add member_number (sequential integer starting from 1 for each gym).
-- ADDITIVE ONLY: adds member_number and populates existing rows.
-- Safe to re-run.

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS member_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER member_code;

CREATE INDEX IF NOT EXISTS idx_members_gym_number ON members (gym_id(50), member_number);

-- Backfill member_number from member_code (e.g. GYM003-M001 -> 1, GYM003-M002 -> 2)
UPDATE members
SET member_number = CAST(SUBSTRING_INDEX(member_code, '-M', -1) AS UNSIGNED)
WHERE member_code LIKE '%-M%';
