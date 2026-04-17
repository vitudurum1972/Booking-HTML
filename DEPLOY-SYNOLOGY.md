# Deployment: Werkzeug-Reservation auf Synology NAS

**Zielumgebung:** DSM 7.2, Web Station 4, MariaDB 10, PHP 8.x  
**Zugriff:** Eigene (Sub-)Domain, z.B. `reservation.gemeinde.ch`

---

## 1. Voraussetzungen im Paketzentrum

Folgende Pakete muessen installiert und aktiv sein:

- **Web Station** (Version 4.x)
- **MariaDB 10**
- **PHP 8.0** oder **PHP 8.2** (im Paketzentrum unter "PHP-Profil" verfuegbar)

Optional:
- **phpMyAdmin** (erleichtert die Datenbank-Verwaltung)

---

## 2. MariaDB einrichten

### 2.1 Root-Passwort setzen

Nach der Installation von MariaDB muss ein Root-Passwort gesetzt werden. Oeffne ein Terminal (SSH) auf der Synology:

```bash
# SSH aktivieren: DSM > Systemsteuerung > Terminal & SNMP > SSH aktivieren

ssh admin@192.168.30.10

# MariaDB-Konsole oeffnen (Pfad kann variieren)
sudo /usr/local/mariadb10/bin/mysql -u root
```

In der MariaDB-Konsole:

```sql
-- Root-Passwort setzen
ALTER USER 'root'@'localhost' IDENTIFIED BY 'Explorer$1972';
FLUSH PRIVILEGES;
```

### 2.2 Datenbank und Benutzer anlegen

```sql
-- Datenbank erstellen
CREATE DATABASE reservation_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Eigenen DB-Benutzer anlegen (nicht root verwenden!)
CREATE USER 'reservation'@'localhost' IDENTIFIED BY 'DB_PASSWORT_HIER';
GRANT ALL PRIVILEGES ON reservation_system.* TO 'reservation'@'localhost';
FLUSH PRIVILEGES;
```

### 2.3 Tabellen importieren

```bash
# Via SSH
sudo /usr/local/mariadb10/bin/mysql -u reservation -p reservation_system < /var/services/web/reservation/install.sql
```

Oder via **phpMyAdmin**: Datenbank `reservation_system` auswaehlen > Tab "Importieren" > `install.sql` hochladen.

**Hinweis:** In `install.sql` steht bereits `CREATE DATABASE` - falls die DB schon existiert, die erste Zeile entfernen oder ignorieren. Die `USE reservation_system;` Zeile sorgt dafuer, dass die richtige DB verwendet wird.

---

## 3. PHP-Profil in Web Station konfigurieren

1. **Web Station** oeffnen > **Skriptsprachen-Einstellungen** > **PHP erstellen**
2. Profil-Name: `PHP-Reservation`
3. PHP-Version: **8.0** oder **8.2**
4. **Extensions aktivieren** (Haken setzen):
   - `curl`
   - `openssl`
   - `pdo_mysql`
   - `mbstring`
   - `json` (meist standardmaessig aktiv)
   - `session` (meist standardmaessig aktiv)
5. PHP-Einstellungen anpassen (optional, unter "Kerneinstellungen"):
   - `display_errors` = `Off` (Produktion)
   - `error_log` = `/var/services/web/reservation/php_errors.log`
   - `date.timezone` = `Europe/Zurich`
6. Speichern

---

## 4. App-Dateien auf die Synology kopieren

### Zielverzeichnis erstellen

```bash
sudo mkdir -p /var/services/web/reservation
```

### Dateien hochladen

Kopiere alle Projektdateien per SFTP, File Station oder Git in:

```
/var/services/web/reservation/
```

Die Struktur sollte so aussehen:

```
/var/services/web/reservation/
  ├── admin/
  │   ├── index.php
  │   ├── items.php
  │   ├── reservations.php
  │   └── users.php
  ├── assets/
  │   ├── style.css
  │   └── wappen.svg
  ├── auth/
  │   ├── .htaccess
  │   ├── microsoft-callback.php
  │   └── microsoft-login.php
  ├── includes/
  │   ├── footer.php
  │   ├── header.php
  │   └── mail.php
  ├── calendar.php
  ├── config.php
  ├── index.php
  ├── items.php
  ├── login.php
  ├── logout.php
  ├── my_reservations.php
  ├── register.php
  └── reserve.php
```

### Berechtigungen setzen

```bash
sudo chown -R http:http /var/services/web/reservation
sudo chmod -R 755 /var/services/web/reservation
```

---

## 5. config.php anpassen

Die wichtigsten Aenderungen in `/var/services/web/reservation/config.php`:

```php
// Datenbank - Synology MariaDB 10 Socket-Verbindung
define('DB_HOST', '/run/mysqld/mysqld10.sock');
define('DB_NAME', 'reservation_system');
define('DB_USER', 'reservation');
define('DB_PASS', 'DB_PASSWORT_HIER');

// App-URL auf deine Subdomain anpassen
define('APP_URL', 'https://reservation.gemeinde.ch');
```

**Wichtig:** Synology MariaDB verwendet standardmaessig einen Unix-Socket statt TCP. Falls der Socket-Pfad nicht funktioniert, probiere:

```php
// Alternative 1: anderer Socket-Pfad
define('DB_HOST', '/run/mysqld/mysqld.sock');

// Alternative 2: TCP-Verbindung
define('DB_HOST', '127.0.0.1');
// (nicht 'localhost', da PHP sonst den Socket sucht)
```

Der PDO-Verbindungsstring in config.php muss fuer Socket-Verbindungen angepasst werden. Ersetze den bestehenden PDO-Block:

```php
try {
    $dsn = (strpos(DB_HOST, '/') === 0)
        ? 'mysql:unix_socket=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4'
        : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Datenbank-Verbindung fehlgeschlagen: ' . $e->getMessage());
}
```

---

## 6. Virtual Host in Web Station einrichten

1. **Web Station** oeffnen > **Webdienst-Portal** > **Erstellen** > **Virtueller Host**
2. Einstellungen:
   - **Hostname:** `reservation.gemeinde.ch` (deine Subdomain)
   - **Port:** 80 (und 443 fuer HTTPS)
   - **Dokumentenstammordner:** `web/reservation`
   - **HTTP-Backend-Server:** Nginx
   - **PHP:** Profil `PHP-Reservation` (das in Schritt 3 erstellte)
3. Speichern

### DNS konfigurieren

Beim DNS-Provider einen A-Record oder CNAME erstellen:

```
reservation.gemeinde.ch  →  A  →  <oeffentliche IP der Synology>
```

Oder fuer internes Netz im lokalen DNS/Router:

```
reservation.gemeinde.ch  →  A  →  192.168.30.10
```

---

## 7. HTTPS mit Let's Encrypt (empfohlen)

1. **DSM** > **Systemsteuerung** > **Sicherheit** > **Zertifikat**
2. **Hinzufuegen** > **Neues Zertifikat hinzufuegen** > **Zertifikat von Let's Encrypt**
3. Domain: `reservation.gemeinde.ch`
4. Nach Erstellung: **Konfigurieren** > dem Virtual Host das Zertifikat zuweisen

**Voraussetzung:** Port 80 muss von aussen erreichbar sein (fuer die Let's Encrypt Validierung).

---

## 8. Nginx-Anpassungen (optional, aber empfohlen)

Web Station 4 erstellt automatisch eine Nginx-Konfiguration fuer den Virtual Host. Falls du Anpassungen brauchst (z.B. den `/missing`-Fehler beheben oder saubere URLs), kannst du eine benutzerdefinierte Konfiguration hinzufuegen.

Erstelle in Web Station unter dem Virtual Host die **Benutzerdefinierte Konfiguration** oder erstelle manuell:

```
/etc/nginx/conf.d/user.conf.d/reservation.conf
```

Beispielinhalt:

```nginx
# Fehlerseiten - verhindert den /missing-Fehler
error_page 404 /login.php;

# PHP-Dateien ohne .php-Endung (optional)
location / {
    try_files $uri $uri/ /index.php?$args;
}

# Sicherheit: versteckte Dateien blockieren
location ~ /\. {
    deny all;
}

# SQL-Dateien blockieren
location ~* \.(sql|md)$ {
    deny all;
}
```

**Hinweis:** Der Nginx-Fehler `GET /missing` aus deinem Error-Log deutet darauf hin, dass in der bestehenden Nginx-Konfiguration ein `try_files ... /missing;` steht. Mit einem eigenen Virtual Host wird eine neue Konfiguration generiert und dieses Problem verschwindet.

---

## 9. Erster Test

1. Oeffne `https://reservation.gemeinde.ch/diag.php` im Browser
2. Pruefe, dass alle Extensions gruen ("geladen") angezeigt werden
3. Oeffne `https://reservation.gemeinde.ch/login.php`
4. Login mit: **admin** / **admin123**
5. **Sofort das Admin-Passwort aendern!**

### Fehlersuche

| Problem | Loesung |
|---------|---------|
| "Datenbank-Verbindung fehlgeschlagen" | Socket-Pfad pruefen: `ls /run/mysqld/` zeigt verfuegbare Sockets |
| Weisse Seite | PHP Error Log pruefen: `tail -f /var/services/web/reservation/php_errors.log` |
| 403 Forbidden | Berechtigungen: `chown -R http:http /var/services/web/reservation` |
| CSS/Bilder laden nicht | `APP_URL` in config.php pruefen - muss exakt der Domain entsprechen |
| "SQLSTATE[HY000]" | MariaDB laeuft nicht: `sudo synoservice --status pkgctl-MariaDB10` |

---

## 10. Sicherheit (Produktion)

- [ ] `diag.php` loeschen nach erfolgreichem Test
- [ ] Admin-Passwort aendern (Standard: admin123)
- [ ] `display_errors = Off` im PHP-Profil
- [ ] DB-Benutzer hat nur Rechte auf `reservation_system` (nicht root verwenden)
- [ ] HTTPS erzwingen (HTTP → HTTPS Redirect in Web Station)
- [ ] Regelmaessige Backups der MariaDB-Datenbank einrichten
- [ ] `.sql` und `.md` Dateien per Nginx blockieren (siehe Schritt 8)
- [ ] Firewall-Regeln in DSM: nur Port 443 (und 80 fuer Let's Encrypt) oeffnen
