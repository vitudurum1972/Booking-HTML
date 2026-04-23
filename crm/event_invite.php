<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/event_helpers.php';
require_access_crm();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/crm/events.php');
}
csrf_check();

$eventId = (int)($_POST['event_id'] ?? 0);
if (!$eventId) {
    flash('error', 'Event-ID fehlt.');
    redirect(APP_URL . '/crm/events.php');
}

// Event laden
$evStmt = $pdo->prepare('SELECT * FROM crm_events WHERE id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();
if (!$event) {
    flash('error', 'Event nicht gefunden.');
    redirect(APP_URL . '/crm/events.php');
}

$redirectBack = APP_URL . '/crm/event_view.php?id=' . $eventId;

// Alle Teilnehmer mit Status "invited" (=offen) laden
$stmt = $pdo->prepare("
    SELECT p.id AS participant_id, c.*
    FROM crm_event_participants p
    JOIN crm_contacts c ON c.id = p.contact_id
    WHERE p.event_id = ? AND p.status = 'invited'
");
$stmt->execute([$eventId]);
$participants = $stmt->fetchAll();

if (!$participants) {
    flash('error', 'Keine ausstehenden Einladungen zu versenden.');
    redirect($redirectBack);
}

$sent   = 0;
$fail   = 0;
$noMail = 0;

foreach ($participants as $p) {
    if (empty($p['email'])) {
        $noMail++;
        continue;
    }
    if (crm_send_invitation($pdo, $event, $p, (int)$p['participant_id'])) {
        $sent++;
    } else {
        $fail++;
    }
}

$msgParts = [];
if ($sent)   $msgParts[] = $sent . ' Einladung' . ($sent !== 1 ? 'en' : '') . ' versendet';
if ($fail)   $msgParts[] = $fail . ' fehlgeschlagen';
if ($noMail) $msgParts[] = $noMail . ' ohne E-Mail-Adresse (übersprungen)';

if ($sent > 0) {
    flash('success', implode(', ', $msgParts) . '.');
} else {
    flash('error', 'Keine Mail versendet. ' . implode(', ', $msgParts) . '.');
}
redirect($redirectBack);
