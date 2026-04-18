<?php
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    flash('error', 'Kein Bestätigungstoken angegeben.');
    redirect('login.php');
}

// Token in DB suchen
$stmt = $pdo->prepare('SELECT id, username, email_verified, email_token_expires FROM users WHERE email_token = ?');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'Ungültiger oder bereits verwendeter Bestätigungslink.');
    redirect('login.php');
}

if ($user['email_verified']) {
    flash('success', 'Ihre E-Mail-Adresse wurde bereits bestätigt. Sie können sich anmelden.');
    redirect('login.php');
}

// Ablauf prüfen
if ($user['email_token_expires'] && strtotime($user['email_token_expires']) < time()) {
    flash('error', 'Der Bestätigungslink ist abgelaufen. Bitte melden Sie sich an, um einen neuen Link zu erhalten.');
    redirect('login.php');
}

// Benutzer aktivieren
$upd = $pdo->prepare('UPDATE users SET email_verified = 1, email_token = NULL, email_token_expires = NULL WHERE id = ?');
$upd->execute([$user['id']]);

flash('success', 'E-Mail-Adresse bestätigt! Sie können sich jetzt anmelden.');
redirect('login.php');
