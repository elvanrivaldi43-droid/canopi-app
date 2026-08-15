<?php
// FILE: tests/kerja-hari-libur/test_absen_masuk_izin.php
// Jalankan: php tests/kerja-hari-libur/test_absen_masuk_izin.php
//
// CELAH KODE ABSEN LAMA — izin yang masuk SETELAH kode dibuat pagi tidak menutup absen.
//
// Ceritanya begini. Cron jam 06:30 membuat + mengirim kode absen pribadi untuk semua
// karyawan yang hari itu memang kerja. Lalu, SIANGNYA, keadaan berubah: karyawan itu
// sakit dan Owner/Mandor mencatat barisnya (`absensi.status` = sakit/izin/cuti/
// dinas_luar), atau karyawan sendiri mengajukan izin (`izin_absen` pending/approved).
//
// Kodenya sudah terlanjur ada di tangan karyawan dan TIDAK ikut dibatalkan. Tiga pintu
// masuk lama sama sekali tidak melihat status izin:
//   - `formMasuk()`   : layar isi kode tetap terbuka, tidak ada tanda apa pun.
//   - `validasiKode()`: kode lama masih dijawab `valid: true`.
//   - `absenMasuk()`  : `Absensi::updateOrCreate` MENIMPA baris izin tadi jadi `hadir`,
//                       lengkap dengan gaji + uang makan harian.
//
// Jadi bukti izin yang sudah dicatat Owner hilang tanpa jejak, digantikan kehadiran
// palsu yang dibayar penuh — dan tidak ada error apa pun yang muncul.
//
// Aturan barunya (SATU definisi izin, dipakai semua pintu):
//   - Pemeriksaan memakai helper yang SUDAH ADA: `adaIzinHariIni()`. Tidak boleh ada
//     salinan daftar status kedua (sakit/izin/cuti/dinas_luar) di ketiga method ini —
//     kalau daftarnya ditulis ulang, suatu hari salah satunya akan ketinggalan diperbaiki.
//   - `absenMasuk()` menolak SEBELUM GPS dihitung, SEBELUM foto diunggah ke R2, dan
//     jelas sebelum `updateOrCreate` — biar tidak ada biaya/efek samping sama sekali.
//   - ALPHA TIDAK IKUT DIBLOKIR. Alpha bukan izin; karyawan yang terlanjur ditandai
//     alpha (mis. cron alpha jalan duluan, lalu dia masuk terlambat sekali) tetap harus
//     bisa absen. Ini yang membedakan guard ini dari "sudah ada baris absensi hari ini".
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$srcCtrl = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');

// Komentar dibuang sebelum diperiksa. Komentar di file INI dan di controller sama-sama
// menyebut "sakit"/"STATUS_IZIN" untuk MENJELASKAN aturannya — kalau ikut terhitung,
// tes ini akan menyatakan kode sudah benar padahal yang cocok cuma kalimat penjelasnya.
$tanpaKomentar = function (string $kode): string {
    $bersih = '';
    foreach (token_get_all("<?php\n" . $kode) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $bersih .= is_array($t) ? $t[1] : $t;
    }
    return $bersih;
};

$badan = function (string $src, string $tanda) use ($tanpaKomentar): string {
    $pos = strpos($src, $tanda);
    if ($pos === false) return '';
    $end  = strpos($src, 'public function', $pos + 20);
    $end2 = strpos($src, 'private function', $pos + 20);
    if ($end2 !== false && ($end === false || $end2 < $end)) $end = $end2;
    return $tanpaKomentar(substr($src, $pos, ($end ?: strlen($src)) - $pos));
};

$bodyForm     = $badan($srcCtrl, 'function formMasuk(');
$bodyMasuk    = $badan($srcCtrl, 'function absenMasuk(');
$bodyValidasi = $badan($srcCtrl, 'function validasiKode(');

$check('badan formMasuk() terbaca',    strlen($bodyForm) > 200,     true);
$check('badan absenMasuk() terbaca',   strlen($bodyMasuk) > 500,    true);
$check('badan validasiKode() terbaca', strlen($bodyValidasi) > 100, true);

// ═══════════════════════════════════════════════════════════
// 1. Helper bersama tetap satu-satunya definisi "ada izin hari ini"
// ═══════════════════════════════════════════════════════════
$check('helper bersama adaIzinHariIni() masih ada',
    str_contains($srcCtrl, 'function adaIzinHariIni('), true);

// ═══════════════════════════════════════════════════════════
// 2. formMasuk(): layar isi kode ditutup kalau hari ini izin
// ═══════════════════════════════════════════════════════════
$check('formMasuk() memeriksa izin lewat helper bersama',
    str_contains($bodyForm, 'adaIzinHariIni('), true);

$posIzinForm = strpos($bodyForm, 'adaIzinHariIni(');
$posViewForm = strpos($bodyForm, 'return view(');
$check('pemeriksaan izin mendahului tampilnya layar isi kode',
    $posIzinForm !== false && $posViewForm !== false && $posIzinForm < $posViewForm, true);
$check('formMasuk() memulangkan karyawan ke halaman absensi (bukan layar kosong)',
    (bool) preg_match('/adaIzinHariIni\(.{0,400}?redirect\(\)->route\(\x27absensi\.index\x27\)/s', $bodyForm), true);

// ═══════════════════════════════════════════════════════════
// 3. validasiKode(): kode lama tidak lagi dijawab valid
// ═══════════════════════════════════════════════════════════
$check('validasiKode() memeriksa izin lewat helper bersama',
    str_contains($bodyValidasi, 'adaIzinHariIni('), true);

$posIzinVal = strpos($bodyValidasi, 'adaIzinHariIni(');
$posJson    = strpos($bodyValidasi, "'valid'");
$check('pemeriksaan izin mendahului jawaban valid/tidak',
    $posIzinVal !== false && $posJson !== false && $posIzinVal < $posJson, true);
$check('validasiKode() punya jalan pulang valid=false',
    (bool) preg_match('/\x27valid\x27\s*=>\s*false/', $bodyValidasi), true);

// ═══════════════════════════════════════════════════════════
// 4. absenMasuk(): ditolak SEBELUM GPS, SEBELUM unggah foto, sebelum menimpa baris
// ═══════════════════════════════════════════════════════════
$check('absenMasuk() memeriksa izin lewat helper bersama',
    str_contains($bodyMasuk, 'adaIzinHariIni('), true);

$posIzin   = strpos($bodyMasuk, 'adaIzinHariIni(');
$posGps    = strpos($bodyMasuk, 'hitungJarak(');
$posFoto   = strpos($bodyMasuk, 'simpanFotoBase64(');
$posSimpan = strpos($bodyMasuk, 'updateOrCreate(');

$check('pemeriksaan izin mendahului perhitungan GPS',
    $posIzin !== false && $posGps !== false && $posIzin < $posGps, true);
$check('pemeriksaan izin mendahului unggah foto ke R2',
    $posIzin !== false && $posFoto !== false && $posIzin < $posFoto, true);
$check('pemeriksaan izin mendahului updateOrCreate (yang menimpa baris izin)',
    $posIzin !== false && $posSimpan !== false && $posIzin < $posSimpan, true);
$check('absenMasuk() menolak lewat JSON success=false (bukan redirect)',
    (bool) preg_match('/adaIzinHariIni\(.{0,600}?response\(\)->json\(\[\x27success\x27\s*=>\s*false/s', $bodyMasuk), true);

// ═══════════════════════════════════════════════════════════
// 5. Tidak ada salinan daftar status kedua di ketiga pintu ini
// ═══════════════════════════════════════════════════════════
foreach ([['formMasuk', $bodyForm], ['absenMasuk', $bodyMasuk], ['validasiKode', $bodyValidasi]] as [$nama, $badanKode]) {
    $check("{$nama}() tidak menyalin daftar status izin (pakai helper, bukan STATUS_IZIN sendiri)",
        str_contains($badanKode, 'STATUS_IZIN'), false);
    $check("{$nama}() tidak menyebut status 'dinas_luar' langsung",
        str_contains($badanKode, 'dinas_luar'), false);
    $check("{$nama}() tidak query IzinAbsen sendiri",
        str_contains($badanKode, 'IzinAbsen'), false);
}

// ═══════════════════════════════════════════════════════════
// 6. Alpha tidak ikut terblokir
// ═══════════════════════════════════════════════════════════
$check('alpha bukan bagian dari daftar status izin',
    in_array('alpha', KerjaHariLiburService::STATUS_IZIN, true), false);
$check('daftar status izin tetap 4 status yang itu-itu saja',
    KerjaHariLiburService::STATUS_IZIN, ['sakit', 'izin', 'cuti', 'dinas_luar']);
$check('tidak ada pintu yang memblokir berdasar "sudah ada baris absensi" (itu akan ikut menjaring alpha)',
    (bool) preg_match('/adaIzinHariIni\(.{0,300}?\x27alpha\x27/s', $bodyMasuk . $bodyForm), false);

// ═══════════════════════════════════════════════════════════
// 7. Pesan awam — karyawan harus paham kenapa ditolak
// ═══════════════════════════════════════════════════════════
$petikPesan = function (string $badanKode): string {
    $pos = strpos($badanKode, 'adaIzinHariIni(');
    if ($pos === false) return '';
    if (preg_match('/[\x27"]([^\x27"]{25,200})[\x27"]/', substr($badanKode, $pos, 600), $m)) return $m[1];
    return '';
};

foreach ([['formMasuk', $bodyForm], ['absenMasuk', $bodyMasuk]] as [$nama, $badanKode]) {
    $pesan = $petikPesan($badanKode);
    $check("{$nama}() punya pesan penolakan yang cukup panjang untuk dimengerti",
        strlen($pesan) >= 25, true);
    $check("{$nama}() pesannya menyebut keadaan yang dimaksud (izin/sakit/cuti)",
        (bool) preg_match('/izin|sakit|cuti/i', $pesan), true);
    $check("{$nama}() pesannya tidak memakai istilah teknis",
        (bool) preg_match('/null|query|updateOrCreate|exception|invalid|forbidden|403/i', $pesan), false);
}

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
