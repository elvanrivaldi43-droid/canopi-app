<?php
// FILE: tests/absensi/test_pulang_cepat.php
// Jalankan: php tests/absensi/test_pulang_cepat.php
// Spec docs/superpowers/specs/2026-10-26-izin-pulang-cepat-design.md (keputusan Elvan 26 Okt).
require __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AbsensiController as A;
use App\Models\Absensi;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// statusSaatPulang(status, menitKerja, jamPulang, jadwal, diizinkan)
$check('pulang tepat jadwal = normal',          A::statusSaatPulang('hadir', 480, '17:00:00', '17:00:00', false), 'hadir');
$check('pulang tepat jadwal (H:i) = normal',    A::statusSaatPulang('hadir', 480, '17:00:05', '17:00', false), 'hadir');
$check('1 menit sebelum jadwal tanpa izin',     A::statusSaatPulang('hadir', 470, '16:59:00', '17:00:00', false), 'setengah_hari');
$check('telat, pulang normal tetap telat',      A::statusSaatPulang('telat', 450, '17:10:00', '17:00:00', false), 'telat');
$check('izin, pulang 13:59 = setengah',         A::statusSaatPulang('hadir', 360, '13:59:00', '17:00:00', true), 'setengah_hari');
$check('izin, pulang 14:00 = penuh',            A::statusSaatPulang('hadir', 360, '14:00:00', '17:00:00', true), 'hadir');
$check('tanpa izin, pulang 15:00 = setengah',   A::statusSaatPulang('hadir', 420, '15:00:00', '17:00:00', false), 'setengah_hari');
$check('<225 menit walau izin = setengah',      A::statusSaatPulang('hadir', 200, '14:30:00', '17:00:00', true), 'setengah_hari');
$check('sudah setengah sejak masuk tetap',      A::statusSaatPulang('setengah_hari', 300, '15:00:00', '17:00:00', true), 'setengah_hari');
$check('masuk siang, pulang normal tetap 1/2',  A::statusSaatPulang('setengah_hari', 300, '17:00:00', '17:00:00', false), 'setengah_hari');

// dendaCheckpointBerlaku(jamPulang, batas)
$check('belum pulang -> denda berlaku',         A::dendaCheckpointBerlaku(null, '12:30'), true);
$check('pulang 11:00 -> progress 12:30 bebas',  A::dendaCheckpointBerlaku('11:00:00', '12:30'), false);
$check('pulang 12:45 -> progress 12:30 kena',   A::dendaCheckpointBerlaku('12:45:00', '12:30'), true);
$check('pulang 12:45 -> kembali 14:00 bebas',   A::dendaCheckpointBerlaku('12:45:00', '14:00'), false);

// bolehAjukanPulangCepat(sudahMasuk, sudahPulang, jamSekarang, jadwal, statusAjuan)
$check('ajukan normal lolos',                   Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', null), null);
$check('ajukan lagi setelah ditolak lolos',     Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'ditolak'), null);
$check('belum masuk ditolak',                   is_string(Absensi::bolehAjukanPulangCepat(false, false, '10:00', '17:00:00', null)), true);
$check('sudah pulang ditolak',                  is_string(Absensi::bolehAjukanPulangCepat(true, true, '10:00', '17:00:00', null)), true);
$check('sudah lewat jadwal ditolak',            is_string(Absensi::bolehAjukanPulangCepat(true, false, '17:00', '17:00:00', null)), true);
$check('masih menunggu ditolak',                is_string(Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'menunggu')), true);
$check('sudah disetujui ditolak',               is_string(Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'disetujui')), true);

// alasanTolakProsesPulangCepat(status, tanggal, hariIni, sudahPulang, pemilikId, aktorId, pemilikLevel, aktorLevel)
$h = '2026-10-26';
$p = fn($pemilikLv, $aktorLv, $st = 'menunggu', $tgl = '2026-10-26', $pulang = false, $pemilikId = 10, $aktorId = 1)
    => Absensi::alasanTolakProsesPulangCepat($st, $tgl, $h, $pulang, $pemilikId, $aktorId, $pemilikLv, $aktorLv);
$check('owner utk teknisi lolos',               $p(5, 1), null);
$check('owner utk admin lolos',                 $p(2, 1), null);
$check('mandor utk teknisi lolos',              $p(5, 3), null);
$check('mandor utk driver lolos',               $p(6, 3), null);
$check('mandor utk admin ditolak',              is_string($p(2, 3)), true);
$check('mandor utk marketing ditolak',          is_string($p(4, 3)), true);
$check('mandor utk toko ditolak',               is_string($p(7, 3)), true);
$check('mandor utk mandor lain ditolak',        is_string($p(3, 3)), true);
$check('admin tak bisa proses',                 is_string($p(5, 2)), true);
$check('diri sendiri ditolak',                  is_string($p(1, 1, 'menunggu', $h, false, 1, 1)), true);
$check('sudah pulang ditolak',                  is_string($p(5, 1, 'menunggu', $h, true)), true);
$check('kemarin kedaluwarsa',                   is_string($p(5, 1, 'menunggu', '2026-10-25')), true);
$check('sudah diproses ditolak',                is_string($p(5, 1, 'disetujui')), true);
$check('tanpa ajuan ditolak',                   is_string($p(5, 1, null)), true);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
