<?php
require_once __DIR__ . '/../config.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'save') {
        $id = (int)$_POST['id'];
        $data = [
            'name'        => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'category'    => trim($_POST['category']),
            'location'    => trim($_POST['location']),
            'quantity'    => max(1, (int)$_POST['quantity']),
            'available'   => isset($_POST['available']) ? 1 : 0,
        ];
        if ($id) {
            $stmt = $pdo->prepare('UPDATE items SET name=?, description=?, category=?, location=?, quantity=?, available=? WHERE id=?');
            $stmt->execute([$data['name'], $data['description'], $data['category'], $data['location'], $data['quantity'], $data['available'], $id]);
            flash('success', 'Gegenstand aktualisiert.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO items (name, description, category, location, quantity, available) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$data['name'], $data['description'], $data['category'], $data['location'], $data['quantity'], $data['available']]);
            flash('success', 'Gegenstand erstellt.');
        }
        redirect('items.php');
    }

    if ($op === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Gegenstand gelöscht.');
        redirect('items.php');
    }
}

if ($action === 'edit' || $action === 'new') {
    $item = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) { flash('error', 'Nicht gefunden.'); redirect('items.php'); }
    }
    $pageTitle = 'Gegenstand bearbeiten';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="card">
        <h1><?= $item ? 'Gegenstand bearbeiten' : 'Neuer Gegenstand' ?></h1>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="save">
            <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
            <label>Name <input type="text" name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
            <label>Beschreibung <textarea name="description" rows="3"><?= e($item['description'] ?? '') ?></textarea></label>
            <label>Kategorie <input type="text" name="category" value="<?= e($item['category'] ?? '') ?>"></label>
            <label>Standort <input type="text" name="location" value="<?= e($item['location'] ?? '') ?>"></label>
            <label>Anzahl <input type="number" name="quantity" min="1" value="<?= (int)($item['quantity'] ?? 1) ?>"></label>
            <label class="checkbox">
                <input type="checkbox" name="available" <?= ($item['available'] ?? 1) ? 'checked' : '' ?>>
                Verfügbar / sichtbar
            </label>
            <button type="submit" class="btn-primary">Speichern</button>
            <a href="items.php" class="btn-secondary">Abbrechen</a>
        </form>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$items = $pdo->query('SELECT * FROM items ORDER BY name')->fetchAll();
$pageTitle = 'Gegenstände verwalten';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1>Gegenstände verwalten</h1>
    <a href="?action=new" class="btn-primary">+ Neuer Gegenstand</a>
</div>

<table>
    <thead><tr><th>Name</th><th>Kategorie</th><th>Standort</th><th>Anzahl</th><th>Status</th><th>Aktion</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
        <tr>
            <td><?= e($it['name']) ?></td>
            <td><?= e($it['category']) ?></td>
            <td><?= e($it['location']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= $it['available'] ? '✓ Aktiv' : '✗ Inaktiv' ?></td>
            <td>
                <a href="?action=edit&id=<?= $it['id'] ?>" class="btn-small">Bearbeiten</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Wirklich löschen?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="op" value="delete">
                    <input type="hidden" name="id" value="<?= $it['id'] ?>">
                    <button type="submit" class="btn-danger btn-small">Löschen</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php include __DIR__ . '/../includes/footer.php'; ?>
