<?php
require_once __DIR__ . '/../config.php';
require_access_crm();

// ── Aktionen ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#00acb3');

        // Farbe validieren (muss ein gültiger Hex-Wert sein)
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#00acb3';
        }
        if ($name === '') {
            flash('error', 'Name ist erforderlich.');
            redirect(APP_URL . '/crm/categories.php');
        }

        if ($id) {
            $pdo->prepare('UPDATE crm_categories SET name=?, color=? WHERE id=?')
                ->execute([$name, $color, $id]);
            flash('success', 'Kategorie «' . $name . '» aktualisiert.');
        } else {
            $pdo->prepare('INSERT INTO crm_categories (name, color) VALUES (?, ?)')
                ->execute([$name, $color]);
            flash('success', 'Kategorie «' . $name . '» erstellt.');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Kontakte in dieser Kategorie auf NULL setzen
        $pdo->prepare('UPDATE crm_contacts SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('SELECT name FROM crm_categories WHERE id = ?');
        $stmt->execute([$id]);
        $cat = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM crm_categories WHERE id = ?')->execute([$id]);
        flash('success', 'Kategorie «' . $cat . '» gelöscht.');
    }

    redirect(APP_URL . '/crm/categories.php');
}

// ── Daten laden ───────────────────────────────────────────
$categories = $pdo->query("
    SELECT c.*, COUNT(k.id) AS contact_count
    FROM crm_categories c
    LEFT JOIN crm_contacts k ON k.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll();

// Bearbeiten-Modus?
$editId  = (int)($_GET['edit'] ?? 0);
$editCat = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM crm_categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
}

$pageTitle = 'Kategorien – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<div style="font-size:13px; color:var(--text-muted); margin-bottom:28px;">
    <a href="index.php">Kontakte</a> &rsaquo; Kategorien
</div>

<div class="page-header">
    <h1>Kategorien verwalten</h1>
</div>

<div style="display:grid; grid-template-columns:1fr 360px; gap:32px; align-items:start;">

    <!-- Kategorienliste -->
    <div>
        <?php if (!$categories): ?>
            <div class="card" style="text-align:center; padding:48px; color:var(--text-muted);">
                Noch keine Kategorien vorhanden.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:44px;">Farbe</th>
                        <th>Name</th>
                        <th style="width:120px; text-align:center;">Kontakte</th>
                        <th style="width:140px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <span style="display:inline-block; width:28px; height:28px; background:<?= e($cat['color']) ?>; border:1px solid rgba(0,0,0,.1);"></span>
                        </td>
                        <td style="font-weight:600; color:var(--text-dark);">
                            <span class="crm-cat-badge" style="background:<?= e($cat['color']) ?>;"><?= e($cat['name']) ?></span>
                        </td>
                        <td style="text-align:center;">
                            <a href="index.php?cat=<?= $cat['id'] ?>" style="font-weight:600;">
                                <?= $cat['contact_count'] ?>
                            </a>
                        </td>
                        <td style="display:flex; gap:6px;">
                            <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn-small btn-secondary">✏️ Bearbeiten</a>
                            <form method="post" onsubmit="return confirm('Kategorie «<?= e(addslashes($cat['name'])) ?>» löschen?\nKontakte dieser Kategorie werden keiner Kategorie zugewiesen.');" style="display:inline;">
                                <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Formular: Neu / Bearbeiten -->
    <div class="card" style="position:sticky; top:24px;">
        <h2><?= $editCat ? 'Kategorie bearbeiten' : 'Neue Kategorie' ?></h2>
        <form method="post" action="categories.php">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <?php if ($editCat): ?>
                <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
            <?php endif; ?>

            <label>Name <span style="color:#c62828;">*</span>
                <input type="text" name="name" required autofocus
                       value="<?= e($editCat['name'] ?? '') ?>"
                       placeholder="z.B. Verein">
            </label>

            <label>Farbe
                <div style="display:flex; gap:10px; align-items:center; margin-top:8px;">
                    <input type="color" name="color"
                           value="<?= e($editCat['color'] ?? '#00acb3') ?>"
                           id="colorPicker"
                           style="width:48px; height:40px; padding:2px; border:1px solid var(--border-strong); cursor:pointer; background:none;">
                    <input type="text" id="colorText"
                           value="<?= e($editCat['color'] ?? '#00acb3') ?>"
                           placeholder="#00acb3"
                           maxlength="7"
                           style="flex:1; margin-top:0;"
                           oninput="document.getElementById('colorPicker').value=this.value">
                </div>
                <!-- Farbvorschläge -->
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                    <?php foreach ([
                        '#00acb3','#3a4bb0','#e67e22','#2e7d32',
                        '#8e24aa','#c62828','#00838f','#546e7a'
                    ] as $c): ?>
                        <span onclick="document.getElementById('colorPicker').value='<?= $c ?>'; document.getElementById('colorText').value='<?= $c ?>';"
                              style="display:inline-block; width:28px; height:28px; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:border-color .15s;"
                              onmouseover="this.style.borderColor='#000'" onmouseout="this.style.borderColor='transparent'"
                              title="<?= $c ?>"></span>
                    <?php endforeach; ?>
                </div>
            </label>

            <!-- Vorschau -->
            <div style="margin-bottom:20px;">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--text-muted); margin-bottom:8px;">Vorschau</div>
                <span id="preview" class="crm-cat-badge"
                      style="background:<?= e($editCat['color'] ?? '#00acb3') ?>;">
                    <?= $editCat ? e($editCat['name']) : 'Beispiel' ?>
                </span>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" class="btn-primary">
                    <?= $editCat ? '💾 Speichern' : '✅ Erstellen' ?>
                </button>
                <?php if ($editCat): ?>
                    <a href="categories.php" class="btn-secondary">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

</div>

<script>
// Farbwähler → Textfeld + Vorschau synchronisieren
document.getElementById('colorPicker').addEventListener('input', function() {
    document.getElementById('colorText').value = this.value;
    updatePreview();
});
document.getElementById('colorText').addEventListener('input', function() {
    if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        document.getElementById('colorPicker').value = this.value;
    }
    updatePreview();
});
// Name → Vorschau
document.querySelector('input[name="name"]').addEventListener('input', function() {
    document.getElementById('preview').textContent = this.value || 'Beispiel';
});
function updatePreview() {
    const color = document.getElementById('colorPicker').value;
    document.getElementById('preview').style.background = color;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
