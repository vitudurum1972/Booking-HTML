<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail.php';
require_access_reservation();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_check();
    $resId = (int)$_POST['reservation_id'];
    $stmt = $pdo->prepare('
        UPDATE reservations
        SET status = "cancelled"
        WHERE id = ? AND user_id = ? AND status IN ("pending","approved")
    ');
    $stmt->execute([$resId, $_SESSION['user_id']]);
    if ($stmt->rowCount()) {
        notify_status_change($pdo, $resId, 'cancelled');
        flash('success', 'Reservierung storniert.');
    }
    redirect('my_reservations.php');
}

$stmt = $pdo->prepare('
    SELECT r.*, i.name AS item_name
    FROM reservations r
    JOIN items i ON i.id = r.item_id
    WHERE r.user_id = ?
    ORDER BY r.start_date DESC
');
$stmt->execute([$_SESSION['user_id']]);
$reservations = $stmt->fetchAll();

$pageTitle = 'Meine Reservierungen - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<h1>Meine Reservierungen</h1>

<?php if (!$reservations): ?>
    <p>Du hast noch keine Reservierungen. <a href="items.php">Jetzt reservieren →</a></p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Gegenstand</th>
            <th>Von</th>
            <th>Bis</th>
            <th>Verwendung</th>
            <th>Status</th>
            <th>Aktion</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($reservations as $r): ?>
        <tr>
            <td><?= e($r['item_name']) ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['start_date'])) ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['end_date'])) ?></td>
            <td>
                <?php $utype = $r['usage_type'] ?? 'privat'; ?>
                <span class="badge usage-<?= e($utype) ?>">
                    <?= $utype === 'geschaeftlich' ? 'Geschäftlich' : 'Privat' ?>
                </span>
            </td>
            <td><span class="badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td>
                <?php if (in_array($r['status'], ['pending','approved'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Reservierung wirklich stornieren?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Stornieren</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
