<?php
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    // --- Neuen Benutzer anlegen ---
    if ($op === 'create_user') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $is_admin  = isset($_POST['is_admin']) ? 1 : 0;

        if (!$username || !$email || !$password) {
            flash('error', 'Benutzername, E-Mail und Passwort sind Pflichtfelder.');
            redirect('users.php?action=create');
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            flash('error', 'Benutzername oder E-Mail existiert bereits.');
            redirect('users.php?action=create');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, full_name, is_admin, auth_provider)
            VALUES (?, ?, ?, ?, ?, "local")
        ');
        $ins->execute([$username, $email, $hash, $full_name, $is_admin]);
        flash('success', 'Benutzer «' . $username . '» wurde erstellt.');
        redirect('users.php');
    }

    // --- Bestehenden Benutzer bearbeiten ---
    $id = (int)($_POST['id'] ?? 0);

    if ($id == $_SESSION['user_id']) {
        flash('error', 'Du kannst dich nicht selbst ändern.');
        redirect('users.php');
    }

    if ($op === 'toggle_admin') {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Adminrechte aktualisiert.');
    } elseif ($op === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Benutzer gelöscht.');
    }
    redirect('users.php');
}

$users = $pdo->query('
    SELECT u.*, (SELECT COUNT(*) FROM reservations WHERE user_id = u.id) AS res_count
    FROM users u
    ORDER BY u.created_at DESC
')->fetchAll();

$pageTitle = 'Benutzer verwalten';
include __DIR__ . '/../includes/header.php';
?>
<h1>Benutzer verwalten</h1>

<?php if (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
<div class="card" style="margin-bottom:2rem;">
    <h2>Neuen Benutzer erstellen</h2>
    <form method="post" action="users.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="create_user">
        <div class="form-group">
            <label for="username">Benutzername *</label>
            <input type="text" id="username" name="username" required minlength="3" maxlength="50">
        </div>
        <div class="form-group">
            <label for="email">E-Mail *</label>
            <input type="email" id="email" name="email" required maxlength="150">
        </div>
        <div class="form-group">
            <label for="full_name">Vollständiger Name</label>
            <input type="text" id="full_name" name="full_name" maxlength="150">
        </div>
        <div class="form-group">
            <label for="password">Passwort *</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_admin" value="1"> Administratorrechte</label>
        </div>
        <button type="submit" class="btn-primary">Benutzer erstellen</button>
        <a href="users.php" class="btn-secondary" style="margin-left:0.5rem;">Abbrechen</a>
    </form>
</div>
<?php else: ?>
<p><a href="users.php?action=create" class="btn-primary">+ Neuer Benutzer</a></p>
<?php endif; ?>

<table>
    <thead><tr><th>Benutzer</th><th>E-Mail</th><th>Name</th><th>Admin</th><th>Res.</th><th>Erstellt</th><th>Aktion</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['full_name']) ?></td>
            <td><?= $u['is_admin'] ? '✓' : '' ?></td>
            <td><?= (int)$u['res_count'] ?></td>
            <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="op" value="toggle_admin">
                    <button type="submit" class="btn-small"><?= $u['is_admin'] ? 'Admin entziehen' : 'Zu Admin' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Benutzer wirklich löschen?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="op" value="delete">
                    <button type="submit" class="btn-danger btn-small">Löschen</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
