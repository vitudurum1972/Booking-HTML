<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail.php';

if (is_logged_in()) redirect('portal.php');

// --- Bestätigungsmail erneut senden ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'resend') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && !$user['email_verified']) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $upd = $pdo->prepare('UPDATE users SET email_token = ?, email_token_expires = ? WHERE id = ?');
        $upd->execute([$token, $expires, $user['id']]);

        $verifyUrl = APP_URL . '/verify.php?token=' . $token;
        $body = "
            <h2>E-Mail-Adresse bestätigen</h2>
            <p>Hallo {$user['username']},</p>
            <p>hier ist Ihr neuer Bestätigungslink für das Gemeindeportal Wangen-Brüttisellen:</p>
            <p style=\"margin: 24px 0;\">
                <a href=\"{$verifyUrl}\"
                   style=\"display:inline-block; padding:14px 28px; background:#00acb3; color:#ffffff;
                          text-decoration:none; font-weight:bold; font-family:Arial,sans-serif;\">
                    E-Mail bestätigen
                </a>
            </p>
            <p style=\"font-size:13px; color:#8a8a8a;\">
                Oder kopieren Sie diesen Link in Ihren Browser:<br>
                <a href=\"{$verifyUrl}\">{$verifyUrl}</a>
            </p>
            <p style=\"font-size:13px; color:#8a8a8a;\">Der Link ist 24 Stunden gültig.</p>
        ";
        send_mail($email, 'E-Mail bestätigen – Gemeindeportal Wangen-Brüttisellen', $body);
    }

    // Immer gleiche Meldung (keine Info ob E-Mail existiert)
    flash('success', 'Falls ein Konto mit dieser Adresse existiert, wurde eine neue Bestätigungsmail gesendet.');
    redirect('login.php');
}

// --- Normaler Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') !== 'resend') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {

        // E-Mail-Verifizierung prüfen
        if (isset($user['email_verified']) && !$user['email_verified']) {
            flash('error', 'verify_needed');
            $_SESSION['unverified_email'] = $user['email'];
            redirect('login.php');
        }

        $_SESSION['user_id']            = $user['id'];
        $_SESSION['username']           = $user['username'];
        $_SESSION['is_admin']           = $user['is_admin'];
        $_SESSION['access_reservation'] = $user['access_reservation'] ?? 1;
        $_SESSION['access_crm']         = $user['access_crm'] ?? 1;
        flash('success', 'Willkommen, ' . $user['username'] . '!');
        redirect('portal.php');
    } else {
        flash('error', 'Ungültige Anmeldedaten.');
    }
}

// Spezialfall: E-Mail nicht verifiziert — Flash VOR dem Header abfangen,
// damit header.php nicht "verify_needed" als normalen Fehler anzeigt.
$verifyNeeded    = false;
$unverifiedEmail = '';
$pendingError    = $_SESSION['flash']['error'] ?? null;
if ($pendingError === 'verify_needed') {
    unset($_SESSION['flash']['error']);
    $verifyNeeded    = true;
    $unverifiedEmail = $_SESSION['unverified_email'] ?? '';
    unset($_SESSION['unverified_email']);
}

$pageTitle = 'Anmelden - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Anmelden</h1>

    <?php if ($verifyNeeded): ?>
    <div class="alert error" style="border-color:#e67e22; color:#7a5c00; background:#fff8ee;">
        <strong>E-Mail noch nicht bestätigt.</strong><br>
        Bitte prüfen Sie Ihr Postfach (auch den Spam-Ordner) und klicken Sie auf den Bestätigungslink.
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="resend">
            <input type="hidden" name="email" value="<?= e($unverifiedEmail) ?>">
            <button type="submit" class="btn-small" style="border-color:#e67e22; color:#7a5c00;">
                Bestätigungsmail erneut senden
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (defined('MS_ENABLED') && MS_ENABLED): ?>
        <a href="<?= APP_URL ?>/auth/microsoft-login.php" class="btn-microsoft">
            <svg viewBox="0 0 23 23" width="20" height="20" aria-hidden="true">
                <rect x="1"  y="1"  width="10" height="10" fill="#f25022"/>
                <rect x="12" y="1"  width="10" height="10" fill="#7fba00"/>
                <rect x="1"  y="12" width="10" height="10" fill="#00a4ef"/>
                <rect x="12" y="12" width="10" height="10" fill="#ffb900"/>
            </svg>
            Mit Microsoft anmelden
        </a>
        <div class="divider"><span>oder mit Passwort</span></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Benutzername oder E-Mail
            <input type="text" name="username" required autofocus>
        </label>
        <label>Passwort
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn-primary">Anmelden</button>
    </form>
    <p>Noch kein Konto? <a href="register.php">Registrieren</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
