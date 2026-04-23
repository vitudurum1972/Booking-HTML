<?php
/**
 * Zentrale Helper-Funktionen für das Event-Modul.
 * Werden von event_participants.php, event_invite.php und rsvp.php verwendet.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/mail.php';

/**
 * Erzeugt einen kryptographisch sicheren, URL-freundlichen Token.
 */
function crm_generate_rsvp_token(): string {
    return bin2hex(random_bytes(24)); // 48 Zeichen hex
}

/**
 * Stellt sicher, dass ein Teilnehmer einen rsvp_token hat.
 * Liefert den (ggf. neu generierten) Token zurück.
 */
function crm_ensure_rsvp_token(PDO $pdo, int $participantId): string {
    $stmt = $pdo->prepare('SELECT rsvp_token FROM crm_event_participants WHERE id = ?');
    $stmt->execute([$participantId]);
    $token = $stmt->fetchColumn();

    if ($token) {
        return (string)$token;
    }

    // neuen Token generieren (bei Kollision erneut versuchen)
    for ($i = 0; $i < 5; $i++) {
        $newToken = crm_generate_rsvp_token();
        try {
            $pdo->prepare('UPDATE crm_event_participants SET rsvp_token = ? WHERE id = ?')
                ->execute([$newToken, $participantId]);
            return $newToken;
        } catch (\Throwable $e) {
            // bei Unique-Kollision (extrem unwahrscheinlich) neu versuchen
            continue;
        }
    }
    throw new RuntimeException('RSVP-Token konnte nicht erzeugt werden.');
}

/**
 * Gibt die öffentliche RSVP-URL für einen Token zurück.
 */
function crm_rsvp_url(string $token): string {
    return APP_URL . '/crm/rsvp.php?t=' . urlencode($token);
}

/**
 * Formatierte Datumsangabe für Mails.
 * @return array [dateStr, timeStr, endStr]
 */
function crm_format_event_datetime(array $event): array {
    $dateStr = date('d.m.Y', strtotime($event['event_date']));
    $timeStr = date('H:i',   strtotime($event['event_date']));
    $endStr  = !empty($event['event_end'])
        ? ' – ' . date('H:i', strtotime($event['event_end'])) . ' Uhr'
        : ' Uhr';
    return [$dateStr, $timeStr, $endStr];
}

/**
 * Baut den HTML-Body für eine Einladungs-E-Mail zusammen.
 */
function crm_build_invitation_mail(array $event, array $contact, string $rsvpUrl): array {
    $name     = trim($contact['first_name'] . ' ' . $contact['last_name']);
    $greeting = $name !== '' ? 'Guten Tag ' . $name : 'Guten Tag';

    [$dateStr, $timeStr, $endStr] = crm_format_event_datetime($event);

    $descHtml = !empty($event['description'])
        ? '<p>' . nl2br(htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8')) . '</p>'
        : '';
    $locHtml = !empty($event['location'])
        ? '<li><strong>Ort:</strong> ' . htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') . '</li>'
        : '';

    $rsvpUrlEsc = htmlspecialchars($rsvpUrl, ENT_QUOTES, 'UTF-8');

    $subject = 'Einladung: ' . $event['title'];
    $body = '
        <h2>' . htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') . '</h2>
        <p>' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . ',</p>
        <p>wir freuen uns, Sie zu folgendem Event einladen zu dürfen:</p>
        <ul>
            <li><strong>Datum:</strong> ' . $dateStr . '</li>
            <li><strong>Zeit:</strong> ' . $timeStr . $endStr . '</li>
            ' . $locHtml . '
        </ul>
        ' . $descHtml . '
        <div style="margin:28px 0; padding:20px; background:#f4f6ff; border-left:4px solid #3a4bb0;">
            <p style="margin:0 0 14px; font-weight:600;">Bitte bestätigen Sie Ihre Teilnahme:</p>
            <p style="margin:0 0 16px;">
                <a href="' . $rsvpUrlEsc . '"
                   style="display:inline-block; padding:12px 24px; background:#3a4bb0; color:#ffffff; text-decoration:none; font-weight:600; margin-right:8px;">
                   An- oder Abmelden
                </a>
            </p>
            <p style="margin:0; font-size:12px; color:#666;">
                Oder diesen Link direkt im Browser öffnen:<br>
                <a href="' . $rsvpUrlEsc . '" style="color:#3a4bb0; word-break:break-all;">' . $rsvpUrlEsc . '</a>
            </p>
        </div>
        <p style="font-size:13px; color:#666;">
            Sie können Ihre Antwort über den obigen Link jederzeit ändern.
        </p>
        <p>Freundliche Grüsse<br>Gemeinde Wangen-Brüttisellen</p>
    ';

    return [$subject, $body];
}

/**
 * Versendet eine Einladungs-E-Mail an einen Teilnehmer (inkl. Token-Link).
 * Setzt bei Erfolg invitation_sent = 1.
 */
function crm_send_invitation(PDO $pdo, array $event, array $contact, int $participantId): bool {
    if (empty($contact['email'])) return false;

    $token   = crm_ensure_rsvp_token($pdo, $participantId);
    $rsvpUrl = crm_rsvp_url($token);

    [$subject, $body] = crm_build_invitation_mail($event, $contact, $rsvpUrl);

    if (send_mail($contact['email'], $subject, $body)) {
        $pdo->prepare('UPDATE crm_event_participants SET invitation_sent = 1 WHERE id = ?')
            ->execute([$participantId]);
        return true;
    }
    return false;
}
