<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'canopi2026') die('❌');
$log = '/home/u8221523/public_html/app/storage/logs/laravel.log';
if (!file_exists($log)) die('Log tidak ada');

$content = file_get_contents($log);

// Ambil error terakhir saja (dari [ERROR] atau [critical] terakhir)
$errors = [];
preg_match_all('/\[\d{4}-\d{2}-\d{2}.*?\] \w+\.(ERROR|CRITICAL|WARNING).*?(?=\[\d{4}-\d{2}-\d{2}|$)/s', $content, $matches);

if (!empty($matches[0])) {
    // Ambil 3 error terakhir
    $last = array_slice($matches[0], -3);
    foreach ($last as $err) {
        echo '<div style="background:#1e293b;border:1px solid #ef4444;border-radius:8px;padding:16px;margin:10px;font-family:monospace;font-size:12px;color:#fca5a5;white-space:pre-wrap;word-break:break-all;">';
        echo htmlspecialchars(substr($err, 0, 1000));
        echo '</div>';
    }
} else {
    // Fallback: ambil 30 baris pertama
    $lines = array_slice(file($log), 0, 30);
    echo '<pre style="background:#111;color:#eee;padding:16px;font-size:12px;white-space:pre-wrap;">';
    echo htmlspecialchars(implode('', $lines));
    echo '</pre>';
}