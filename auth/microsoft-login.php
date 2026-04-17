<?php
/**
 * Startet den Microsoft OAuth 2.0 Authorization Code Flow.
 * Leitet den Benutzer zur Microsoft-Anmeldeseite weiter.
 */
require_once __DIR__ . '/../config.php';

if (!MS_ENABLED) {
    flash('error', 'Microsoft-Anmeldung ist nicht konfiguriert.');
    redirect(APP_URL . '/login.php');
}

// Random State zur CSRF-Absicherung erzeugen und in Session speichern
$state = bin2hex(random_bytes(16));
$_SESSION['ms_oauth_state'] = $state;

// Authorization URL zusammenbauen
$authUrl = 'https://login.microsoftonline.com/' . rawurlencode(MS_TENANT_ID) . '/oauth2/v2.0/authorize';
$params = [
    'client_id'     => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri'  => MS_REDIRECT_URI,
    'response_mode' => 'query',
    'scope'         => 'openid profile email User.Read',
    'state'         => $state,
    'prompt'        => 'select_account',
];

header('Location: ' . $authUrl . '?' . http_build_query($params));
exit;
