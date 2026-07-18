-- Add follow-up date tracking to referrals
ALTER TABLE referrals
  ADD COLUMN IF NOT EXISTS follow_up_date DATE NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS follow_up_notes TEXT NULL AFTER follow_up_date;

CREATE INDEX IF NOT EXISTS idx_referrals_follow_up ON referrals(follow_up_date);
