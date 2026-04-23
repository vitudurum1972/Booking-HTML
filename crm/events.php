<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

// Filter: upcoming / past / all
$filter = $_GET['filter'] ?? 'upcoming';
$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filter === 'upcoming') {
    $where[] = 'e.event_date >= NOW() - INTERVAL 1 DAY';
} elseif ($filter === 'past') {
    $where[] = 'e.event_date < NOW() - INTERVAL 1 DAY';
}

if ($search !== '') {
    $where[] = '(e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$sql = "
    SELECT e.*,
           (SELECT COUNT(*) FROM crm_event_participants p WHERE p.event_id = e.id) AS participant_count,
           (SELECT COUNT(*) FROM crm_event_participants p WHERE p.event_id = e.id AND p.status = 'confirmed') AS confirmed_count,
           (SELECT COUNT(*) FROM crm_event_participants p WHERE p.event_id = e.id AND p.status = 'declined')  AS declined_count,
           (SELECT COUNT(*) FROM crm_event_participants p WHERE p.event_id = e.id AND p.status = 'invited')   AS pending_count
    FROM crm_events e
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ($filter === 'past') ? ' ORDER BY e.event_date DESC' : ' ORDER BY e.event_date ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = 'Events – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Events / Veranstaltungen</h1>
    <a href="<?= APP_URL ?>/crm/event_form.php" class="btn-primary">+ Neues Event</a>
</div>

<!-- Filter / Suche -->
<form method="get" class="filter-bar" style="flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:20px;">
    <input type="text" name="q" placeholder="Titel, Ort, Beschreibung …"
           value="<?= e($search) ?>" style="flex:2; min-width:200px;">
    <select name="filter" style="min-width:160px;">
        <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Anstehende Events</option>
        <option value="past"     <?= $filter === 'past'     ? 'selected' : '' ?>>Vergangene Events</option>
        <option value="all"      <?= $filter === 'all'      ? 'selected' : '' ?>>Alle Events</option>
    </select>
    <button type="submit" class="btn-primary" style="padding:10px 22px;">Anzeigen</button>
    <?php if ($search): ?>
        <a href="events.php?filter=<?= e($filter) ?>" class="btn-secondary" style="padding:10px 18px;">Zurücksetzen</a>
    <?php endif; ?>
</form>

<?php if (!$events): ?>
    <div class="card" style="text-align:center; padding:60px 40px; color:var(--text-muted);">
        <div style="font-size:40px; margin-bottom:16px;">📅</div>
        <p style="font-size:16px; margin:0 0 20px;">
            <?php if ($filter === 'upcoming'): ?>
                Keine anstehenden Events.
            <?php elseif ($filter === 'past'): ?>
                Keine vergangenen Events.
            <?php else: ?>
                Keine Events gefunden.
            <?php endif; ?>
        </p>
        <a href="event_form.php" class="btn-primary">Erstes Event anlegen</a>
    </div>
<?php else: ?>
    <div style="font-size:13px; color:var(--text-muted); margin-bottom:14px;">
        <?= count($events) ?> Event<?= count($events) != 1 ? 's' : '' ?> gefunden
    </div>
    <div class="event-grid">
    <?php foreach ($events as $ev):
        $ts        = strtotime($ev['event_date']);
        $isPast    = $ts < time();
        $dateFmt   = date('d.m.Y', $ts);
        $timeFmt   = date('H:i',   $ts);
        $weekday   = ['So','Mo','Di','Mi','Do','Fr','Sa'][(int)date('w', $ts)];
    ?>
        <a href="event_view.php?id=<?= $ev['id'] ?>" class="event-card <?= $isPast ? 'past' : '' ?>">
            <div class="event-card-date">
                <div class="event-card-weekday"><?= $weekday ?></div>
                <div class="event-card-day"><?= date('d', $ts) ?></div>
                <div class="event-card-month"><?= strtoupper(date('M', $ts)) ?></div>
            </div>
            <div class="event-card-body">
                <h3 style="margin:0 0 6px;"><?= e($ev['title']) ?></h3>
                <div style="font-size:13px; color:var(--text-muted); margin-bottom:8px;">
                    🕐 <?= $dateFmt ?>, <?= $timeFmt ?> Uhr
                    <?php if ($ev['location']): ?>
                        &middot; 📍 <?= e($ev['location']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($ev['description']): ?>
                    <div style="font-size:13px; color:var(--text); margin-bottom:10px;">
                        <?= e(mb_strimwidth($ev['description'], 0, 140, '…')) ?>
                    </div>
                <?php endif; ?>
                <div class="event-stats">
                    <span class="event-stat invited">📨 <?= (int)$ev['pending_count'] ?> offen</span>
                    <span class="event-stat confirmed">✅ <?= (int)$ev['confirmed_count'] ?> zugesagt</span>
                    <span class="event-stat declined">❌ <?= (int)$ev['declined_count'] ?> abgesagt</span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.event-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 16px;
}
.event-card {
    display: flex;
    gap: 0;
    background: #fff;
    border: 1px solid var(--border);
    text-decoration: none;
    color: inherit;
    transition: transform .15s, box-shadow .15s, border-color .15s;
}
.event-card:hover {
    transform: translateY(-2px);
    border-color: #3a4bb0;
    box-shadow: 0 4px 14px rgba(58,75,176,.12);
}
.event-card.past { opacity: .6; }
.event-card.past:hover { opacity: 1; }
.event-card-date {
    background: #3a4bb0;
    color: #fff;
    padding: 16px 18px;
    text-align: center;
    min-width: 92px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.event-card.past .event-card-date { background: #8a90a8; }
.event-card-weekday {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    opacity: .85;
}
.event-card-day {
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    margin: 4px 0;
}
.event-card-month {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    opacity: .85;
}
.event-card-body {
    padding: 14px 18px;
    flex: 1;
    min-width: 0;
}
.event-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}
.event-stat {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 2px;
    background: #eef2ff;
    color: #3a4bb0;
}
.event-stat.confirmed { background: #e6f4ea; color: #2e7d32; }
.event-stat.declined  { background: #fdecea; color: #c62828; }
.event-stat.invited   { background: #fff4e5; color: #b26a00; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
