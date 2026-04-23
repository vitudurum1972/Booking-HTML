-- ============================================================
--  CRM-System – Events / Veranstaltungsmodul
--  Wangen-Brüttisellen
--  Ausführen: mysql -u reservation -p reservation_system < crm/migrate_events.sql
-- ============================================================

-- Events
CREATE TABLE IF NOT EXISTS crm_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    location      VARCHAR(300) DEFAULT '',
    event_date    DATETIME     NOT NULL,
    event_end     DATETIME     NULL,
    max_participants INT       NULL,
    created_by    INT          NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teilnehmer eines Events
CREATE TABLE IF NOT EXISTS crm_event_participants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT          NOT NULL,
    contact_id    INT          NOT NULL,
    status        ENUM('invited','confirmed','declined') NOT NULL DEFAULT 'invited',
    invited_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    responded_at  DATETIME     NULL,
    invitation_sent TINYINT(1) NOT NULL DEFAULT 0,
    rsvp_token    VARCHAR(64)  NULL,
    note          VARCHAR(500) DEFAULT '',
    FOREIGN KEY (event_id)   REFERENCES crm_events(id)   ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_event_contact (event_id, contact_id),
    UNIQUE KEY uniq_rsvp_token (rsvp_token),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Spalte nachrüsten (für bereits migrierte Installationen)
ALTER TABLE crm_event_participants
    ADD COLUMN IF NOT EXISTS rsvp_token VARCHAR(64) NULL AFTER invitation_sent;

-- Unique-Index für Token nachrüsten (ignoriert Fehler wenn bereits vorhanden)
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'crm_event_participants'
      AND index_name   = 'uniq_rsvp_token'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE crm_event_participants ADD UNIQUE KEY uniq_rsvp_token (rsvp_token)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
