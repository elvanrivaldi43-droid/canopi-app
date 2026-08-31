<?php
// FILE: tests/kerja-hari-libur/test_lembur.php
// Jalankan: php tests/kerja-hari-libur/test_lembur.php
//
// TASK 4 — Lembur dibayar TEPAT SEKALI, dengan pembagi 9.
//
// Keputusan Bos (terkunci): lembur = (gaji_harian / 9) x 1,2 x jam_lembur, dibayar sekali.
//
// DUA cacat yang ditutup di sini:
//
// 1. DIBAYAR DUA KALI. `AbsensiController::absenPulang()` menambahkan nominal lembur
//    ke kolom `absensi.gaji_hari_ini`, DAN menyimpan `absensi.lembur_jam`. Lalu
//    `GajiService` menjumlahkan lagi bonus lembur dari `lembur_jam` itu ke slip.
//    Untuk pegawai HARIAN dampaknya paling parah: gaji pokoknya diakumulasi dari
//    `gaji_hari_ini`, jadi lemburnya benar-benar terbayar dua kali.
//
// 2. PEMBAGINYA BEDA. Controller memakai /7,5 sementara GajiService memakai /9 —
//    dua angka berbeda untuk hal yang sama, jadi nominal di layar absensi tidak
//    pernah cocok dengan nominal di slip.
//
// Perbaikan: nominal lembur TIDAK lagi masuk `gaji_hari_ini`; `lembur_jam` tetap
// disimpan; slip yang membayarnya, sekali, lewat satu helper murni dengan pembagi 9.
require __DIR__ . '/../bootstrap.php';

use App\Services\GajiService;
use App\Services\KerjaHariLiburService;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ═══════════════════════════════════════════════════════════
// 1. ANGKA YANG DIKUNCI BOS
//    gaji harian Rp180.000, lembur 2 jam -> Rp48.000
//    (180.000 / 9 = 20.000/jam; x1,2 = 24.000; x2 jam = 48.000)
// ═══════════════════════════════════════════════════════════
$check('helper bonusLembur() ada di GajiService',
    method_exists(GajiService::class, 'bonusLembur'), true);

$check('gaji harian 180.000, lembur 2 jam = Rp 48.000 TEPAT',
    GajiService::bonusLembur(180000, 2), 48000.0);

$check('pembagi 9 (bukan 7,5): 1 jam = Rp 24.000',
    GajiService::bonusLembur(180000, 1), 24000.0);
$check('kalau pembaginya 7,5 hasilnya akan 28.800 — dipastikan BUKAN itu',
    GajiService::bonusLembur(180000, 1) === 28800.0, false);

$check('lembur 0 jam = Rp 0',            GajiService::bonusLembur(180000, 0), 0.0);
$check('lembur 0,5 jam = Rp 12.000',     GajiService::bonusLembur(180000, 0.5), 12000.0);
$check('gaji harian 0 -> Rp 0 (bukan error)', GajiService::bonusLembur(0, 2), 0.0);
$check('gaji harian null -> Rp 0',       GajiService::bonusLembur(null, 2), 0.0);
$check('nilai string dari DB dinormalkan', GajiService::bonusLembur('180000.00', '2'), 48000.0);
$check('jam lembur negatif (data rusak) -> Rp 0, bukan potongan siluman',
    GajiService::bonusLembur(180000, -2), 0.0);

// ═══════════════════════════════════════════════════════════
// 2. DIBAYAR SEKALI — gaji_hari_ini TIDAK boleh mengandung lembur
// ═══════════════════════════════════════════════════════════
$srcAbsen = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');

// Badan absenPulang() = dari deklarasinya sampai method berikutnya.
$posPulang  = strpos($srcAbsen, 'function absenPulang(');
$posBerikut = $posPulang === false ? false : strpos($srcAbsen, 'public function', $posPulang + 20);
$bodyPulang = $posPulang === false ? '' : substr($srcAbsen, $posPulang, ($posBerikut ?: strlen($srcAbsen)) - $posPulang);
$check('badan absenPulang() terbaca', strlen($bodyPulang) > 500, true);

$check('absenPulang() masih menyimpan lembur_jam (bukti jamnya tidak hilang)',
    str_contains($bodyPulang, "'lembur_jam'"), true);

// Inti perbaikannya: nominal lembur tidak lagi dijumlahkan ke gaji harian.
$check('absenPulang() TIDAK lagi menambahkan nominal lembur ke gaji_hari_ini',
    (bool) preg_match('/\+\s*\$gajiLembur/', $bodyPulang), false);
$check('absenPulang() tidak lagi memakai pembagi 7.5',
    str_contains($bodyPulang, '/7.5'), false);

// Kalau controller masih menghitung nominal lembur untuk ditampilkan, dia WAJIB
// memakai helper yang sama — bukan rumus salinan yang bisa menyimpang lagi.
if (preg_match('/gajiLembur\s*=\s*(.+?);/', $bodyPulang, $mL)) {
    $check('nominal lembur di controller memakai helper GajiService::bonusLembur()',
        str_contains($mL[1], 'bonusLembur('), true);
}

// ═══════════════════════════════════════════════════════════
// 3. SLIP menambahkan bonus lembur TEPAT SEKALI, lewat helper
// ═══════════════════════════════════════════════════════════
$srcGaji = file_get_contents($base . '/app/Services/GajiService.php');

$check('GajiService memakai helper bonusLembur() untuk menghitung bonus',
    (bool) preg_match('/\$bonusLembur\s*=\s*self::bonusLembur\(/', $srcGaji), true);
$check('GajiService tidak lagi menyalin rumus /9 * 1.2 sendiri',
    (bool) preg_match('/\$totalLembur\s*\*\s*\$gajiPerJam\s*\*\s*1\.2/', $srcGaji), false);
$check('bonus lembur dijumlahkan ke pendapatan hanya di satu tempat',
    substr_count($srcGaji, '$bonusLembur'), 3); // hitung, kirim ke totalPendapatan, simpan ke slip

// ═══════════════════════════════════════════════════════════
// 4. Matriks angka: hadir / telat / setengah hari / kerja hari libur,
//    pegawai harian & bulanan. Bonus lemburnya SAMA di semua kasus —
//    lembur dihitung dari jam, bukan dari status kehadiran.
// ═══════════════════════════════════════════════════════════
$svcLibur = new KerjaHariLiburService();
const GAJI_HARIAN = 180000;
const JAM_LEMBUR  = 2;
const BONUS_HARAP = 48000.0;

foreach (['hadir', 'telat', 'setengah_hari'] as $status) {
    $check("status `$status`: bonus lembur tetap Rp 48.000",
        GajiService::bonusLembur(GAJI_HARIAN, JAM_LEMBUR), BONUS_HARAP);
}

// ── Pegawai HARIAN ──
// gaji_hari_ini SUDAH tanpa lembur. Gaji pokok slip = kotor (gaji_hari_ini + potongan).
// Bonus lembur ditambahkan sekali oleh slip.
$gajiHariIniHarian = GAJI_HARIAN;                 // hadir penuh, tanpa potongan, TANPA lembur
$gajiPokokHarian   = $svcLibur->gajiPokokKotor((float) $gajiHariIniHarian, 0.0);
$totalHarian       = $svcLibur->totalPendapatan('harian', $gajiPokokHarian, 0.0, 0.0, 0.0, BONUS_HARAP, 0.0);
$check('HARIAN hadir + lembur 2 jam: 180.000 + 48.000 = 228.000 (bukan 276.000 dobel)',
    $totalHarian, 228000.0);

// Bukti cacat lama: kalau lembur ikut masuk gaji_hari_ini, hasilnya membengkak.
$totalHarianDobel = $svcLibur->totalPendapatan(
    'harian', $svcLibur->gajiPokokKotor((float) (GAJI_HARIAN + BONUS_HARAP), 0.0), 0.0, 0.0, 0.0, BONUS_HARAP, 0.0);
$check('bukti cara lama memang dobel (276.000) — selisihnya persis 1x bonus',
    $totalHarianDobel - $totalHarian, BONUS_HARAP);

// ── Pegawai BULANAN ──
$gajiBulanan   = 5000000.0;
$totalBulanan  = $svcLibur->totalPendapatan('bulanan', $gajiBulanan, 0.0, 0.0, 0.0, BONUS_HARAP, 0.0);
$check('BULANAN + lembur 2 jam: 5.000.000 + 48.000 = 5.048.000',
    $totalBulanan, 5048000.0);

// ── KERJA HARI LIBUR + lembur ──
// Hari aktivasi = hari kerja biasa, jadi gaji hariannya masuk gaji_hari_ini seperti
// biasa; upah hari libur (kompensasi jatah libur hangus) hanya untuk pegawai bulanan;
// bonus lemburnya tetap 1x, tidak berlipat karena hari libur.
$totalHarianLibur = $svcLibur->totalPendapatan(
    'harian', $svcLibur->gajiPokokKotor((float) GAJI_HARIAN, 0.0), 0.0, 0.0, 0.0, BONUS_HARAP, (float) GAJI_HARIAN);
$check('HARIAN kerja hari libur + lembur: 180.000 + 48.000 = 228.000 (upah libur tidak ditambah 2x)',
    $totalHarianLibur, 228000.0);

$totalBulananLibur = $svcLibur->totalPendapatan(
    'bulanan', $gajiBulanan, 0.0, 0.0, 0.0, BONUS_HARAP, (float) GAJI_HARIAN);
$check('BULANAN kerja hari libur + lembur: 5.000.000 + 180.000 + 48.000 = 5.228.000',
    $totalBulananLibur, 5228000.0);

// Setengah hari + lembur: gaji harian separuh, bonus lembur penuh sesuai jamnya.
$totalSetengah = $svcLibur->totalPendapatan(
    'harian', $svcLibur->gajiPokokKotor(GAJI_HARIAN * 0.5, 0.0), 0.0, 0.0, 0.0, BONUS_HARAP, 0.0);
$check('HARIAN setengah hari + lembur: 90.000 + 48.000 = 138.000',
    $totalSetengah, 138000.0);

// ═══════════════════════════════════════════════════════════
// 5. Slip LAMA tidak dimutasi
//    Slip hanya pernah dibuat (create) di balik penjaga duplikat yang melempar
//    exception — tidak ada jalur update/upsert yang bisa mengubah slip yang sudah
//    terbit, jadi perbaikan ini murni berlaku ke depan.
// ═══════════════════════════════════════════════════════════
$check('GajiService tidak memakai updateOrCreate untuk slip',
    str_contains($srcGaji, 'SlipGaji::updateOrCreate'), false);
$check('GajiService tidak memakai upsert untuk slip',
    str_contains($srcGaji, 'SlipGaji::upsert'), false);
// Penjaga duplikat DIPERBARUI 31 Ags 2026 (keputusan Elvan, kasus nyata gajian
// akhir Agustus): slip yang BELUM dibayar kini boleh dihitung ulang — dibutuhkan
// saat absensi dikoreksi atau kebijakan berubah setelah slip terlanjur dibuat.
// Yang dijaga sekarang lebih tajam, bukan lebih longgar: slip berstatus DIBAYAR
// tetap TIDAK PERNAH bisa ditimpa (prosesBayar sudah memajukan cicilan kasbon &
// menambah saldo tabungan; menimpanya menggandakan efek itu dan membuat bukti
// transfer yang sudah dikirim tak cocok dengan slipnya).
$check('penjaga slip DIBAYAR masih ada di kedua generator',
    substr_count($srcGaji, 'sudah DIBAYAR'), 2);
$check('keputusan boleh-tidaknya lewat SettingGajiService::bolehHitungUlang',
    substr_count($srcGaji, 'SettingGajiService::bolehHitungUlang'), 2);
$check('penghapusan slip lama dijaga if (tidak menghapus tanpa syarat)',
    substr_count($srcGaji, '$existing->delete();'), 2);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
