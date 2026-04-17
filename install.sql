-- Reservationssystem für Werkzeuge/Geräte
-- Datenbank anlegen und importieren

CREATE DATABASE IF NOT EXISTS reservation_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservation_system;

-- Benutzer
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    full_name VARCHAR(150),
    is_admin TINYINT(1) DEFAULT 0,
    auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local',
    microsoft_id VARCHAR(100) UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Gegenstände (Werkzeuge/Geräte)
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    location VARCHAR(150),
    quantity INT DEFAULT 1,
    available TINYINT(1) DEFAULT 1,
    image_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Reservierungen
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('pending','approved','rejected','cancelled','completed') DEFAULT 'approved',
    usage_type ENUM('privat','geschaeftlich') NOT NULL DEFAULT 'privat',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Standard-Admin (Passwort: admin123 — bitte nach Installation ändern!)
INSERT INTO users (username, email, password_hash, full_name, is_admin)
VALUES ('admin', 'admin@example.com', '$2b$10$yc0tLi.u8yXsdRp.e8UFx.Cv7XiGtrkRhKhOwHaQsBQczSNZLdrGq', 'Administrator', 1);

-- Beispiel-Gegenstände
INSERT INTO items (name, description, category, location, quantity) VALUES
('Bohrmaschine Bosch GSB 18V', 'Akku-Schlagbohrmaschine inkl. 2 Akkus und Ladegerät', 'Bohrmaschinen', 'Werkstatt A - Regal 1', 2),
('Leiter 6m Alu', 'Aluminium-Schiebeleiter, 6 Meter', 'Leitern', 'Lager Halle B', 1),
('Rasenmäher Honda', 'Benzin-Rasenmäher, 53cm Schnittbreite', 'Gartengeräte', 'Außenlager', 1),
('Schlagbohrhammer Hilti', 'Schwerer Bohrhammer mit SDS-Max Aufnahme', 'Bohrmaschinen', 'Werkstatt A - Regal 2', 1),
('Winkelschleifer 230mm', 'Makita Winkelschleifer für Metall- und Steinarbeiten', 'Trennschleifer', 'Werkstatt A - Regal 3', 3);
