-- ============================================================
-- nikahin – E-Wedding Invitation Platform
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS nikahin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nikahin;

-- ─── USERS ───────────────────────────────────────────────
CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(180) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  avatar      VARCHAR(255) DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─── INVITATIONS ─────────────────────────────────────────
CREATE TABLE invitations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  slug            VARCHAR(120) NOT NULL UNIQUE,
  groom_name      VARCHAR(120) NOT NULL,
  bride_name      VARCHAR(120) NOT NULL,
  wedding_date    DATE NOT NULL,
  wedding_time    TIME NOT NULL,
  venue_name      VARCHAR(255) DEFAULT NULL,
  venue_address   TEXT DEFAULT NULL,
  venue_lat       DECIMAL(10,8) DEFAULT NULL,
  venue_lng       DECIMAL(11,8) DEFAULT NULL,
  theme           VARCHAR(60) DEFAULT 'elegant',
  status          ENUM('draft','published') DEFAULT 'draft',
  published_at    DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── INVITATION DETAILS ──────────────────────────────────
CREATE TABLE invitation_details (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id         INT NOT NULL UNIQUE,
  -- Groom
  groom_father          VARCHAR(120) DEFAULT NULL,
  groom_mother          VARCHAR(120) DEFAULT NULL,
  groom_color           VARCHAR(20) DEFAULT NULL,
  groom_religion        VARCHAR(60) DEFAULT NULL,
  groom_bio             TEXT DEFAULT NULL,
  groom_photo           VARCHAR(255) DEFAULT NULL,
  -- Bride
  bride_father          VARCHAR(120) DEFAULT NULL,
  bride_mother          VARCHAR(120) DEFAULT NULL,
  bride_color           VARCHAR(20) DEFAULT NULL,
  bride_religion        VARCHAR(60) DEFAULT NULL,
  bride_bio             TEXT DEFAULT NULL,
  bride_photo           VARCHAR(255) DEFAULT NULL,
  -- Gift
  bank_name             VARCHAR(120) DEFAULT NULL,
  bank_account          VARCHAR(60) DEFAULT NULL,
  bank_holder           VARCHAR(120) DEFAULT NULL,
  -- AI
  ai_prompt             TEXT DEFAULT NULL,
  ai_design_json        JSON DEFAULT NULL,
  -- Hero / cover
  hero_photo            VARCHAR(255) DEFAULT NULL,
  prewedding_photos     JSON DEFAULT NULL,
  reference_images      JSON DEFAULT NULL,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
);

-- ─── GUESTS ──────────────────────────────────────────────
CREATE TABLE guests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT NOT NULL,
  name            VARCHAR(120) NOT NULL,
  phone           VARCHAR(30) DEFAULT NULL,
  email           VARCHAR(180) DEFAULT NULL,
  sent_at         DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
);

-- ─── RSVP ────────────────────────────────────────────────
CREATE TABLE rsvp (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT NOT NULL,
  guest_id        INT DEFAULT NULL,
  name            VARCHAR(120) NOT NULL,
  status          ENUM('attending','not_attending','maybe') NOT NULL,
  attendance_count INT DEFAULT 1,
  message         TEXT DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
  FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
);

-- ─── GREETINGS ───────────────────────────────────────────
CREATE TABLE greetings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT NOT NULL,
  name            VARCHAR(120) NOT NULL,
  message         TEXT NOT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
);

-- ─── PAYMENTS ────────────────────────────────────────────
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id   INT NOT NULL,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 99000.00,
  status          ENUM('pending','paid','failed') DEFAULT 'pending',
  payment_ref     VARCHAR(120) DEFAULT NULL,
  paid_at         DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
);

-- ─── INDEXES ─────────────────────────────────────────────
CREATE INDEX idx_invitations_user ON invitations(user_id);
CREATE INDEX idx_invitations_slug ON invitations(slug);
CREATE INDEX idx_guests_invitation ON guests(invitation_id);
CREATE INDEX idx_rsvp_invitation ON rsvp(invitation_id);
CREATE INDEX idx_greetings_invitation ON greetings(invitation_id);
