<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/crm/events.php');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Event-ID fehlt.');
    redirect(APP_URL . '/crm/events.php');
}

$stmt = $pdo->prepare('SELECT title FROM crm_events WHERE id = ?');
$stmt->execute([$id]);
$title = $stmt->fetchColumn();

if (!$title) {
    flash('error', 'Event nicht gefunden.');
    redirect(APP_URL . '/crm/events.php');
}

// Löschen (Teilnehmer werden per ON DELETE CASCADE mitentfernt)
$pdo->prepare('DELETE FROM crm_events WHERE id = ?')->execute([$id]);

flash('success', 'Event «' . $title . '» wurde gelöscht.');
redirect(APP_URL . '/crm/events.php');
