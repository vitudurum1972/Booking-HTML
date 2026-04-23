<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/event_helpers.php';
require_access_crm();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/crm/events.php');
}
csrf_check();

$action   = $_POST['action']   ?? '';
$eventId  = (int)($_POST['event_id'] ?? 0);

if (!$eventId) {
    flash('error', 'Event-ID fehlt.');
    redirect(APP_URL . '/crm/events.php');
}

// Event laden (für Mail-Text)
$evStmt = $pdo->prepare('SELECT * FROM crm_events WHERE id = ?');
$evStmt->execute([$eventId]);
$event = $evStmt->fetch();
if (!$event) {
    flash('error', 'Event nicht gefunden.');
    redirect(APP_URL . '/crm/events.php');
}

$redirectBack = APP_URL . '/crm/event_view.php?id=' . $eventId;

// ─── Aktion: Teilnehmer hinzufügen (mehrere gleichzeitig) ─────────────
if ($action === 'add_bulk') {
    $contactIds = $_POST['contact_ids'] ?? [];
    if (!is_array($contactIds)) $contactIds = [];
    $contactIds = array_map('intval', $contactIds);
    $contactIds = array_filter($contactIds, fn($x) => $x > 0);

    if (!$contactIds) {
        flash('error', 'Keine Kontakte ausgewählt.');
        redirect($redirectBack);
    }

    $sendMail = !empty($_POST['send_invitation']);

    // Alle bereits vorhandenen Teilnehmer ausschliessen
    $existStmt = $pdo->prepare('SELECT contact_id FROM crm_event_participants WHERE event_id = ?');
    $existStmt->execute([$eventId]);
    $existing = array_map('intval', $existStmt->fetchAll(PDO::FETCH_COLUMN));
    $toAdd = array_diff($contactIds, $existing);

    if (!$toAdd) {
        flash('error', 'Alle ausgewählten Kontakte sind bereits eingeladen.');
        redirect($redirectBack);
    }

    $ins = $pdo->prepare("
        INSERT INTO crm_event_participants (event_id, contact_id, status, invitation_sent, rsvp_token)
        VALUES (?, ?, 'invited', 0, ?)
    ");

    $added      = 0;
    $mailsSent  = 0;
    $mailsFail  = 0;

    foreach ($toAdd as $cid) {
        try {
            $token = crm_generate_rsvp_token();
            $ins->execute([$eventId, $cid, $token]);
            $participantId = (int)$pdo->lastInsertId();
            $added++;

            if ($sendMail) {
                $cStmt = $pdo->prepare('SELECT * FROM crm_contacts WHERE id = ?');
                $cStmt->execute([$cid]);
                $contact = $cStmt->fetch();
                if ($contact && $contact['email']) {
                    if (crm_send_invitation($pdo, $event, $contact, $participantId)) {
                        $mailsSent++;
                    } else {
                        $mailsFail++;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[Event] Konnte Teilnehmer nicht hinzufügen: ' . $e->getMessage());
        }
    }

    $msg = $added . ' Teilnehmer hinzugefügt';
    if ($sendMail) {
        $msg .= ', ' . $mailsSent . ' Einladungs-Mail' . ($mailsSent !== 1 ? 's' : '') . ' versendet';
        if ($mailsFail > 0) $msg .= ' (' . $mailsFail . ' fehlgeschlagen)';
    }
    flash('success', $msg . '.');
    redirect($redirectBack);
}

// ─── Aktion: Status eines Teilnehmers ändern ──────────────────────────
if ($action === 'set_status') {
    $pid    = (int)($_POST['participant_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['invited', 'confirmed', 'declined'], true) || !$pid) {
        flash('error', 'Ungültiger Status.');
        redirect($redirectBack);
    }

    $respondedAt = in_array($status, ['confirmed','declined'], true) ? date('Y-m-d H:i:s') : null;
    $pdo->prepare('UPDATE crm_event_participants SET status = ?, responded_at = ? WHERE id = ? AND event_id = ?')
        ->execute([$status, $respondedAt, $pid, $eventId]);

    $labels = ['invited' => 'Eingeladen', 'confirmed' => 'Zugesagt', 'declined' => 'Abgesagt'];
    flash('success', 'Status aktualisiert: ' . $labels[$status] . '.');
    redirect($redirectBack);
}

// ─── Aktion: Teilnehmer entfernen ─────────────────────────────────────
if ($action === 'remove') {
    $pid = (int)($_POST['participant_id'] ?? 0);
    if ($pid) {
        $pdo->prepare('DELETE FROM crm_event_participants WHERE id = ? AND event_id = ?')
            ->execute([$pid, $eventId]);
        flash('success', 'Teilnehmer entfernt.');
    }
    redirect($redirectBack);
}

// Unbekannte Aktion
flash('error', 'Unbekannte Aktion.');
redirect($redirectBack);
