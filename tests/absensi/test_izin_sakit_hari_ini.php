<?php
// FILE: tests/absensi/test_izin_sakit_hari_ini.php
// Jalankan: php tests/absensi/test_izin_sakit_hari_ini.php
// Keputusan Elvan 24 Okt 2026: izin SAKIT boleh untuk hari ini (orang tak bisa
// merencanakan sakit). Izin/cuti/dinas luar tetap minimal H+1. Sakit hari ini
// ditolak kalau sudah absen masuk — supaya hari yang sudah dikerjakan tidak
// diam-diam jadi sakit tak dibayar; itu jalur Koreksi Owner.
require __DIR__ . '/../bootstrap.php';

use App\Models\IzinAbsen;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$hari = '2026-10-24';
$check('min sakit = hari ini', IzinAbsen::tanggalMinimal('sakit', $hari), '2026-10-24');
$check('min izin = besok', IzinAbsen::tanggalMinimal('izin', $hari), '2026-10-25');
$check('min cuti = besok', IzinAbsen::tanggalMinimal('cuti', $hari), '2026-10-25');

$t = fn($tipe, $tgl, $masuk = false) => IzinAbsen::alasanTolakTanggal($tipe, $tgl, $hari, $masuk);
$check('sakit hari ini lolos', $t('sakit', '2026-10-24'), null);
$check('sakit besok lolos', $t('sakit', '2026-10-25'), null);
$check('sakit kemarin ditolak', is_string($t('sakit', '2026-10-23')), true);
$check('sakit hari ini sudah masuk ditolak', is_string($t('sakit', '2026-10-24', true)), true);
$check('sakit besok walau hari ini sudah masuk lolos', $t('sakit', '2026-10-25', true), null);
$check('izin hari ini ditolak', is_string($t('izin', '2026-10-24')), true);
$check('izin besok lolos', $t('izin', '2026-10-25'), null);
$check('cuti hari ini ditolak', is_string($t('cuti', '2026-10-24')), true);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
