-- ============================================================
--  Gemeindeportal Wangen-Brüttisellen – Vollständige Installation
--  Enthält: Reservationssystem + CRM-System + Benutzerverwaltung
--  Stand: April 2026
--
--  Ausführen:
--    mysql -u root -p < install.sql
--  oder in phpMyAdmin -> SQL-Tab einfügen.
-- ============================================================

CREATE DATABASE IF NOT EXISTS reservation_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE reservation_system;

-- ────────────────────────────────────────────────────────────
--  1. BENUTZER
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50)  NOT NULL UNIQUE,
    email               VARCHAR(150) NOT NULL UNIQUE,
    email_verified      TINYINT(1)   NOT NULL DEFAULT 1,
    email_token         VARCHAR(64)  NULL     DEFAULT NULL,
    email_token_expires DATETIME     NULL     DEFAULT NULL,
    password_hash       VARCHAR(255) NULL,
    full_name           VARCHAR(150) NULL,
    is_admin            TINYINT(1)   NOT NULL DEFAULT 0,
    access_reservation  TINYINT(1)   NOT NULL DEFAULT 1,
    access_crm          TINYINT(1)   NOT NULL DEFAULT 1,
    auth_provider       ENUM('local','microsoft') NOT NULL DEFAULT 'local',
    microsoft_id        VARCHAR(100) NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_microsoft_id (microsoft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Admin (Login: admin / admin123 — bitte nach Installation ändern!)
INSERT INTO users (username, email, password_hash, full_name, is_admin, email_verified, auth_provider)
VALUES (
    'admin',
    'admin@example.com',
    '$2b$10$yc0tLi.u8yXsdRp.e8UFx.Cv7XiGtrkRhKhOwHaQsBQczSNZLdrGq',
    'Administrator',
    1,
    1,
    'local'
);

-- ────────────────────────────────────────────────────────────
--  2. GEGENSTÄNDE (Werkzeuge / Geräte)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    category    VARCHAR(100),
    location    VARCHAR(150),
    quantity    INT          DEFAULT 1,
    available   TINYINT(1)   DEFAULT 1,
    image_url   VARCHAR(255),
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beispiel-Gegenstände
INSERT INTO items (name, description, category, location, quantity) VALUES
('Bohrmaschine Bosch GSB 18V', 'Akku-Schlagbohrmaschine inkl. 2 Akkus und Ladegerät', 'Bohrmaschinen', 'Werkstatt A - Regal 1', 2),
('Leiter 6m Alu',              'Aluminium-Schiebeleiter, 6 Meter',                     'Leitern',       'Lager Halle B',         1),
('Rasenmäher Honda',           'Benzin-Rasenmäher, 53cm Schnittbreite',                 'Gartengeräte',  'Aussenlager',           1),
('Schlagbohrhammer Hilti',     'Schwerer Bohrhammer mit SDS-Max Aufnahme',              'Bohrmaschinen', 'Werkstatt A - Regal 2', 1),
('Winkelschleifer 230mm',      'Makita Winkelschleifer für Metall- und Steinarbeiten',  'Trennschleifer', 'Werkstatt A - Regal 3', 3);

-- ────────────────────────────────────────────────────────────
--  3. RESERVIERUNGEN
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    item_id     INT NOT NULL,
    start_date  DATETIME NOT NULL,
    end_date    DATETIME NOT NULL,
    status      ENUM('pending','approved','rejected','cancelled','completed') DEFAULT 'approved',
    usage_type  ENUM('privat','geschaeftlich') NOT NULL DEFAULT 'privat',
    occasion    VARCHAR(255) NOT NULL DEFAULT '',
    notes       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
--  4. CRM – KATEGORIEN
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS crm_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#00acb3',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO crm_categories (id, name, color) VALUES
    (1, 'Privat',      '#00acb3'),
    (2, 'Verein',      '#3a4bb0'),
    (3, 'Behörde',     '#e67e22'),
    (4, 'Unternehmen', '#2e7d32'),
    (5, 'Schule',      '#8e24aa');

-- ────────────────────────────────────────────────────────────
--  5. CRM – KONTAKTE
-- ────────────────────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
--  6. CRM – NOTIZEN / AKTIVITÄTEN
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS crm_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT      NOT NULL,
    note       TEXT     NOT NULL,
    created_by INT      NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
