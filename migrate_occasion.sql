-- Migration: Spalte "occasion" (Anlass) zur reservations-Tabelle hinzufügen
-- Pflichtfeld wenn usage_type = 'geschaeftlich'
-- Ausführen: mysql -u reservation -p reservation_system < migrate_occasion.sql

ALTER TABLE reservations
  ADD COLUMN IF NOT EXISTS occasion VARCHAR(255) NOT NULL DEFAULT ''
  AFTER usage_type;
