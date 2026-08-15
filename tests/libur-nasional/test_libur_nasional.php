<?php
// FILE: tests/libur-nasional/test_libur_nasional.php
// Jalankan: php tests/libur-nasional/test_libur_nasional.php
require __DIR__ . '/../bootstrap.php';

use App\Services\LiburService;
use Carbon\Carbon;

$svc = new LiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── expandLiburNasional ──────────────────────────────────────
$check('rentang 1 hari, tanpa piket -> 1 override tambah',
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', []),
    [['tanggal' => '2026-08-17', 'jenis' => 'tambah']]);

$check('rentang 3 hari, tanpa piket -> 3 override tambah berurutan',
    $svc->expandLiburNasional('2026-08-17', '2026-08-19', []),
    [
        ['tanggal' => '2026-08-17', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-18', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-19', 'jenis' => 'tambah'],
    ]);

$check('rentang 3 hari, 1 tanggal di-piket -> tanggal itu TIDAK ada di hasil (bukan libur buat dia)',
    $svc->expandLiburNasional('2026-08-17', '2026-08-19', ['2026-08-18']),
    [
        ['tanggal' => '2026-08-17', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-19', 'jenis' => 'tambah'],
    ]);

$check('semua tanggal di-piket -> hasil kosong',
    $svc->expandLiburNasional('2026-08-17', '2026-08-18', ['2026-08-17', '2026-08-18']),
    []);

// ── Precedence: libur nasional menang lawan override pribadi kalau tanggal sama ──
// cocokLiburPada() return di override PERTAMA yang cocok tanggalnya, jadi urutan array menentukan.
$liburNasionalDulu = array_merge(
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', []), // override nasional 'tambah'
    [['tanggal' => '2026-08-17', 'jenis' => 'batal']]           // override pribadi 'batal' (karyawan minta tetap kerja hari itu)
);
$check('libur nasional (tambah) ditaruh duluan -> menang lawan override pribadi (batal) di tanggal sama -> true (tetap libur)',
    $svc->cocokLiburPada(null, $liburNasionalDulu, Carbon::create(2026, 8, 17)), true);

$karyawanPiket = array_merge(
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', ['2026-08-17']), // kosong, karena di-piket
    [] // tanpa override pribadi -> fallback ke default
);
$check('karyawan di-piket tanggal itu -> tidak ada override nasional yang di-generate -> fallback ke default (null -> false, kerja normal)',
    $svc->cocokLiburPada(null, $karyawanPiket, Carbon::create(2026, 8, 17)), false);

// ── Kasus 2 libur nasional overlap, piket harus konsisten di keduanya ──
$liburA = $svc->expandLiburNasional('2026-08-15', '2026-08-19', ['2026-08-17']); // "Cuti Bersama", piket 17 Agustus
$liburB = $svc->expandLiburNasional('2026-08-17', '2026-08-17', ['2026-08-17']); // "HUT RI", piket tanggal sama
$gabungan = array_merge($liburA, $liburB);
$tanggal17 = array_filter($gabungan, fn($o) => $o['tanggal'] === '2026-08-17');
$check('piket di tanggal yang di-cover 2 libur nasional sekaligus -> tanggal itu TIDAK muncul di override manapun (konsisten dikecualikan di semua)',
    count($tanggal17), 0);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
