<?php
require_once __DIR__ . '/config.php';
require_login();

// Benutzerdaten laden (inkl. auth_provider und password_hash)
$stmt = $pdo->prepare('SELECT id, username, email, password_hash, auth_provider FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // Konto existiert nicht mehr -> abmelden
    session_destroy();
    redirect('login.php');
}

// Microsoft-Benutzer ohne lokales Passwort können hier kein Passwort ändern
$isMicrosoftOnly = ($user['auth_provider'] === 'microsoft') && empty($user['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isMicrosoftOnly) {
    csrf_check();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $newPassword2    = $_POST['new_password2']    ?? '';

    $errors = [];

    // 1) Aktuelles Passwort prüfen
    if (empty($user['password_hash']) || !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
    }

    // 2) Neues Passwort validieren
    if (strlen($newPassword) < 6) {
        $errors[] = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
    }
    if ($newPassword !== $newPassword2) {
        $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
    }
    if ($newPassword !== '' && $newPassword === $currentPassword) {
        $errors[] = 'Das neue Passwort muss sich vom aktuellen Passwort unterscheiden.';
    }

    if (!$errors) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, $user['id']]);

        flash('success', 'Ihr Passwort wurde erfolgreich geändert.');
        redirect('portal.php');
    } else {
        flash('error', implode(' ', $errors));
    }
}

$pageTitle = 'Passwort ändern - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Passwort ändern</h1>

    <?php if ($isMicrosoftOnly): ?>
        <div class="alert error" style="border-color:#3a4bb0; color:#2d3a8c; background:#eef0fb;">
            <strong>Nicht verfügbar.</strong><br>
            Ihr Konto meldet sich über Microsoft an. Bitte ändern Sie Ihr Passwort direkt
            in Ihrem Microsoft-Konto.
        </div>
        <p><a href="portal.php" class="btn-small">Zurück zum Portal</a></p>
    <?php else: ?>
        <p style="color:#8a8a8a; font-size:14px;">
            Angemeldet als <strong><?= e($user['username']) ?></strong>
            (<?= e($user['email']) ?>)
        </p>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label>Aktuelles Passwort
                <input type="password" name="current_password" required autofocus autocomplete="current-password">
            </label>

            <label>Neues Passwort
                <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
            </label>

            <label>Neues Passwort wiederholen
                <input type="password" name="new_password2" required minlength="6" autocomplete="new-password">
            </label>

            <button type="submit" class="btn-primary">Passwort ändern</button>
            <a href="portal.php" class="btn-small" style="margin-left:10px;">Abbrechen</a>
        </form>

        <p style="margin-top:20px; font-size:13px; color:#8a8a8a;">
            Hinweis: Das neue Passwort muss mindestens 6 Zeichen lang sein.
        </p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
