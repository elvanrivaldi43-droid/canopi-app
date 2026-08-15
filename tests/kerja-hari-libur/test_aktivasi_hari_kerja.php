<?php
// FILE: tests/kerja-hari-libur/test_aktivasi_hari_kerja.php
// Jalankan: php tests/kerja-hari-libur/test_aktivasi_hari_kerja.php
//
// TASK 3 — Aktivasi kerja hari libur benar-benar menjadi HARI KERJA NORMAL.
//
// Keputusan Bos (terkunci): "Aktivasi membatalkan libur tanpa pengganti; tanggal
// menjadi hari kerja biasa."
//
// Ini PEMBALIKAN dari rancangan awal fitur. Rancangan awal menahan tanggal itu tetap
// berstatus libur lalu MENGELUARKAN barisnya dari statistik supaya persentase tidak
// tembus 100%. Akibatnya karyawan yang sudah diaktifkan lalu TIDAK MASUK hilang dari
// laporan: alpha-nya tidak terhitung, KPI-nya tidak turun, dan tidak ada yang tahu
// dia mangkir. Sekarang tanggal aktivasi masuk penyebut DAN pembilang, jadi
// hasilnya konsisten secara matematis tanpa membuang record apa pun.
//
// Upah ekstra 1x gaji harian TIDAK hilang — itu tetap dihitung terpisah lewat
// `hari_kerja_libur` / `upah_hari_libur` (kompensasi karena jatah liburnya hangus).
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;
use App\Services\LiburService;
use Carbon\Carbon;

$svcLibur = new LiburService();
$svc      = new KerjaHariLiburService();
$base     = dirname(__DIR__, 2);

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Minggu, 16 Agustus 2026 — hari libur default karyawan (0 = Minggu).
const TGL_AKTIVASI = '2026-08-16';
$tglAktivasi = Carbon::parse(TGL_AKTIVASI);

// ═══════════════════════════════════════════════════════════
// 1. Aktivasi = override `batal` berprioritas TERTINGGI
// ═══════════════════════════════════════════════════════════
$check('expandAktivasi() mengubah tanggal aktivasi jadi override `batal`',
    $svcLibur->expandAktivasi([TGL_AKTIVASI]),
    [['tanggal' => TGL_AKTIVASI, 'jenis' => 'batal']]);

$check('expandAktivasi() tanpa tanggal = array kosong',
    $svcLibur->expandAktivasi([]), []);

$check('expandAktivasi() menangani banyak tanggal',
    count($svcLibur->expandAktivasi([TGL_AKTIVASI, '2026-08-23'])), 2);

// ── Lawan jadwal libur DEFAULT (Minggu) ──
$check('tanpa aktivasi: Minggu = libur',
    $svcLibur->cocokLiburPada(0, [], $tglAktivasi), true);
$check('DENGAN aktivasi: Minggu jadi hari kerja',
    $svcLibur->cocokLiburPada(0, $svcLibur->expandAktivasi([TGL_AKTIVASI]), $tglAktivasi), false);

// ── Lawan override JADWAL (ajuan tambah libur yang sudah di-approve) ──
$overrideJadwal = [['tanggal' => TGL_AKTIVASI, 'jenis' => 'tambah']];
$check('tanpa aktivasi: override `tambah` bikin hari itu libur',
    $svcLibur->cocokLiburPada(1, $overrideJadwal, $tglAktivasi), true);
$check('aktivasi MENANG atas override jadwal `tambah`',
    $svcLibur->cocokLiburPada(1, array_merge($svcLibur->expandAktivasi([TGL_AKTIVASI]), $overrideJadwal), $tglAktivasi),
    false);

// ── Lawan LIBUR NASIONAL ──
$liburNasional = $svcLibur->expandLiburNasional('2026-08-15', '2026-08-17', []);
$check('tanpa aktivasi: libur nasional bikin hari itu libur',
    $svcLibur->cocokLiburPada(1, $liburNasional, $tglAktivasi), true);
$check('aktivasi MENANG atas libur nasional',
    $svcLibur->cocokLiburPada(1, array_merge($svcLibur->expandAktivasi([TGL_AKTIVASI]), $liburNasional), $tglAktivasi),
    false);

// ── Lawan SEMUANYA sekaligus ──
$semua = array_merge(
    $svcLibur->expandAktivasi([TGL_AKTIVASI]),
    $liburNasional,
    $overrideJadwal
);
$check('aktivasi MENANG atas libur nasional + override jadwal + default sekaligus',
    $svcLibur->cocokLiburPada(0, $semua, $tglAktivasi), false);

// Tanggal LAIN tidak ikut terpengaruh — aktivasi hanya membatalkan tanggalnya sendiri.
$check('tanggal lain tetap libur (aktivasi tidak bocor ke hari lain)',
    $svcLibur->cocokLiburPada(0, $svcLibur->expandAktivasi([TGL_AKTIVASI]), Carbon::parse('2026-08-23')),
    true);

// ═══════════════════════════════════════════════════════════
// 2. Hari kerja +1 (tanggal aktivasi masuk PENYEBUT)
// ═══════════════════════════════════════════════════════════
// Agustus 2026: 31 hari, 5 hari Minggu (2,9,16,23,30) -> 26 hari kerja.
$hariKerjaTanpa = $svcLibur->hitungHariKerjaPada(0, [], 8, 2026);
$check('Agustus 2026 tanpa aktivasi = 26 hari kerja', $hariKerjaTanpa, 26);

$hariKerjaDengan = $svcLibur->hitungHariKerjaPada(0, $svcLibur->expandAktivasi([TGL_AKTIVASI]), 8, 2026);
$check('Agustus 2026 dengan 1 aktivasi = 27 hari kerja (+1)', $hariKerjaDengan, 27);
$check('selisihnya persis 1 hari', $hariKerjaDengan - $hariKerjaTanpa, 1);

$check('2 aktivasi = +2 hari kerja',
    $svcLibur->hitungHariKerjaPada(0, $svcLibur->expandAktivasi([TGL_AKTIVASI, '2026-08-23']), 8, 2026),
    28);

// ═══════════════════════════════════════════════════════════
// 3. Statistik: baris aktivasi IKUT dihitung, tidak dibuang
// ═══════════════════════════════════════════════════════════
// 3 hari kerja reguler (2 hadir, 1 alpha) + 2 hari aktivasi (1 hadir, 1 alpha).
$rows = [
    ['status' => 'hadir', 'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'hadir', 'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'alpha', 'kerja_hari_libur' => false, 'upah_hari_libur' => 0],
    ['status' => 'hadir', 'kerja_hari_libur' => true,  'upah_hari_libur' => 200000],
    ['status' => 'alpha', 'kerja_hari_libur' => true,  'upah_hari_libur' => 0],
];
$stat = $svc->statistikKehadiran($rows);

$check('hadir = 3 — hadir di hari aktivasi IKUT dihitung', $stat['hadir'], 3);
$check('alpha = 2 — alpha di hari aktivasi IKUT dihitung (tidak lagi disembunyikan)',
    $stat['alpha'], 2);
$check('kerja_libur = 1 — hanya yang benar-benar bekerja (alpha tidak dibayar)',
    $stat['kerja_libur'], 1);
$check('upah_libur = 200.000 — alpha di hari aktivasi tidak dibayar',
    $stat['upah_libur'], 200000.0);

// ═══════════════════════════════════════════════════════════
// 4. KPI maksimum 100% SECARA MATEMATIS
//    (pembilang & penyebut sama-sama memasukkan hari aktivasi)
// ═══════════════════════════════════════════════════════════
// Semua hari kerja reguler hadir (26) + 1 hari aktivasi hadir = 27 hadir dari 27 hari.
$check('27 hadir dari 27 hari kerja (26 + 1 aktivasi) = 100% tepat',
    $svc->persenHadir(27, 27), 100.0);

// Dan kalau hari aktivasinya MANGKIR, persentasenya benar-benar turun —
// inilah yang dulu tersembunyi.
$check('26 hadir dari 27 hari kerja (mangkir di hari aktivasi) = ~96.3%, BUKAN 100%',
    round($svc->persenHadir(26, 27), 1), 96.3);
$check('mangkir di hari aktivasi benar-benar menurunkan persentase',
    $svc->persenHadir(26, 27) < 100.0, true);

// Simulasi utuh sebulan: 26 hari kerja reguler + 1 aktivasi.
$sebulan = array_merge(
    array_fill(0, 26, ['status' => 'hadir', 'kerja_hari_libur' => false, 'upah_hari_libur' => 0]),
    [['status' => 'hadir', 'kerja_hari_libur' => true, 'upah_hari_libur' => 200000]]
);
$statBulan  = $svc->statistikKehadiran($sebulan);
$penyebut   = $svcLibur->hitungHariKerjaPada(0, $svcLibur->expandAktivasi([TGL_AKTIVASI]), 8, 2026);
$check('simulasi sebulan: pembilang 27, penyebut 27 -> 100% tanpa perlu dipangkas',
    (float) (($statBulan['hadir'] / $penyebut) * 100), 100.0);
$check('simulasi sebulan: hasil lewat persenHadir() juga 100%',
    $svc->persenHadir($statBulan['hadir'], $penyebut), 100.0);

// ═══════════════════════════════════════════════════════════
// 5. Upah: harian sekali, bulanan dapat tambahan 1x snapshot
// ═══════════════════════════════════════════════════════════
// Hari aktivasi sekarang hari kerja biasa, jadi gaji hariannya sudah masuk
// gaji_hari_ini seperti hari kerja lain. Yang ditambahkan cuma KOMPENSASI
// jatah libur yang hangus, dan itu hanya relevan buat pegawai bulanan
// (gaji pokoknya tidak ikut bertambah karena masuk 1 hari ekstra).
$check('pegawai HARIAN: tidak ada tambahan (upahnya sudah masuk gaji harian)',
    $svc->tambahanPendapatan('harian', 200000.0), 0.0);
$check('pegawai BULANAN: dapat tambahan 1x snapshot',
    $svc->tambahanPendapatan('bulanan', 200000.0), 200000.0);
$check('pegawai PROJECT: diperlakukan seperti harian',
    $svc->tambahanPendapatan('project', 200000.0), 0.0);

// Uang makan tetap SEKALI (dari sum absensi), tidak ditambah lagi dari snapshot.
$check('uang makan masuk sekali saja di totalPendapatan',
    $svc->totalPendapatan('bulanan', 5000000.0, 500000.0, 0.0, 0.0, 0.0, 200000.0),
    5700000.0);

// ═══════════════════════════════════════════════════════════
// 6. Wrapper database memakai prioritas ini di SEMUA jalurnya
// ═══════════════════════════════════════════════════════════
$srcLibur = file_get_contents($base . '/app/Services/LiburService.php');

$check('LiburService punya pengambil aktivasi dari DB',
    str_contains($srcLibur, 'ambilAktivasiKerjaLibur'), true);

// Prioritas tertinggi = ditaruh PALING DEPAN saat merge, karena cocokLiburPada()
// berhenti di override pertama yang tanggalnya cocok. Kalau ditaruh di belakang
// libur nasional, libur nasional yang menang dan aktivasi diam-diam tidak berlaku.
foreach (['isLibur', 'hitungHariKerja', 'petaLiburBulan'] as $fn) {
    $pos  = strpos($srcLibur, "function $fn(");
    $body = $pos === false ? '' : substr($srcLibur, $pos, 700);
    $check("$fn() ikut mengambil override aktivasi",
        str_contains($body, 'ambilAktivasiKerjaLibur('), true);

    // Posisi argumen pertama di array_merge = prioritas tertinggi.
    $posMerge = strpos($body, 'array_merge(');
    $posAkt   = strpos($body, 'ambilAktivasiKerjaLibur(');
    $posNas   = strpos($body, 'ambilLiburNasional(');
    $check("$fn(): aktivasi di-merge SEBELUM libur nasional (prioritas tertinggi)",
        $posAkt !== false && $posNas !== false && $posAkt < $posNas, true);
}

// ═══════════════════════════════════════════════════════════
// 7. Konsumen TIDAK LAGI membuang baris aktivasi dari statistik
// ═══════════════════════════════════════════════════════════
$srcGaji   = file_get_contents($base . '/app/Services/GajiService.php');
$srcKpi    = file_get_contents($base . '/app/Services/KpiService.php');
$srcProfil = file_get_contents($base . '/app/Http/Controllers/ProfilController.php');
$srcAbsen  = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');

foreach ([
    'GajiService'        => $srcGaji,
    'KpiService'         => $srcKpi,
    'ProfilController'   => $srcProfil,
    'AbsensiController'  => $srcAbsen,
] as $nama => $src) {
    $check("$nama tidak lagi membuang baris aktivasi lewat hanyaReguler()",
        str_contains($src, 'hanyaReguler('), false);
}

// Upah ekstra tetap dihitung terpisah — jangan sampai ikut terhapus.
$check('GajiService tetap menghitung upah hari libur terpisah',
    str_contains($srcGaji, 'hanyaKerjaLiburBekerja'), true);

// ═══════════════════════════════════════════════════════════
// 8. PESAN KE KARYAWAN TIDAK BOLEH BERBOHONG
//
// Rancangan awal menjanjikan "jatah libur kamu TIDAK hangus" — kalimat itu ikut
// dikirim lewat Telegram, muncul di layar absen, dan di dialog konfirmasi Owner.
// Setelah keputusan Bos dikunci (aktivasi membatalkan libur TANPA pengganti),
// kalimat itu jadi TIDAK BENAR: tanggalnya berubah menjadi hari kerja biasa dan
// jatah liburnya memang terpakai. Kompensasinya adalah upah 1x gaji harian,
// bukan libur pengganti.
//
// Janji yang salah ke karyawan lebih berbahaya daripada bug diam-diam: ini yang
// mereka pakai memutuskan mau masuk atau tidak.
// ═══════════════════════════════════════════════════════════
$berkasPesan = [
    'app/Models/KerjaHariLibur.php',
    'app/Http/Controllers/AbsensiController.php',
    'resources/views/absensi/kode-hari-ini.blade.php',
    'docs/sql/2026-08-15-kerja-hari-libur.sql',
];
foreach ($berkasPesan as $rel) {
    $isi = file_get_contents($base . '/' . $rel);
    $check("`$rel` tidak lagi menjanjikan jatah libur tidak hangus",
        (bool) preg_match('/tidak hangus/i', $isi), false);
    $check("`$rel` tidak lagi menyatakan hari itu tetap dihitung libur",
        (bool) preg_match('/tetap dihitung libur/i', $isi), false);
}

// Gantinya harus menyebutkan bahwa hari itu jadi hari kerja / jatah libur terpakai.
$srcViewKode = file_get_contents($base . '/resources/views/absensi/kode-hari-ini.blade.php');
$check('layar Owner/Mandor menjelaskan jatah libur terpakai',
    (bool) preg_match('/jatah libur.*(terpakai|hangus|dipakai)|hari kerja biasa/i', $srcViewKode), true);
$check('pesan aktivasi menyebut hari kerja biasa',
    (bool) preg_match('/hari kerja biasa/i', $srcAbsen), true);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
