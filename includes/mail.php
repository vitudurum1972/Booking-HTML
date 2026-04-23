<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/SmtpMailer.php';

/**
 * Mail versenden via SMTP (Microsoft 365).
 * Signatur ist kompatibel mit allen bestehenden Aufrufen im Projekt
 * und akzeptiert optional einen iCalendar-Termin (ICS-Text).
 *
 * @param  string      $to          Empfänger-Adresse
 * @param  string      $subject     Betreff
 * @param  string      $body        HTML-Inhalt (wird in <html><body> verpackt)
 * @param  string|null $icsContent  Optionaler iCalendar-Inhalt (VCALENDAR-Text)
 * @param  string      $icsMethod   REQUEST | CANCEL | PUBLISH (Standard: REQUEST)
 * @return bool
 */
function send_mail($to, $subject, $body, ?string $icsContent = null, string $icsMethod = 'REQUEST') {
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

        $result = $mailer->send($to, $subject, $body, $icsContent, $icsMethod);

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
 * iCalendar-Text (RFC 5545) für eine Reservierung erzeugen.
 * Kompatibel mit Outlook, Google Calendar, Apple Mail etc.
 *
 * @param  array  $r        Reservierungsdaten mit id, start_date, end_date, item_name,
 *                           usage_type, occasion, user_email, item_location (optional)
 * @param  string $method   REQUEST (neu/aktualisieren) oder CANCEL (stornieren)
 * @param  int    $sequence Versionsnummer (0 = Original, 1+ = Updates/Cancels)
 * @return string           VCALENDAR-Text mit CRLF-Zeilenenden
 */
function build_reservation_ics(array $r, string $method = 'REQUEST', int $sequence = 0): string {
    $method = strtoupper($method);
    if (!in_array($method, ['REQUEST', 'CANCEL', 'PUBLISH'], true)) {
        $method = 'REQUEST';
    }

    // Zeiten in UTC (Outlook/Exchange erwartet dies für Terminladungen ohne VTIMEZONE)
    $startTs = strtotime($r['start_date']);
    $endTs   = strtotime($r['end_date']);
    $dtStart = gmdate('Ymd\THis\Z', $startTs);
    $dtEnd   = gmdate('Ymd\THis\Z', $endTs);
    $dtStamp = gmdate('Ymd\THis\Z');

    // Eindeutige UID – stabil über Updates/Cancels hinweg, sonst erkennt Outlook
    // die Aktualisierung nicht und zeigt zwei separate Termine an.
    $domain = parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST) ?: 'wangen-bruettisellen.ch';
    $uid = 'reservation-' . (int)$r['id'] . '@' . $domain;

    $itemName  = $r['item_name'] ?? 'Reservierung';
    $summary   = 'Reservierung: ' . $itemName;
    $location  = $r['item_location'] ?? '';

    // Beschreibung zusammensetzen
    $descParts = [];
    if ($method === 'CANCEL') {
        $descParts[] = 'Ihre Reservierung wurde storniert.';
    } else {
        $descParts[] = 'Ihre Reservierung wurde bestätigt.';
    }
    $descParts[] = 'Gegenstand: ' . $itemName;
    if (!empty($r['usage_type'])) {
        $descParts[] = 'Verwendung: ' . ($r['usage_type'] === 'geschaeftlich' ? 'Geschäftlich' : 'Privat');
    }
    if (($r['usage_type'] ?? '') === 'geschaeftlich' && !empty($r['occasion'])) {
        $descParts[] = 'Anlass: ' . $r['occasion'];
    }
    if (!empty($r['notes'])) {
        $descParts[] = 'Bemerkungen: ' . $r['notes'];
    }
    $description = implode("\n", $descParts);

    $organizerEmail = defined('MAIL_FROM')      ? MAIL_FROM      : 'no-reply@wangen-bruettisellen.ch';
    $organizerName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Gemeindeportal Wangen-Brüttisellen';
    $attendeeEmail  = $r['user_email'] ?? '';
    $attendeeName   = $r['username']   ?? $attendeeEmail;

    $status = ($method === 'CANCEL') ? 'CANCELLED' : 'CONFIRMED';

    $lines = [];
    $lines[] = 'BEGIN:VCALENDAR';
    $lines[] = 'VERSION:2.0';
    $lines[] = 'PRODID:-//Gemeinde Wangen-Brüttisellen//Reservationssystem//DE';
    $lines[] = 'METHOD:' . $method;
    $lines[] = 'CALSCALE:GREGORIAN';
    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $uid;
    $lines[] = 'SEQUENCE:' . $sequence;
    $lines[] = 'DTSTAMP:' . $dtStamp;
    $lines[] = 'DTSTART:' . $dtStart;
    $lines[] = 'DTEND:'   . $dtEnd;
    $lines[] = 'SUMMARY:' . ics_escape($summary);
    if ($location !== '') {
        $lines[] = 'LOCATION:' . ics_escape($location);
    }
    $lines[] = 'DESCRIPTION:' . ics_escape($description);
    $lines[] = 'STATUS:' . $status;
    $lines[] = 'TRANSP:OPAQUE';
    $lines[] = 'ORGANIZER;CN=' . ics_escape($organizerName) . ':mailto:' . $organizerEmail;
    if ($attendeeEmail !== '') {
        $lines[] = 'ATTENDEE;CN=' . ics_escape($attendeeName)
                 . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:' . $attendeeEmail;
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    // RFC 5545 verlangt Line-Folding bei >75 Oktetten – jede Zeile falten
    $folded = array_map('ics_fold_line', $lines);
    return implode("\r\n", $folded) . "\r\n";
}

/**
 * iCalendar-Text escapen: Backslash, Komma, Semikolon, Newline.
 */
function ics_escape(string $s): string {
    return str_replace(
        ['\\',   "\r\n", "\n", "\r", ',',  ';'],
        ['\\\\', '\\n',  '\\n','\\n', '\\,', '\\;'],
        $s
    );
}

/**
 * Line-Folding nach RFC 5545 §3.1: Zeilen dürfen max. 75 Oktetten lang sein,
 * Fortsetzungen beginnen mit einem Leerzeichen.
 */
function ics_fold_line(string $line): string {
    if (strlen($line) <= 75) {
        return $line;
    }
    $parts = [];
    $offset = 0;
    $len = strlen($line);
    // Erste Zeile: 75 Zeichen, weitere: 74 (+ führendes Leerzeichen = 75)
    $parts[] = substr($line, 0, 75);
    $offset = 75;
    while ($offset < $len) {
        $parts[] = ' ' . substr($line, $offset, 74);
        $offset += 74;
    }
    return implode("\r\n", $parts);
}

/**
 * Benachrichtigung bei neuer Reservierung (Benutzer + Admin).
 * Dem Benutzer wird zusätzlich ein Outlook-Termin (ICS) mitgeschickt.
 */
function notify_new_reservation($pdo, $reservationId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.email AS user_email, u.username,
                   i.name AS item_name, i.location AS item_location
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

        // ICS-Termin für den Benutzer
        $ics = build_reservation_ics($r, 'REQUEST', 0);

        // Benutzer informieren (mit Termin-Einladung)
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
            <p>Der Termin ist als Outlook-/Kalender-Einladung an diese Mail angehängt –
               ein Klick auf <em>„Annehmen“</em> übernimmt ihn direkt in Ihren Kalender.</p>
            <p>Sie können Ihre Reservierung jederzeit im Portal einsehen oder stornieren.</p>";
        send_mail($r['user_email'], 'Reservierung bestätigt: ' . $r['item_name'], $userBody, $ics, 'REQUEST');

        // Admin informieren (ohne ICS, reine Info)
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
 * Bei "cancelled" oder "rejected" wird eine CANCEL-ICS mitgeschickt,
 * damit Outlook den Termin automatisch aus dem Kalender entfernt.
 */
function notify_status_change($pdo, $reservationId, $newStatus) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.email AS user_email, u.username,
                   i.name AS item_name, i.location AS item_location
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

        // Bei Stornierung/Ablehnung: CANCEL-ICS mitschicken, damit Outlook
        // den ursprünglichen Termin aus dem Kalender entfernt.
        $ics = null;
        $icsMethod = 'REQUEST';
        if (in_array($newStatus, ['cancelled', 'rejected'], true)) {
            $ics = build_reservation_ics($r, 'CANCEL', 1);
            $icsMethod = 'CANCEL';
            $body .= "<p>Der Termin wird automatisch aus Ihrem Outlook-Kalender entfernt.</p>";
        } elseif ($newStatus === 'approved') {
            // Erneute Bestätigung → REQUEST mit höherer Sequence
            $ics = build_reservation_ics($r, 'REQUEST', 1);
            $icsMethod = 'REQUEST';
        }

        send_mail($r['user_email'], 'Reservierung ' . $label . ': ' . $r['item_name'], $body, $ics, $icsMethod);
    } catch (\Throwable $e) {
        error_log('[Mail] notify_status_change Fehler: ' . $e->getMessage());
    }
}
