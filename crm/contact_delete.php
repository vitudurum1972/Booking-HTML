<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/crm/index.php');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM crm_contacts WHERE id = ?');
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if ($c) {
        $pdo->prepare('DELETE FROM crm_contacts WHERE id = ?')->execute([$id]);
        flash('success', 'Kontakt «' . trim($c['first_name'] . ' ' . $c['last_name']) . '» gelöscht.');
    }
}

redirect(APP_URL . '/crm/index.php');
