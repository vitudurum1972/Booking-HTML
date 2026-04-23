<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/crm/events.php');

$stmt = $pdo->prepare('SELECT * FROM crm_events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) {
    flash('error', 'Event nicht gefunden.');
    redirect(APP_URL . '/crm/events.php');
}

// Teilnehmer laden (mit Kontakt-Daten)
$pStmt = $pdo->prepare("
    SELECT p.*, c.first_name, c.last_name, c.organization, c.email, c.phone,
           cat.name AS category_name, cat.color AS category_color
    FROM crm_event_participants p
    JOIN crm_contacts c ON c.id = p.contact_id
    LEFT JOIN crm_categories cat ON cat.id = c.category_id
    WHERE p.event_id = ?
    ORDER BY p.status ASC, c.last_name ASC, c.first_name ASC
");
$pStmt->execute([$id]);
$participants = $pStmt->fetchAll();

$participantIds = array_column($participants, 'contact_id');

// Zählungen
$stats = [
    'total'     => count($participants),
    'invited'   => 0,
    'confirmed' => 0,
    'declined'  => 0,
];
foreach ($participants as $p) {
    $stats[$p['status']]++;
}

// Alle Kontakte für Auswahl-Modal
$allContacts = $pdo->query("
    SELECT c.id, c.first_name, c.last_name, c.organization, c.email,
           cat.name AS category_name, cat.color AS category_color
    FROM crm_contacts c
    LEFT JOIN crm_categories cat ON cat.id = c.category_id
    ORDER BY c.last_name ASC, c.first_name ASC
")->fetchAll();

$eventTs      = strtotime($event['event_date']);
$isPast       = $eventTs < time();
$pageTitle    = e($event['title']) . ' – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:13px; color:var(--text-muted); margin-bottom:28px;">
    <a href="events.php">Events</a> &rsaquo; <?= e($event['title']) ?>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 style="margin-bottom:8px;"><?= e($event['title']) ?></h1>
        <div style="font-size:15px; color:var(--text-muted); font-weight:500;">
            📅 <?= date('l, d.m.Y', $eventTs) ?> &middot; <?= date('H:i', $eventTs) ?> Uhr
            <?php if ($event['event_end']): ?>
                – <?= date('H:i', strtotime($event['event_end'])) ?> Uhr
            <?php endif; ?>
            <?php if ($event['location']): ?>
                &middot; 📍 <?= e($event['location']) ?>
            <?php endif; ?>
        </div>
        <?php if ($isPast): ?>
            <span class="crm-cat-badge" style="background:#8a90a8; margin-top:10px; display:inline-block;">Vergangen</span>
        <?php endif; ?>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="event_form.php?id=<?= $id ?>" class="btn-secondary">✏️ Bearbeiten</a>
        <form method="post" action="event_delete.php"
              onsubmit="return confirm('Event «<?= e(addslashes($event['title'])) ?>» wirklich löschen?\nAlle Teilnehmerdaten gehen verloren.');"
              style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn-danger">🗑 Löschen</button>
        </form>
    </div>
</div>

<?php if ($event['description']): ?>
<div class="card" style="margin-bottom:24px;">
    <h2 style="margin-bottom:12px;">Beschreibung</h2>
    <div style="white-space:pre-wrap; color:var(--text);"><?= e($event['description']) ?></div>
</div>
<?php endif; ?>

<!-- Statistik-Übersicht -->
<div class="event-stats-grid">
    <div class="stat-card">
        <div class="stat-card-value"><?= $stats['total'] ?><?= $event['max_participants'] ? ' / ' . $event['max_participants'] : '' ?></div>
        <div class="stat-card-label">Eingeladen</div>
    </div>
    <div class="stat-card stat-confirmed">
        <div class="stat-card-value"><?= $stats['confirmed'] ?></div>
        <div class="stat-card-label">✅ Zugesagt</div>
    </div>
    <div class="stat-card stat-declined">
        <div class="stat-card-value"><?= $stats['declined'] ?></div>
        <div class="stat-card-label">❌ Abgesagt</div>
    </div>
    <div class="stat-card stat-pending">
        <div class="stat-card-value"><?= $stats['invited'] ?></div>
        <div class="stat-card-label">⏳ Ausstehend</div>
    </div>
</div>

<!-- Teilnehmerliste -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <h2 style="margin:0;">Teilnehmer</h2>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button type="button" class="btn-primary" onclick="openAddModal()">+ Kontakte einladen</button>
            <?php if ($stats['invited'] > 0): ?>
            <form method="post" action="event_invite.php" style="display:inline;"
                  onsubmit="return confirm('Einladungs-E-Mails an alle Kontakte mit Status «Eingeladen» senden?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="event_id" value="<?= $id ?>">
                <button type="submit" class="btn-secondary">📧 Einladungen senden (<?= $stats['invited'] ?>)</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$participants): ?>
        <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
            <div style="font-size:36px; margin-bottom:10px;">👥</div>
            <p style="margin:0 0 16px;">Noch keine Teilnehmer eingeladen.</p>
            <button type="button" class="btn-primary" onclick="openAddModal()">Kontakte jetzt einladen</button>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Organisation</th>
                    <th>E-Mail</th>
                    <th>Status</th>
                    <th>Mail gesendet</th>
                    <th style="width:240px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($participants as $p):
                $name = trim($p['first_name'] . ' ' . $p['last_name']);
                $statusLabels = [
                    'invited'   => ['⏳ Eingeladen',  '#fff4e5', '#b26a00'],
                    'confirmed' => ['✅ Zugesagt',    '#e6f4ea', '#2e7d32'],
                    'declined'  => ['❌ Abgesagt',    '#fdecea', '#c62828'],
                ];
                $lab = $statusLabels[$p['status']] ?? $statusLabels['invited'];
            ?>
                <tr>
                    <td>
                        <a href="contact_view.php?id=<?= $p['contact_id'] ?>"
                           style="font-weight:600; color:var(--text-dark);"><?= e($name) ?></a>
                        <?php if ($p['category_name']): ?>
                            <br><span class="crm-cat-badge" style="background:<?= e($p['category_color']) ?>; margin-top:4px;"><?= e($p['category_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($p['organization'] ?: '–') ?></td>
                    <td>
                        <?php if ($p['email']): ?>
                            <a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a>
                        <?php else: ?>
                            <span style="color:#c62828; font-size:12px;">keine E-Mail</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge" style="background:<?= $lab[1] ?>; color:<?= $lab[2] ?>;">
                            <?= $lab[0] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($p['invitation_sent']): ?>
                            <span style="color:#2e7d32; font-size:12px;">✓ Ja</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:12px;">Nein</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            <?php if ($p['status'] !== 'confirmed'): ?>
                            <form method="post" action="event_participants.php" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="event_id" value="<?= $id ?>">
                                <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn-small" style="background:#e6f4ea; color:#2e7d32; border:1px solid #2e7d32; padding:5px 10px; font-size:11px;" title="Als zugesagt markieren">✅ Zugesagt</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($p['status'] !== 'declined'): ?>
                            <form method="post" action="event_participants.php" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="event_id" value="<?= $id ?>">
                                <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="status" value="declined">
                                <button type="submit" class="btn-small" style="background:#fdecea; color:#c62828; border:1px solid #c62828; padding:5px 10px; font-size:11px;" title="Als abgesagt markieren">❌ Abgesagt</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($p['status'] !== 'invited'): ?>
                            <form method="post" action="event_participants.php" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="event_id" value="<?= $id ?>">
                                <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="status" value="invited">
                                <button type="submit" class="btn-small btn-secondary" style="padding:5px 10px; font-size:11px;" title="Zurück auf Eingeladen">↺</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="event_participants.php" style="display:inline;"
                                  onsubmit="return confirm('Teilnehmer «<?= e(addslashes($name)) ?>» entfernen?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="event_id" value="<?= $id ?>">
                                <input type="hidden" name="participant_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-small btn-danger" style="padding:5px 10px; font-size:11px;" title="Entfernen">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal: Kontakte zum Event hinzufügen -->
<div id="addModal" class="modal-overlay" onclick="if(event.target===this) closeAddModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="margin:0;">Kontakte zum Event einladen</h2>
            <button type="button" class="modal-close" onclick="closeAddModal()">×</button>
        </div>
        <div class="modal-body">
            <form method="post" action="event_participants.php" id="addForm">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_bulk">
                <input type="hidden" name="event_id" value="<?= $id ?>">

                <div style="display:flex; gap:10px; margin-bottom:16px; align-items:center; flex-wrap:wrap;">
                    <input type="text" id="contactSearch" placeholder="🔍 Kontakt suchen (Name, Organisation, E-Mail)…"
                           style="flex:1; min-width:240px; padding:10px 14px; border:1px solid var(--border-strong); font-size:14px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; margin:0; white-space:nowrap;">
                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="margin:0;">
                        Alle sichtbaren auswählen
                    </label>
                    <span id="selectedCount" style="font-size:13px; color:var(--text-muted); white-space:nowrap;">0 ausgewählt</span>
                </div>

                <div class="contact-picker">
                <?php
                $availableCount = 0;
                foreach ($allContacts as $c):
                    if (in_array($c['id'], $participantIds, true)) continue; // schon eingeladen
                    $availableCount++;
                    $cname = trim($c['first_name'] . ' ' . $c['last_name']);
                    $searchText = mb_strtolower($cname . ' ' . $c['organization'] . ' ' . $c['email']);
                ?>
                    <label class="contact-picker-row" data-search="<?= e($searchText) ?>">
                        <input type="checkbox" name="contact_ids[]" value="<?= $c['id'] ?>" onchange="updateCount()">
                        <div class="contact-picker-info">
                            <div class="contact-picker-name"><?= e($cname) ?></div>
                            <div class="contact-picker-meta">
                                <?php if ($c['organization']): ?><?= e($c['organization']) ?><?php endif; ?>
                                <?php if ($c['email']): ?>
                                    <?= $c['organization'] ? ' · ' : '' ?><?= e($c['email']) ?>
                                <?php else: ?>
                                    <span style="color:#c62828;"> · keine E-Mail</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($c['category_name']): ?>
                            <span class="crm-cat-badge" style="background:<?= e($c['category_color']) ?>;"><?= e($c['category_name']) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
                </div>

                <?php if ($availableCount === 0): ?>
                    <div style="text-align:center; padding:32px 12px; color:var(--text-muted);">
                        Alle vorhandenen Kontakte sind bereits eingeladen.
                        <br><a href="contact_form.php" style="margin-top:10px; display:inline-block;">+ Neuen Kontakt anlegen</a>
                    </div>
                <?php endif; ?>

                <div style="margin-top:16px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; margin:0 auto 0 0;">
                        <input type="checkbox" name="send_invitation" value="1" checked style="margin:0;">
                        Einladungs-E-Mails direkt senden
                    </label>
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Abbrechen</button>
                    <button type="submit" class="btn-primary" id="addSubmit" disabled>Ausgewählte einladen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
}
.event-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 28px;
}
.stat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-left: 4px solid #3a4bb0;
    padding: 18px 20px;
}
.stat-card-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1;
}
.stat-card-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted);
    margin-top: 6px;
}
.stat-card.stat-confirmed { border-left-color: #2e7d32; }
.stat-card.stat-declined  { border-left-color: #c62828; }
.stat-card.stat-pending   { border-left-color: #b26a00; }

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.55);
    z-index: 1000;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 16px;
    overflow-y: auto;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #fff;
    max-width: 720px;
    width: 100%;
    max-height: calc(100vh - 80px);
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,.3);
}
.modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--text-muted);
    line-height: 1;
    padding: 0 8px;
}
.modal-close:hover { color: var(--text-dark); }
.modal-body {
    padding: 20px 22px;
    overflow-y: auto;
}
.contact-picker {
    border: 1px solid var(--border);
    max-height: 380px;
    overflow-y: auto;
    background: var(--bg-alt);
}
.contact-picker-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    margin: 0;
    background: #fff;
    transition: background .12s;
}
.contact-picker-row:hover { background: #f4f6ff; }
.contact-picker-row:last-child { border-bottom: none; }
.contact-picker-row input[type="checkbox"] { margin: 0; flex-shrink: 0; }
.contact-picker-info { flex: 1; min-width: 0; }
.contact-picker-name { font-weight: 600; color: var(--text-dark); font-size: 14px; }
.contact-picker-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.contact-picker-row.hidden { display: none; }
</style>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
    document.getElementById('contactSearch').focus();
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}
function updateCount() {
    const checked = document.querySelectorAll('#addForm input[name="contact_ids[]"]:checked').length;
    document.getElementById('selectedCount').textContent = checked + ' ausgewählt';
    document.getElementById('addSubmit').disabled = (checked === 0);
    document.getElementById('addSubmit').textContent = checked > 0
        ? 'Ausgewählte ' + checked + ' einladen'
        : 'Ausgewählte einladen';
}
function toggleAll(cb) {
    document.querySelectorAll('.contact-picker-row:not(.hidden) input[name="contact_ids[]"]').forEach(x => x.checked = cb.checked);
    updateCount();
}
document.getElementById('contactSearch').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.contact-picker-row').forEach(row => {
        if (!q || row.dataset.search.includes(q)) {
            row.classList.remove('hidden');
        } else {
            row.classList.add('hidden');
        }
    });
    document.getElementById('selectAll').checked = false;
});
// ESC schliesst Modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAddModal();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
