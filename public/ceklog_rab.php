<?php
// Upload ke: /home/u8221523/public_html/app/public/ceklog_rab.php
// Akses: app.kanopibsd.co.id/ceklog_rab.php?key=canopi2026

$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') die('Unauthorized');

$logFile = '/home/u8221523/public_html/app/storage/logs/laravel.log';
$lines = (int)($_GET['lines'] ?? 80);
$filter = $_GET['filter'] ?? '';

if (!file_exists($logFile)) { echo "Log tidak ditemukan: $logFile"; exit; }

// Ambil N baris terakhir
$content = shell_exec("tail -n {$lines} " . escapeshellarg($logFile));

if ($filter) {
    $rows = explode("\n", $content);
    $rows = array_filter($rows, fn($r) => stripos($r, $filter) !== false);
    $content = implode("\n", $rows);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Laravel Log — RAB Debug</title>
<style>
body{background:#0f172a;color:#f1f5f9;font-family:monospace;padding:16px;margin:0}
h2{color:#fbbf24;font-size:16px;margin:0 0 12px}
.bar{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.btn{padding:6px 12px;background:#1e293b;border:1px solid #334155;color:#f1f5f9;
     border-radius:6px;text-decoration:none;font-size:12px}
.btn:hover{border-color:#fbbf24;color:#fbbf24}
pre{background:#0a0a0a;border:1px solid #1e293b;border-radius:8px;
    padding:14px;font-size:11px;line-height:1.5;overflow:auto;
    white-space:pre-wrap;word-break:break-all;max-height:80vh}
.err{color:#f87171}.warn{color:#fbbf24}.info{color:#86efac}
input{background:#1e293b;border:1px solid #334155;color:#f1f5f9;
      border-radius:6px;padding:6px 10px;font-size:12px;outline:none}
</style>
</head>
<body>
<h2>Laravel Log — <?= date('H:i:s') ?></h2>
<div class="bar">
    <a href="?key=canopi2026&lines=50" class="btn">50 baris</a>
    <a href="?key=canopi2026&lines=100" class="btn">100 baris</a>
    <a href="?key=canopi2026&lines=200" class="btn">200 baris</a>
    <a href="?key=canopi2026&lines=50&filter=ERROR" class="btn" style="color:#f87171">ERROR saja</a>
    <a href="?key=canopi2026&lines=50&filter=rab" class="btn" style="color:#fbbf24">Filter: RAB</a>
    <a href="?key=canopi2026&lines=50&filter=exception" class="btn">Exception</a>
    <form style="display:flex;gap:6px" method="get">
        <input type="hidden" name="key" value="canopi2026">
        <input type="text" name="filter" placeholder="Filter kata..." value="<?= htmlspecialchars($filter) ?>">
        <button type="submit" class="btn">Cari</button>
    </form>
</div>
<pre><?php
$lines_arr = explode("\n", $content);
foreach ($lines_arr as $line) {
    if (empty(trim($line))) continue;
    if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
        echo '<span class="err">' . htmlspecialchars($line) . '</span>' . "\n";
    } elseif (stripos($line, 'WARNING') !== false) {
        echo '<span class="warn">' . htmlspecialchars($line) . '</span>' . "\n";
    } else {
        echo htmlspecialchars($line) . "\n";
    }
}
?></pre>
</body>
</html>
