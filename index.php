<?php
require_once __DIR__ . '/config.php';
require_access_reservation();

// Dashboard-Daten
$stmt = $pdo->query('SELECT COUNT(*) FROM items WHERE available = 1');
$itemCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status IN ("pending","approved")');
$stmt->execute([$_SESSION['user_id']]);
$activeResv = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT r.*, i.name AS item_name
    FROM reservations r
    JOIN items i ON i.id = r.item_id
    WHERE r.user_id = ?
      AND r.end_date >= NOW()
      AND r.status IN ('pending','approved')
    ORDER BY r.start_date ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming = $stmt->fetchAll();

$pageTitle = 'Dashboard - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<h1>Willkommen, <?= e($_SESSION['username']) ?>!</h1>

<div class="stats">
    <div class="stat-card">
        <div class="stat-num"><?= $itemCount ?></div>
        <div class="stat-label">Verfügbare Gegenstände</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= $activeResv ?></div>
        <div class="stat-label">Deine aktiven Reservierungen</div>
    </div>
</div>

<div class="card">
    <h2>Deine nächsten Reservierungen</h2>
    <?php if (!$upcoming): ?>
        <p>Keine anstehenden Reservierungen. <a href="items.php">Gegenstand reservieren →</a></p>
    <?php else: ?>
        <table>
            <thead><tr><th>Gegenstand</th><th>Von</th><th>Bis</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $r): ?>
                <tr>
                    <td><?= e($r['item_name']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($r['start_date'])) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($r['end_date'])) ?></td>
                    <td><span class="badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="grid-2">
    <a href="items.php" class="card link-card">
        <h3>🔧 Gegenstände durchsuchen</h3>
        <p>Finde Werkzeuge und Geräte die du reservieren möchtest.</p>
    </a>
    <a href="calendar.php" class="card link-card">
        <h3>📅 Kalender ansehen</h3>
        <p>Siehe alle Reservierungen in der Kalenderübersicht.</p>
    </a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
