<?php
require_once __DIR__ . '/config.php';
require_admin();

// --- POST-Aktionen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    // Neuen Benutzer anlegen
    if ($op === 'create_user') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $is_admin           = isset($_POST['is_admin']) ? 1 : 0;
        $access_reservation = isset($_POST['access_reservation']) ? 1 : 0;
        $access_crm         = isset($_POST['access_crm']) ? 1 : 0;

        if (!$username || !$email || !$password) {
            flash('error', 'Benutzername, E-Mail und Passwort sind Pflichtfelder.');
            redirect('portal_users.php?action=create');
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            flash('error', 'Benutzername oder E-Mail existiert bereits.');
            redirect('portal_users.php?action=create');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, full_name, is_admin, access_reservation, access_crm, email_verified, auth_provider)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, "local")
        ');
        $ins->execute([$username, $email, $hash, $full_name, $is_admin, $access_reservation, $access_crm]);
        flash('success', 'Benutzer «' . $username . '» wurde erstellt.');
        redirect('portal_users.php');
    }

    // Bestehenden Benutzer bearbeiten
    $id = (int)($_POST['id'] ?? 0);

    if ($id == $_SESSION['user_id']) {
        flash('error', 'Du kannst dich nicht selbst ändern.');
        redirect('portal_users.php');
    }

    if ($op === 'toggle_admin') {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Adminrechte aktualisiert.');
    } elseif ($op === 'toggle_reservation') {
        $stmt = $pdo->prepare('UPDATE users SET access_reservation = 1 - access_reservation WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Berechtigung Reservationssystem aktualisiert.');
    } elseif ($op === 'toggle_crm') {
        $stmt = $pdo->prepare('UPDATE users SET access_crm = 1 - access_crm WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Berechtigung CRM aktualisiert.');
    } elseif ($op === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Benutzer gelöscht.');
    }
    redirect('portal_users.php');
}

// --- Benutzerliste laden ---
$users = $pdo->query('
    SELECT u.*, (SELECT COUNT(*) FROM reservations WHERE user_id = u.id) AS res_count
    FROM users u
    ORDER BY u.created_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung – Gemeindeportal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gothic+A1:wght@300;400;500;600;700;800&display=swap">
    <style>
        :root {
            --primary: #00acb3;
            --primary-dark: #008a8f;
            --primary-light: #e6f7f8;
            --accent: #7b2d8e;
            --accent-dark: #5e1f6d;
            --text: #595a59;
            --text-dark: #000000;
            --text-muted: #8a8a8a;
            --border: #e3e3e5;
            --border-strong: #c8c8cb;
            --bg: #ffffff;
            --bg-alt: #f5f5f7;
            --bg-dark: #191919;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Gothic A1", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { color: var(--primary-dark); }

        /* -- Header -- */
        .topbar {
            background: #ffffff;
            padding: 22px 0;
            border-bottom: 1px solid var(--border);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 30px; }
        .topbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .brand {
            color: var(--text-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand:hover { text-decoration: none; }
        .brand-wappen { width: 72px; height: auto; display: block; }
        .brand-text { display: flex; flex-direction: column; gap: 2px; }
        .brand-sub { font-size: 13px; font-weight: 400; color: var(--text); }
        .brand-main {
            font-size: 22px; font-weight: 700; color: var(--text-dark);
            border-top: 1px solid var(--text-dark); padding-top: 3px; line-height: 1.1;
        }
        .brand:hover .brand-main { color: var(--primary); }
        .brand-app { font-size: 11px; font-weight: 500; color: var(--accent); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 2px; }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .topbar-nav a {
            color: var(--text);
            font-weight: 500;
            font-size: 15px;
            padding: 4px 0;
            border-bottom: 2px solid transparent;
            transition: color .15s, border-color .15s;
        }
        .topbar-nav a:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
            text-decoration: none;
        }
        .topbar-nav .user-badge {
            font-size: 14px;
            color: var(--text-muted);
        }
        .topbar-nav .btn-logout {
            display: inline-block;
            padding: 8px 18px;
            border: 1px solid var(--border-strong);
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            transition: background .15s, border-color .15s;
        }
        .topbar-nav .btn-logout:hover {
            background: var(--bg-alt);
            border-color: var(--primary);
        }

        /* -- Page Header -- */
        .page-header-section {
            background: linear-gradient(135deg, #f5f5f7 0%, #f3eaf6 100%);
            border-bottom: 1px solid var(--border);
            padding: 50px 0 40px;
        }
        .page-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .page-header-section h1 {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0;
            line-height: 1.15;
        }
        .page-header-section .breadcrumb {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .page-header-section .breadcrumb a { color: var(--primary); }

        /* -- Alerts -- */
        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            font-size: 15px;
            border-left: 4px solid;
            background: var(--bg-alt);
        }
        .alert.success { border-color: #2e7d32; color: #1b5e20; background: #edf7ed; }
        .alert.error   { border-color: #c62828; color: #8a1a1a; background: #fdecec; }

        /* -- Content -- */
        .content-section {
            padding: 50px 0 80px;
        }

        /* -- Card / Form -- */
        .card {
            background: #ffffff;
            padding: 32px;
            border: 1px solid var(--border);
            margin-bottom: 26px;
        }
        .card h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0 0 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            margin-bottom: 6px;
        }
        .form-group input[type=text],
        .form-group input[type=email],
        .form-group input[type=password] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-strong);
            border-radius: 0;
            font-size: 15px;
            font-family: "Gothic A1", sans-serif;
            background: #ffffff;
            color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(123, 45, 142, 0.12);
        }
        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 400;
            cursor: pointer;
        }
        .form-group .checkbox-label input { width: auto; }

        /* -- Buttons -- */
        .btn-primary {
            display: inline-block;
            padding: 12px 28px;
            background: var(--accent);
            color: #ffffff;
            border: 2px solid var(--accent);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: "Gothic A1", sans-serif;
            letter-spacing: 0.3px;
            transition: background .18s, border-color .18s;
        }
        .btn-primary:hover {
            background: var(--accent-dark);
            border-color: var(--accent-dark);
            color: #ffffff;
            text-decoration: none;
        }
        .btn-secondary {
            display: inline-block;
            padding: 12px 28px;
            background: #ffffff;
            color: var(--accent);
            border: 2px solid var(--accent);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: "Gothic A1", sans-serif;
            letter-spacing: 0.3px;
            transition: background .18s, color .18s;
        }
        .btn-secondary:hover {
            background: var(--accent);
            color: #ffffff;
            text-decoration: none;
        }
        .btn-small {
            display: inline-block;
            padding: 7px 16px;
            border: 1px solid var(--border-strong);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: "Gothic A1", sans-serif;
            background: #ffffff;
            color: var(--text);
            transition: background .15s, border-color .15s;
        }
        .btn-small:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-danger {
            display: inline-block;
            padding: 7px 16px;
            border: 1px solid #c62828;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: "Gothic A1", sans-serif;
            background: #ffffff;
            color: #c62828;
            transition: background .15s, color .15s;
        }
        .btn-danger:hover {
            background: #c62828;
            color: #ffffff;
        }

        /* -- Table -- */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border: 1px solid var(--border);
        }
        table th, table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        table th {
            background: var(--bg-alt);
            color: var(--text-dark);
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            border-bottom: 2px solid var(--accent);
        }
        table tr:last-child td { border-bottom: none; }
        table tr:hover td { background: var(--bg-alt); }

        /* -- Footer -- */
        .footer {
            background: var(--bg-dark);
            color: #b0b0b0;
            padding: 40px 0;
            text-align: center;
            font-size: 13px;
        }
        .footer p { margin: 0 0 8px; }
        .footer p:last-child { margin: 0; }
        .footer a { color: var(--primary); }
        .footer a:hover { color: #ffffff; }

        /* -- Responsive -- */
        @media (max-width: 768px) {
            .page-header-section h1 { font-size: 26px; }
            .container { padding: 0 20px; }
            table th, table td { padding: 10px 12px; }
            .card { padding: 22px 20px; }
            .topbar .container { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="topbar">
    <div class="container">
        <a href="portal.php" class="brand">
            <img src="assets/wappen.svg" alt="Wappen Wangen-Brüttisellen" class="brand-wappen"
                 onerror="this.onerror=null; this.src='https://www.wangen-bruettisellen.ch/dist/wangen-bruettisellen/2022/images/logo.518ffa4d5ee04f4b1372.svg';">
            <span class="brand-text">
                <span class="brand-sub">Gemeinde</span>
                <span class="brand-main">Wangen-Brüttisellen</span>
                <span class="brand-app">Benutzerverwaltung</span>
            </span>
        </a>
        <nav class="topbar-nav">
            <a href="portal.php">Portal</a>
            <span class="user-badge">👤 <?= e($_SESSION['username']) ?></span>
            <a href="logout.php" class="btn-logout">Abmelden</a>
        </nav>
    </div>
</header>

<!-- Page Header -->
<section class="page-header-section">
    <div class="container">
        <div class="breadcrumb"><a href="portal.php">Portal</a> / Benutzerverwaltung</div>
        <div class="page-header-inner">
            <h1>Benutzerverwaltung</h1>
            <?php if (!isset($_GET['action']) || $_GET['action'] !== 'create'): ?>
                <a href="portal_users.php?action=create" class="btn-primary">+ Neuer Benutzer</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Content -->
<section class="content-section">
    <div class="container">

        <?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
        <div class="card">
            <h2>Neuen Benutzer erstellen</h2>
            <form method="post" action="portal_users.php">
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
                    <label class="checkbox-label"><input type="checkbox" name="is_admin" value="1"> Administratorrechte</label>
                </div>
                <div class="form-group">
                    <label style="margin-bottom:10px;">Berechtigungen</label>
                    <label class="checkbox-label" style="margin-bottom:8px;"><input type="checkbox" name="access_reservation" value="1" checked> Reservationssystem</label>
                    <label class="checkbox-label"><input type="checkbox" name="access_crm" value="1" checked> CRM-System</label>
                </div>
                <button type="submit" class="btn-primary">Benutzer erstellen</button>
                <a href="portal_users.php" class="btn-secondary" style="margin-left:0.5rem;">Abbrechen</a>
            </form>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Benutzer</th>
                    <th>Name</th>
                    <th>Admin</th>
                    <th>Reservation</th>
                    <th>CRM</th>
                    <th>Erstellt</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
                <tr>
                    <td>
                        <div style="font-weight:600; color:var(--text-dark);"><?= e($u['username']) ?></div>
                        <div style="font-size:12px; color:var(--text-muted);"><?= e($u['email']) ?></div>
                    </td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= $u['is_admin'] ? '<span style="color:#2e7d32; font-weight:700;">✓</span>' : '' ?></td>
                    <td>
                        <?php if ($isSelf): ?>
                            <?= $u['access_reservation'] ? '<span style="color:#2e7d32; font-weight:700;">✓</span>' : '<span style="color:#c62828;">✗</span>' ?>
                        <?php else: ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="op" value="toggle_reservation">
                            <button type="submit" class="btn-small" style="min-width:40px; <?= $u['access_reservation'] ? 'color:#2e7d32; border-color:#2e7d32;' : 'color:#c62828; border-color:#c62828;' ?>"><?= $u['access_reservation'] ? '✓' : '✗' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isSelf): ?>
                            <?= $u['access_crm'] ? '<span style="color:#2e7d32; font-weight:700;">✓</span>' : '<span style="color:#c62828;">✗</span>' ?>
                        <?php else: ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="op" value="toggle_crm">
                            <button type="submit" class="btn-small" style="min-width:40px; <?= $u['access_crm'] ? 'color:#2e7d32; border-color:#2e7d32;' : 'color:#c62828; border-color:#c62828;' ?>"><?= $u['access_crm'] ? '✓' : '✗' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if (!$isSelf): ?>
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
                            <button type="submit" class="btn-danger">Löschen</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Gemeinde Wangen-Brüttisellen</p>
        <p><a href="https://www.wangen-bruettisellen.ch" target="_blank">www.wangen-bruettisellen.ch</a></p>
    </div>
</footer>

</body>
</html>
