<?php
// FILE: tests/kerja-hari-libur/test_kode_absen_atomik.php
// Jalankan: php tests/kerja-hari-libur/test_kode_absen_atomik.php
//
// Kode absen harus 1 per karyawan per tanggal, bahkan kalau 2 request datang barengan
// (Owner klik "Aktifkan Masuk Hari Ini" dua kali, atau cron + halaman kode jalan bersamaan).
//
// CATATAN: server ini tidak punya extension PDO MySQL/SQLite, jadi race condition tidak bisa
// dijalankan beneran. Yang diuji di sini: (a) format kode, (b) jalur tulis memakai operasi
// atomik bawaan Laravel, dan (c) skema baru punya unique (tanggal, user_id) yang jadi
// SYARAT keatomikan itu — tanpa unique index, createOrFirst pun tetap bisa dobel.
require __DIR__ . '/../bootstrap.php';

use App\Models\KodeAbsen;

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── Yang diuji harus kode worktree ini, bukan repo utama ────
// (vendor/ dishare; classmap composer repo utama menang atas PSR-4 — lihat tests/bootstrap.php)
$check('App\Models\KodeAbsen dimuat dari worktree ini',
    str_starts_with((new ReflectionClass(KodeAbsen::class))->getFileName(), realpath(__DIR__ . '/../../app')), true);

// ── Format kode (murni) ─────────────────────────────────────
$kode = KodeAbsen::generateKode();
$check('kode 6 karakter',                 strlen($kode), 6);
$check('kode huruf besar/angka aman saja (tanpa I, O, 0, 1 yang gampang salah baca)',
    (bool) preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $kode), true);

$acak = [];
for ($i = 0; $i < 50; $i++) $acak[] = KodeAbsen::generateKode();
$check('kode tidak selalu sama (acak)', count(array_unique($acak)) > 1, true);

// ── Jalur tulis atomik ──────────────────────────────────────
$src = file_get_contents(__DIR__ . '/../../app/Models/KodeAbsen.php');
$check('kodeHariIniUntuk() memakai createOrFirst (INSERT + tangkap duplikat), bukan cek-lalu-insert',
    str_contains($src, 'createOrFirst'), true);
$check('pola lama non-atomik (->first() lalu self::create) sudah tidak ada',
    (bool) preg_match('/->first\(\);\s*if \(!\$record\)/', $src), false);
$check('kunci pencarian tetap per user + tanggal (kode tetap pribadi)',
    str_contains($src, "'user_id'") && str_contains($src, "'tanggal'"), true);

// ── Skema: unique (tanggal, user_id) untuk instalasi baru ───
$migrasi = '';
foreach (glob(__DIR__ . '/../../database/migrations/*.php') as $f) {
    $isi = file_get_contents($f);
    if (str_contains($isi, 'kode_absen')) $migrasi .= $isi;
}
$check('ada migrasi yang menambah kolom user_id ke kode_absen',
    str_contains($migrasi, 'user_id'), true);
$check('ada unique (tanggal, user_id) di skema kode_absen',
    (bool) preg_match("/unique\(\s*\[\s*'tanggal'\s*,\s*'user_id'\s*\]/", $migrasi), true);
$check('migrasi aman dijalankan di DB yang kolom/indexnya sudah ada (production)',
    str_contains($migrasi, 'hasColumn'), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
