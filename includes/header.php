<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container">
        <a href="<?= APP_URL ?>/index.php" class="brand">
            <img src="<?= APP_URL ?>/assets/wappen.svg" alt="Wappen Wangen-Brüttisellen" class="brand-wappen"
                 onerror="this.onerror=null; this.src='https://www.wangen-bruettisellen.ch/dist/wangen-bruettisellen/2022/images/logo.518ffa4d5ee04f4b1372.svg';">
            <span class="brand-text">
                <span class="brand-sub">Gemeinde</span>
                <span class="brand-main">Wangen-Brüttisellen</span>
                <span class="brand-app"><?= e(APP_NAME) ?></span>
            </span>
        </a>
        <nav>
            <?php if (is_logged_in()): ?>
                <a href="<?= APP_URL ?>/items.php">Gegenstände</a>
                <a href="<?= APP_URL ?>/calendar.php">Kalender</a>
                <a href="<?= APP_URL ?>/my_reservations.php">Meine Reservierungen</a>
                <?php if (is_admin()): ?>
                    <a href="<?= APP_URL ?>/admin/index.php" class="admin-link">Admin</a>
                <?php endif; ?>
                <span class="user">👤 <?= e($_SESSION['username']) ?></span>
                <a href="<?= APP_URL ?>/logout.php" class="btn-small">Abmelden</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login.php">Anmelden</a>
                <a href="<?= APP_URL ?>/register.php">Registrieren</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
