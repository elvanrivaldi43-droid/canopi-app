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
// Keputusan Elvan 21 Okt: Marketing (4) juga boleh mengajukan (lapor siangnya tetap bebas lokasi)
$check('marketing, sudah masuk -> boleh',        $boleh(LuarKota::bolehAjukan(4, true, false, false)), true);
foreach ([1, 3, 5, 6] as $lv) {
    $check("level $lv -> ditolak (bukan level yang boleh mengajukan)", $boleh(LuarKota::bolehAjukan($lv, true, false, false)), false);
}
$check('marketing tetap BEBAS GPS siang (tidak ikut wajib)', \App\Http\Controllers\AbsensiController::gpsSiangWajib(4), false);
$check('belum absen masuk -> ditolak',           $boleh(LuarKota::bolehAjukan(2, false, false, false)), false);
$check('sudah absen pulang -> ditolak',          $boleh(LuarKota::bolehAjukan(2, true, true, false)), false);
$check('sudah ada aktif/menunggu -> ditolak',    $boleh(LuarKota::bolehAjukan(2, true, false, true)), false);

// ── alasanTolakProses(status, tanggal, pemilikId, aktorId, hariIni, pemilikLevel, aktorLevel) ──
$h = '2026-10-20';
$check('menunggu, hari ini, owner proses admin -> boleh', $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 2, 1)), true);
$check('ajuan sendiri -> ditolak',                 $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 5, $h, 2, 2)), false);
$check('ajuan kemarin -> ditolak (kedaluwarsa)',   $boleh(LuarKota::alasanTolakProses('menunggu', '2026-10-19', 5, 9, $h, 2, 1)), false);
foreach (['aktif', 'ditolak', 'selesai', 'dibatalkan'] as $st) {
    $check("status $st -> ditolak (sudah diproses)", $boleh(LuarKota::alasanTolakProses($st, $h, 5, 9, $h, 2, 1)), false);
}
// Keputusan Elvan 21 Okt: ajuan Marketing (4) & Toko Besi (7) -> Owner ATAU Admin Ops; ajuan Admin Ops (2) -> Owner saja
$check('admin ops proses ajuan marketing -> boleh',  $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 4, 2)), true);
$check('owner proses ajuan marketing -> boleh',      $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 4, 1)), true);
$check('admin ops proses ajuan admin lain -> ditolak', $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 2, 2)), false);
$check('admin ops proses ajuan toko besi -> boleh',    $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 7, 2)), true);
$check('supervisor proses ajuan toko besi -> ditolak', $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 7, 3)), false);
$check('supervisor proses ajuan marketing -> ditolak', $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h, 4, 3)), false);

// ── Form Luar Kota manual tak boleh jadi jalan pintas (review 20 Okt) ──
// alasanTolakBuatManual(userIds, levelTarget, aktorId, aktorLevel)
// Keputusan Elvan 21 Okt: Luar Kota utk Admin Ops (2) & Toko Besi (7) HANYA Owner (cegah admin saling aktifkan).
$check('owner aktifkan dirinya sendiri -> boleh',        $boleh(LuarKota::alasanTolakBuatManual([3, 1], [3, 1], 1, 1)), true);
$check('owner aktifkan admin & toko besi -> boleh',      $boleh(LuarKota::alasanTolakBuatManual([2, 7], [2, 7], 1, 1)), true);
$check('admin aktifkan teknisi/driver -> boleh',         $boleh(LuarKota::alasanTolakBuatManual([5, 6], [5, 6], 2, 2)), true);
$check('admin centang dirinya sendiri -> ditolak',       $boleh(LuarKota::alasanTolakBuatManual([5, 2], [5, 2], 2, 2)), false);
$check('admin centang dirinya (id string) -> ditolak',   $boleh(LuarKota::alasanTolakBuatManual(['5', '2'], [5, 2], 2, 2)), false);
$check('admin aktifkan admin lain -> ditolak',           $boleh(LuarKota::alasanTolakBuatManual([8], [2], 2, 2)), false);
$check('supervisor aktifkan admin -> ditolak',           $boleh(LuarKota::alasanTolakBuatManual([8], [2], 3, 3)), false);
$check('supervisor aktifkan toko besi (str) -> ditolak', $boleh(LuarKota::alasanTolakBuatManual([9], ['7'], 3, 3)), false);
$check('edit/selesai/batal baris aktif -> boleh',      LuarKota::bisaDiubahManual('aktif'), true);
foreach (['menunggu', 'ditolak', 'selesai', 'dibatalkan'] as $st) {
    $check("edit/selesai/batal baris $st -> ditolak", LuarKota::bisaDiubahManual($st), false);
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
