<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail.php';

if (is_logged_in()) redirect('portal.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    $errors = [];
    if (strlen($username) < 3) $errors[] = 'Benutzername zu kurz.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
    if (strlen($password) < 6) $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein.';
    if ($password !== $password2) $errors[] = 'Passwörter stimmen nicht überein.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Benutzername oder E-Mail ist bereits registriert.';
        }
    }

    if (!$errors) {
        // Token für E-Mail-Bestätigung
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $hash    = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, full_name, email_verified, email_token, email_token_expires)
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ');
        $stmt->execute([$username, $email, $hash, $fullName, $token, $expires]);

        // Bestätigungsmail senden
        $verifyUrl = APP_URL . '/verify.php?token=' . $token;
        $body = "
            <h2>E-Mail-Adresse bestätigen</h2>
            <p>Hallo {$username},</p>
            <p>vielen Dank für Ihre Registrierung beim Gemeindeportal Wangen-Brüttisellen.</p>
            <p>Bitte klicken Sie auf den folgenden Link, um Ihre E-Mail-Adresse zu bestätigen:</p>
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

        flash('success', 'Konto erstellt! Wir haben Ihnen eine E-Mail an <strong>' . e($email) . '</strong> gesendet. Bitte klicken Sie auf den Bestätigungslink, um Ihr Konto zu aktivieren.');
        redirect('login.php');
    } else {
        flash('error', implode(' ', $errors));
    }
}

$pageTitle = 'Registrieren - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Registrieren</h1>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Benutzername
            <input type="text" name="username" required>
        </label>
        <label>Vollständiger Name
            <input type="text" name="full_name">
        </label>
        <label>E-Mail
            <input type="email" name="email" required>
        </label>
        <label>Passwort
            <input type="password" name="password" required>
        </label>
        <label>Passwort wiederholen
            <input type="password" name="password2" required>
        </label>
        <button type="submit" class="btn-primary">Konto erstellen</button>
    </form>
    <p>Bereits registriert? <a href="login.php">Anmelden</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
