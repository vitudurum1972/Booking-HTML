<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/SmtpMailer.php';

/**
 * Mail versenden via SMTP (Microsoft 365).
 * Signatur ist kompatibel mit allen bestehenden Aufrufen im Projekt.
 *
 * @param  string $to       Empfänger-Adresse
 * @param  string $subject  Betreff
 * @param  string $body     HTML-Inhalt (wird in <html><body> verpackt)
 * @return bool
 */
function send_mail($to, $subject, $body) {
    // Prüfen ob SMTP konfiguriert ist
    if (!defined('SMTP_PASS') || SMTP_PASS === '') {
        error_log('[Mail] SMTP-Passwort nicht konfiguriert (SMTP_PASS in config.php). Mail nicht gesendet an: ' . $to);
        return false;
    }

    try {
        $mailer = new SmtpMailer(
            SMTP_HOST,
            SMTP_PORT,
            SMTP_USER,
            SMTP_PASS,
            MAIL_FROM,
            MAIL_FROM_NAME,
            15,
            defined('SMTP_DEBUG') && SMTP_DEBUG
        );

        $result = $mailer->send($to, $subject, $body);

        if (!$result) {
            error_log('[Mail] Versand fehlgeschlagen an ' . $to . ': ' . $mailer->getLastError());
        }

        // Debug-Log ausgeben wenn aktiviert
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            foreach ($mailer->getLog() as $line) {
                error_log('[Mail Debug] ' . $line);
            }
        }

        return $result;
    } catch (\Throwable $e) {
        error_log('[Mail] Ausnahme beim Versand an ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Benachrichtigung bei neuer Reservierung (Benutzer + Admin).
 */
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

        // Anlass nur bei geschäftlicher Verwendung anzeigen
        $occasionLine = '';
        if (($r['usage_type'] ?? 'privat') === 'geschaeftlich' && !empty($r['occasion'])) {
            $occasionLine = '<li><strong>Anlass:</strong> ' . htmlspecialchars($r['occasion'], ENT_QUOTES, 'UTF-8') . '</li>';
        }

        // Benutzer informieren
        $userBody = "<h2>Reservierung bestätigt</h2>
            <p>Hallo {$r['username']},</p>
            <p>Ihre Reservierung wurde erfolgreich erfasst und automatisch bestätigt:</p>
            <ul>
              <li><strong>Gegenstand:</strong> {$r['item_name']}</li>
              <li><strong>Von:</strong> {$start}</li>
              <li><strong>Bis:</strong> {$end}</li>
              <li><strong>Verwendung:</strong> {$usage}</li>
              {$occasionLine}
              <li><strong>Status:</strong> Bestätigt</li>
            </ul>
            <p>Sie können Ihre Reservierung jederzeit im Portal einsehen oder stornieren.</p>";
        send_mail($r['user_email'], 'Reservierung bestätigt: ' . $r['item_name'], $userBody);

        // Admin informieren
        $adminBody = "<h2>Neue Reservierung</h2>
            <p>Benutzer <strong>{$r['username']}</strong> hat eine neue Reservierung erstellt (automatisch bestätigt):</p>
            <ul>
              <li><strong>Gegenstand:</strong> {$r['item_name']}</li>
              <li><strong>Von:</strong> {$start}</li>
              <li><strong>Bis:</strong> {$end}</li>
              <li><strong>Verwendung:</strong> {$usage}</li>
              {$occasionLine}
            </ul>";
        send_mail(ADMIN_EMAIL, 'Neue Reservierung: ' . $r['item_name'], $adminBody);
    } catch (\Throwable $e) {
        error_log('[Mail] notify_new_reservation Fehler: ' . $e->getMessage());
    }
}

/**
 * Benachrichtigung bei Statusänderung einer Reservierung.
 */
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
            <p>Der Status Ihrer Reservierung für <strong>{$r['item_name']}</strong> wurde geändert: <strong>{$label}</strong>.</p>";
        send_mail($r['user_email'], 'Reservierung ' . $label . ': ' . $r['item_name'], $body);
    } catch (\Throwable $e) {
        error_log('[Mail] notify_status_change Fehler: ' . $e->getMessage());
    }
}
