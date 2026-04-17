<?php
require_once __DIR__ . '/config.php';
require_login();

$search = trim($_GET['q'] ?? '');
$cat    = trim($_GET['cat'] ?? '');

$sql = 'SELECT * FROM items WHERE available = 1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat !== '') {
    $sql .= ' AND category = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Gegenstände - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<h1>Gegenstände</h1>

<form method="get" class="filter-bar">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Suchen...">
    <select name="cat">
        <option value="">Alle Kategorien</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary">Filtern</button>
</form>

<div class="items-grid">
    <?php if (!$items): ?>
        <p>Keine Gegenstände gefunden.</p>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
        <div class="item-card">
            <h3><?= e($item['name']) ?></h3>
            <?php if ($item['category']): ?>
                <span class="badge"><?= e($item['category']) ?></span>
            <?php endif; ?>
            <p><?= e($item['description']) ?></p>
            <p class="meta">📍 <?= e($item['location'] ?: 'Kein Standort') ?> &middot; Anzahl: <?= (int)$item['quantity'] ?></p>
            <a href="reserve.php?item_id=<?= $item['id'] ?>" class="btn-primary">Reservieren</a>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
