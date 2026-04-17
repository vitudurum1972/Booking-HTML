-- Migration: Microsoft-Login-Unterstützung zur users-Tabelle hinzufügen
-- Ausführen in phpMyAdmin auf der bestehenden Datenbank.

ALTER TABLE users
    MODIFY password_hash VARCHAR(255) NULL;

ALTER TABLE users
    ADD COLUMN auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local' AFTER is_admin;

ALTER TABLE users
    ADD COLUMN microsoft_id VARCHAR(100) NULL AFTER auth_provider;

ALTER TABLE users
    ADD UNIQUE KEY uniq_microsoft_id (microsoft_id);
