-- ============================================================
-- LOSTLINK local database schema (for XAMPP / local MySQL)
-- Import this in phpMyAdmin (Import tab) or run in the SQL tab.
-- It creates the database, all tables, and one starter admin.
-- ============================================================

CREATE DATABASE IF NOT EXISTS lost_found_db
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE lost_found_db;

-- ---------- users ----------
CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(255) NOT NULL,
    email            VARCHAR(255) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    role             VARCHAR(20)  NOT NULL DEFAULT 'user',   -- 'user' | 'admin'
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    otp_code_hash    VARCHAR(255) NULL,
    otp_expires_at   DATETIME NULL,
    otp_last_sent_at DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- lost_items ----------
CREATE TABLE IF NOT EXISTS lost_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    item_name   VARCHAR(255) NOT NULL,
    description TEXT NULL,
    item_type   VARCHAR(10) NOT NULL DEFAULT 'lost',          -- 'lost' | 'found'
    picture     VARCHAR(255) NULL,
    date_lost   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FULLTEXT KEY ft_item (item_name, description)             -- needed by ai_search.php
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- verifications ----------
CREATE TABLE IF NOT EXISTS verifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    item_id    INT NOT NULL,
    user_id    INT NOT NULL,
    question1  VARCHAR(255) NULL,
    question2  VARCHAR(255) NULL,
    answer1    TEXT NULL,
    answer2    TEXT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'pending',        -- pending|answered|approved|rejected
    INDEX idx_item_id (item_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- notifications ----------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- item_claims (new claim feature) ----------
CREATE TABLE IF NOT EXISTS item_claims (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_id       INT NOT NULL,
    claimant_id   INT NOT NULL,
    claim_details TEXT NOT NULL,
    status        ENUM('pending','approved','rejected','collected') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   DATETIME NULL,
    collected_at  DATETIME NULL,
    INDEX idx_item_id (item_id),
    INDEX idx_claimant_id (claimant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- starter admin account ----------
-- Email:    admin@vu.local
-- Password: Admin@123   (stored as md5; login.php accepts md5 and auto-upgrades it)
INSERT INTO users (full_name, email, password, role, is_active)
VALUES ('Site Admin', 'admin@vu.local', '0e7517141fb53f21ee439b355b5a1d0a', 'admin', 1);
