<?php
require_once __DIR__ . '/../config.php';

/**
 * Einfache Mailfunktion mit PHP mail()
 * Für Produktivbetrieb empfohlen: PHPMailer mit SMTP.
 */
function send_mail($to, $subject, $body) {
    // mail() ist auf Synology oft nicht konfiguriert – Fehler abfangen statt crashen
    if (!function_exists('mail')) {
        error_log('[Reservation] mail() nicht verfuegbar – E-Mail nicht gesendet an ' . $to);
        return false;
    }
    try {
        $headers = [
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
            'Reply-To: ' . MAIL_FROM,
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0',
            'X-Mailer: PHP/' . phpversion(),
        ];
        $html = '<html><body style="font-family:Arial,sans-serif;">' . $body . '</body></html>';
        $result = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
        if (!$result) {
            error_log('[Reservation] mail() hat false zurueckgegeben fuer: ' . $to);
        }
        return $result;
    } catch (\Throwable $e) {
        error_log('[Reservation] mail() Fehler: ' . $e->getMessage());
        return false;
    }
}

function notify_new_reservation($pdo, $reservationId) {
    try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.email AS user_email, u.username, i.name AS item_name
        FROM reservations r
        JOIN users u ON u.id = r.user_id
        JOIN items i ON i.id = r.item_id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $r = $stmt->fetch();
    if (!$r) return;

    $start = date('d.m.Y H:i', strtotime($r['start_date']));
    $end   = date('d.m.Y H:i', strtotime($r['end_date']));
    $usage = ($r['usage_type'] ?? 'privat') === 'geschaeftlich' ? 'Geschäftlich' : 'Privat';

    // Benutzer informieren
    $userBody = "<h2>Reservierung bestätigt</h2>
        <p>Hallo {$r['username']},</p>
        <p>Deine Reservierung wurde erfolgreich erfasst und automatisch bestätigt:</p>
        <ul>
          <li><strong>Gegenstand:</strong> {$r['item_name']}</li>
          <li><strong>Von:</strong> {$start}</li>
          <li><strong>Bis:</strong> {$end}</li>
          <li><strong>Verwendung:</strong> {$usage}</li>
          <li><strong>Status:</strong> Bestätigt</li>
        </ul>
        <p>Du kannst deine Reservierung jederzeit in der App einsehen oder stornieren.</p>";
    send_mail($r['user_email'], 'Reservierung bestätigt: ' . $r['item_name'], $userBody);

    // Admin informieren
    $adminBody = "<h2>Neue Reservierung</h2>
        <p>Benutzer <strong>{$r['username']}</strong> hat eine neue Reservierung erstellt (automatisch bestätigt):</p>
        <ul>
          <li><strong>Gegenstand:</strong> {$r['item_name']}</li>
          <li><strong>Von:</strong> {$start}</li>
          <li><strong>Bis:</strong> {$end}</li>
          <li><strong>Verwendung:</strong> {$usage}</li>
        </ul>";
    send_mail(ADMIN_EMAIL, 'Neue Reservierung: ' . $r['item_name'], $adminBody);
    } catch (\Throwable $e) {
        error_log('[Reservation] notify_new_reservation Fehler: ' . $e->getMessage());
    }
}

function notify_status_change($pdo, $reservationId, $newStatus) {
    try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.email AS user_email, u.username, i.name AS item_name
        FROM reservations r
        JOIN users u ON u.id = r.user_id
        JOIN items i ON i.id = r.item_id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $r = $stmt->fetch();
    if (!$r) return;

    $labels = [
        'approved'  => 'bestätigt',
        'rejected'  => 'abgelehnt',
        'cancelled' => 'storniert',
        'completed' => 'abgeschlossen',
    ];
    $label = $labels[$newStatus] ?? $newStatus;

    $body = "<h2>Reservierung {$label}</h2>
        <p>Hallo {$r['username']},</p>
        <p>Der Status deiner Reservierung für <strong>{$r['item_name']}</strong> wurde geändert: <strong>{$label}</strong>.</p>";
    send_mail($r['user_email'], 'Reservierung ' . $label . ': ' . $r['item_name'], $body);
    } catch (\Throwable $e) {
        error_log('[Reservation] notify_status_change Fehler: ' . $e->getMessage());
    }
}
