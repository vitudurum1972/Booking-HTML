<?php
require_once __DIR__ . '/../config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('crm/index.php');

$stmt = $pdo->prepare("
    SELECT c.*, cat.name AS category_name, cat.color AS category_color
    FROM crm_contacts c
    LEFT JOIN crm_categories cat ON cat.id = c.category_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contact = $stmt->fetch();
if (!$contact) {
    flash('error', 'Kontakt nicht gefunden.');
    redirect(APP_URL . '/crm/index.php');
}

// Notizen laden
$noteStmt = $pdo->prepare("
    SELECT n.*, u.username AS author
    FROM crm_notes n
    LEFT JOIN users u ON u.id = n.created_by
    WHERE n.contact_id = ?
    ORDER BY n.created_at DESC
");
$noteStmt->execute([$id]);
$notes = $noteStmt->fetchAll();

$name = trim($contact['first_name'] . ' ' . $contact['last_name']);
$pageTitle = e($name) . ' – CRM-System';
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:13px; color:var(--text-muted); margin-bottom:28px;">
    <a href="index.php">Kontakte</a> &rsaquo; <?= e($name) ?>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 style="margin-bottom:8px;"><?= e($name) ?></h1>
        <?php if ($contact['organization']): ?>
            <div style="font-size:16px; color:var(--text-muted); font-weight:500;"><?= e($contact['organization']) ?></div>
        <?php endif; ?>
        <?php if ($contact['category_name']): ?>
            <span class="crm-cat-badge" style="background:<?= e($contact['category_color']) ?>; margin-top:10px; display:inline-block;">
                <?= e($contact['category_name']) ?>
            </span>
        <?php endif; ?>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="contact_form.php?id=<?= $id ?>" class="btn-secondary">✏️ Bearbeiten</a>
        <form method="post" action="contact_delete.php" onsubmit="return confirm('Kontakt «<?= e(addslashes($name)) ?>» wirklich löschen?');" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn-danger">🗑 Löschen</button>
        </form>
    </div>
</div>

<!-- Kontakt-Details -->
<div class="card" style="margin-bottom:32px;">
    <h2 style="margin-bottom:20px;">Kontaktdaten</h2>
    <div class="contact-meta-grid">
        <div class="contact-meta-item">
            <div class="contact-meta-label">E-Mail</div>
            <div class="contact-meta-value">
                <?php if ($contact['email']): ?>
                    <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
                <?php else: ?>
                    <span class="empty">Nicht angegeben</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Telefon</div>
            <div class="contact-meta-value">
                <?php if ($contact['phone']): ?>
                    <a href="tel:<?= e($contact['phone']) ?>"><?= e($contact['phone']) ?></a>
                <?php else: ?>
                    <span class="empty">Nicht angegeben</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Zusatz</div>
            <div class="contact-meta-value">
                <?php if ($contact['zusatz']): ?>
                    <?= e($contact['zusatz']) ?>
                <?php else: ?>
                    <span class="empty">Nicht angegeben</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Webseite</div>
            <div class="contact-meta-value">
                <?php if ($contact['webseite']): ?>
                    <a href="<?= e($contact['webseite']) ?>" target="_blank" rel="noopener">
                        <?= e($contact['webseite']) ?>
                    </a>
                <?php else: ?>
                    <span class="empty">Nicht angegeben</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Adresse</div>
            <div class="contact-meta-value">
                <?php if ($contact['address']): ?>
                    <?= nl2br(e($contact['address'])) ?>
                <?php else: ?>
                    <span class="empty">Nicht angegeben</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Kategorie</div>
            <div class="contact-meta-value">
                <?php if ($contact['category_name']): ?>
                    <span class="crm-cat-badge" style="background:<?= e($contact['category_color']) ?>;"><?= e($contact['category_name']) ?></span>
                <?php else: ?>
                    <span class="empty">Keine Kategorie</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="contact-meta-item" style="grid-column: 1 / -1;">
            <div class="contact-meta-label">Tags</div>
            <div class="contact-meta-value">
                <?php
                $tags = array_filter(array_map('trim', explode(',', $contact['tags'] ?? '')));
                if ($tags): foreach ($tags as $t): ?>
                    <a href="index.php?tag=<?= urlencode($t) ?>" class="crm-tag" style="text-decoration:none;color:#3a4bb0;"><?= e($t) ?></a>
                <?php endforeach; else: ?>
                    <span class="empty">Keine Tags</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($contact['notes']): ?>
        <div class="contact-meta-item" style="grid-column: 1 / -1;">
            <div class="contact-meta-label">Allgemeine Notizen</div>
            <div class="contact-meta-value" style="white-space:pre-wrap;"><?= e($contact['notes']) ?></div>
        </div>
        <?php endif; ?>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Erstellt</div>
            <div class="contact-meta-value"><?= date('d.m.Y H:i', strtotime($contact['created_at'])) ?></div>
        </div>
        <div class="contact-meta-item">
            <div class="contact-meta-label">Zuletzt aktualisiert</div>
            <div class="contact-meta-value"><?= date('d.m.Y H:i', strtotime($contact['updated_at'])) ?></div>
        </div>
    </div>
</div>

<!-- Notizen & Aktivitäten -->
<div class="card">
    <h2>Notizen &amp; Aktivitäten</h2>

    <!-- Neue Notiz -->
    <form method="post" action="note_save.php" style="margin-bottom:32px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="contact_id" value="<?= $id ?>">
        <label style="font-weight:700; font-size:14px; color:var(--text-dark); display:block; margin-bottom:8px;">
            Neue Notiz / Aktivität hinzufügen
        </label>
        <textarea name="note" rows="3" placeholder="Notiz eingeben …" required
                  style="width:100%; padding:12px 14px; border:1px solid var(--border-strong); font-family:inherit; font-size:15px; resize:vertical; margin-bottom:12px;"></textarea>
        <button type="submit" class="btn-primary">Notiz speichern</button>
    </form>

    <!-- Bestehende Notizen -->
    <?php if (!$notes): ?>
        <p style="color:var(--text-muted); font-style:italic;">Noch keine Notizen vorhanden.</p>
    <?php else: ?>
        <?php foreach ($notes as $note): ?>
            <div class="note-card">
                <div class="note-card-meta">
                    🕐 <?= date('d.m.Y H:i', strtotime($note['created_at'])) ?>
                    <?php if ($note['author']): ?> &middot; 👤 <?= e($note['author']) ?><?php endif; ?>
                </div>
                <div class="note-card-text"><?= e($note['note']) ?></div>
                <div class="note-card-actions">
                    <form method="post" action="note_save.php"
                          onsubmit="return confirm('Notiz wirklich löschen?');" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <input type="hidden" name="contact_id" value="<?= $id ?>">
                        <button type="submit" class="btn-small btn-danger">🗑 Löschen</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
