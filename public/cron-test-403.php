<?php
// FILE TES SEMENTARA — diagnosa 403 cron-kode-absen.php, HAPUS setelah selesai dipakai.
// Tidak menyentuh DB atau Telegram sama sekali.

$key = $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');
echo "OK - " . date('Y-m-d H:i:s') . " UTC\n";
echo "IP  : " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";
echo "UA  : " . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n";
