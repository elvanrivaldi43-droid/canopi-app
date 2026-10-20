<?php
// FILE: tests/absensi/test_ajuan_luar_kantor.php
// Jalankan: php tests/absensi/test_ajuan_luar_kantor.php
// Spec: docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md
require __DIR__ . '/../bootstrap.php';

use App\Models\LuarKota;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};
$boleh = fn (?string $alasan) => $alasan === null;

// ── bolehAjukan(level, sudahMasuk, sudahPulang, adaAktifAtauMenunggu) ──
$check('admin ops, sudah masuk -> boleh',        $boleh(LuarKota::bolehAjukan(2, true, false, false)), true);
$check('toko besi, sudah masuk -> boleh',        $boleh(LuarKota::bolehAjukan(7, true, false, false)), true);
foreach ([1, 3, 4, 5, 6] as $lv) {
    $check("level $lv -> ditolak (bukan level wajib GPS)", $boleh(LuarKota::bolehAjukan($lv, true, false, false)), false);
}
$check('belum absen masuk -> ditolak',           $boleh(LuarKota::bolehAjukan(2, false, false, false)), false);
$check('sudah absen pulang -> ditolak',          $boleh(LuarKota::bolehAjukan(2, true, true, false)), false);
$check('sudah ada aktif/menunggu -> ditolak',    $boleh(LuarKota::bolehAjukan(2, true, false, true)), false);

// ── alasanTolakProses(status, tanggal, pemilikId, aktorId, hariIni) ──
$h = '2026-10-20';
$check('menunggu, hari ini, orang lain -> boleh',  $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h)), true);
$check('ajuan sendiri -> ditolak',                 $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 5, $h)), false);
$check('ajuan kemarin -> ditolak (kedaluwarsa)',   $boleh(LuarKota::alasanTolakProses('menunggu', '2026-10-19', 5, 9, $h)), false);
foreach (['aktif', 'ditolak', 'selesai', 'dibatalkan'] as $st) {
    $check("status $st -> ditolak (sudah diproses)", $boleh(LuarKota::alasanTolakProses($st, $h, 5, 9, $h)), false);
}

// ── Regresi: hanya status `aktif` yang membebaskan GPS ──
// Koneksi mysql "malas": cukup untuk toSql(), tak pernah benar-benar connect ke DB.
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection(['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'x', 'username' => 'x', 'password' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$q = LuarKota::query()->aktifPadaTanggal('2026-10-20');
$check('scope aktifPadaTanggal memfilter status', str_contains($q->toSql(), '`status` = ?'), true);
$check('scope aktifPadaTanggal binding status = aktif', $q->getBindings()[0] ?? null, 'aktif');

// ── Label ──
$check('label menunggu', (new LuarKota(['status' => 'menunggu']))->labelStatus(), 'Menunggu');
$check('label ditolak',  (new LuarKota(['status' => 'ditolak']))->labelStatus(), 'Ditolak');

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
