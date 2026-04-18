<?php
// Datenbank-Konfiguration
// Synology MariaDB 10: Unix-Socket verwenden (schneller als TCP)
// Falls TCP noetig: '127.0.0.1' verwenden (nicht 'localhost'!)
define('DB_HOST', '/run/mysqld/mysqld10.sock');
define('DB_NAME', 'reservation_system');
define('DB_USER', 'reservation');
define('DB_PASS', 'Explorer$72');  // <-- Hier das DB-Passwort eintragen

// E-Mail Konfiguration
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Reservationssystem');
define('ADMIN_EMAIL', 'admin@example.com');

// App-Einstellungen
define('APP_NAME', 'Reservation-System');
define('APP_URL', 'https://crm.wangen-bruettisellen.ch');  // <-- Deine Domain anpassen

// Microsoft Entra ID (Azure AD) OAuth-Konfiguration
// Werte aus dem Microsoft Entra Admin Center (entra.microsoft.com) -> App-Registrierungen
define('MS_ENABLED', true);                // Auf true setzen, sobald die Werte unten gepflegt sind
define('MS_TENANT_ID', 'fd1361ba-1258-404d-a6f4-921d7f8d9330');  // z.B. xxxx-xxxx-xxxx-xxxx oder 'gemeinde.onmicrosoft.com'
define('MS_CLIENT_ID', '818b2ffc-2dee-4c2f-80ad-1f8f01cec219');  // Application (client) ID
define('MS_CLIENT_SECRET', 'l5.8Q~P5OSR9HQc-ekBbsBCcYyiBcNMwl6VSCawA');  // Client Secret (Value, nicht die ID!)
define('MS_REDIRECT_URI', APP_URL . '/auth/microsoft-callback.php');
// Wird ein neuer Benutzer per Microsoft angelegt, erhält er standardmäßig KEINE Adminrechte.
// Um einem Benutzer Adminrechte zu geben: in der DB is_admin=1 setzen oder Admin-Bereich nutzen.

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PDO Verbindung
try {
    // Unix-Socket (Pfad beginnt mit /) oder TCP-Verbindung automatisch erkennen
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

// Hilfsfunktionen
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Zugriff verweigert.');
    }
}

function has_access_reservation() {
    return isset($_SESSION['access_reservation']) && $_SESSION['access_reservation'] == 1;
}

function has_access_crm() {
    return isset($_SESSION['access_crm']) && $_SESSION['access_crm'] == 1;
}

function require_access_reservation() {
    require_login();
    if (!has_access_reservation() && !is_admin()) {
        http_response_code(403);
        die('Kein Zugriff auf das Reservationssystem.');
    }
}

function require_access_crm() {
    require_login();
    if (!has_access_crm() && !is_admin()) {
        http_response_code(403);
        die('Kein Zugriff auf das CRM-System.');
    }
}

function flash($key, $msg = null) {
    if ($msg === null) {
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    $_SESSION['flash'][$key] = $msg;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        die('Ungültiges CSRF Token');
    }
}
