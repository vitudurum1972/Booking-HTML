-- ============================================================
-- Rebuild users table
-- ACHTUNG: Löscht die bestehende users-Tabelle komplett neu.
-- Reservierungen bleiben erhalten, aber der Fremdschlüssel auf
-- users wird temporär entfernt und danach neu angelegt.
-- Ausführen in phpMyAdmin -> SQL-Tab.
-- ============================================================

-- 1) FK von reservations auf users entfernen (falls vorhanden)
SET @fk := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'reservations'
      AND COLUMN_NAME  = 'user_id'
      AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);
SET @sql := IF(@fk IS NOT NULL,
    CONCAT('ALTER TABLE reservations DROP FOREIGN KEY ', @fk),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Alte Tabelle entfernen
DROP TABLE IF EXISTS users;

-- 3) Neu anlegen (inkl. Microsoft-Login-Felder)
CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NULL,
    full_name      VARCHAR(150) NULL,
    is_admin       TINYINT(1)   NOT NULL DEFAULT 0,
    auth_provider  ENUM('local','microsoft') NOT NULL DEFAULT 'local',
    microsoft_id   VARCHAR(100) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_microsoft_id (microsoft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Standard-Admin wieder einfügen (Login: admin / admin123)
INSERT INTO users (username, email, password_hash, full_name, is_admin, auth_provider)
VALUES (
    'admin',
    'admin@example.com',
    '$2b$10$3FowMzVhJjinwz.2JZbK4.p/nXI8CDnnpebFzi9dFwtsLjvnEFenm',
    'Administrator',
    1,
    'local'
);

-- 5) Fremdschlüssel auf reservations wiederherstellen (falls Tabelle existiert)
SET @hasRes := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations'
);
SET @sql := IF(@hasRes > 0,
    'ALTER TABLE reservations ADD CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
