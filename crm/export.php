<?php
/**
 * Exportiert alle Kontakte (mit den aktuell gesetzten Filtern) als Excel-Datei (.xlsx).
 *
 * Übernommene GET-Parameter:
 *   - q   : Suchbegriff (Name, Organisation, E-Mail, Telefon)
 *   - cat : Kategorie-ID (0 = alle)
 *   - tag : Tag
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/xlsx_writer.php';
require_access_crm();

$search = trim($_GET['q']   ?? '');
$catId  = (int)($_GET['cat'] ?? 0);
$tag    = trim($_GET['tag'] ?? '');

// Selben WHERE-Aufbau wie in index.php verwenden
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
    SELECT c.*, cat.name AS category_name
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

// Kategoriename für den Dateinamen (falls nach Kategorie gefiltert)
$catName = '';
if ($catId > 0) {
    $cStmt = $pdo->prepare('SELECT name FROM crm_categories WHERE id = ?');
    $cStmt->execute([$catId]);
    $catName = (string)$cStmt->fetchColumn();
}

// Spalten / Header
$headers = [
    'Vorname',
    'Nachname',
    'Organisation',
    'Zusatz',
    'Kategorie',
    'E-Mail',
    'Telefon',
    'Webseite',
    'Adresse',
    'Tags',
    'Notizen',
    'Erstellt am',
];

// Zeilen aufbauen
$rows = [];
foreach ($contacts as $c) {
    $rows[] = [
        $c['first_name']     ?? '',
        $c['last_name']      ?? '',
        $c['organization']   ?? '',
        $c['zusatz']         ?? '',
        $c['category_name']  ?? '',
        $c['email']          ?? '',
        $c['phone']          ?? '',
        $c['webseite']       ?? '',
        // Adresse: evtl. mehrzeilig → für Excel in eine Zeile mit Kommas
        trim(str_replace(["\r\n", "\r", "\n"], ', ', $c['address'] ?? '')),
        $c['tags']           ?? '',
        $c['notes']          ?? '',
        $c['created_at'] ? date('d.m.Y H:i', strtotime($c['created_at'])) : '',
    ];
}

// Download-Dateiname
$parts = ['Kontakte'];
if ($catName !== '') $parts[] = preg_replace('/[^A-Za-z0-9äöüÄÖÜ_-]+/u', '_', $catName);
if ($tag !== '')     $parts[] = 'Tag_' . preg_replace('/[^A-Za-z0-9äöüÄÖÜ_-]+/u', '_', $tag);
$parts[] = date('Y-m-d');
$downloadName = implode('_', $parts) . '.xlsx';

$sheetName = $catName !== '' ? mb_substr($catName, 0, 31) : 'Kontakte';

crm_stream_xlsx($downloadName, $sheetName, $headers, $rows);
