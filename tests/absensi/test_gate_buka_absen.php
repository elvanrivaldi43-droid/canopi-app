<?php
// FILE: tests/absensi/test_gate_buka_absen.php
// Jalankan: php tests/absensi/test_gate_buka_absen.php
// Mirror boolean gate di AbsensiController::formMasuk() — JAM_BUKA_ABSEN
// dikecualikan kalau user lagi mode luar kota.

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$diblokir = function (string $jamSekarang, string $jamBuka, bool $sedangLuarKota): bool {
    return $jamSekarang < $jamBuka && !$sedangLuarKota;
};

$check('06:00, bukan luar kota -> diblokir', $diblokir('06:00', '06:30', false), true);
$check('06:00, luar kota aktif -> TIDAK diblokir', $diblokir('06:00', '06:30', true), false);
$check('04:00, luar kota aktif -> TIDAK diblokir', $diblokir('04:00', '06:30', true), false);
$check('07:00, bukan luar kota -> TIDAK diblokir (sudah lewat jam buka)', $diblokir('07:00', '06:30', false), false);
$check('07:00, luar kota aktif -> TIDAK diblokir', $diblokir('07:00', '06:30', true), false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
