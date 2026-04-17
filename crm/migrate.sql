-- ============================================================
--  CRM-System – Datenbankstruktur
--  Wangen-Brüttisellen
--  Ausführen: mysql -u reservation -p reservation_system < crm/migrate.sql
-- ============================================================

-- Kategorien
CREATE TABLE IF NOT EXISTS crm_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#00acb3',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standardkategorien einfügen
INSERT IGNORE INTO crm_categories (id, name, color) VALUES
    (1, 'Privat',       '#00acb3'),
    (2, 'Verein',       '#3a4bb0'),
    (3, 'Behörde',      '#e67e22'),
    (4, 'Unternehmen',  '#2e7d32'),
    (5, 'Schule',       '#8e24aa');

-- Kontakte
CREATE TABLE IF NOT EXISTS crm_contacts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    first_name   VARCHAR(100) DEFAULT '',
    last_name    VARCHAR(150) NOT NULL,
    organization VARCHAR(200) DEFAULT '',
    email        VARCHAR(200) DEFAULT '',
    phone        VARCHAR(60)  DEFAULT '',
    address      TEXT,
    notes        TEXT,
    category_id  INT          NULL,
    tags         VARCHAR(500) DEFAULT '',
    zusatz       VARCHAR(200) DEFAULT '',
    webseite     VARCHAR(300) DEFAULT '',
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES crm_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Felder nachrüsten (falls Tabelle bereits existiert)
ALTER TABLE crm_contacts
    ADD COLUMN IF NOT EXISTS zusatz   VARCHAR(200) DEFAULT '' AFTER tags,
    ADD COLUMN IF NOT EXISTS webseite VARCHAR(300) DEFAULT '' AFTER zusatz;

-- Notizen / Aktivitäten zu Kontakten
CREATE TABLE IF NOT EXISTS crm_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT          NOT NULL,
    note       TEXT         NOT NULL,
    created_by INT          NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
