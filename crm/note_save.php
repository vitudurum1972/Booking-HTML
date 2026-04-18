<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/crm/index.php');
}
csrf_check();

$action    = $_POST['action']     ?? 'add';
$contactId = (int)($_POST['contact_id'] ?? 0);

if ($action === 'delete') {
    $noteId = (int)($_POST['note_id'] ?? 0);
    if ($noteId && $contactId) {
        $stmt = $pdo->prepare('DELETE FROM crm_notes WHERE id = ? AND contact_id = ?');
        $stmt->execute([$noteId, $contactId]);
        flash('success', 'Notiz gelöscht.');
    }
} else {
    // add
    $note = trim($_POST['note'] ?? '');
    if ($note !== '' && $contactId) {
        $stmt = $pdo->prepare('INSERT INTO crm_notes (contact_id, note, created_by) VALUES (?, ?, ?)');
        $stmt->execute([$contactId, $note, $_SESSION['user_id']]);
        flash('success', 'Notiz gespeichert.');
    }
}

redirect(APP_URL . '/crm/contact_view.php?id=' . $contactId);
