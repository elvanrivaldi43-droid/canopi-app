<?php
// FILE: tests/kerja-hari-libur/test_statistik_kerja_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_statistik_kerja_hari_libur.php
//
// Aturan hitung untuk slip, rekap bulanan, halaman absensi, dan profil:
//   - "hari kerja libur" hanya dihitung dari status yang benar-benar bekerja
//     (hadir/telat/setengah_hari) — alpha/izin berpenanda TIDAK dihitung
//   - kehadiran di hari yang DIAKTIFKAN ikut statistik hadir/alpha/telat, karena
//     aktivasi membatalkan libur: tanggalnya jadi hari kerja biasa dan ikut masuk
//     penyebut hari kerja (LiburService::expandAktivasi). Lihat juga
//     tests/kerja-hari-libur/test_aktivasi_hari_kerja.php.
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// 1 bulan contoh: 3 hari kerja reguler + 3 hari libur yang disentuh
$rows = [
    ['status' => 'hadir',         'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'telat',         'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'alpha',         'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'izin',          'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    // hari libur: masuk beneran
    ['status' => 'hadir',         'kerja_hari_libur' => true,  'upah_hari_libur' => 200000],
    ['status' => 'setengah_hari', 'kerja_hari_libur' => true,  'upah_hari_libur' => 100000],
    // hari libur: diaktifkan tapi TIDAK masuk -> di-alpha cron, upah 0
    ['status' => 'alpha',         'kerja_hari_libur' => true,  'upah_hari_libur' => 0],
];

// ── Hari kerja libur yang dihitung/dibayar ──────────────────
$check('hari kerja libur = 2 (hadir + setengah hari), alpha berpenanda tidak dihitung',
    $svc->hanyaKerjaLiburBekerja($rows)->count(), 2);

$check('upah hari libur = 300.000 (alpha berpenanda menyumbang 0)',
    (float) $svc->hanyaKerjaLiburBekerja($rows)->sum('upah_hari_libur'), 300000.0);

$check('baris berpenanda (semua status) tetap 3 — dipakai untuk menandai upah ekstra',
    $svc->hanyaKerjaLibur($rows)->count(), 3);

$check('izin berpenanda tidak dihitung sebagai hari kerja libur',
    $svc->hanyaKerjaLiburBekerja([
        ['status' => 'izin', 'kerja_hari_libur' => true, 'upah_hari_libur' => 0],
    ])->count(), 0);

// ── Statistik ringkas (halaman absensi, rekap bulanan, profil) ──
$stat = $svc->statistikKehadiran($rows);

// Aktivasi = hari kerja biasa, jadi barisnya IKUT dihitung.
// 4 hadir = hadir + telat + (hadir aktivasi) + (setengah_hari aktivasi).
$check('hadir = 4 — kehadiran di hari aktivasi ikut dihitung',
    $stat['hadir'], 4);
// Yang diaktifkan lalu MANGKIR harus kelihatan alpha-nya — dulu tersembunyi.
$check('alpha = 2 — alpha di hari aktivasi ikut terhitung',
    $stat['alpha'], 2);
$check('telat = 1',                         $stat['telat'], 1);
$check('izin = 1',                          $stat['izin'], 1);
$check('kerja libur = 2 (yang dibayar)',    $stat['kerja_libur'], 2);
$check('upah libur = 300.000',              $stat['upah_libur'], 300000.0);

// Baris lama (kolom kerja_hari_libur belum ada) dianggap reguler — jangan sampai
// data sebelum fitur ini tiba-tiba hilang dari statistik.
$lama = [['status' => 'hadir'], ['status' => 'alpha']];
$check('baris lama tanpa kolom penanda tetap dihitung reguler',
    $svc->statistikKehadiran($lama)['hadir'], 1);
$check('baris lama tidak dihitung sebagai kerja hari libur',
    $svc->statistikKehadiran($lama)['kerja_libur'], 0);

// ── Persentase kehadiran tetap maksimal 100% ────────────────
// 3 hari kerja terjadwal + 2 hari yang DIAKTIFKAN = penyebut 5, hadir semua.
// Pembilang & penyebut sama-sama memasukkan hari aktivasi -> 100% pas,
// tanpa membuang record dan tanpa perlu dipangkas.
$check('persen hadir = 100% pas (5 hadir dari 5 hari kerja termasuk 2 aktivasi)',
    $svc->persenHadir($svc->statistikKehadiran([
        ['status' => 'hadir', 'kerja_hari_libur' => false],
        ['status' => 'hadir', 'kerja_hari_libur' => false],
        ['status' => 'hadir', 'kerja_hari_libur' => false],
        ['status' => 'hadir', 'kerja_hari_libur' => true],
        ['status' => 'hadir', 'kerja_hari_libur' => true],
    ])['hadir'], 5), 100.0);

// ── Konsumen benar-benar memakai aturan ini ─────────────────
$gaji   = file_get_contents(__DIR__ . '/../../app/Services/GajiService.php');
$absen  = file_get_contents(__DIR__ . '/../../app/Http/Controllers/AbsensiController.php');
$profil = file_get_contents(__DIR__ . '/../../app/Http/Controllers/ProfilController.php');

$check('GajiService menghitung hari kerja libur dari status bekerja saja',
    str_contains($gaji, 'hanyaKerjaLiburBekerja'), true);
$check('halaman absensi (statistik ringkas) memakai statistikKehadiran()',
    str_contains($absen, 'statistikKehadiran'), true);
$check('halaman profil memakai aturan hari kerja libur yang sama',
    str_contains($profil, 'hanyaKerjaLiburBekerja') || str_contains($profil, 'statistikKehadiran'), true);
// Semantik baru: TIDAK ada konsumen yang membuang baris aktivasi dari statistik.
foreach (['GajiService' => $gaji, 'AbsensiController' => $absen, 'ProfilController' => $profil] as $nama => $src) {
    $check("$nama tidak membuang baris aktivasi (hanyaReguler sudah dihapus)",
        str_contains($src, 'hanyaReguler('), false);
}
$check('halaman absensi tidak lagi menghitung hadir langsung dari semua baris',
    str_contains($absen, "\$bulanIni->whereIn('status',['hadir','telat','setengah_hari'])->count()"), false);

// ── KpiService: status setengah hari harus dikenali ─────────
// Status yang dipakai seluruh sistem adalah `setengah_hari` (lihat migrasi absensi,
// AbsensiController, dan GajiService). KpiService menulis `setengah` — nilai yang
// TIDAK PERNAH ADA di kolom status, jadi karyawan yang pulang setengah hari tidak
// terhitung hadir sama sekali di KPI: persen kehadirannya turun dan kelas KPI-nya
// bisa jatuh, padahal dia masuk kerja.
// Kebijakan KPI lain (ambang telat/alpha/persen) sengaja TIDAK disentuh.
$srcKpi = file_get_contents(__DIR__ . '/../../app/Services/KpiService.php');
$check('baris $jumlahHadir di KpiService terbaca',
    (bool) preg_match('/\$jumlahHadir\s*=\s*\$absensi->whereIn\(\s*[\'"]status[\'"]\s*,\s*(.+?)\)\s*->count\(\)/s', $srcKpi, $mKpi), true);

$daftarStatusKpi = [];
if (!empty($mKpi[1])) {
    $ekspresi = trim($mKpi[1]);
    // Boleh literal array, boleh konstanta bersama — dua-duanya diselesaikan di sini.
    if (str_contains($ekspresi, 'STATUS_BEKERJA')) {
        $daftarStatusKpi = KerjaHariLiburService::STATUS_BEKERJA;
    } elseif (preg_match_all('/[\'"]([a-z_]+)[\'"]/', $ekspresi, $mm)) {
        $daftarStatusKpi = $mm[1];
    }
}
$check('KPI menghitung "hadir" dari daftar status yang tidak kosong', count($daftarStatusKpi) > 0, true);
$check('KPI mengenali status setengah_hari (nilai asli di kolom status)',
    in_array('setengah_hari', $daftarStatusKpi, true), true);
$check('KPI tidak lagi memakai "setengah" (status yang tidak pernah ada)',
    in_array('setengah', $daftarStatusKpi, true), false);
$check('status hadir & telat tetap ikut dihitung (kebijakan KPI lain tidak berubah)',
    in_array('hadir', $daftarStatusKpi, true) && in_array('telat', $daftarStatusKpi, true), true);
$check('tidak ada sisa literal "setengah" (tanpa _hari) di KpiService',
    (bool) preg_match("/[\'\"]setengah[\'\"]/", $srcKpi), false);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
