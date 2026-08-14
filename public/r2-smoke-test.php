<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi_r2_smoke_2026') {
    http_response_code(403);
    die('Akses ditolak.');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = new \App\Services\R2Service();
$isiTes = 'tes koneksi R2 — ' . now();
$url = $svc->put('smoke-test/tes.txt', $isiTes, 'text/plain');

if (!$url) {
    echo "GAGAL upload. Cek laravel.log untuk detail error (kemungkinan kredensial .env salah).";
    exit;
}

echo "Upload berhasil. URL: {$url}\n\n";
echo "Buka URL di atas di tab baru — harus tampil tulisan: \"{$isiTes}\"\n\n";
echo "Kalau sudah dicek dan benar, HAPUS FILE INI (public/r2-smoke-test.php) dan hapus juga object 'smoke-test/tes.txt' dari bucket R2 (lewat dashboard Cloudflare).";
