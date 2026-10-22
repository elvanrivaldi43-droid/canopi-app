<?php
// FILE: tests/absensi/test_gps_wajib_siang.php
// Jalankan: php tests/absensi/test_gps_wajib_siang.php
// Keputusan Elvan 20 Okt 2026: lapor progress / kembali kerja (siang) wajib di
// titik lokasi HANYA utk Admin Operasional (2) & Admin Toko Besi (7). Level lain
// bebas, lokasinya cukup dicatat. Absen masuk/pulang TIDAK diubah.
require __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AbsensiController;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$wajib = [1 => false, 2 => true, 3 => false, 4 => false, 5 => false, 6 => false, 7 => true];
foreach ($wajib as $level => $exp) {
    $check("level $level wajib GPS siang", AbsensiController::gpsSiangWajib($level), $exp);
}

// Keputusan Elvan 22 Okt: Marketing (4) absen di titik WORKSHOP, sama dengan teknisi.
foreach ([3 => true, 4 => true, 5 => true, 6 => true, 2 => false, 7 => false] as $level => $exp) {
    $check("level $level pakai titik workshop", in_array($level, AbsensiController::LEVEL_WORKSHOP, true), $exp);
}

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
