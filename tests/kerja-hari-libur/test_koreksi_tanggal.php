<?php
// FILE: tests/kerja-hari-libur/test_koreksi_tanggal.php
// Jalankan: php tests/kerja-hari-libur/test_koreksi_tanggal.php
//
// TASK 5 — Koreksi manual memakai TANGGAL REKAP YANG DIPILIH, bukan hari ini.
//
// Cacat yang ditutup: halaman rekap punya filter tanggal, tapi form Koreksi di
// dalamnya TIDAK mengirim tanggal sama sekali. `koreksiManual()` lalu jatuh ke
// `$request->tanggal ?? today()`. Jadi kalau Owner memfilter ke 10 Agustus dan
// mencatat absen manual seseorang di situ, barisnya diam-diam ditulis untuk HARI INI.
//
// Akibatnya berlapis: tanggal yang mau diperbaiki tetap kosong (alpha tetap alpha),
// hari ini malah dapat baris palsu, dan kalau tanggal itu hari libur karyawan,
// pemeriksaan "kerja hari libur" pun dilakukan atas tanggal yang salah — termasuk
// pembuatan baris otorisasi berupah di tanggal yang keliru.
require __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AbsensiController;
use Carbon\Carbon;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Acuan tetap supaya hasil tes tidak berubah tiap hari dijalankan.
$hariIni = Carbon::parse('2026-08-15');
$tolak   = fn($tgl) => AbsensiController::alasanTolakTanggalKoreksi($tgl, $hariIni);
$boleh   = fn($tgl) => $tolak($tgl) === null;

// ═══════════════════════════════════════════════════════════
// 1. Tanggal rekap yang dipilih DITERIMA apa adanya
// ═══════════════════════════════════════════════════════════
$check('tanggal masa lalu (10 Agustus) diterima', $boleh('2026-08-10'), true);
$check('hari ini diterima',                        $boleh('2026-08-15'), true);
$check('tanggal jauh di masa lalu diterima',       $boleh('2026-01-02'), true);

// ═══════════════════════════════════════════════════════════
// 2. WAJIB format Y-m-d — tidak boleh kosong/relatif/ngawur
// ═══════════════════════════════════════════════════════════
$check('tanggal kosong DITOLAK (tidak diam-diam jatuh ke hari ini)', $boleh(null), false);
$check('string kosong DITOLAK',              $boleh(''), false);
$check('format d/m/Y DITOLAK',               $boleh('10/08/2026'), false);
$check('format d-m-Y DITOLAK',               $boleh('10-08-2026'), false);
$check('tanggal dengan jam DITOLAK',         $boleh('2026-08-10 09:00:00'), false);
$check('kata relatif "today" DITOLAK',       $boleh('today'), false);
$check('kata relatif "yesterday" DITOLAK',   $boleh('yesterday'), false);
$check('teks ngawur DITOLAK',                $boleh('bukan-tanggal'), false);
$check('tanggal tidak ada di kalender (31 Februari) DITOLAK', $boleh('2026-02-31'), false);
$check('bulan 13 DITOLAK',                   $boleh('2026-13-01'), false);
$check('0000-00-00 DITOLAK',                 $boleh('0000-00-00'), false);

// ═══════════════════════════════════════════════════════════
// 3. Tanggal MASA DEPAN ditolak
//    Mencatat kehadiran untuk hari yang belum terjadi tidak masuk akal,
//    dan bisa dipakai memberi upah hari libur di tanggal yang belum ada.
// ═══════════════════════════════════════════════════════════
$check('besok DITOLAK',            $boleh('2026-08-16'), false);
$check('bulan depan DITOLAK',      $boleh('2026-09-01'), false);
$check('tahun depan DITOLAK',      $boleh('2027-01-01'), false);

// Pesan tolaknya harus jelas, bukan sekadar "invalid".
$check('alasan tolak masa depan menyebut "depan"',
    str_contains(strtolower((string) $tolak('2026-08-16')), 'depan'), true);
$check('alasan tolak format bukan null',
    is_string($tolak('10/08/2026')), true);

// ═══════════════════════════════════════════════════════════
// 4. Controller memakai tanggal itu — TIDAK jatuh ke today()
// ═══════════════════════════════════════════════════════════
$srcAbsen = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');

$posManual  = strpos($srcAbsen, 'function koreksiManual(');
$posBerikut = $posManual === false ? false : strpos($srcAbsen, 'public static function', $posManual + 20);
$bodyManual = $posManual === false ? '' : substr($srcAbsen, $posManual, ($posBerikut ?: strlen($srcAbsen)) - $posManual);

$check('badan koreksiManual() terbaca', strlen($bodyManual) > 500, true);

$check('koreksiManual() TIDAK lagi jatuh ke today() saat tanggal kosong',
    (bool) preg_match('/\$request->tanggal\s*\?\?\s*today\(\)/', $bodyManual), false);
$check('koreksiManual() memanggil penjaga tanggal',
    str_contains($bodyManual, 'alasanTolakTanggalKoreksi('), true);

// Penjaga dijalankan SEBELUM baris absensi/otorisasi ditulis — kalau tidak, permintaan
// yang ditolak tetap meninggalkan baris nyasar di tanggal yang salah.
$posGuard  = strpos($bodyManual, 'alasanTolakTanggalKoreksi(');
$posCreate = strpos($bodyManual, 'Absensi::create(');
$posOtor   = strpos($bodyManual, 'KerjaHariLibur::firstOrCreate(');
$check('penjaga tanggal dicek SEBELUM Absensi::create()',
    $posGuard !== false && $posCreate !== false && $posGuard < $posCreate, true);
$check('penjaga tanggal dicek SEBELUM baris otorisasi dibuat',
    $posGuard !== false && $posOtor !== false && $posGuard < $posOtor, true);

// ═══════════════════════════════════════════════════════════
// 5. View rekap MENGIRIM tanggal yang sedang difilter
// ═══════════════════════════════════════════════════════════
$srcRekap = file_get_contents($base . '/resources/views/absensi/rekap.blade.php');

$check('form koreksi mengirim field `tanggal`',
    (bool) preg_match('/name="tanggal"[^>]*value="\{\{\s*\$tanggal\s*\}\}"/', $srcRekap), true);
$check('field tanggal berada di dalam form koreksi (bukan cuma filter GET)',
    substr_count($srcRekap, 'name="tanggal"'), 2); // 1 filter GET + 1 di form koreksi

// Field-nya harus setelah pembuka <form id="formKoreksi"> supaya ikut ter-POST.
$posForm = strpos($srcRekap, 'id="formKoreksi"');
$posTgl  = strpos($srcRekap, 'name="tanggal"', $posForm ?: 0);
$check('field tanggal berada SETELAH pembuka form koreksi',
    $posForm !== false && $posTgl !== false && $posTgl > $posForm, true);

// ═══════════════════════════════════════════════════════════
// 6. Owner-only tetap — koreksi mengubah nominal gaji karyawan lain
// ═══════════════════════════════════════════════════════════
$json   = shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null');
$routes = json_decode((string) $json, true);
$byName = [];
foreach ((array) $routes as $r) {
    if (!empty($r['name'])) $byName[$r['name']] = $r;
}
foreach (['absensi.koreksi', 'absensi.koreksi-manual'] as $rt) {
    $check("route `$rt` tetap Owner-only (level:1)",
        in_array('level:1', $byName[$rt]['middleware'] ?? [], true), true);
}

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
