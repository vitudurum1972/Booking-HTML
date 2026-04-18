-- Migration: Berechtigungen pro Benutzer fuer Reservationssystem und CRM
-- Standardmaessig haben alle bestehenden Benutzer Zugriff auf beide Systeme.

ALTER TABLE users
  ADD COLUMN access_reservation TINYINT(1) NOT NULL DEFAULT 1 AFTER is_admin,
  ADD COLUMN access_crm        TINYINT(1) NOT NULL DEFAULT 1 AFTER access_reservation;
