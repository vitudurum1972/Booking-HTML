<?php
require_once __DIR__ . '/config.php';
require_login();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemeindeportal – Wangen-Brüttisellen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gothic+A1:wght@300;400;500;600;700;800&display=swap">
    <style>
        :root {
            --primary: #00acb3;
            --primary-dark: #008a8f;
            --primary-light: #e6f7f8;
            --text: #595a59;
            --text-dark: #000000;
            --text-muted: #8a8a8a;
            --border: #e3e3e5;
            --bg: #ffffff;
            --bg-alt: #f5f5f7;
            --bg-dark: #191919;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Gothic A1", -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { color: var(--primary-dark); }

        /* ── Header ── */
        .topbar {
            background: #ffffff;
            padding: 22px 0;
            border-bottom: 1px solid var(--border);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 30px; }
        .topbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .brand {
            color: var(--text-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand:hover { text-decoration: none; }
        .brand-wappen { width: 72px; height: auto; display: block; }
        .brand-text { display: flex; flex-direction: column; gap: 2px; }
        .brand-sub { font-size: 13px; font-weight: 400; color: var(--text); }
        .brand-main {
            font-size: 22px; font-weight: 700; color: var(--text-dark);
            border-top: 1px solid var(--text-dark); padding-top: 3px; line-height: 1.1;
        }
        .brand:hover .brand-main { color: var(--primary); }
        .brand-app { font-size: 11px; font-weight: 500; color: var(--primary); letter-spacing: 0.8px; text-transform: uppercase; margin-top: 2px; }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, #f5f5f7 0%, #e6f7f8 100%);
            border-bottom: 1px solid var(--border);
            padding: 80px 0 70px;
            text-align: center;
        }
        .hero-eyebrow {
            font-size: 12px; font-weight: 700; color: var(--primary);
            text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 18px;
        }
        .hero h1 {
            font-size: 48px; font-weight: 800; color: var(--text-dark);
            line-height: 1.1; margin: 0 0 22px; letter-spacing: -0.5px;
        }
        .hero p {
            font-size: 18px; color: var(--text); max-width: 580px;
            margin: 0 auto; font-weight: 400;
        }

        /* ── Portal Cards ── */
        .portal-section {
            padding: 80px 0 100px;
            background: var(--bg);
        }
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 32px;
            margin-top: 0;
        }
        .portal-card {
            border: 1px solid var(--border);
            background: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: border-color .2s, box-shadow .2s, transform .2s;
            position: relative;
            overflow: hidden;
        }
        .portal-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .3s ease;
        }
        .portal-card:hover::before { transform: scaleX(1); }
        .portal-card:hover {
            border-color: var(--primary);
            box-shadow: 0 12px 40px rgba(0, 172, 179, 0.14);
            transform: translateY(-4px);
            text-decoration: none;
        }
        .portal-card-icon {
            font-size: 48px;
            margin-bottom: 24px;
            display: block;
            line-height: 1;
        }
        .portal-card h2 {
            font-size: 26px; font-weight: 800; color: var(--text-dark);
            margin: 0 0 14px; line-height: 1.15;
        }
        .portal-card:hover h2 { color: var(--primary); }
        .portal-card p {
            color: var(--text); font-size: 15px; line-height: 1.65;
            margin: 0 0 32px; flex-grow: 1;
        }
        .portal-card-features {
            list-style: none; padding: 0; margin: 0 0 36px;
        }
        .portal-card-features li {
            font-size: 13px; color: var(--text-muted); padding: 5px 0;
            border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px;
        }
        .portal-card-features li:last-child { border-bottom: none; }
        .portal-card-features li::before {
            content: ''; display: inline-block;
            width: 6px; height: 6px;
            background: var(--primary); flex-shrink: 0;
        }
        .portal-card-cta {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--primary); color: #ffffff;
            padding: 14px 28px; font-weight: 700; font-size: 14px;
            letter-spacing: 0.3px; align-self: flex-start;
            transition: background .18s;
        }
        .portal-card:hover .portal-card-cta { background: var(--primary-dark); }
        .portal-card-cta .arrow { font-size: 18px; transition: transform .2s; }
        .portal-card:hover .portal-card-cta .arrow { transform: translateX(4px); }

        /* CRM card accent color */
        .portal-card.crm-card::before { background: #3a4bb0; }
        .portal-card.crm-card:hover { border-color: #3a4bb0; box-shadow: 0 12px 40px rgba(58, 75, 176, 0.12); }
        .portal-card.crm-card:hover h2 { color: #3a4bb0; }
        .portal-card.crm-card .portal-card-features li::before { background: #3a4bb0; }
        .portal-card.crm-card .portal-card-cta { background: #3a4bb0; }
        .portal-card.crm-card:hover .portal-card-cta { background: #2d3a8c; }

        /* Admin/Benutzerverwaltung card accent color */
        .portal-card.admin-card::before { background: #7b2d8e; }
        .portal-card.admin-card:hover { border-color: #7b2d8e; box-shadow: 0 12px 40px rgba(123, 45, 142, 0.12); }
        .portal-card.admin-card:hover h2 { color: #7b2d8e; }
        .portal-card.admin-card .portal-card-features li::before { background: #7b2d8e; }
        .portal-card.admin-card .portal-card-cta { background: #7b2d8e; }
        .portal-card.admin-card:hover .portal-card-cta { background: #5e1f6d; }

        /* ── Info Strip ── */
        .info-strip {
            background: var(--bg-alt);
            border-top: 1px solid var(--border);
            padding: 40px 0;
        }
        .info-strip .container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
        }
        .info-item {
            text-align: center;
        }
        .info-item-label {
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 4px;
        }
        .info-item-value {
            font-size: 15px; font-weight: 600; color: var(--text-dark);
        }

        /* ── Footer ── */
        .footer {
            background: var(--bg-dark);
            color: #b0b0b0;
            padding: 40px 0;
            text-align: center;
            font-size: 13px;
        }
        .footer p { margin: 0 0 8px; }
        .footer p:last-child { margin: 0; }
        .footer a { color: var(--primary); }
        .footer a:hover { color: #ffffff; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .portal-card { padding: 36px 28px; }
            .container { padding: 0 20px; }
            .info-strip .container { gap: 30px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="topbar">
    <div class="container">
        <a href="portal.php" class="brand">
            <img src="assets/wappen.svg" alt="Wappen Wangen-Brüttisellen" class="brand-wappen"
                 onerror="this.onerror=null; this.src='https://www.wangen-bruettisellen.ch/dist/wangen-bruettisellen/2022/images/logo.518ffa4d5ee04f4b1372.svg';">
            <span class="brand-text">
                <span class="brand-sub">Gemeinde</span>
                <span class="brand-main">Wangen-Brüttisellen</span>
                <span class="brand-app">Gemeindeportal</span>
            </span>
        </a>
        <nav style="display:flex; align-items:center; gap:20px;">
            <span style="font-size:14px; color:var(--text-muted);">👤 <?= e($_SESSION['username']) ?></span>
            <a href="change_password.php"
               style="display:inline-block; padding:8px 18px; border:1px solid var(--border-strong);
                      font-size:13px; font-weight:600; color:var(--text); text-decoration:none;
                      transition:background .15s, border-color .15s;"
               onmouseover="this.style.background='var(--bg-alt)'; this.style.borderColor='var(--primary)';"
               onmouseout="this.style.background=''; this.style.borderColor='var(--border-strong)';">
                Passwort ändern
            </a>
            <a href="logout.php"
               style="display:inline-block; padding:8px 18px; border:1px solid var(--border-strong);
                      font-size:13px; font-weight:600; color:var(--text); text-decoration:none;
                      transition:background .15s, border-color .15s;"
               onmouseover="this.style.background='var(--bg-alt)'; this.style.borderColor='var(--primary)';"
               onmouseout="this.style.background=''; this.style.borderColor='var(--border-strong)';">
                Abmelden
            </a>
        </nav>
    </div>
</header>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero-eyebrow">Digitale Dienste</div>
        <h1>Gemeindeportal<br>Wangen-Brüttisellen</h1>
        <p>Reservieren Sie Werkzeuge und Geräte oder verwalten Sie Kontakte im CRM-System der Gemeinde.</p>
    </div>
</section>

<!-- Portal Cards -->
<section class="portal-section">
    <div class="container">
        <div class="portal-grid">

            <!-- Reservation System -->
            <?php if (has_access_reservation() || is_admin()): ?>
            <a href="index.php" class="portal-card">
                <span class="portal-card-icon">🔧</span>
                <h2>Reservationssystem</h2>
                <p>Reservieren Sie Werkzeuge, Geräte und Gegenstände der Gemeinde. Übersichtlich, einfach und jederzeit zugänglich.</p>
                <ul class="portal-card-features">
                    <li>Gegenstände durchsuchen und reservieren</li>
                    <li>Kalenderansicht aller Buchungen</li>
                    <li>Eigene Reservierungen verwalten</li>
                    <li>Anmeldung mit Microsoft-Konto</li>
                </ul>
                <span class="portal-card-cta">
                    Zum Reservationssystem
                    <span class="arrow">→</span>
                </span>
            </a>
            <?php endif; ?>

            <!-- CRM System -->
            <?php if (has_access_crm() || is_admin()): ?>
            <a href="crm/index.php" class="portal-card crm-card">
                <span class="portal-card-icon">👥</span>
                <h2>CRM-System</h2>
                <p>Verwalten Sie Kontakte, Organisationen und Aktivitäten der Gemeinde zentral an einem Ort.</p>
                <ul class="portal-card-features">
                    <li>Kontakte und Organisationen erfassen</li>
                    <li>Notizen und Aktivitäten festhalten</li>
                    <li>Kategorien und Tags vergeben</li>
                    <li>Schnellsuche und Filter</li>
                </ul>
                <span class="portal-card-cta">
                    Zum CRM-System
                    <span class="arrow">→</span>
                </span>
            </a>
            <?php endif; ?>

            <?php if (is_admin()): ?>
            <!-- Benutzerverwaltung -->
            <a href="portal_users.php" class="portal-card admin-card">
                <span class="portal-card-icon">🛡</span>
                <h2>Benutzerverwaltung</h2>
                <p>Verwalten Sie Benutzerkonten, vergeben Sie Administratorrechte und erstellen Sie neue Zugänge.</p>
                <ul class="portal-card-features">
                    <li>Benutzerkonten erstellen und löschen</li>
                    <li>Administratorrechte vergeben</li>
                    <li>Übersicht aller Benutzer</li>
                    <li>Reservierungsstatistiken pro Benutzer</li>
                </ul>
                <span class="portal-card-cta">
                    Zur Benutzerverwaltung
                    <span class="arrow">→</span>
                </span>
            </a>
            <?php endif; ?>

        </div>
    </div>
</section>

<!-- Info Strip -->
<div class="info-strip">
    <div class="container">
        <div class="info-item">
            <div class="info-item-label">Gemeinde</div>
            <div class="info-item-value">Wangen-Brüttisellen</div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Kanton</div>
            <div class="info-item-value">Zürich</div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Webseite</div>
            <div class="info-item-value"><a href="https://www.wangen-bruettisellen.ch" target="_blank">wangen-bruettisellen.ch</a></div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Gemeinde Wangen-Brüttisellen</p>
        <p><a href="https://www.wangen-bruettisellen.ch" target="_blank">www.wangen-bruettisellen.ch</a></p>
    </div>
</footer>

</body>
</html>
