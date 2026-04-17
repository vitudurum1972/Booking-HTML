<?php
require_once __DIR__ . '/config.php';
require_login();

$month = (int)($_GET['m'] ?? date('n'));
$year  = (int)($_GET['y'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = date('n');
$filterItem = (int)($_GET['item'] ?? 0);

$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('N', $firstDay); // 1=Mo

$monthStart = date('Y-m-d 00:00:00', $firstDay);
$monthEnd = date('Y-m-d 23:59:59', mktime(0,0,0,$month,$daysInMonth,$year));

// Alle Gegenstände für Filter-Dropdown laden
$allItems = $pdo->query('SELECT id, name FROM items ORDER BY name')->fetchAll();

$sql = "
    SELECT r.*, i.name AS item_name, u.username, u.full_name AS user_fullname
    FROM reservations r
    JOIN items i ON i.id = r.item_id
    JOIN users u ON u.id = r.user_id
    WHERE r.status IN ('pending','approved')
      AND r.start_date <= ?
      AND r.end_date >= ?
";
$params = [$monthEnd, $monthStart];

if ($filterItem > 0) {
    $sql .= " AND r.item_id = ?";
    $params[] = $filterItem;
}

$sql .= " ORDER BY r.start_date";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Pro Tag gruppieren
$byDay = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dayStart = mktime(0,0,0,$month,$d,$year);
    $dayEnd = mktime(23,59,59,$month,$d,$year);
    foreach ($reservations as $r) {
        $rs = strtotime($r['start_date']);
        $re = strtotime($r['end_date']);
        if ($rs <= $dayEnd && $re >= $dayStart) {
            $byDay[$d][] = $r;
        }
    }
}

$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear  = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;

$monthNames = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

$pageTitle = 'Kalender - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<h1>Kalender</h1>

<div class="cal-filter">
    <form method="get" class="filter-form">
        <input type="hidden" name="m" value="<?= $month ?>">
        <input type="hidden" name="y" value="<?= $year ?>">
        <label for="item-filter">Gegenstand:</label>
        <select name="item" id="item-filter" onchange="this.form.submit()">
            <option value="0">Alle Gegenstände</option>
            <?php foreach ($allItems as $it): ?>
                <option value="<?= $it['id'] ?>" <?= $filterItem == $it['id'] ? 'selected' : '' ?>><?= e($it['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="cal-nav">
    <a href="?m=<?= $prevMonth ?>&y=<?= $prevYear ?>&item=<?= $filterItem ?>" class="btn-secondary">&larr; <?= $monthNames[$prevMonth] ?></a>
    <h2><?= $monthNames[$month] ?> <?= $year ?></h2>
    <a href="?m=<?= $nextMonth ?>&y=<?= $nextYear ?>&item=<?= $filterItem ?>" class="btn-secondary"><?= $monthNames[$nextMonth] ?> &rarr;</a>
</div>

<table class="calendar">
    <thead>
        <tr>
            <th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th>
        </tr>
    </thead>
    <tbody>
    <tr>
        <?php for ($i = 1; $i < $startWeekday; $i++) echo '<td class="empty"></td>'; ?>
        <?php
        $wd = $startWeekday;
        for ($d = 1; $d <= $daysInMonth; $d++):
            $isToday = (date('Y-n-j') === "$year-$month-$d");
        ?>
            <td class="<?= $isToday ? 'today' : '' ?>">
                <div class="daynum"><?= $d ?></div>
                <?php if (!empty($byDay[$d])): ?>
                    <?php foreach (array_slice($byDay[$d], 0, 3) as $r):
                        $displayName = $r['user_fullname'] ?: $r['username'];
                        $usageLabel = ($r['usage_type'] ?? 'privat') === 'geschaeftlich' ? 'G' : 'P';
                        $usageClass = ($r['usage_type'] ?? 'privat') === 'geschaeftlich' ? 'usage-geschaeftlich' : 'usage-privat';
                    ?>
                        <div class="cal-event" title="<?= e($r['item_name']) ?> — <?= e($displayName) ?> (<?= $usageLabel === 'G' ? 'Geschäftlich' : 'Privat' ?>)">
                            <span class="cal-event-item"><?= e($r['item_name']) ?></span>
                            <span class="cal-event-user"><?= e($displayName) ?></span>
                            <span class="cal-event-badge <?= $usageClass ?>"><?= $usageLabel ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($byDay[$d]) > 3): ?>
                        <div class="cal-more">+<?= count($byDay[$d]) - 3 ?> weitere</div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        <?php
            if ($wd % 7 == 0 && $d < $daysInMonth) echo '</tr><tr>';
            $wd++;
        endfor;
        while ($wd % 7 != 1) { echo '<td class="empty"></td>'; $wd++; }
        ?>
    </tr>
    </tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
