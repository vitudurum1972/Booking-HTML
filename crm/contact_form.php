<?php
require_once __DIR__ . '/../config.php';
require_login();

$id      = (int)($_GET['id'] ?? 0);
$contact = null;
$isEdit  = false;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM crm_contacts WHERE id = ?');
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if (!$contact) {
        flash('error', 'Kontakt nicht gefunden.');
        redirect(APP_URL . '/crm/index.php');
    }
    $isEdit = true;
}

// Kategorien
$categories = $pdo->query('SELECT * FROM crm_categories ORDER BY name')->fetchAll();

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $data = [
        'first_name'   => trim($_POST['first_name']   ?? ''),
        'last_name'    => trim($_POST['last_name']     ?? ''),
        'organization' => trim($_POST['organization']  ?? ''),
        'email'        => trim($_POST['email']         ?? ''),
        'phone'        => trim($_POST['phone']         ?? ''),
        'address'      => trim($_POST['address']       ?? ''),
        'notes'        => trim($_POST['notes']         ?? ''),
        'category_id'  => (int)($_POST['category_id'] ?? 0) ?: null,
        'tags'         => trim($_POST['tags']          ?? ''),
        'zusatz'       => trim($_POST['zusatz']        ?? ''),
        'webseite'     => trim($_POST['webseite']      ?? ''),
    ];

    if ($data['last_name'] === '') {
        flash('error', 'Nachname ist erforderlich.');
        redirect(APP_URL . '/crm/contact_form.php' . ($isEdit ? '?id=' . $id : ''));
    }

    if ($isEdit) {
        $stmt = $pdo->prepare("
            UPDATE crm_contacts SET
                first_name=?, last_name=?, organization=?, email=?, phone=?,
                address=?, notes=?, category_id=?, tags=?, zusatz=?, webseite=?
            WHERE id=?
        ");
        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['organization'],
            $data['email'], $data['phone'], $data['address'], $data['notes'],
            $data['category_id'], $data['tags'], $data['zusatz'], $data['webseite'], $id
        ]);
        flash('success', 'Kontakt «' . $data['first_name'] . ' ' . $data['last_name'] . '» aktualisiert.');
        redirect(APP_URL . '/crm/contact_view.php?id=' . $id);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO crm_contacts
                (first_name, last_name, organization, email, phone, address, notes, category_id, tags, zusatz, webseite)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['first_name'], $data['last_name'], $data['organization'],
            $data['email'], $data['phone'], $data['address'], $data['notes'],
            $data['category_id'], $data['tags'], $data['zusatz'], $data['webseite']
        ]);
        $newId = $pdo->lastInsertId();
        flash('success', 'Kontakt «' . $data['first_name'] . ' ' . $data['last_name'] . '» erstellt.');
        redirect(APP_URL . '/crm/contact_view.php?id=' . $newId);
    }
}

$pageTitle = ($isEdit ? 'Kontakt bearbeiten' : 'Neuer Kontakt') . ' – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:13px; color:var(--text-muted); margin-bottom:28px;">
    <a href="index.php">Kontakte</a>
    <?php if ($isEdit): ?> &rsaquo; <a href="contact_view.php?id=<?= $id ?>"><?= e(trim($contact['first_name'] . ' ' . $contact['last_name'])) ?></a><?php endif; ?>
    &rsaquo; <?= $isEdit ? 'Bearbeiten' : 'Neuer Kontakt' ?>
</div>

<div class="page-header">
    <h1><?= $isEdit ? 'Kontakt bearbeiten' : 'Neuer Kontakt' ?></h1>
</div>

<div class="card" style="max-width:760px;">
    <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- Name -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <label>Vorname
                <input type="text" name="first_name"
                       value="<?= e($contact['first_name'] ?? '') ?>"
                       placeholder="z.B. Hans" autofocus>
            </label>
            <label>Nachname <span style="color:#c62828;">*</span>
                <input type="text" name="last_name" required
                       value="<?= e($contact['last_name'] ?? '') ?>"
                       placeholder="z.B. Muster">
            </label>
        </div>

        <!-- Organisation & Zusatz -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <label>Organisation
                <input type="text" name="organization"
                       value="<?= e($contact['organization'] ?? '') ?>"
                       placeholder="z.B. Gemeinde Wangen-Brüttisellen">
            </label>
            <label>Zusatz
                <input type="text" name="zusatz"
                       value="<?= e($contact['zusatz'] ?? '') ?>"
                       placeholder="z.B. Abteilung, Funktion, Abteilung">
            </label>
        </div>

        <!-- Kategorie -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <label>Kategorie
                <select name="category_id">
                    <option value="">– Keine –</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= (($contact['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Webseite
                <input type="url" name="webseite"
                       value="<?= e($contact['webseite'] ?? '') ?>"
                       placeholder="https://www.beispiel.ch">
            </label>
        </div>

        <!-- E-Mail & Telefon -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <label>E-Mail
                <input type="email" name="email"
                       value="<?= e($contact['email'] ?? '') ?>"
                       placeholder="person@beispiel.ch">
            </label>
            <label>Telefon
                <input type="text" name="phone"
                       value="<?= e($contact['phone'] ?? '') ?>"
                       placeholder="+41 44 000 00 00">
            </label>
        </div>

        <!-- Adresse -->
        <label>Adresse
            <textarea name="address" rows="3"
                      placeholder="Strasse, PLZ Ort"><?= e($contact['address'] ?? '') ?></textarea>
        </label>

        <!-- Tags -->
        <label>Tags
            <input type="text" name="tags"
                   value="<?= e($contact['tags'] ?? '') ?>"
                   placeholder="z.B. Vorstand, Bildung, Projektgruppe (kommagetrennt)">
            <span style="font-size:12px; color:var(--text-muted); font-weight:400;">Mehrere Tags mit Komma trennen</span>
        </label>

        <!-- Allgemeine Notiz -->
        <label>Allgemeine Notiz
            <textarea name="notes" rows="4"
                      placeholder="Allgemeine Informationen zu diesem Kontakt …"><?= e($contact['notes'] ?? '') ?></textarea>
        </label>

        <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:8px;">
            <button type="submit" class="btn-primary"><?= $isEdit ? '💾 Änderungen speichern' : '✅ Kontakt erstellen' ?></button>
            <?php if ($isEdit): ?>
                <a href="contact_view.php?id=<?= $id ?>" class="btn-secondary">Abbrechen</a>
            <?php else: ?>
                <a href="index.php" class="btn-secondary">Abbrechen</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
