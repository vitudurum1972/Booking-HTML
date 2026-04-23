<?php require_once __DIR__ . '/../../config.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CRM-System') ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
    <style>
        /* CRM-spezifische Erweiterungen */
        .brand-app.crm { color: #3a4bb0; }
        .topbar nav .crm-btn {
            background: #3a4bb0;
            color: #ffffff;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            border-bottom: none;
        }
        .topbar nav .crm-btn:hover {
            background: #2d3a8c;
            color: #ffffff;
            border-color: transparent;
        }
        .crm-tag {
            display: inline-block;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            background: #eef2ff;
            color: #3a4bb0;
            margin: 2px 3px 2px 0;
            letter-spacing: 0.2px;
        }
        .crm-cat-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #ffffff;
        }
        .note-card {
            border: 1px solid var(--border);
            padding: 18px 20px;
            margin-bottom: 14px;
            background: var(--bg-alt);
            position: relative;
        }
        .note-card-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 500;
        }
        .note-card-text {
            font-size: 15px;
            color: var(--text);
            white-space: pre-wrap;
            word-break: break-word;
        }
        .note-card-actions {
            margin-top: 12px;
        }
        .contact-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 0;
        }
        .contact-meta-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .contact-meta-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .contact-meta-value {
            font-size: 15px;
            color: var(--text-dark);
            font-weight: 500;
        }
        .contact-meta-value a { color: var(--primary); }
        .contact-meta-value.empty { color: var(--text-muted); font-style: italic; font-weight: 400; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container">
        <a href="<?= APP_URL ?>/portal.php" class="brand">
            <img src="<?= APP_URL ?>/assets/wappen.svg" alt="Wappen Wangen-Brüttisellen" class="brand-wappen"
                 onerror="this.onerror=null; this.src='https://www.wangen-bruettisellen.ch/dist/wangen-bruettisellen/2022/images/logo.518ffa4d5ee04f4b1372.svg';">
            <span class="brand-text">
                <span class="brand-sub">Gemeinde</span>
                <span class="brand-main">Wangen-Brüttisellen</span>
                <span class="brand-app crm">CRM-System</span>
            </span>
        </a>
        <nav>
            <?php if (is_logged_in()): ?>
                <?php if (has_access_crm() || is_admin()): ?>
                <a href="<?= APP_URL ?>/crm/index.php">Kontakte</a>
                <a href="<?= APP_URL ?>/crm/events.php">Events</a>
                <a href="<?= APP_URL ?>/crm/categories.php">Kategorien</a>
                <a href="<?= APP_URL ?>/crm/contact_form.php" class="crm-btn">+ Neuer Kontakt</a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/portal.php">Portal</a>
                <span class="user">👤 <?= e($_SESSION['username']) ?></span>
                <a href="<?= APP_URL ?>/logout.php" class="btn-small btn-secondary">Abmelden</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login.php">Anmelden</a>
                <a href="<?= APP_URL ?>/portal.php">Portal</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
