<?php
// FILE: tests/jadwal-libur/test_libur_service.php
// Jalankan: php tests/jadwal-libur/test_libur_service.php
require __DIR__ . '/../../vendor/autoload.php';

use App\Services\LiburService;
use Carbon\Carbon;

$svc = new LiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── cocokLiburPada ──────────────────────────────────────────
// 11 Agustus 2026 dipastikan hari Selasa (Carbon::dayOfWeek: 0=Minggu..6=Sabtu, Selasa=2)
$selasa = Carbon::create(2026, 8, 11);
$check('tanggal contoh memang Selasa (dayOfWeek=2)', $selasa->dayOfWeek, 2);

$check('default cocok hari, tanpa override -> true',
    $svc->cocokLiburPada(2, [], $selasa), true);

$check('default beda hari, tanpa override -> false',
    $svc->cocokLiburPada(6, [], $selasa), false);

$check('tanpa default (null), tanpa override -> selalu false',
    $svc->cocokLiburPada(null, [], $selasa), false);

$check('default cocok TAPI ada override batal di tanggal itu -> false (override menang)',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], $selasa), false);

$check('default TIDAK cocok TAPI ada override tambah di tanggal itu -> true',
    $svc->cocokLiburPada(6, [['tanggal' => '2026-08-11', 'jenis' => 'tambah']], $selasa), true);

$check('override ada tapi beda tanggal -> fallback ke default (cocok) -> true',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-12', 'jenis' => 'batal']], $selasa), true);

// ── hitungHariKerjaPada ──────────────────────────────────────
// Agustus 2026 = 31 hari.
$jumlahSelasa = 0;
for ($i = 1; $i <= 31; $i++) {
    if (Carbon::create(2026, 8, $i)->dayOfWeek === 2) $jumlahSelasa++;
}

$check('tanpa default -> semua 31 hari kehitung hari kerja',
    $svc->hitungHariKerjaPada(null, [], 8, 2026), 31);

$check('default Selasa -> 31 dikurangi jumlah Selasa di Agustus 2026',
    $svc->hitungHariKerjaPada(2, [], 8, 2026), 31 - $jumlahSelasa);

$check('default Selasa + 1 override batal (1 Selasa dibatalkan) -> nambah 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], 8, 2026),
    31 - $jumlahSelasa + 1);

$check('tanpa default + 1 override tambah (nambah 1 libur) -> ngurang 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(null, [['tanggal' => '2026-08-05', 'jenis' => 'tambah']], 8, 2026),
    31 - 1);

$check('Februari 2026 (28 hari, bukan kabisat), tanpa default -> 28 hari kerja',
    $svc->hitungHariKerjaPada(null, [], 2, 2026), 28);

$check('hitungHariKerjaPada dengan cap $sampaiHari=15, tanpa default -> 15 hari kehitung (bukan 31)',
    $svc->hitungHariKerjaPada(null, [], 8, 2026, 15), 15);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
