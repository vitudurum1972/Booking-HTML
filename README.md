# Werkzeug-Reservationssystem

Ein Multiuser-Webapp zur Reservation von Werkzeugen und Geräten. Entwickelt in PHP + MySQL.

## Features

- Benutzer-Registrierung und Login (mit CSRF-Schutz und gehashten Passwörtern)
- Gegenstände durchsuchen und filtern nach Kategorie
- Reservierungen erstellen mit Zeitraum-Konfliktprüfung
- Kalenderansicht aller Reservierungen
- Meine Reservierungen verwalten (stornieren)
- Admin-Bereich: Gegenstände, Reservierungen, Benutzer verwalten
- E-Mail-Benachrichtigungen bei neuen und geänderten Reservierungen
- Responsive Design

## Voraussetzungen

- PHP 7.4+ (empfohlen 8.x)
- MySQL 5.7+ oder MariaDB 10+
- Webserver (Apache/Nginx) mit PHP
- PHP `mail()` funktionsfähig ODER manuelle Integration von PHPMailer/SMTP

## Installation

1. **Dateien auf den Server kopieren**
   Alle Dateien in ein Verzeichnis deines Webservers kopieren (z.B. `htdocs/reservation`).

2. **Datenbank einrichten**
   In MySQL/phpMyAdmin die Datei `install.sql` ausführen. Sie erstellt die Datenbank `reservation_system` mit allen Tabellen und Beispieldaten.

3. **Konfiguration anpassen**
   In `config.php` die Datenbank-Zugangsdaten und `APP_URL` auf deine Umgebung anpassen:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'reservation_system');
   define('DB_USER', 'dein_user');
   define('DB_PASS', 'dein_passwort');
   define('APP_URL', 'http://deinserver.local/reservation');
   define('MAIL_FROM', 'noreply@deinedomain.ch');
   define('ADMIN_EMAIL', 'admin@deinedomain.ch');
   ```

4. **Aufrufen**
   Im Browser `APP_URL` öffnen. Mit dem Standard-Admin einloggen:
   - **Benutzer:** `admin`
   - **Passwort:** `admin123`

   **WICHTIG:** Passwort sofort ändern (derzeit über DB; Profil-Seite kann bei Bedarf ergänzt werden).

## Verzeichnisstruktur

```
Booking-HTML/
├── config.php              # Datenbank- und App-Konfiguration
├── install.sql             # DB-Schema mit Beispieldaten
├── index.php               # Dashboard
├── login.php               # Login
├── register.php            # Registrierung
├── logout.php              # Abmelden
├── items.php               # Gegenstände durchsuchen
├── reserve.php             # Reservation erfassen
├── my_reservations.php     # Eigene Reservierungen
├── calendar.php            # Kalenderansicht
├── admin/
│   ├── index.php           # Admin-Dashboard
│   ├── items.php           # Gegenstände verwalten
│   ├── reservations.php    # Reservierungen verwalten
│   └── users.php           # Benutzer verwalten
├── includes/
│   ├── header.php          # Gemeinsames Layout-Header
│   ├── footer.php          # Gemeinsames Layout-Footer
│   └── mail.php            # E-Mail-Funktionen
├── assets/
│   └── style.css           # CSS-Stylesheet
└── README.md
```

## Sicherheit

- Passwörter sind mit `password_hash()` (bcrypt) gespeichert
- CSRF-Tokens für alle POST-Formulare
- Prepared Statements (PDO) gegen SQL-Injection
- `htmlspecialchars()` für alle Ausgaben
- Session-basiertes Auth

Für Produktivbetrieb zusätzlich empfohlen:
- HTTPS / TLS
- PHPMailer mit SMTP statt PHP `mail()`
- Regelmäßige Backups
- Rate-Limiting beim Login

## Microsoft-Anmeldung (Entra ID / Azure AD)

Die App unterstützt optional Single-Sign-On mit Microsoft-Konten aus eurem Organisations-Tenant.

### Schritt 1: App im Microsoft Entra Admin Center registrieren

1. Auf https://entra.microsoft.com einloggen (Admin-Rechte nötig)
2. **Identität → Anwendungen → App-Registrierungen → Neue Registrierung**
3. Eingaben:
   - **Name:** Werkzeug-Reservation (oder beliebig)
   - **Kontotypen:** *Nur Konten in diesem Organisationsverzeichnis*
   - **Umleitungs-URI:** Typ **Web**, URL: `https://DEINE-DOMAIN/reservation/auth/microsoft-callback.php`
4. Nach dem Registrieren findest du die **Application (client) ID** und **Directory (tenant) ID** auf der Übersichtsseite — notieren.
5. Links auf **Zertifikate & Geheimnisse → Neuer geheimer Clientschlüssel** klicken. Den **Wert (Value)** sofort kopieren — er wird nur einmal angezeigt.
6. Links auf **API-Berechtigungen** → standardmäßig ist `User.Read` bereits drin. Das reicht.

### Schritt 2: Werte in `config.php` eintragen

```php
define('MS_ENABLED', true);
define('MS_TENANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
define('MS_CLIENT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
define('MS_CLIENT_SECRET', 'DER_KOPIERTE_WERT');
```

Die `MS_REDIRECT_URI` wird automatisch aus `APP_URL` gebildet — muss aber **exakt** mit der im Portal eingetragenen URI übereinstimmen.

### Schritt 3: HTTPS sicherstellen

Microsoft erlaubt nur HTTPS-Redirect-URIs. Auf der Synology:
Systemsteuerung → Sicherheit → Zertifikat → Zertifikat hinzufügen → Let's Encrypt
Dann im Web Station Portal das Zertifikat deinem Webservice zuweisen.

### Schritt 4: DB aktualisieren (bei bestehender Installation)

```sql
ALTER TABLE users
  ADD COLUMN auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local' AFTER is_admin,
  ADD COLUMN microsoft_id VARCHAR(100) UNIQUE AFTER auth_provider,
  MODIFY password_hash VARCHAR(255) NULL;
```

### Verhalten

- Auf der Login-Seite erscheint ein Button **"Mit Microsoft anmelden"**
- Bei erstmaliger Anmeldung wird automatisch ein Benutzerkonto angelegt (ohne Adminrechte)
- Bestehende lokale Benutzer mit gleicher E-Mail-Adresse werden automatisch mit ihrem Microsoft-Konto verknüpft
- Der klassische Login (Benutzername/Passwort) bleibt weiterhin verfügbar — der Admin kann sich immer lokal anmelden, falls Microsoft mal nicht erreichbar ist

## Hinweis zu E-Mails

Die `mail()`-Funktion von PHP funktioniert nur wenn der Server richtig konfiguriert ist. Im lokalen XAMPP/WAMP werden Mails meist nicht versendet. Für echten Versand PHPMailer mit SMTP-Konfiguration einbinden in `includes/mail.php`.
