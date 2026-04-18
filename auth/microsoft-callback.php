<?php
/**
 * Callback vom Microsoft OAuth Flow.
 * Tauscht den Authorization Code gegen ein Access Token, ruft Benutzerdaten ab
 * und meldet den Benutzer in der App an (oder legt ihn beim ersten Mal an).
 */

// --- Debug: Fehler sichtbar machen (nach erfolgreichem Setup entfernen) ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Unhandled Exceptions abfangen und lesbar ausgeben
set_exception_handler(function ($e) {
    http_response_code(500);
    echo '<h1>Microsoft-Callback Fehler</h1>';
    echo '<p><strong>' . htmlspecialchars(get_class($e)) . ':</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    exit;
});

if (!MS_ENABLED) {
    flash('error', 'Microsoft-Anmeldung ist nicht konfiguriert.');
    redirect(APP_URL . '/login.php');
}

// Fehler von Microsoft?
if (isset($_GET['error'])) {
    $msg = $_GET['error_description'] ?? $_GET['error'];
    flash('error', 'Microsoft-Anmeldung fehlgeschlagen: ' . $msg);
    redirect(APP_URL . '/login.php');
}

// State validieren (CSRF-Schutz)
$state = $_GET['state'] ?? '';
if (!$state || !isset($_SESSION['ms_oauth_state']) || !hash_equals($_SESSION['ms_oauth_state'], $state)) {
    flash('error', 'Ungültiger State-Parameter. Bitte erneut anmelden.');
    redirect(APP_URL . '/login.php');
}
unset($_SESSION['ms_oauth_state']);

$code = $_GET['code'] ?? '';
if (!$code) {
    flash('error', 'Kein Authorization Code erhalten.');
    redirect(APP_URL . '/login.php');
}

/* ------ Hilfsfunktion: HTTP-Request (cURL bevorzugt, Stream-Fallback) ------ */
function http_request($url, $method = 'GET', $data = null, $headers = []) {
    // 1) cURL verwenden, falls verfügbar
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['body' => $body, 'code' => $code, 'error' => $err];
    }

    // 2) Stream-Fallback (SSL-Verifizierung deaktiviert, weil auf Synology oft kein CA-Bundle)
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout'       => 20,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
        ],
    ];
    if ($data !== null) {
        $opts['http']['content'] = $data;
    }
    $ctx  = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    $err = '';
    if ($body === false) {
        $last = error_get_last();
        $err  = $last['message'] ?? 'unknown';
    }
    return ['body' => $body, 'code' => $code, 'error' => $err];
}

/* ------ 1. Code gegen Token tauschen ------ */
$tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode(MS_TENANT_ID) . '/oauth2/v2.0/token';
$postFields = [
    'client_id'     => MS_CLIENT_ID,
    'client_secret' => MS_CLIENT_SECRET,
    'code'          => $code,
    'redirect_uri'  => MS_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
    'scope'         => 'openid profile email User.Read',
];

$resp = http_request(
    $tokenUrl,
    'POST',
    http_build_query($postFields),
    ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
);
$response = $resp['body'];
$httpCode = $resp['code'];

if ($response === false || $httpCode !== 200) {
    flash('error', 'Token-Anfrage fehlgeschlagen (HTTP ' . $httpCode . '): ' . $resp['error'] . ' ' . $response);
    redirect(APP_URL . '/login.php');
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    flash('error', 'Kein Access Token erhalten: ' . $response);
    redirect(APP_URL . '/login.php');
}

$accessToken = $tokenData['access_token'];

/* ------ 2. Benutzerdaten via Microsoft Graph abrufen ------ */
$resp = http_request(
    'https://graph.microsoft.com/v1.0/me',
    'GET',
    null,
    ['Authorization: Bearer ' . $accessToken, 'Accept: application/json']
);
$meResponse = $resp['body'];
$meHttp     = $resp['code'];

if ($meResponse === false || $meHttp !== 200) {
    flash('error', 'Benutzerdaten konnten nicht abgerufen werden (HTTP ' . $meHttp . '): ' . $resp['error']);
    redirect(APP_URL . '/login.php');
}

$me = json_decode($meResponse, true);
$msId    = $me['id'] ?? '';
$email   = $me['mail'] ?? $me['userPrincipalName'] ?? '';
$name    = $me['displayName'] ?? '';
$givenN  = $me['givenName'] ?? '';

if (!$msId || !$email) {
    flash('error', 'Unvollständige Benutzerdaten von Microsoft erhalten.');
    redirect(APP_URL . '/login.php');
}

/* ------ 3. Benutzer in DB suchen oder anlegen ------ */
// Zuerst nach microsoft_id suchen
$stmt = $pdo->prepare('SELECT * FROM users WHERE microsoft_id = ?');
$stmt->execute([$msId]);
$user = $stmt->fetch();

// Falls nicht gefunden: nach E-Mail suchen (Account-Verknüpfung für bestehende Benutzer)
if (!$user) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Bestehenden Benutzer mit Microsoft-ID verknüpfen
        $upd = $pdo->prepare('UPDATE users SET microsoft_id = ?, auth_provider = "microsoft" WHERE id = ?');
        $upd->execute([$msId, $user['id']]);
    }
}

// Falls immer noch nicht gefunden: neuen Benutzer anlegen
if (!$user) {
    // Eindeutigen Username ableiten (vor dem @ in der E-Mail, bei Kollision mit Zahl)
    $baseUser = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', strstr($email, '@', true) ?: $email));
    if (strlen($baseUser) < 3) $baseUser = 'user' . substr($msId, 0, 6);
    $username = $baseUser;
    $i = 1;
    while (true) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if (!$check->fetch()) break;
        $username = $baseUser . $i++;
        if ($i > 999) { $username = $baseUser . substr($msId, 0, 6); break; }
    }

    $ins = $pdo->prepare('
        INSERT INTO users (username, email, full_name, auth_provider, microsoft_id, is_admin, email_verified)
        VALUES (?, ?, ?, "microsoft", ?, 0, 1)
    ');
    $ins->execute([$username, $email, $name, $msId]);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

/* ------ 4. Session anlegen ------ */
$_SESSION['user_id']            = $user['id'];
$_SESSION['username']           = $user['username'];
$_SESSION['is_admin']           = $user['is_admin'];
$_SESSION['access_reservation'] = $user['access_reservation'] ?? 1;
$_SESSION['access_crm']         = $user['access_crm'] ?? 1;

flash('success', 'Willkommen, ' . ($user['full_name'] ?: $user['username']) . '!');
redirect(APP_URL . '/portal.php');
