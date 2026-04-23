<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

$id     = (int)($_GET['id'] ?? 0);
$event  = null;
$isEdit = false;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM crm_events WHERE id = ?');
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    if (!$event) {
        flash('error', 'Event nicht gefunden.');
        redirect(APP_URL . '/crm/events.php');
    }
    $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location']    ?? '');
    $eventDate   = trim($_POST['event_date']  ?? '');
    $eventEnd    = trim($_POST['event_end']   ?? '');
    $maxPart     = trim($_POST['max_participants'] ?? '');
    $maxPart     = ($maxPart === '' ? null : (int)$maxPart);

    if ($title === '') {
        flash('error', 'Titel ist erforderlich.');
        redirect(APP_URL . '/crm/event_form.php' . ($isEdit ? '?id=' . $id : ''));
    }
    if ($eventDate === '') {
        flash('error', 'Datum / Uhrzeit ist erforderlich.');
        redirect(APP_URL . '/crm/event_form.php' . ($isEdit ? '?id=' . $id : ''));
    }

    // Normalisiere datetime-local Format (YYYY-MM-DDTHH:MM) zu MySQL DATETIME
    $eventDate = str_replace('T', ' ', $eventDate);
    if (strlen($eventDate) === 16) $eventDate .= ':00';
    if ($eventEnd !== '') {
        $eventEnd = str_replace('T', ' ', $eventEnd);
        if (strlen($eventEnd) === 16) $eventEnd .= ':00';
    } else {
        $eventEnd = null;
    }

    if ($isEdit) {
        $stmt = $pdo->prepare("
            UPDATE crm_events SET
                title=?, description=?, location=?, event_date=?, event_end=?, max_participants=?
            WHERE id=?
        ");
        $stmt->execute([$title, $description, $location, $eventDate, $eventEnd, $maxPart, $id]);
        flash('success', 'Event «' . $title . '» aktualisiert.');
        redirect(APP_URL . '/crm/event_view.php?id=' . $id);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO crm_events (title, description, location, event_date, event_end, max_participants, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $location, $eventDate, $eventEnd, $maxPart, $_SESSION['user_id'] ?? null]);
        $newId = $pdo->lastInsertId();
        flash('success', 'Event «' . $title . '» erstellt. Wählen Sie jetzt die Teilnehmer aus.');
        redirect(APP_URL . '/crm/event_view.php?id=' . $newId);
    }
}

// Für datetime-local das richtige Format bereitstellen
$eventDateVal = '';
$eventEndVal  = '';
if ($event) {
    $eventDateVal = date('Y-m-d\TH:i', strtotime($event['event_date']));
    if ($event['event_end']) {
        $eventEndVal = date('Y-m-d\TH:i', strtotime($event['event_end']));
    }
}

$pageTitle = ($isEdit ? 'Event bearbeiten' : 'Neues Event') . ' – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:13px; color:var(--text-muted); margin-bottom:28px;">
    <a href="events.php">Events</a>
    <?php if ($isEdit): ?>
        &rsaquo; <a href="event_view.php?id=<?= $id ?>"><?= e($event['title']) ?></a> &rsaquo; Bearbeiten
    <?php else: ?>
        &rsaquo; Neues Event
    <?php endif; ?>
</div>

<div class="page-header">
    <h1><?= $isEdit ? 'Event bearbeiten' : 'Neues Event' ?></h1>
</div>

<div class="card" style="max-width:760px;">
    <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Titel <span style="color:#c62828;">*</span>
            <input type="text" name="title" required autofocus
                   value="<?= e($event['title'] ?? '') ?>"
                   placeholder="z.B. Vereinsapéro 2026">
        </label>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <label>Beginn (Datum & Uhrzeit) <span style="color:#c62828;">*</span>
                <input type="datetime-local" name="event_date" required
                       value="<?= e($eventDateVal) ?>">
            </label>
            <label>Ende (optional)
                <input type="datetime-local" name="event_end"
                       value="<?= e($eventEndVal) ?>">
            </label>
        </div>

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">
            <label>Ort
                <input type="text" name="location"
                       value="<?= e($event['location'] ?? '') ?>"
                       placeholder="z.B. Gemeindesaal, Wangen">
            </label>
            <label>Max. Teilnehmer
                <input type="number" name="max_participants" min="1"
                       value="<?= e($event['max_participants'] ?? '') ?>"
                       placeholder="optional">
            </label>
        </div>

        <label>Beschreibung / Einladungstext
            <textarea name="description" rows="6"
                      placeholder="Beschreibung des Events – dieser Text wird in die Einladungs-Mail übernommen."><?= e($event['description'] ?? '') ?></textarea>
            <span style="font-size:12px; color:var(--text-muted); font-weight:400;">
                Dieser Text erscheint in der Einladungs-E-Mail an die Kontakte.
            </span>
        </label>

        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:8px;">
            <button type="submit" class="btn-primary">
                <?= $isEdit ? '💾 Änderungen speichern' : '✅ Event erstellen' ?>
            </button>
            <?php if ($isEdit): ?>
                <a href="event_view.php?id=<?= $id ?>" class="btn-secondary">Abbrechen</a>
            <?php else: ?>
                <a href="events.php" class="btn-secondary">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
