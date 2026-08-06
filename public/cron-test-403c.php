<?php
// FILE TES SEMENTARA — diagnosa 403 cron-kode-absen.php, HAPUS setelah selesai dipakai.
// Sama seperti cron-kode-absen.php (boot Laravel + query DB) TAPI TIDAK kirim apapun ke Telegram.

$key = $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$jumlah = User::where('level', '!=', 1)->where('status', 'aktif')->count();

header('Content-Type: text/plain');
echo "OK - Laravel + DB jalan, tanpa kirim Telegram\n";
echo "Waktu  : " . date('Y-m-d H:i:s') . "\n";
echo "Jumlah karyawan aktif: {$jumlah}\n";
