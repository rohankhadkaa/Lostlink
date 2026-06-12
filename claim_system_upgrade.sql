-- ============================================================
-- LOSTLINK — Claim Management upgrade
-- Run once in phpMyAdmin (SQL tab) against your lost_found_db.
-- Safe to read top-to-bottom; comments explain each step.
-- ============================================================

-- 1) Add claimant contact fields to the claim
ALTER TABLE item_claims
  ADD COLUMN claimant_name VARCHAR(255) NULL AFTER claimant_id,
  ADD COLUMN contact_info  VARCHAR(255) NULL AFTER claimant_name;

-- 2) Widen status to a string so the full workflow fits
--    (Submitted -> Under Review -> Verification in Progress ->
--     Verified/Ready for Collection -> Collected, plus Rejected)
ALTER TABLE item_claims
  MODIFY status VARCHAR(32) NOT NULL DEFAULT 'submitted';

-- 3) Map any pre-existing values from the earlier version
UPDATE item_claims SET status='submitted'            WHERE status='pending';
UPDATE item_claims SET status='ready_for_collection' WHERE status='approved';
-- 'rejected' and 'collected' already match the new vocabulary

-- 4) Conversation + audit trail (one row per message OR system event)
CREATE TABLE IF NOT EXISTS claim_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    claim_id    INT NOT NULL,
    sender_role VARCHAR(10) NOT NULL,          -- 'user' | 'admin' | 'system'
    sender_id   INT NULL,                      -- users.id, or NULL for 'system'
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_claim_id (claim_id),
    INDEX idx_claim_after (claim_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
