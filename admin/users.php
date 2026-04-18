<?php
// Benutzerverwaltung wurde ins Portal verschoben.
// Redirect zur neuen Seite, damit alte Links/Bookmarks weiterhin funktionieren.
require_once __DIR__ . '/../config.php';
require_admin();
header('Location: ' . APP_URL . '/portal_users.php');
exit;
