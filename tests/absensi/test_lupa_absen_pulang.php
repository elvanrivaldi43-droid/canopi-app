<?php
// FILE: tests/absensi/test_lupa_absen_pulang.php
// Jalankan: php tests/absensi/test_lupa_absen_pulang.php
//
// KASUS NYATA gajian 31 Ags 2026 (ditemukan dari data production):
// Sahrul hidayat absen masuk 07:35, absen PULANG 20:00:25, kerja 12+ jam —
// tapi statusnya ALPHA dan gaji hari itu Rp 0, malah MINUS 40.000.
// Penyebabnya cron jam 20:00: "sudah absen masuk tapi belum absen pulang -> ALPHA,
// gaji dinolkan". Lupa menekan tombol pulang dihukum seperti tidak masuk sama sekali,
// DAN alpha menghanguskan bonus KPI sebulan.
//
// Keputusan Elvan 31 Ags 2026: lupa absen pulang = DIBAYAR SEPARUH (setengah_hari),
// bukan alpha. Status `setengah_hari` sudah ada di sistem & sudah dihitung separuh.
//
// Bug kedua yang ikut ditutup: denda checkpoint (lupa lapor progress / kembali kerja)
// tetap jalan untuk hari yang statusnya BUKAN hari kerja -> gaji 0 dikurangi denda
// jadi MINUS, dan minus itu menggerus gaji hari-hari lain (gaji pokok harian =
// jumlah gaji_hari_ini sebulan).

require_once __DIR__ . '/../../app/Services/KerjaHariLiburService.php';

use App\Services\KerjaHariLiburService;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$svc = new KerjaHariLiburService();

// ── Status untuk "sudah absen masuk, belum absen pulang" saat cron jam 20:00.
check('sudah masuk & belum pulang -> setengah_hari (bukan alpha)',
    $svc->statusLupaPulang(true, false), 'setengah_hari');
check('sudah masuk & SUDAH pulang -> null (jangan diutak-atik)',
    $svc->statusLupaPulang(true, true), null);
check('belum masuk sama sekali -> null (itu urusan cabang alpha yang lain)',
    $svc->statusLupaPulang(false, false), null);

// ── Gaji hari itu: separuh, dikurangi potongan telat, TIDAK BOLEH minus.
check('gaji separuh: 120.000 -> 60.000',
    $svc->gajiLupaPulang(120000, 0), 60000.0);
check('separuh dikurangi denda: 120.000/2 - 10.000 = 50.000',
    $svc->gajiLupaPulang(120000, 10000), 50000.0);
check('denda melebihi separuh -> MENTOK 0, tidak minus',
    $svc->gajiLupaPulang(60000, 41666.67), 0.0);
check('gaji harian 0 (pegawai bulanan) -> 0, bukan minus',
    $svc->gajiLupaPulang(0, 23000), 0.0);

// ── Denda checkpoint hanya untuk hari yang BENAR-BENAR hari kerja.
// Inilah yang bikin gaji minus: orang sudah di-alpha, lalu buka aplikasi,
// dendanya tetap dipotong dari gaji yang sudah 0.
check('status hadir -> boleh didenda',        $svc->bolehDendaCheckpoint('hadir'), true);
check('status telat -> boleh didenda',        $svc->bolehDendaCheckpoint('telat'), true);
check('status setengah_hari -> boleh didenda', $svc->bolehDendaCheckpoint('setengah_hari'), true);
check('status ALPHA -> TIDAK boleh',          $svc->bolehDendaCheckpoint('alpha'), false);
check('status izin -> TIDAK boleh',           $svc->bolehDendaCheckpoint('izin'), false);
check('status sakit -> TIDAK boleh',          $svc->bolehDendaCheckpoint('sakit'), false);
check('status cuti -> TIDAK boleh',           $svc->bolehDendaCheckpoint('cuti'), false);
check('status dinas_luar -> TIDAK boleh',     $svc->bolehDendaCheckpoint('dinas_luar'), false);
check('status kosong/baru -> boleh (belum ditandai apa-apa)', $svc->bolehDendaCheckpoint(null), true);

// ── Penjaga terakhir: berapa pun dendanya, gaji sehari tak boleh jatuh di bawah nol.
check('kurangi denda: 100.000 - 5.000 = 95.000', $svc->kurangiDenda(100000, 5000), 95000.0);
check('denda lebih besar dari gaji -> 0',        $svc->kurangiDenda(20000, 41666.67), 0.0);
check('gaji sudah 0 -> tetap 0',                 $svc->kurangiDenda(0, 23000), 0.0);
check('gaji sudah minus (data lama) -> dinormalkan ke 0', $svc->kurangiDenda(-40000, 0), 0.0);

exit($fail ? 1 : 0);
