<?php
/**
 * Öffentliche RSVP-Seite (KEIN Login nötig).
 * Eingeladene Kontakte bestätigen oder stornieren hier ihre Teilnahme
 * über einen persönlichen Token-Link aus der Einladungs-Mail.
 */
require_once __DIR__ . '/../config.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');

// Sicherheit: Token-Format prüfen (48 Hex-Zeichen)
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    $error = 'Der Einladungs-Link ist ungültig oder unvollständig.';
    $participant = null;
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, c.first_name, c.last_name, c.email, c.organization,
               e.title AS event_title, e.description AS event_description,
               e.location AS event_location, e.event_date, e.event_end, e.max_participants,
               e.id AS event_id
        FROM crm_event_participants p
        JOIN crm_contacts c ON c.id = p.contact_id
        JOIN crm_events   e ON e.id = p.event_id
        WHERE p.rsvp_token = ?
    ");
    $stmt->execute([$token]);
    $participant = $stmt->fetch();

    if (!$participant) {
        http_response_code(404);
        $error = 'Der Einladungs-Link ist ungültig oder wurde zurückgezogen.';
    } else {
        $error = null;
    }
}

// POST: Status setzen
$successMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $participant && !$error) {
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['confirmed', 'declined'], true)) {
        $pdo->prepare("
            UPDATE crm_event_participants
            SET status = ?, responded_at = NOW()
            WHERE rsvp_token = ?
        ")->execute([$newStatus, $token]);

        // Teilnehmer neu laden (für aktuelle Anzeige)
        $stmt = $pdo->prepare("
            SELECT p.*, c.first_name, c.last_name, c.email, c.organization,
                   e.title AS event_title, e.description AS event_description,
                   e.location AS event_location, e.event_date, e.event_end, e.max_participants,
                   e.id AS event_id
            FROM crm_event_participants p
            JOIN crm_contacts c ON c.id = p.contact_id
            JOIN crm_events   e ON e.id = p.event_id
            WHERE p.rsvp_token = ?
        ");
        $stmt->execute([$token]);
        $participant = $stmt->fetch();

        $successMsg = $newStatus === 'confirmed'
            ? 'Vielen Dank für Ihre Zusage! Wir freuen uns auf Sie.'
            : 'Schade – Ihre Absage wurde registriert. Vielen Dank für die Rückmeldung.';
    }
}

// Zeitangaben
$isPast = false;
if ($participant) {
    $eventTs = strtotime($participant['event_date']);
    $isPast  = $eventTs < time() - 86400; // 1 Tag Toleranz
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einladung<?= $participant ? ' – ' . e($participant['event_title']) : '' ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
    <style>
        body { background: #f3f4f8; }
        .rsvp-wrap {
            max-width: 640px;
            margin: 40px auto;
            padding: 0 16px;
        }
        .rsvp-brand {
            text-align: center;
            margin-bottom: 24px;
        }
        .rsvp-brand img {
            width: 72px;
            height: auto;
        }
        .rsvp-brand .title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2233;
            margin-top: 10px;
        }
        .rsvp-card {
            background: #ffffff;
            border: 1px solid #e0e3ea;
            padding: 36px 40px;
        }
        .rsvp-card h1 {
            margin: 0 0 10px;
            font-size: 26px;
            color: #1a2233;
        }
        .rsvp-subtitle {
            color: #6a7080;
            font-size: 15px;
            margin-bottom: 28px;
        }
        .rsvp-meta {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 10px 18px;
            padding: 18px 0;
            border-top: 1px solid #e6e8ef;
            border-bottom: 1px solid #e6e8ef;
            margin-bottom: 22px;
            font-size: 15px;
        }
        .rsvp-meta dt {
            font-weight: 700;
            color: #6a7080;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.6px;
            padding-top: 3px;
        }
        .rsvp-meta dd {
            margin: 0;
            color: #1a2233;
        }
        .rsvp-description {
            font-size: 15px;
            line-height: 1.55;
            color: #3a3f4b;
            margin: 22px 0 28px;
            white-space: pre-wrap;
        }
        .rsvp-status {
            margin-bottom: 22px;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 600;
            border-left: 4px solid #3a4bb0;
            background: #f4f6ff;
        }
        .rsvp-status.confirmed { border-left-color: #2e7d32; background: #e6f4ea; color: #1b5e20; }
        .rsvp-status.declined  { border-left-color: #c62828; background: #fdecea; color: #a31515; }
        .rsvp-success {
            padding: 16px 20px;
            background: #e6f4ea;
            color: #1b5e20;
            border-left: 4px solid #2e7d32;
            font-weight: 600;
            margin-bottom: 22px;
        }
        .rsvp-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .rsvp-btn {
            flex: 1;
            min-width: 160px;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-align: center;
            letter-spacing: 0.3px;
            transition: transform .1s, box-shadow .12s;
        }
        .rsvp-btn:hover { transform: translateY(-1px); }
        .rsvp-btn-yes {
            background: #2e7d32;
            color: #fff;
        }
        .rsvp-btn-yes:hover { background: #1b5e20; }
        .rsvp-btn-no {
            background: #ffffff;
            color: #c62828;
            border: 2px solid #c62828;
        }
        .rsvp-btn-no:hover { background: #fdecea; }
        .rsvp-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }
        .rsvp-error {
            padding: 40px 30px;
            background: #fff;
            border: 1px solid #e0e3ea;
            text-align: center;
            color: #c62828;
        }
        .rsvp-error h2 { margin: 0 0 10px; color: #c62828; }
        .rsvp-note {
            font-size: 12px;
            color: #8a90a2;
            text-align: center;
            margin-top: 18px;
        }
    </style>
</head>
<body>

<div class="rsvp-wrap">

    <div class="rsvp-brand">
        <img src="<?= APP_URL ?>/assets/wappen.svg" alt="Wappen Wangen-Brüttisellen"
             onerror="this.onerror=null; this.src='https://www.wangen-bruettisellen.ch/dist/wangen-bruettisellen/2022/images/logo.518ffa4d5ee04f4b1372.svg';">
        <div class="title">Gemeinde Wangen-Brüttisellen</div>
    </div>

    <?php if ($error): ?>
        <div class="rsvp-error">
            <h2>Einladung nicht gefunden</h2>
            <p><?= e($error) ?></p>
            <p style="color:#6a7080; font-size:13px; margin-top:16px;">
                Bitte überprüfen Sie den Link aus Ihrer Einladungs-Mail.
            </p>
        </div>
    <?php else:
        $name      = trim($participant['first_name'] . ' ' . $participant['last_name']);
        $dateStr   = date('l, d.m.Y', $eventTs);
        $timeStr   = date('H:i', $eventTs);
        $endStr    = $participant['event_end']
                        ? ' – ' . date('H:i', strtotime($participant['event_end'])) . ' Uhr'
                        : ' Uhr';
    ?>
        <div class="rsvp-card">
            <h1>Einladung</h1>
            <div class="rsvp-subtitle">
                Guten Tag <?= e($name) ?>,<br>
                Sie sind eingeladen zu:
            </div>

            <h2 style="margin:0 0 18px; font-size:22px; color:#3a4bb0;">
                <?= e($participant['event_title']) ?>
            </h2>

            <dl class="rsvp-meta">
                <dt>Datum</dt>    <dd><?= $dateStr ?></dd>
                <dt>Zeit</dt>     <dd><?= $timeStr . $endStr ?></dd>
                <?php if ($participant['event_location']): ?>
                    <dt>Ort</dt>  <dd><?= e($participant['event_location']) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($participant['event_description']): ?>
                <div class="rsvp-description"><?= e($participant['event_description']) ?></div>
            <?php endif; ?>

            <?php if ($successMsg): ?>
                <div class="rsvp-success"><?= e($successMsg) ?></div>
            <?php endif; ?>

            <?php
            $currentStatus = $participant['status'];
            if ($currentStatus === 'confirmed'): ?>
                <div class="rsvp-status confirmed">
                    ✅ Aktueller Status: <strong>Sie haben zugesagt</strong>
                </div>
            <?php elseif ($currentStatus === 'declined'): ?>
                <div class="rsvp-status declined">
                    ❌ Aktueller Status: <strong>Sie haben abgesagt</strong>
                </div>
            <?php else: ?>
                <div class="rsvp-status">
                    ⏳ Bitte teilen Sie uns Ihre Teilnahme mit:
                </div>
            <?php endif; ?>

            <?php if ($isPast): ?>
                <div class="rsvp-note" style="color:#c62828; font-weight:600;">
                    Dieses Event ist bereits vergangen – eine Antwort ist nicht mehr möglich.
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <div class="rsvp-actions">
                        <button type="submit" name="status" value="confirmed"
                                class="rsvp-btn rsvp-btn-yes"
                                <?= $currentStatus === 'confirmed' ? 'disabled' : '' ?>>
                            ✅ Ich nehme teil
                        </button>
                        <button type="submit" name="status" value="declined"
                                class="rsvp-btn rsvp-btn-no"
                                <?= $currentStatus === 'declined' ? 'disabled' : '' ?>>
                            ❌ Ich kann nicht
                        </button>
                    </div>
                </form>
                <p class="rsvp-note">
                    Sie können Ihre Antwort jederzeit über diesen Link ändern.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
