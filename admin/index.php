<?php
require_once __DIR__ . '/../config.php';
require_admin();

$counts = [
    'users'    => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'items'    => $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
    'pending'  => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn(),
    'active'   => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'approved' AND end_date >= NOW()")->fetchColumn(),
];

$pageTitle = 'Admin Dashboard - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>
<h1>Admin Dashboard</h1>

<div class="stats">
    <div class="stat-card"><div class="stat-num"><?= $counts['users'] ?></div><div class="stat-label">Benutzer</div></div>
    <div class="stat-card"><div class="stat-num"><?= $counts['items'] ?></div><div class="stat-label">Gegenstände</div></div>
    <div class="stat-card"><div class="stat-num"><?= $counts['pending'] ?></div><div class="stat-label">Offene Anfragen</div></div>
    <div class="stat-card"><div class="stat-num"><?= $counts['active'] ?></div><div class="stat-label">Aktive Reservierungen</div></div>
</div>

<div class="grid-2">
    <a href="items.php" class="card link-card"><h3>🔧 Gegenstände verwalten</h3><p>Werkzeuge hinzufügen, bearbeiten, entfernen.</p></a>
    <a href="reservations.php" class="card link-card"><h3>📋 Reservierungen verwalten</h3><p>Anfragen prüfen und bestätigen.</p></a>
    <a href="../portal_users.php" class="card link-card"><h3>👥 Benutzer verwalten</h3><p>Benutzerkonten einsehen (im Portal).</p></a>
    <a href="../index.php" class="card link-card"><h3>⬅ Zurück zur App</h3><p>Zur Benutzeransicht wechseln.</p></a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
