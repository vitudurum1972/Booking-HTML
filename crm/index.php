<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

// Suchparameter
$search   = trim($_GET['q']   ?? '');
$catId    = (int)($_GET['cat'] ?? 0);
$tag      = trim($_GET['tag'] ?? '');

// Kategorien für Filter
$categories = $pdo->query('SELECT * FROM crm_categories ORDER BY name')->fetchAll();

// Kontakte abfragen
$params = [];
$where  = [];

if ($search !== '') {
    $where[]  = '(c.first_name LIKE ? OR c.last_name LIKE ? OR c.organization LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($catId > 0) {
    $where[]  = 'c.category_id = ?';
    $params[] = $catId;
}
if ($tag !== '') {
    $where[]  = 'c.tags LIKE ?';
    $params[] = '%' . $tag . '%';
}

$sql = "
    SELECT c.*, cat.name AS category_name, cat.color AS category_color
    FROM crm_contacts c
    LEFT JOIN crm_categories cat ON cat.id = c.category_id
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.last_name ASC, c.first_name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll();

// Alle Tags sammeln (für Tag-Cloud)
$allTagsStmt = $pdo->query("SELECT DISTINCT tags FROM crm_contacts WHERE tags != '' AND tags IS NOT NULL");
$allTags = [];
foreach ($allTagsStmt->fetchAll(PDO::FETCH_COLUMN) as $tagStr) {
    foreach (array_map('trim', explode(',', $tagStr)) as $t) {
        if ($t !== '') $allTags[$t] = ($allTags[$t] ?? 0) + 1;
    }
}
arsort($allTags);
$topTags = array_slice($allTags, 0, 12, true);

$pageTitle = 'Kontakte – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Kontakte</h1>
    <a href="<?= APP_URL ?>/crm/contact_form.php" class="btn-primary">+ Neuer Kontakt</a>
</div>

<!-- Filter Bar -->
<form method="get" class="filter-bar" style="flex-wrap:wrap; gap:10px; align-items:flex-end;">
    <input type="text" name="q" placeholder="Name, Organisation, E-Mail, Telefon …"
           value="<?= e($search) ?>" style="flex:2; min-width:200px;">
    <select name="cat" style="min-width:160px;">
        <option value="0">Alle Kategorien</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary" style="padding:10px 22px;">Suchen</button>
    <?php if ($search || $catId || $tag): ?>
        <a href="index.php" class="btn-secondary" style="padding:10px 18px;">Zurücksetzen</a>
    <?php endif; ?>
</form>

<?php if ($tag): ?>
    <div style="margin-bottom:18px;">
        <span style="font-size:13px; color:var(--text-muted);">Gefiltert nach Tag:</span>
        <span class="crm-tag"><?= e($tag) ?></span>
        <a href="index.php" style="font-size:12px; margin-left:8px;">✕ entfernen</a>
    </div>
<?php endif; ?>

<?php if ($topTags): ?>
    <div style="margin-bottom:28px; display:flex; flex-wrap:wrap; align-items:center; gap:6px;">
        <span style="font-size:12px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-right:4px;">Tags:</span>
        <?php foreach ($topTags as $t => $cnt): ?>
            <a href="?tag=<?= urlencode($t) ?><?= $catId ? '&cat='.$catId : '' ?>"
               class="crm-tag" style="text-decoration:none; color:#3a4bb0;"><?= e($t) ?> <span style="opacity:.6;">(<?= $cnt ?>)</span></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Kontakttabelle -->
<?php if (!$contacts): ?>
    <div class="card" style="text-align:center; padding:60px 40px; color:var(--text-muted);">
        <div style="font-size:40px; margin-bottom:16px;">👥</div>
        <p style="font-size:16px; margin:0 0 20px;">Keine Kontakte gefunden.</p>
        <a href="contact_form.php" class="btn-primary">Ersten Kontakt anlegen</a>
    </div>
<?php else: ?>
    <div style="font-size:13px; color:var(--text-muted); margin-bottom:14px;"><?= count($contacts) ?> Kontakt<?= count($contacts) != 1 ? 'e' : '' ?> gefunden</div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Organisation</th>
                <th>Kategorie</th>
                <th>E-Mail</th>
                <th>Telefon</th>
                <th>Tags</th>
                <th style="width:110px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contacts as $c): ?>
            <tr>
                <td>
                    <a href="contact_view.php?id=<?= $c['id'] ?>" style="font-weight:600; color:var(--text-dark);">
                        <?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?>
                    </a>
                </td>
                <td><?= e($c['organization'] ?: '–') ?></td>
                <td>
                    <?php if ($c['category_name']): ?>
                        <span class="crm-cat-badge" style="background:<?= e($c['category_color']) ?>;"><?= e($c['category_name']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-muted);">–</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['email']): ?>
                        <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                    <?php else: ?> – <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['phone']): ?>
                        <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
                    <?php else: ?> – <?php endif; ?>
                </td>
                <td style="max-width:160px;">
                    <?php foreach (array_filter(array_map('trim', explode(',', $c['tags'] ?? ''))) as $t): ?>
                        <a href="?tag=<?= urlencode($t) ?>" class="crm-tag" style="text-decoration:none;color:#3a4bb0;"><?= e($t) ?></a>
                    <?php endforeach; ?>
                </td>
                <td>
                    <a href="contact_view.php?id=<?= $c['id'] ?>" class="btn-small btn-secondary" style="margin-right:4px;">Ansehen</a>
                    <a href="contact_form.php?id=<?= $c['id'] ?>" class="btn-small" style="border:1px solid var(--border-strong); padding:7px 12px; font-size:12px; color:var(--text-muted);">✏️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
