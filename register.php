<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) redirect('index.php');

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
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $fullName]);
        flash('success', 'Konto erstellt. Bitte anmelden.');
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
