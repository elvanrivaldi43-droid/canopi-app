<?php
// FILE: tests/penggajian/test_setting_gaji.php
// Jalankan: php tests/penggajian/test_setting_gaji.php
//
// Dua kebijakan gaji jadi saklar (keputusan Elvan 31 Ags 2026):
//   - Bonus KPI: DITUNDA ("belum bisa nyalakan sekarang ... mungkin Oktober atau Desember")
//   - Tabungan wajib Rp 100.000: karyawan belum diberi tahu, belum boleh memotong.
//     Barisnya TETAP tampil di slip (Rp 0), bukan disembunyikan — supaya transparan.
//
// Yang dikunci di sini adalah LOGIKA SAKLARNYA saja (fungsi murni, tanpa DB):
// nilai efektif yang dipakai slip = nominal kalau saklar nyala, 0 kalau mati.
// Kolom DB belum ada di production saat kode ini mendarat -> default WAJIB mati,
// jangan sampai fallback-nya malah menyalakan potongan yang belum disetujui.

require_once __DIR__ . '/../../app/Services/SettingGajiService.php';

use App\Services\SettingGajiService;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

// ── Bonus KPI: nominal per kelas hanya dibayar kalau saklar nyala.
check('KPI mati -> gold jadi 0',      SettingGajiService::nilaiBonusKpi(150000, false), 0);
check('KPI mati -> platinum jadi 0',  SettingGajiService::nilaiBonusKpi(300000, false), 0);
check('KPI nyala -> gold utuh',       SettingGajiService::nilaiBonusKpi(150000, true), 150000);
check('KPI nyala -> none tetap 0',    SettingGajiService::nilaiBonusKpi(0, true), 0);

// ── Tabungan wajib: nominal tetap 100.000, yang disetel hanya nyala/mati.
check('tabungan mati -> 0',    SettingGajiService::nilaiTabunganWajib(100000, false), 0);
check('tabungan nyala -> 100k', SettingGajiService::nilaiTabunganWajib(100000, true), 100000);

// ── Default saat kolom/tabel BELUM ADA di production: WAJIB mati.
// Kalau ini salah, potongan yang belum disetujui karyawan bisa jalan diam-diam.
check('setting kosong -> KPI mati',      SettingGajiService::aktifDari(null, 'bonus_kpi_aktif'), false);
check('setting kosong -> tabungan mati', SettingGajiService::aktifDari(null, 'tabungan_wajib_aktif'), false);
check('kolom absen -> mati',             SettingGajiService::aktifDari((object) [], 'bonus_kpi_aktif'), false);
check('nilai 0 -> mati',                 SettingGajiService::aktifDari((object) ['bonus_kpi_aktif' => 0], 'bonus_kpi_aktif'), false);
check('nilai 1 -> nyala',                SettingGajiService::aktifDari((object) ['bonus_kpi_aktif' => 1], 'bonus_kpi_aktif'), true);
check('nilai "1" (string) -> nyala',     SettingGajiService::aktifDari((object) ['bonus_kpi_aktif' => '1'], 'bonus_kpi_aktif'), true);

// ── Hitung ulang slip: hanya yang BELUM dibayar.
// Slip "dibayar" sudah menimbulkan efek samping nyata di prosesBayar (cicilan kasbon
// maju, saldo tabungan bertambah) -- menghapus & membuat ulang akan mengulang efek itu
// atau meninggalkan pembukuan tak cocok. Jadi dikunci: draft/menunggu_konfirmasi boleh,
// dibayar TIDAK PERNAH.
check('draft boleh dihitung ulang',              SettingGajiService::bolehHitungUlang('draft'), true);
check('menunggu_konfirmasi boleh',               SettingGajiService::bolehHitungUlang('menunggu_konfirmasi'), true);
check('DIBAYAR tidak boleh',                     SettingGajiService::bolehHitungUlang('dibayar'), false);
check('status tak dikenal -> tidak boleh (aman)', SettingGajiService::bolehHitungUlang('entah'), false);
check('status null -> tidak boleh',              SettingGajiService::bolehHitungUlang(null), false);

exit($fail ? 1 : 0);
