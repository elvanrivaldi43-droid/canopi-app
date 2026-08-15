<?php
// FILE: tests/kerja-hari-libur/test_payroll_kerja_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_payroll_kerja_hari_libur.php
//
// Tes ANGKA untuk aturan bayaran kerja hari libur (murni, tanpa database):
//   - pegawai harian dibayar TEPAT 1x gaji harian (bukan 2x)
//   - pegawai bulanan dapat TAMBAHAN 1x gaji harian
//   - uang makan tidak dobel
//   - potongan telat pada hari libur tidak nambah potongan baru
//   - persentase kehadiran tidak bisa lewat 100%
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Angka contoh 1 karyawan
const GAJI_HARIAN   = 200000.0;
const UANG_MAKAN    = 25000.0;
const POTONGAN_1JAM = 20000.0;  // telat 1 jam = Rp20.000 (AbsensiController::POTONGAN_TELAT)
const GAJI_BULANAN  = 5000000.0;

// ── upahHariLibur: nominal per status ───────────────────────
// Upah hari libur disimpan KOTOR (belum dipotong). Potongan telat tetap lewat
// jalur potongan_telat yang sudah ada, biar tidak kepotong dua kali.
$check('hadir -> 1x gaji harian (kotor)',           $svc->upahHariLibur(GAJI_HARIAN, 'hadir'), 200000.0);
$check('telat -> tetap 1x kotor (potongan lewat potongan_telat)', $svc->upahHariLibur(GAJI_HARIAN, 'telat'), 200000.0);
$check('setengah hari -> 0.5x',                     $svc->upahHariLibur(GAJI_HARIAN, 'setengah_hari'), 100000.0);
$check('alpha -> 0',                                $svc->upahHariLibur(GAJI_HARIAN, 'alpha'), 0.0);
$check('izin -> 0',                                 $svc->upahHariLibur(GAJI_HARIAN, 'izin'), 0.0);
$check('snapshot 0 -> 0',                           $svc->upahHariLibur(0, 'hadir'), 0.0);

// ── tambahanPendapatan: harian vs bulanan ───────────────────
$check('harian: upah hari libur TIDAK ditambah lagi (sudah masuk gaji_hari_ini)',
    $svc->tambahanPendapatan('harian', 200000.0), 0.0);
$check('bulanan: upah hari libur ditambah 1x di atas gaji bulanan',
    $svc->tambahanPendapatan('bulanan', 200000.0), 200000.0);
$check('project: diperlakukan seperti harian (gaji_hari_ini yang dipakai)',
    $svc->tambahanPendapatan('project', 200000.0), 0.0);
$check('tipe gaji null: aman, tidak menambah apa-apa',
    $svc->tambahanPendapatan(null, 200000.0), 0.0);

// ── Skenario A: pegawai HARIAN kerja di hari libur, tidak telat ──
// gaji_hari_ini hari itu sudah 1x gaji harian (alur absen normal).
$gajiPokokHarian = GAJI_HARIAN;                                   // sum(gaji_hari_ini) sebulan (1 hari kerja)
$umBulan         = UANG_MAKAN;                                    // sum(uang_makan_hari_ini)
$totalUpahLibur  = $svc->upahHariLibur(GAJI_HARIAN, 'hadir');     // audit
$pendapatanA     = $svc->totalPendapatan('harian', $gajiPokokHarian, $umBulan, 0, 0, 0, $totalUpahLibur);
$check('A. harian di hari libur: pendapatan = 1x gaji harian + 1x uang makan',
    $pendapatanA, 225000.0);
$check('A. BUKAN 2x gaji harian',
    $pendapatanA === (GAJI_HARIAN * 2 + UANG_MAKAN), false);
$check('A. uang makan cuma sekali (bukan 2x 25rb)',
    $pendapatanA - $gajiPokokHarian, UANG_MAKAN);

// ── Skenario B: pegawai BULANAN kerja di hari libur, tidak telat ──
$pendapatanB = $svc->totalPendapatan('bulanan', GAJI_BULANAN, UANG_MAKAN, 0, 0, 0, $totalUpahLibur);
$check('B. bulanan di hari libur: gaji bulanan + 1x gaji harian + uang makan',
    $pendapatanB, 5225000.0);
$check('B. tambahannya tepat 1x gaji harian',
    $pendapatanB - GAJI_BULANAN - UANG_MAKAN, GAJI_HARIAN);

// ── Skenario C: pegawai BULANAN kerja hari libur TAPI telat 1 jam ──
// Potongan telat masuk lewat total potongan (sekali), bukan dipotong dari upah libur juga.
$upahLiburTelat = $svc->upahHariLibur(GAJI_HARIAN, 'telat');
$pendapatanC    = $svc->totalPendapatan('bulanan', GAJI_BULANAN, UANG_MAKAN, 0, 0, 0, $upahLiburTelat);
$bersihC        = $pendapatanC - POTONGAN_1JAM;
$check('C. bulanan telat di hari libur: potongan kepotong TEPAT SEKALI',
    $bersihC, GAJI_BULANAN + UANG_MAKAN + GAJI_HARIAN - POTONGAN_1JAM);

// ── Skenario D: POTONGAN PEGAWAI HARIAN HARUS KEPOTONG TEPAT SEKALI ─────────
// Keputusan Bos: potongan tepat sekali untuk pegawai harian, hari biasa maupun hari libur.
//
// Duduk perkaranya (kode lama):
//   absensi.gaji_hari_ini = tarif - potongan   -> SUDAH bersih (AbsensiController::absenMasuk)
//   GajiService: gajiPokok = sum(gaji_hari_ini), lalu potongan_telat dikurangi LAGI
//   -> pegawai harian kepotong DUA KALI.
//
// Perbaikannya: gaji pokok harian dikembalikan ke KOTOR dulu (gaji_hari_ini + potongan_telat)
// supaya baris potongan di slip tetap ditampilkan sekali dan hasil akhirnya benar.
$gajiHariIniTelat = GAJI_HARIAN - POTONGAN_1JAM;                             // yang tersimpan di DB
$gajiPokokKotorD  = $svc->gajiPokokKotor($gajiHariIniTelat, POTONGAN_1JAM);  // yang dipakai slip
$check('D. gaji pokok harian dikembalikan KOTOR (transparan di slip)',
    $gajiPokokKotorD, GAJI_HARIAN);
$pendapatanD = $svc->totalPendapatan('harian', $gajiPokokKotorD, UANG_MAKAN, 0, 0, 0, 0.0);
$bersihD     = $pendapatanD - POTONGAN_1JAM;                                 // GajiService: totalPotongan
$check('D. harian telat di hari BIASA: 200.000 + UM 25.000 - potongan 20.000 = 205.000',
    $bersihD, 205000.0);
$check('D. potongan kepotong TEPAT SEKALI (bukan 2x)',
    (GAJI_HARIAN + UANG_MAKAN) - $bersihD, POTONGAN_1JAM);
$check('D. pegawai harian TIDAK dirugikan 1x potongan lagi',
    $bersihD === (GAJI_HARIAN - (2 * POTONGAN_1JAM) + UANG_MAKAN), false);
$check('D. tanpa potongan sama sekali: gaji pokok tidak berubah',
    $svc->gajiPokokKotor(GAJI_HARIAN, 0.0), GAJI_HARIAN);

// ── Skenario D2: potongan checkpoint (Lapor Progress / Kembali Kerja) ───────
// Dua denda flat menumpuk di kolom yang sama (potongan_telat) dan sama-sama sudah
// dikurangkan dari gaji_hari_ini — rekonstruksi kotor harus menutup dua-duanya.
$potonganDua        = POTONGAN_1JAM * 2;                       // telat masuk + kelewat jam 12:30
$gajiHariIniDuaDenda = GAJI_HARIAN - $potonganDua;
$pokokKotorD2       = $svc->gajiPokokKotor($gajiHariIniDuaDenda, $potonganDua);
$check('D2. dua denda checkpoint: gaji pokok kotor tetap 1x gaji harian',
    $pokokKotorD2, GAJI_HARIAN);
$bersihD2 = $svc->totalPendapatan('harian', $pokokKotorD2, UANG_MAKAN, 0, 0, 0, 0.0) - $potonganDua;
$check('D2. bersih = 200.000 + 25.000 - 40.000 = 185.000 (tiap denda kepotong sekali)',
    $bersihD2, 185000.0);

// ── Skenario E: kerja hari libur TIDAK menambah potongan ketiga ────
// Pegawai harian telat di HARI LIBUR harus persis sama hasilnya dengan hari biasa (skenario D).
$upahLiburHarianTelat = $svc->upahHariLibur(GAJI_HARIAN, 'telat');
$pendapatanE = $svc->totalPendapatan('harian', $gajiPokokKotorD, UANG_MAKAN, 0, 0, 0, $upahLiburHarianTelat);
$bersihE     = $pendapatanE - POTONGAN_1JAM;
$check('E. harian telat di hari LIBUR = sama persis dengan hari biasa (kerja hari libur tidak nambah potongan)',
    $bersihE, $bersihD);
$check('E. harian di hari libur pun potongannya tepat sekali',
    $bersihE, 205000.0);

// ── Pemisahan baris reguler vs kerja hari libur ─────────────
$rows = [
    (object) ['status' => 'hadir', 'kerja_hari_libur' => 0,    'upah_hari_libur' => 0],
    (object) ['status' => 'telat', 'kerja_hari_libur' => 0,    'upah_hari_libur' => 0],
    (object) ['status' => 'hadir', 'kerja_hari_libur' => 1,    'upah_hari_libur' => 200000],
    (object) ['status' => 'hadir', 'kerja_hari_libur' => true, 'upah_hari_libur' => 100000],
    (object) ['status' => 'hadir'], // baris lama (kolom belum ada) -> dianggap reguler
];
$check('hanyaKerjaLibur: 2 baris',                                     $svc->hanyaKerjaLibur($rows)->count(), 2);
// Baris aktivasi TIDAK lagi dibuang dari statistik — sejak aktivasi membatalkan
// libur, hari itu hari kerja biasa dan semua barisnya ikut dihitung.
$check('statistik menghitung SEMUA baris hadir (2 reguler + 2 aktivasi + 1 baris lama)',
    $svc->statistikKehadiran($rows)['hadir'], 5);
$check('total upah hari libur dijumlahkan dari baris kerja libur saja',
    (float) $svc->hanyaKerjaLibur($rows)->sum('upah_hari_libur'), 300000.0);
$check('baris array (bukan objek) juga dikenali',
    $svc->hanyaKerjaLibur([['kerja_hari_libur' => 1]])->count(), 1);

// ── Persentase kehadiran tidak boleh > 100% ─────────────────
// 26 hari kerja terjadwal + 2 hari yang DIAKTIFKAN masuk = 28 hari kerja,
// hadir semua. Penyebut ikut bertambah 2 (LiburService::expandAktivasi bikin
// tanggal aktivasi jadi hari kerja), jadi hasilnya 100% secara matematis —
// bukan karena barisnya dibuang, dan bukan karena dipangkas di ujung.
$hadirSemua = $svc->statistikKehadiran(array_merge(
    array_fill(0, 26, (object) ['status' => 'hadir', 'kerja_hari_libur' => 0]),
    array_fill(0, 2,  (object) ['status' => 'hadir', 'kerja_hari_libur' => 1])
))['hadir'];

$check('hadir = 28 (26 reguler + 2 hari aktivasi, semua dihitung)', $hadirSemua, 28);
$check('persen hadir = 100% pas dengan penyebut 28 (26 + 2 aktivasi)',
    $svc->persenHadir($hadirSemua, 28), 100.0);

// Kalau salah satu hari aktivasi MANGKIR, persentasenya benar-benar turun —
// ini yang dulu tersembunyi waktu baris aktivasi dibuang dari statistik.
$statMangkir = $svc->statistikKehadiran(array_merge(
    array_fill(0, 26, (object) ['status' => 'hadir', 'kerja_hari_libur' => 0]),
    [(object) ['status' => 'hadir', 'kerja_hari_libur' => 1]],
    [(object) ['status' => 'alpha', 'kerja_hari_libur' => 1]]
));
$check('mangkir di hari aktivasi: hadir 27 dari 28', $statMangkir['hadir'], 27);
$check('mangkir di hari aktivasi: alpha-nya TERHITUNG (tidak disembunyikan)',
    $statMangkir['alpha'], 1);
$check('mangkir di hari aktivasi: persen turun di bawah 100%',
    $svc->persenHadir($statMangkir['hadir'], 28) < 100.0, true);

// Pertahanan kedua: apa pun yang masuk, hasil akhirnya tidak pernah > 100%.
// KPI/slip/profil membaca angka ini apa adanya; "107% hadir" bikin Bos tidak
// percaya laporannya, dan `hitungKelasKpi()` menilai ambang persen ini.
// Sumber >100% tidak cuma kerja hari libur: baris absensi dobel (koreksi manual
// menimpa/di hari libur nasional) atau jadwal libur yang diubah di tengah bulan
// (penyebut mengecil setelah baris kehadiran terlanjur tercatat) juga bisa.
// Clamp ini sekarang murni jaring pengaman untuk sumber lain (baris absensi dobel,
// atau jadwal libur yang diubah di tengah bulan sehingga penyebutnya mengecil
// setelah kehadiran terlanjur tercatat) — bukan lagi pertahanan utama.
$check('CLAMP: hadir 28 dari 26 hari kerja -> 100% (tidak 107,7%)',
    $svc->persenHadir(28, 26), 100.0);
$check('CLAMP: hadir 30 dari 20 -> 100% (tidak 150%)', $svc->persenHadir(30, 20), 100.0);
$check('CLAMP: hadir 27 dari 26 -> 100% (batas tipis pun ikut)', $svc->persenHadir(27, 26), 100.0);
$check('pas 100% tetap 100% (bukan dibulatkan turun)', $svc->persenHadir(26, 26), 100.0);
$check('di bawah 100% TIDAK ikut berubah', $svc->persenHadir(25, 26), (25 / 26) * 100);

$check('hari kerja 0 -> 0% (tidak dibagi nol)', $svc->persenHadir(5, 0), 0.0);
$check('hadir 20 dari 26 -> ~76.9%', round($svc->persenHadir(20, 26), 1), 76.9);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
