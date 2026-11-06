<?php
// FILE: tests/absensi/test_status_tidak_hadir.php
// Jalankan: php tests/absensi/test_status_tidak_hadir.php
// Status absensi 'tidak_hadir' (Penugasan Harian).
require __DIR__.'/../bootstrap.php';

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$stat = (new \App\Services\KerjaHariLiburService())->statistikKehadiran([
    ['status' => 'hadir'], ['status' => 'tidak_hadir'], ['status' => 'alpha'],
]);
$check('hadir = 1', $stat['hadir'], 1);
$check('alpha = 1', $stat['alpha'], 1);
$check('izin = 0 (tidak_hadir bukan izin)', $stat['izin'], 0);
$check('tidak_hadir = 1', $stat['tidak_hadir'], 1);

$a = new \App\Models\Absensi(['status' => 'tidak_hadir']);
$check('label', str_contains($a->statusLabel(), 'Tidak Hadir'), true);
$check('warna bukan default', $a->statusColor() !== '#64748B', true);

// Mirror syarat if di public/cron-alpha.php
$lewatiAlpha = fn(bool $sudahMasuk, bool $adaIzin, bool $sudahAlpha, bool $lewatiLibur, bool $tidakHadir): bool
    => !(!$sudahMasuk && !$adaIzin && !$sudahAlpha && !$lewatiLibur && !$tidakHadir);
$check('tidak_hadir -> tidak di-alpha', $lewatiAlpha(false, false, false, false, true), true);
$check('mangkir biasa -> di-alpha', $lewatiAlpha(false, false, false, false, false), false);

// Pastikan cron-alpha benar-benar memuat syaratnya
$check('cron-alpha memeriksa tidak_hadir',
    str_contains(file_get_contents(__DIR__.'/../../public/cron-alpha.php'), '!$lewati && !$tidakHadir'), true);

exit($fail ? 1 : 0);
