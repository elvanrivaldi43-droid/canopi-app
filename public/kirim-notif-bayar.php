<?php
// FILE: public/kirim-notif-bayar.php
// SEKALI PAKAI (31 Okt 2026): kirim notif "sudah dibayar" ke slip yang dibayar bulan ini,
// sebelum fitur notif Bayar Semua ada. HAPUS file ini setelah dipakai.
// Tanpa &kirim=1 cuma menampilkan daftar (aman). Dijalankan 2x = notif terkirim 2x.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!\App\Services\CronAuth::valid((string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    die('Forbidden');
}

$kirim = ($_GET['kirim'] ?? '') === '1';
$slips = \App\Models\SlipGaji::with('user')
    ->where('status', 'dibayar')
    ->whereMonth('tanggal_bayar', now()->month)
    ->whereYear('tanggal_bayar', now()->year)
    ->get();

header('Content-Type: text/plain; charset=utf-8');
echo ($kirim ? "=== KIRIM ===" : "=== DAFTAR SAJA (tambah &kirim=1 untuk kirim) ===") . "\n";
foreach ($slips as $s) {
    $nama = $s->user?->name ?? '(user hilang)';
    $tg   = $s->user?->telegram_chat_id ? 'TG' : 'belum connect TG';
    echo "- {$nama} | {$s->periodeLabel()} {$s->namaBulan()} {$s->tahun} | Rp "
       . number_format($s->gaji_bersih, 0, ',', '.') . " | dibayar {$s->tanggal_bayar} | {$tg}\n";
    if ($kirim) \App\Http\Controllers\PenggajianController::notifSudahDibayar($s);
}
echo "Total: " . $slips->count() . " slip\n";
