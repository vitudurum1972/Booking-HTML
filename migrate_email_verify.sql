-- Migration: E-Mail-Verifizierung bei Registrierung
-- Bestehende Benutzer werden als verifiziert markiert (email_verified = 1).
-- Neue Registrierungen müssen ihre E-Mail bestätigen bevor sie sich einloggen können.

ALTER TABLE users
  ADD COLUMN email_verified       TINYINT(1)  NOT NULL DEFAULT 1 AFTER email,
  ADD COLUMN email_token          VARCHAR(64)  NULL     DEFAULT NULL AFTER email_verified,
  ADD COLUMN email_token_expires  DATETIME     NULL     DEFAULT NULL AFTER email_token;
