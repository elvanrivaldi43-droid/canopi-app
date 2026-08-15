<?php
// FILE: tests/kerja-hari-libur/test_koreksi_kerja_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_koreksi_kerja_hari_libur.php
//
// Tes ANGKA jalur KOREKSI absen (murni, tanpa database):
//   - koreksi baris kerja hari libur memakai SNAPSHOT tarif (gaji harian & uang makan),
//     bukan tarif karyawan saat dikoreksi -> histori tidak berubah kalau tarif naik/turun
//   - koreksi manual di tanggal libur hanya ditandai & diupah kalau audit otorisasi
//     dibuat aktor level 1/3 DAN statusnya benar-benar bekerja
//
// Formula gaji/uang makan/potongan TIDAK diubah di sini — yang berubah cuma SUMBER tarifnya.
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Snapshot saat karyawan diaktifkan masuk hari libur
const SNAP_GAJI = 200000.0;
const SNAP_UM   = 25000.0;
// Tarif karyawan SEKARANG (sudah naik setelah hari itu lewat)
const NOW_GAJI  = 300000.0;
const NOW_UM    = 40000.0;

// ── Sumber tarif untuk koreksi ──────────────────────────────
$check('kerja hari libur -> tarif dari SNAPSHOT (bukan tarif sekarang)',
    $svc->tarifKoreksi(true, SNAP_GAJI, SNAP_UM, NOW_GAJI, NOW_UM),
    ['gaji_harian' => 200000.0, 'uang_makan' => 25000.0]);

$check('hari kerja biasa -> tarif karyawan sekarang (perilaku lama tidak berubah)',
    $svc->tarifKoreksi(false, SNAP_GAJI, SNAP_UM, NOW_GAJI, NOW_UM),
    ['gaji_harian' => 300000.0, 'uang_makan' => 40000.0]);

$check('kerja hari libur tapi baris otorisasi hilang -> fallback tarif sekarang (jangan bayar 0)',
    $svc->tarifKoreksi(true, null, null, NOW_GAJI, NOW_UM),
    ['gaji_harian' => 300000.0, 'uang_makan' => 40000.0]);

$check('snapshot bertipe string dari DB dinormalkan jadi float',
    $svc->tarifKoreksi(true, '200000.00', '25000.00', NOW_GAJI, NOW_UM),
    ['gaji_harian' => 200000.0, 'uang_makan' => 25000.0]);

// ── Nominal hasil koreksi (formula lama, tarif snapshot) ────
$check('koreksi status hadir: gaji & UM & upah libur SEMUANYA dari snapshot',
    $svc->nominalKoreksi('hadir', SNAP_GAJI, SNAP_UM, 0.0),
    ['gaji_hari_ini' => 200000.0, 'uang_makan_hari_ini' => 25000.0, 'upah_hari_libur' => 200000.0]);

$check('koreksi status telat: potongan tetap dikurangi sekali dari gaji (aturan lama)',
    $svc->nominalKoreksi('telat', SNAP_GAJI, SNAP_UM, 20000.0),
    ['gaji_hari_ini' => 180000.0, 'uang_makan_hari_ini' => 25000.0, 'upah_hari_libur' => 200000.0]);

$check('koreksi setengah hari: 0.5x dari snapshot, bukan 0.5x tarif sekarang',
    $svc->nominalKoreksi('setengah_hari', SNAP_GAJI, SNAP_UM, 0.0),
    ['gaji_hari_ini' => 100000.0, 'uang_makan_hari_ini' => 12500.0, 'upah_hari_libur' => 100000.0]);

$check('koreksi jadi alpha: gaji 0, UM 0, upah hari libur 0',
    $svc->nominalKoreksi('alpha', SNAP_GAJI, SNAP_UM, 0.0),
    ['gaji_hari_ini' => 0.0, 'uang_makan_hari_ini' => 0.0, 'upah_hari_libur' => 0.0]);

$check('koreksi jadi izin: gaji 0 tapi UM tetap (aturan lama), upah hari libur 0',
    $svc->nominalKoreksi('izin', SNAP_GAJI, SNAP_UM, 0.0),
    ['gaji_hari_ini' => 0.0, 'uang_makan_hari_ini' => 25000.0, 'upah_hari_libur' => 0.0]);

$check('potongan lebih besar dari gaji -> gaji tidak minus (aturan lama max(0,..))',
    $svc->nominalKoreksi('hadir', SNAP_GAJI, SNAP_UM, 500000.0)['gaji_hari_ini'], 0.0);

$check('status tidak dikenal -> null (nilai lama dipertahankan controller)',
    $svc->nominalKoreksi('entah', SNAP_GAJI, SNAP_UM, 0.0), null);

// ── Koreksi manual di tanggal libur: kapan ditandai/diupah ──
// Argumen: (levelAktor, isLibur, status, adaOtorisasi)
$tandai = fn(...$a) => $svc->tandaiKerjaLiburManual(...$a);

$check('Owner catat manual hadir di tanggal libur -> ditandai kerja hari libur',
    $tandai(1, true, 'hadir', false), true);
$check('Mandor catat manual telat di tanggal libur -> ditandai',
    $tandai(3, true, 'telat', false), true);
$check('Mandor catat manual setengah hari di tanggal libur -> ditandai',
    $tandai(3, true, 'setengah_hari', false), true);

$check('status alpha di tanggal libur TANPA otorisasi -> TIDAK ditandai/diupah',
    $tandai(1, true, 'alpha', false), false);
$check('status izin di tanggal libur TANPA otorisasi -> TIDAK ditandai/diupah',
    $tandai(1, true, 'izin', false), false);
$check('status sakit di tanggal libur TANPA otorisasi -> TIDAK ditandai/diupah',
    $tandai(1, true, 'sakit', false), false);

$check('aktor level 2 (Admin) tidak bisa menandai kerja hari libur',
    $tandai(2, true, 'hadir', false), false);
$check('aktor level 5 (Teknisi) tidak bisa menandai kerja hari libur',
    $tandai(5, true, 'hadir', false), false);
$check('aktor level string "3" tetap boleh (kolom level tanpa cast)',
    $tandai('3', true, 'hadir', false), true);

$check('tanggal BUKAN libur -> tidak pernah ditandai kerja hari libur',
    $tandai(1, false, 'hadir', false), false);

// Audit yang SUDAH ada tidak boleh terhapus oleh koreksi manual berikutnya
// (mis. cron 13:00 sudah menandai alpha untuk karyawan yang diaktifkan tapi tidak masuk).
$check('otorisasi sudah ada + status alpha -> penanda audit dipertahankan (upah tetap 0)',
    $tandai(1, true, 'alpha', true), true);
$check('otorisasi sudah ada -> upah untuk status alpha tetap 0',
    $svc->upahHariLibur(SNAP_GAJI, 'alpha'), 0.0);
// Jadwal libur karyawan bisa diubah Owner SETELAH hari itu lewat (hari_libur_default /
// tukar libur). Fakta "hari itu dulu diotorisasi" tidak boleh ikut hilang karenanya.
$check('otorisasi ada tapi jadwal libur sudah berubah -> penanda tetap dipertahankan',
    $tandai(1, false, 'hadir', true), true);

// ── Audit baru hanya dibuat untuk kondisi yang sah ──────────
$buat = fn(...$a) => $svc->buatAuditManual(...$a);
$check('audit BARU dibuat saat Owner catat hadir di tanggal libur',
    $buat(1, true, 'hadir', false), true);
$check('audit BARU tidak dibuat kalau otorisasinya sudah ada',
    $buat(1, true, 'hadir', true), false);
$check('audit BARU tidak dibuat untuk status alpha',
    $buat(1, true, 'alpha', false), false);
$check('audit BARU tidak dibuat oleh aktor tanpa akses',
    $buat(4, true, 'hadir', false), false);
$check('audit BARU tidak dibuat di tanggal yang bukan libur',
    $buat(1, false, 'hadir', false), false);

// ═══════════════════════════════════════════════════════════
// KOREKSI MANUAL TIDAK BOLEH MENIMPA BARIS ABSENSI YANG SUDAH ADA
// ═══════════════════════════════════════════════════════════
// `koreksi-baru/{userId}` dipakai untuk tanggal yang BELUM punya baris absensi.
// Di UI, tombol Koreksi otomatis mengarah ke `/{id}/koreksi` kalau barisnya sudah
// ada — tapi POST bisa dikirim langsung (form basi, tab lama, atau id absen kosong
// karena halaman belum di-refresh). Kalau jalur manual tetap menulis, satu POST bisa
// MENGHAPUS jam masuk/pulang, GPS, foto, penanda kerja hari libur, dan potongan yang
// sudah tercatat — diganti angka hasil hitung ulang, tanpa jejak nilai lamanya.
use App\Http\Controllers\AbsensiController;

$check('AbsensiController::alasanTolakKoreksiManual() ada',
    method_exists(AbsensiController::class, 'alasanTolakKoreksiManual'), true);

if (method_exists(AbsensiController::class, 'alasanTolakKoreksiManual')) {
    $check('tanggal belum punya baris absensi -> boleh dicatat manual (null)',
        AbsensiController::alasanTolakKoreksiManual(false), null);
    $check('sudah ada baris absensi -> DITOLAK (bukan ditimpa)',
        is_string(AbsensiController::alasanTolakKoreksiManual(true)), true);
    $check('pesan tolak mengarahkan pakai tombol Koreksi',
        str_contains(strtolower((string) AbsensiController::alasanTolakKoreksiManual(true)), 'koreksi'), true);
}

// ── Jalur tulisnya benar-benar dijaga (bukan cuma helper yang menganggur) ──
$srcCtl = file_get_contents(__DIR__ . '/../../app/Http/Controllers/AbsensiController.php');
$ambilBody = function (string $src, string $nama): string {
    $p = strpos($src, "function $nama(");
    if ($p === false) return '';
    $buka = strpos($src, '{', $p);
    $depth = 0;
    for ($i = $buka; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) return substr($src, $buka, $i - $buka + 1); }
    }
    return '';
};
$bodyManual = $ambilBody($srcCtl, 'koreksiManual');
$check('body koreksiManual() terbaca', $bodyManual !== '', true);
$check('koreksiManual() memanggil penjaga alasanTolakKoreksiManual',
    str_contains($bodyManual, 'alasanTolakKoreksiManual'), true);
$check('koreksiManual() TIDAK lagi memakai updateOrCreate (itu yang menimpa baris lama)',
    str_contains($bodyManual, 'updateOrCreate'), false);
$check('penjaga dijalankan SEBELUM baris absensi ditulis',
    strpos($bodyManual, 'alasanTolakKoreksiManual') < strpos($bodyManual . 'Absensi::create', 'Absensi::create'), true);

// ═══════════════════════════════════════════════════════════
// KOREKSI MANUAL DI TANGGAL LIBUR: TARIF 0 DITOLAK DI DEPAN
// ═══════════════════════════════════════════════════════════
// Tombol Aktivasi sudah menolak karyawan yang `gaji_harian`-nya kosong. Koreksi manual
// adalah pintu KEDUA yang bisa membuat baris otorisasi + absensi kerja hari libur, dan
// pintu itu tidak ikut menjalankan alasanTolakAktivasi() — jadi tanpa penjaga ini,
// karyawan bisa dicatat masuk di hari liburnya dan dibayar Rp 0 lewat jalur manual.
// Aturannya dipakai bersama dari SATU helper murni, bukan disalin ulang di controller.
$check('helper alasanTolakTarif() dipakai bersama (bukan aturan kembar)',
    method_exists($svc, 'alasanTolakTarif'), true);

if (method_exists($svc, 'alasanTolakTarif')) {
    $check('tarif snapshot/karyawan 0 -> ditolak',   is_string($svc->alasanTolakTarif(0)), true);
    $check('tarif snapshot terisi -> boleh (null)',  $svc->alasanTolakTarif(SNAP_GAJI), null);
}

$check('koreksiManual() memanggil alasanTolakTarif()',
    str_contains($bodyManual, 'alasanTolakTarif('), true);
// Posisinya dicek eksplisit != false: kalau penjaganya belum ada, strpos() balik false
// yang dianggap 0 oleh perbandingan "<" -> asersi urutan lulus padahal penjaganya nihil.
$posTarif = strpos($bodyManual, 'alasanTolakTarif(');
$check('penjaga tarif dijalankan SEBELUM baris otorisasi dibuat',
    $posTarif !== false && $posTarif < strpos($bodyManual, 'KerjaHariLibur::firstOrCreate'), true);
$check('penjaga tarif dijalankan SEBELUM baris absensi ditulis',
    $posTarif !== false && $posTarif < strpos($bodyManual, 'Absensi::create'), true);
// Penjaganya cuma untuk hari libur + status yang benar-benar bekerja. Hari kerja biasa
// dan status alpha/izin tidak membayar upah hari libur, jadi tidak boleh ikut diblokir
// (kalau ikut, Owner tidak bisa lagi mencatat alpha untuk karyawan bergaji harian kosong).
$check('penjaga tarif dibatasi ke tanggal libur',
    str_contains($bodyManual, '$isLibur &&'), true);
$check('penjaga tarif dibatasi ke status yang benar-benar bekerja (STATUS_BEKERJA)',
    str_contains($bodyManual, 'STATUS_BEKERJA'), true);

// Jalur koreksi biasa (`/{id}/koreksi`) tetap boleh mengubah baris yang sudah ada —
// itu memang tugasnya, dan sekarang Owner-only.
$bodyKoreksi = $ambilBody($srcCtl, 'koreksi');
$check('koreksi() biasa tetap mengubah baris existing (tidak ikut diblokir)',
    str_contains($bodyKoreksi, '$absen->update('), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
