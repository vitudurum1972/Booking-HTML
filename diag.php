<?php
/**
 * Diagnose-Seite: zeigt, welche PHP-Extensions und HTTP-Features verfügbar sind.
 * Nach dem Debug wieder löschen.
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><title>PHP Diagnose</title>
<style>
  body{font-family:Gothic A1,Arial,sans-serif;max-width:800px;margin:2em auto;padding:1em;color:#333}
  h1{color:#00acb3}
  table{border-collapse:collapse;width:100%;margin:1em 0}
  td,th{padding:6px 10px;border-bottom:1px solid #eee;text-align:left}
  .ok{color:#0a0;font-weight:bold}
  .fail{color:#c00;font-weight:bold}
  pre{background:#f5f5f7;padding:10px;border-radius:4px;overflow:auto}
</style></head><body>
<h1>PHP Diagnose</h1>
<p>PHP-Version: <strong><?= PHP_VERSION ?></strong></p>

<h2>Wichtige Extensions</h2>
<table>
<?php
$exts = ['curl', 'openssl', 'pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($exts as $e) {
    $ok = extension_loaded($e);
    printf('<tr><td>%s</td><td class="%s">%s</td></tr>',
        htmlspecialchars($e),
        $ok ? 'ok' : 'fail',
        $ok ? 'geladen' : 'FEHLT');
}
?>
</table>

<h2>HTTP-Wrapper (file_get_contents)</h2>
<table>
<tr><td>allow_url_fopen</td><td class="<?= ini_get('allow_url_fopen') ? 'ok' : 'fail' ?>"><?= ini_get('allow_url_fopen') ? 'an' : 'aus' ?></td></tr>
<tr><td>Registrierte Wrapper</td><td><?= htmlspecialchars(implode(', ', stream_get_wrappers())) ?></td></tr>
<tr><td>https-Wrapper</td><td class="<?= in_array('https', stream_get_wrappers()) ? 'ok' : 'fail' ?>"><?= in_array('https', stream_get_wrappers()) ? 'verfügbar' : 'FEHLT (openssl nicht geladen)' ?></td></tr>
</table>

<h2>Test: HTTPS-Request an Microsoft</h2>
<?php
$url = 'https://login.microsoftonline.com/';
// 1) cURL
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo '<p>cURL-Test: ' . ($r !== false ? '<span class="ok">OK (HTTP ' . $code . ')</span>' : '<span class="fail">FEHLER: ' . htmlspecialchars($err) . '</span>') . '</p>';
} else {
    echo '<p>cURL nicht verfügbar.</p>';
}
// 2) Streams
$ctx = stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
$r = @file_get_contents($url, false, $ctx);
if ($r !== false) {
    echo '<p>Stream-Test: <span class="ok">OK</span></p>';
} else {
    $err = error_get_last();
    echo '<p>Stream-Test: <span class="fail">FEHLER: ' . htmlspecialchars($err['message'] ?? 'unbekannt') . '</span></p>';
}
?>

<h2>php.ini</h2>
<pre><?= htmlspecialchars(php_ini_loaded_file() ?: '(keine)') ?></pre>

<p style="color:#c00"><strong>Diese Datei nach dem Debugging löschen!</strong></p>
</body></html>
