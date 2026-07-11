<?php
// debug_rab.php — taruh di public/, akses sekali untuk lihat error log
// Upload ke: /home/u8221523/public_html/app/public/debug_rab.php
// Akses: app.kanopibsd.co.id/debug_rab.php?key=canopi2026

if (($_GET['key'] ?? '') !== 'canopi2026') die('no');

$logFile = '/home/u8221523/public_html/app/storage/logs/laravel.log';

// Ambil 100 baris terakhir
$lines = [];
if (file_exists($logFile)) {
    $all = file($logFile);
    $lines = array_slice($all, -100);
} else {
    $lines = ['Log file tidak ditemukan: ' . $logFile];
}

$content = implode('', $lines);
?>
<!DOCTYPE html>
<html>
<head>
<title>Debug RAB</title>
<style>
body{background:#0f172a;color:#f1f5f9;font-family:monospace;padding:20px}
h2{color:#ef4444}
pre{background:#1e293b;padding:16px;border-radius:8px;font-size:12px;
    line-height:1.5;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.err{color:#f87171}.warn{color:#fbbf24}.info{color:#93c5fd}
</style>
</head>
<body>
<h2>Laravel Log — 100 baris terakhir</h2>
<pre><?php
$text = htmlspecialchars($content);
$text = preg_replace('/\[.*?ERROR.*?\]/i', '<span class="err">$0</span>', $text);
$text = preg_replace('/\[.*?WARNING.*?\]/i', '<span class="warn">$0</span>', $text);
echo $text;
?></pre>
<p style="font-size:11px;color:#334155;margin-top:12px">
    File: <?= $logFile ?> | Size: <?= file_exists($logFile) ? round(filesize($logFile)/1024) . ' KB' : 'N/A' ?>
</p>
</body>
</html>
