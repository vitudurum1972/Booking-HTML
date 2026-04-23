<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail.php';
require_access_reservation();

$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? AND available = 1');
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) {
    flash('error', 'Gegenstand nicht gefunden.');
    redirect('items.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $usageType = $_POST['usage_type'] ?? 'privat';
    if (!in_array($usageType, ['privat', 'geschaeftlich'], true)) {
        $usageType = 'privat';
    }
    // Anlass nur bei "geschaeftlich" berücksichtigen – bei privat leer lassen
    $occasion = ($usageType === 'geschaeftlich') ? trim($_POST['occasion'] ?? '') : '';

    $errors = [];
    $startTs = strtotime($start);
    $endTs   = strtotime($end);

    if (!$startTs || !$endTs) $errors[] = 'Ungültige Datumsangabe.';
    elseif ($startTs >= $endTs) $errors[] = 'Enddatum muss nach Startdatum liegen.';
    elseif ($startTs < time() - 60) $errors[] = 'Startdatum darf nicht in der Vergangenheit liegen.';

    // Pflichtfeld Anlass bei geschäftlicher Nutzung
    if ($usageType === 'geschaeftlich' && $occasion === '') {
        $errors[] = 'Bei geschäftlicher Verwendung muss ein Anlass angegeben werden.';
    }
    if (mb_strlen($occasion) > 255) {
        $errors[] = 'Anlass darf maximal 255 Zeichen haben.';
    }

    if (!$errors) {
        // Konflikt prüfen - wie viele sind im Zeitraum bereits reserviert?
        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE item_id = ?
              AND status IN ('pending','approved')
              AND NOT (end_date <= ? OR start_date >= ?)
        ");
        $conflictStmt->execute([$itemId, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
        $overlap = (int)$conflictStmt->fetchColumn();

        if ($overlap >= (int)$item['quantity']) {
            $errors[] = 'Alle verfügbaren Exemplare sind in diesem Zeitraum bereits reserviert.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('
            INSERT INTO reservations (user_id, item_id, start_date, end_date, usage_type, occasion, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, "approved")
        ');
        $stmt->execute([
            $_SESSION['user_id'],
            $itemId,
            date('Y-m-d H:i:s', $startTs),
            date('Y-m-d H:i:s', $endTs),
            $usageType,
            $occasion,
            $notes,
        ]);
        $resId = $pdo->lastInsertId();
        notify_new_reservation($pdo, $resId);
        flash('success', 'Reservierung bestätigt. Du erhältst eine Bestätigung per E-Mail.');
        redirect('my_reservations.php');
    } else {
        flash('error', implode(' ', $errors));
    }
}

// Bestehende Reservierungen für Anzeige
$stmt = $pdo->prepare("
    SELECT start_date, end_date FROM reservations
    WHERE item_id = ? AND status IN ('pending','approved') AND end_date >= NOW()
    ORDER BY start_date
");
$stmt->execute([$itemId]);
$existing = $stmt->fetchAll();

$pageTitle = 'Reservieren - ' . e($item['name']);
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Reservieren: <?= e($item['name']) ?></h1>
    <p><?= e($item['description']) ?></p>
    <p class="meta">📍 <?= e($item['location']) ?> &middot; Anzahl verfügbar: <?= (int)$item['quantity'] ?></p>

    <form method="post" id="reserveForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
        <label>Von
            <input type="datetime-local" name="start_date" required value="<?= e($_POST['start_date'] ?? date('Y-m-d\TH:i')) ?>">
        </label>
        <label>Bis
            <input type="datetime-local" name="end_date" required value="<?= e($_POST['end_date'] ?? date('Y-m-d\TH:i', strtotime('+1 day'))) ?>">
        </label>
        <label>Verwendung
            <?php $ut = $_POST['usage_type'] ?? 'privat'; ?>
            <div class="radio-group">
                <label class="radio">
                    <input type="radio" name="usage_type" value="privat"
                           id="usage_privat"
                           <?= $ut === 'privat' ? 'checked' : '' ?>
                           onchange="toggleOccasion()">
                    <span>Privat</span>
                </label>
                <label class="radio">
                    <input type="radio" name="usage_type" value="geschaeftlich"
                           id="usage_geschaeftlich"
                           <?= $ut === 'geschaeftlich' ? 'checked' : '' ?>
                           onchange="toggleOccasion()">
                    <span>Geschäftlich</span>
                </label>
            </div>
        </label>

        <!-- Anlass: erscheint nur bei geschäftlicher Nutzung -->
        <label id="occasionWrapper" style="<?= $ut === 'geschaeftlich' ? '' : 'display:none;' ?>">
            Anlass <span style="color:#c62828;">*</span>
            <input type="text" name="occasion" id="occasionField"
                   maxlength="255"
                   placeholder="z.B. Vereinsversammlung, Gemeindeausflug, Schulanlass …"
                   value="<?= e($_POST['occasion'] ?? '') ?>"
                   <?= $ut === 'geschaeftlich' ? 'required' : '' ?>>
            <span style="font-size:12px; color:var(--text-muted); font-weight:400;">
                Bei geschäftlicher Verwendung ist der Anlass obligatorisch.
            </span>
        </label>

        <label>Notizen (optional)
            <textarea name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
        </label>
        <button type="submit" class="btn-primary">Reservieren</button>
        <a href="items.php" class="btn-secondary">Abbrechen</a>
    </form>
</div>

<script>
function toggleOccasion() {
    var wrapper = document.getElementById('occasionWrapper');
    var field   = document.getElementById('occasionField');
    var isBusiness = document.getElementById('usage_geschaeftlich').checked;
    if (isBusiness) {
        wrapper.style.display = '';
        field.setAttribute('required', 'required');
    } else {
        wrapper.style.display = 'none';
        field.removeAttribute('required');
        // Wert leeren, damit bei Wechsel auf "privat" kein alter Anlass mitgeschickt wird
        field.value = '';
    }
}
// Initial beim Laden ausführen (falls Browser den Radio-Status wiederhergestellt hat)
document.addEventListener('DOMContentLoaded', toggleOccasion);
</script>

<?php if ($existing): ?>
<div class="card">
    <h3>Bereits belegte Zeiträume</h3>
    <ul>
    <?php foreach ($existing as $e): ?>
        <li><?= date('d.m.Y H:i', strtotime($e['start_date'])) ?> - <?= date('d.m.Y H:i', strtotime($e['end_date'])) ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
