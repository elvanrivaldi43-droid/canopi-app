<?php
// FILE: tests/kerja-hari-libur/test_kirim_kode_izin.php
// Jalankan: php tests/kerja-hari-libur/test_kirim_kode_izin.php
//
// FIX WAVE — "Buat & Kirim Kode" tidak boleh mengabaikan izin/sakit/cuti/dinas luar.
//
// Cacatnya: `AbsensiController::aktifkanKerjaHariLibur()` sudah memeriksa bentrok izin
// (baris absensi berstatus sakit/izin/cuti/dinas_luar HARI INI, atau ajuan IzinAbsen
// yang masih pending/approved) — tapi `kirimKodeAbsen()` TIDAK. Padahal keduanya
// sama-sama pintu yang membuat kode absen dan mengirimkannya lewat Telegram.
//
// Akibatnya karyawan yang hari itu sakit/cuti/dinas luar tetap bisa dibuatkan kode
// dengan satu klik, lalu dikirimi pesan "kode absen kamu hari ini" — mengundang dia
// absen di hari yang sudah tercatat izin, dan menabrak alasan yang persis sama dengan
// yang sudah dijaga di jalur aktivasi (cron pagi pun sudah melewati mereka sejak
// 6 Agustus). Kegagalan senyap: tidak ada error, kodenya benar-benar terbuat.
//
// Aturan barunya:
//   - Definisi "ada izin hari ini" ditulis SATU kali (helper bersama), dipakai jalur
//     aktivasi MAUPUN jalur kirim kode manual — biar tidak bisa diperbaiki sebelah.
//   - Kode DITOLAK SEBELUM dibuat/dikirim (guard mendahului barisHariIniUntuk()).
//   - Halaman "Kode Absen Hari Ini" menampilkan status izinnya dan MENYEMBUNYIKAN
//     kedua tombol (Aktifkan & Buat/Kirim), biar tidak jadi jebakan klik.
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
$srcView = file_get_contents($base . '/resources/views/absensi/kode-hari-ini.blade.php');

$badan = function (string $src, string $tanda): string {
    $pos = strpos($src, $tanda);
    if ($pos === false) return '';
    $end = strpos($src, 'public function', $pos + 20);
    $end2 = strpos($src, 'private function', $pos + 20);
    if ($end2 !== false && ($end === false || $end2 < $end)) $end = $end2;
    return substr($src, $pos, ($end ?: strlen($src)) - $pos);
};

// ═══════════════════════════════════════════════════════════
// 1. Daftar status izin ditulis SATU kali sebagai konstanta
// ═══════════════════════════════════════════════════════════
$check('KerjaHariLiburService punya konstanta STATUS_IZIN',
    defined(KerjaHariLiburService::class . '::STATUS_IZIN'), true);
$check('STATUS_IZIN = sakit/izin/cuti/dinas_luar',
    defined(KerjaHariLiburService::class . '::STATUS_IZIN') ? KerjaHariLiburService::STATUS_IZIN : null,
    ['sakit', 'izin', 'cuti', 'dinas_luar']);

// ═══════════════════════════════════════════════════════════
// 2. Label izin untuk layar (murni, tanpa database)
// ═══════════════════════════════════════════════════════════
if (method_exists(KerjaHariLiburService::class, 'labelStatusIzin')) {
    $check('status absensi `sakit` -> label "Sakit"',
        KerjaHariLiburService::labelStatusIzin('sakit', false), 'Sakit');
    $check('status absensi `cuti` -> label "Cuti"',
        KerjaHariLiburService::labelStatusIzin('cuti', false), 'Cuti');
    $check('status absensi `dinas_luar` -> label "Dinas Luar"',
        KerjaHariLiburService::labelStatusIzin('dinas_luar', false), 'Dinas Luar');
    $check('status absensi `izin` -> label "Izin"',
        KerjaHariLiburService::labelStatusIzin('izin', false), 'Izin');
    // Ajuan yang masih berjalan tetap harus terlihat, walau baris absensinya belum ada.
    $check('belum ada baris absensi tapi ada ajuan izin pending/approved -> tetap berlabel',
        KerjaHariLiburService::labelStatusIzin(null, true), 'Ajuan Izin');
    $check('status kerja biasa (hadir) tidak dianggap izin',
        KerjaHariLiburService::labelStatusIzin('hadir', false), null);
    $check('alpha BUKAN izin (dia tetap boleh dibuatkan kode)',
        KerjaHariLiburService::labelStatusIzin('alpha', false), null);
    $check('tanpa apa pun -> null',
        KerjaHariLiburService::labelStatusIzin(null, false), null);
    // Baris absensi menang atas ajuan: yang sudah tercatat lebih pasti dari yang diajukan.
    $check('baris absensi menang atas ajuan',
        KerjaHariLiburService::labelStatusIzin('sakit', true), 'Sakit');
} else {
    $check('KerjaHariLiburService punya labelStatusIzin()', false, true);
}

// ═══════════════════════════════════════════════════════════
// 3. Satu definisi "ada izin hari ini", dipakai KEDUA pintu
// ═══════════════════════════════════════════════════════════
$check('AbsensiController punya helper bersama adaIzinHariIni()',
    str_contains($srcCtrl, 'function adaIzinHariIni('), true);

$bodyHelper = $badan($srcCtrl, 'function adaIzinHariIni(');
$check('helper memeriksa baris absensi berstatus izin (STATUS_IZIN)',
    str_contains($bodyHelper, 'STATUS_IZIN'), true);
$check('helper memeriksa ajuan IzinAbsen yang masih berjalan',
    str_contains($bodyHelper, 'IzinAbsen'), true);
$check('helper menghitung ajuan pending sebagai penghalang',
    str_contains($bodyHelper, "'pending'"), true);
$check('helper menghitung ajuan approved sebagai penghalang',
    str_contains($bodyHelper, "'approved'"), true);

$bodyAkt   = $badan($srcCtrl, 'function aktifkanKerjaHariLibur(');
$bodyKirim = $badan($srcCtrl, 'function kirimKodeAbsen(');

$check('badan aktifkanKerjaHariLibur() terbaca', strlen($bodyAkt) > 300, true);
$check('badan kirimKodeAbsen() terbaca', strlen($bodyKirim) > 300, true);

$check('jalur aktivasi memakai helper bersama (bukan query kembar)',
    str_contains($bodyAkt, 'adaIzinHariIni('), true);
$check('jalur kirim kode manual memakai helper bersama yang SAMA',
    str_contains($bodyKirim, 'adaIzinHariIni('), true);

// ═══════════════════════════════════════════════════════════
// 4. Kode DITOLAK sebelum dibuat & sebelum dikirim
// ═══════════════════════════════════════════════════════════
$posIzin  = strpos($bodyKirim, 'adaIzinHariIni(');
$posBuat  = strpos($bodyKirim, 'barisHariIniUntuk(');
$posKirim = strpos($bodyKirim, 'TelegramService');

$check('pemeriksaan izin mendahului PEMBUATAN kode',
    $posIzin !== false && $posBuat !== false && $posIzin < $posBuat, true);
$check('pemeriksaan izin mendahului PENGIRIMAN Telegram',
    $posIzin !== false && $posKirim !== false && $posIzin < $posKirim, true);

// Pesan tolaknya harus menyebut alasannya, biar Owner/Mandor tidak bingung.
$check('kirimKodeAbsen menolak dengan pesan yang menyebut izin',
    (bool) preg_match('/error\s*\',\s*"[^"]*izin/i', $bodyKirim)
        || (bool) preg_match("/error\s*',\s*\"[^\"]*sakit/i", $bodyKirim), true);

// ═══════════════════════════════════════════════════════════
// 5. Halaman kode absen: status izin terlihat, tombol disembunyikan
// ═══════════════════════════════════════════════════════════
$bodyGet = $badan($srcCtrl, 'function kodeHariIni(');
$check('kodeHariIni() mengirim label izin ke view',
    str_contains($bodyGet, "'izin'"), true);
$check('kodeHariIni() memakai labelStatusIzin() (satu sumber label)',
    str_contains($bodyGet, 'labelStatusIzin('), true);
// Data izin diambil sekali untuk semua karyawan (bukan query di dalam loop).
$check('data izin diambil di luar perulangan (tanpa query per baris)',
    strpos($bodyGet, 'IzinAbsen') !== false
        && strpos($bodyGet, 'IzinAbsen') < strpos($bodyGet, '->map('), true);

$check('view menampilkan label izin',
    (bool) preg_match('/@if\s*\(\s*\$d\[\x27izin\x27\]\s*\)/', $srcView), true);

$posViewIzin  = strpos($srcView, "\$d['izin']");
$posFormAkt   = strpos($srcView, "route('absensi.kerja-hari-libur'");
$posFormKode  = strpos($srcView, "route('absensi.kirim-kode'");

$check('cabang izin diperiksa SEBELUM tombol Aktifkan',
    $posViewIzin !== false && $posFormAkt !== false && $posViewIzin < $posFormAkt, true);
$check('cabang izin diperiksa SEBELUM tombol Buat & Kirim Kode',
    $posViewIzin !== false && $posFormKode !== false && $posViewIzin < $posFormKode, true);
$check('cabang libur turun jadi @elseif (jadi izin & libur tidak dua-duanya tampil)',
    (bool) preg_match('/@elseif\s*\(\s*\$d\[\x27libur\x27\]\s*&&\s*!\s*\$d\[\x27kerja_libur\x27\]\s*\)/', $srcView), true);

// Jumlah form TIDAK bertambah — cabang izin tidak boleh menambah tombol baru.
$check('view tetap punya 2 form (aktivasi + kirim kode)', substr_count($srcView, '@csrf'), 2);

// Aturan project: SVG, bukan emoji (emoji bisa korup di server).
$potongan = $posViewIzin === false ? '' : substr($srcView, $posViewIzin, 700);
$check('badge izin tidak memakai emoji (aturan project: SVG saja)',
    (bool) preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $potongan), false);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
