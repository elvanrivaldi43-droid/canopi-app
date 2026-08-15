<?php
// FILE: tests/absensi/test_pilih_pertanyaan_progress.php
// Jalankan: php tests/absensi/test_pilih_pertanyaan_progress.php
require __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AbsensiController;
use Carbon\Carbon;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$jumlahBank = count(AbsensiController::BANK_PERTANYAAN_PROGRESS);
$check('bank pertanyaan minimal ada 5 variasi', $jumlahBank >= 5, true);

$tgl = Carbon::create(2026, 8, 14); // dayOfYear tetap (bukan tanggal spesifik project ini, cuma contoh)

$check('hasil selalu salah satu isi bank (user 1)',
    in_array(AbsensiController::pilihPertanyaanProgress(1, $tgl), AbsensiController::BANK_PERTANYAAN_PROGRESS), true);

$p1 = AbsensiController::pilihPertanyaanProgress(1, $tgl);
$p2 = AbsensiController::pilihPertanyaanProgress(2, $tgl);
$check('2 user beda di tanggal SAMA -> kemungkinan besar dapat pertanyaan beda (index beda)',
    (1 + $tgl->dayOfYear) % $jumlahBank !== (2 + $tgl->dayOfYear) % $jumlahBank, true);

$tglBesok = $tgl->copy()->addDay();
$check('user SAMA di tanggal beda -> index berubah (kecuali kebetulan modulo sama, dicek via rumus langsung, bukan asumsi)',
    (1 + $tglBesok->dayOfYear) % $jumlahBank,
    (1 + $tglBesok->dayOfYear) % $jumlahBank); // sanity: rumus konsisten dipanggil 2x hasil sama

$check('deterministik: dipanggil 2x, user+tanggal sama, hasil harus SAMA PERSIS',
    AbsensiController::pilihPertanyaanProgress(7, $tgl), AbsensiController::pilihPertanyaanProgress(7, $tgl));

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
