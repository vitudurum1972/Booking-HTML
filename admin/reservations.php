<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mail.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)$_POST['id'];
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['approved','rejected','cancelled','completed','pending'];
    if (in_array($newStatus, $allowed)) {
        $stmt = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $id]);
        notify_status_change($pdo, $id, $newStatus);
        flash('success', 'Status aktualisiert.');
    }
    redirect('reservations.php' . (isset($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
}

$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending','approved','all','past'];
if (!in_array($filter, $validFilters)) $filter = 'pending';

$sql = "
    SELECT r.*, i.name AS item_name, u.username, u.email
    FROM reservations r
    JOIN items i ON i.id = r.item_id
    JOIN users u ON u.id = r.user_id
";
$params = [];
if ($filter === 'pending')       { $sql .= " WHERE r.status = 'pending'"; }
elseif ($filter === 'approved')  { $sql .= " WHERE r.status = 'approved' AND r.end_date >= NOW()"; }
elseif ($filter === 'past')      { $sql .= " WHERE r.end_date < NOW()"; }
$sql .= ' ORDER BY r.start_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'Reservierungen verwalten';
include __DIR__ . '/../includes/header.php';
?>
<h1>Reservierungen verwalten</h1>

<div class="tabs">
    <a href="?filter=pending"  class="<?= $filter==='pending' ?'active':'' ?>">Offen</a>
    <a href="?filter=approved" class="<?= $filter==='approved'?'active':'' ?>">Aktiv</a>
    <a href="?filter=past"     class="<?= $filter==='past'    ?'active':'' ?>">Vergangen</a>
    <a href="?filter=all"      class="<?= $filter==='all'     ?'active':'' ?>">Alle</a>
</div>

<?php if (!$reservations): ?>
    <p>Keine Reservierungen in dieser Kategorie.</p>
<?php else: ?>
<table>
    <thead><tr><th>Benutzer</th><th>Gegenstand</th><th>Von</th><th>Bis</th><th>Verwendung</th><th>Status</th><th>Aktion</th></tr></thead>
    <tbody>
    <?php foreach ($reservations as $r): ?>
        <tr>
            <td><?= e($r['username']) ?><br><small><?= e($r['email']) ?></small></td>
            <td><?= e($r['item_name']) ?><?php if ($r['notes']): ?><br><small><?= e($r['notes']) ?></small><?php endif; ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['start_date'])) ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['end_date'])) ?></td>
            <td>
                <?php $utype = $r['usage_type'] ?? 'privat'; ?>
                <span class="badge usage-<?= e($utype) ?>">
                    <?= $utype === 'geschaeftlich' ? 'Geschäftlich' : 'Privat' ?>
                </span>
                <?php if ($utype === 'geschaeftlich' && !empty($r['occasion'])): ?>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
                        <strong>Anlass:</strong> <?= e($r['occasion']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><span class="badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <select name="status">
                        <option value="pending"   <?= $r['status']==='pending'  ?'selected':'' ?>>Offen</option>
                        <option value="approved"  <?= $r['status']==='approved' ?'selected':'' ?>>Bestätigt</option>
                        <option value="rejected"  <?= $r['status']==='rejected' ?'selected':'' ?>>Abgelehnt</option>
                        <option value="cancelled" <?= $r['status']==='cancelled'?'selected':'' ?>>Storniert</option>
                        <option value="completed" <?= $r['status']==='completed'?'selected':'' ?>>Abgeschlossen</option>
                    </select>
                    <button type="submit" class="btn-small btn-primary">✓</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
