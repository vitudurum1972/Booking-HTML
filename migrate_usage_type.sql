-- Migration: usage_type zur reservations-Tabelle hinzufügen
-- Ausführen falls die Spalte fehlt (Fehlermeldung: Unknown column 'usage_type')

ALTER TABLE reservations
  ADD COLUMN IF NOT EXISTS usage_type ENUM('privat','geschaeftlich') NOT NULL DEFAULT 'privat'
  AFTER status;
