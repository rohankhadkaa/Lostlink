-- ============================================================
-- LOSTLINK — Claim enhancements (run once in phpMyAdmin)
-- Adds: rejection reason, archive flag, message read status, audit log.
-- All additive; nothing existing is removed.
-- ============================================================
USE lost_found_db;

-- Feature 5: optional rejection reason
ALTER TABLE item_claims
  ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER status;

-- Feature 6: archive collected items so they leave the active listing
ALTER TABLE lost_items
  ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0;

-- Feature 4: per-message read receipt
ALTER TABLE claim_messages
  ADD COLUMN read_at DATETIME NULL;

-- Feature 10: structured audit trail (who did what, when)
CREATE TABLE IF NOT EXISTS claim_audit (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  claim_id   INT NOT NULL,
  user_id    INT NULL,                 -- actor; NULL = system
  action     VARCHAR(64) NOT NULL,     -- claim_created | status_change | message_sent | rejected | collected ...
  detail     VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_claim_id (claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
