<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) redirect('portal.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
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

$pageTitle = 'Anmelden - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>Anmelden</h1>

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
