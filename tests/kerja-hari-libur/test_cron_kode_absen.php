<?php
// FILE: tests/kerja-hari-libur/test_cron_kode_absen.php
// Jalankan: php tests/kerja-hari-libur/test_cron_kode_absen.php
//
// Cron kode absen pagi (06:30) dulu menulis kodenya sendiri: SELECT dulu, kalau kosong
// baru INSERT, dengan alfabet kode disalin ulang di dalam file cron. Dua masalah:
//   1. Tidak atomik. Dua eksekusi barengan (cron-job.org pernah retry, plus Owner bisa
//      menekan "Aktifkan Masuk Hari Ini" di menit yang sama) sama-sama melihat "belum
//      ada", sama-sama INSERT -> DUA kode valid untuk satu karyawan di satu hari.
//   2. Alfabet kode dobel di dua tempat -> gampang menyimpang dari KodeAbsen.
// Sekarang lewat KodeAbsen::barisHariIniUntuk() (createOrFirst, tabrakan unique
// ditangkap DB).
//
// Yang WAJIB tidak berubah: pengiriman tetap idempotent (kode yang sudah ada TIDAK
// dikirim ulang — insiden 6 Agustus, karyawan menerima kode 4x dalam satu pagi) dan
// pengecualian tetap berlaku (owner, nonaktif, izin/sakit/cuti/dinas luar, jadwal libur).
//
// CATATAN: server ini tidak punya PDO MySQL/SQLite, jadi cron tidak bisa dijalankan
// beneran. Kontrak model diuji lewat reflection, alur cron lewat pembacaan sumbernya.
require __DIR__ . '/../bootstrap.php';

use App\Models\KodeAbsen;

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$posisi = function (string $isi, string $jarum): int {
    $p = strpos($isi, $jarum);
    return $p === false ? PHP_INT_MAX : $p;
};

// ── Kontrak model: generator atomik yang bisa ditanya "baru atau lama?" ──
$check('KodeAbsen::barisHariIniUntuk() ada (mengembalikan BARIS, bukan cuma string)',
    method_exists(KodeAbsen::class, 'barisHariIniUntuk'), true);

if (method_exists(KodeAbsen::class, 'barisHariIniUntuk')) {
    $r = new ReflectionMethod(KodeAbsen::class, 'barisHariIniUntuk');
    $check('barisHariIniUntuk() statik & publik', $r->isStatic() && $r->isPublic(), true);
    $check('barisHariIniUntuk() mengembalikan model KodeAbsen (punya wasRecentlyCreated)',
        (string) $r->getReturnType(), 'self');
    $check('barisHariIniUntuk() menerima User', (string) ($r->getParameters()[0]->getType() ?? ''), 'App\Models\User');
}

$srcModel = file_get_contents(__DIR__ . '/../../app/Models/KodeAbsen.php');
$check('barisHariIniUntuk() memakai createOrFirst (atomik lewat unique index)',
    (bool) preg_match('/function barisHariIniUntuk.*?createOrFirst/s', $srcModel), true);
$check('kodeHariIniUntuk() kini delegasi ke barisHariIniUntuk (satu jalur tulis)',
    (bool) preg_match('/function kodeHariIniUntuk.*?barisHariIniUntuk/s', $srcModel), true);
$check('generateKode() tetap satu-satunya sumber alfabet kode',
    substr_count($srcModel, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 1);

// Format kode (perilaku nyata, bukan pembacaan sumber)
$kode = KodeAbsen::generateKode();
$check('kode 6 karakter dari alfabet aman',
    (bool) preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $kode), true);

// ── Alur cron ───────────────────────────────────────────────
$cronPath = __DIR__ . '/../../public/cron-kode-absen.php';
$check('public/cron-kode-absen.php ada', file_exists($cronPath), true);
$cron = file_exists($cronPath) ? file_get_contents($cronPath) : '';

$check('cron memakai generator atomik KodeAbsen::barisHariIniUntuk()',
    str_contains($cron, 'barisHariIniUntuk'), true);
$check('cron tidak lagi menyalin alfabet kode sendiri',
    str_contains($cron, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), false);
$check('cron tidak lagi INSERT sendiri (KodeAbsen::create)',
    str_contains($cron, 'KodeAbsen::create'), false);
$check('cron tidak lagi cek-lalu-insert (SELECT dulu, INSERT belakangan)',
    (bool) preg_match('/KodeAbsen::whereDate\([^)]*\)[^;]*->first\(\)/', $cron), false);

// ── Idempotensi pengiriman (insiden 6 Agustus: kode terkirim 4x sepagi) ──
$check('cron memutuskan kirim dari wasRecentlyCreated (hanya baris yang BARU dibuat)',
    str_contains($cron, 'wasRecentlyCreated'), true);
$check('penjaga wasRecentlyCreated berada SEBELUM pengiriman Telegram',
    $posisi($cron, 'wasRecentlyCreated') < $posisi($cron, 'TelegramService::class'), true);
$check('kode yang sudah ada dilewati (continue), bukan dikirim ulang',
    (bool) preg_match('/wasRecentlyCreated.*?continue;/s', $cron), true);
$check('penghitung "sudah ada" tetap dilaporkan ke layar hasil cron',
    str_contains($cron, '$sudahAda'), true);

// ── Pengecualian yang tidak boleh hilang ────────────────────
$check('owner (level 1) tidak dikirimi kode',
    (bool) preg_match("/where\('level',\s*'!=',\s*1\)/", $cron), true);
$check('hanya karyawan berstatus aktif',
    (bool) preg_match("/where\('status',\s*'aktif'\)/", $cron), true);
$check('karyawan izin/sakit/cuti/dinas luar dikecualikan',
    (bool) preg_match("/whereIn\('status',\s*\['sakit',\s*'izin',\s*'cuti',\s*'dinas_luar'\]\)/", $cron), true);
$check('daftar pengecualian izin dipakai untuk menyaring karyawan',
    (bool) preg_match("/whereNotIn\('id',\s*\\\$offHariIni\)/", $cron), true);
$check('karyawan yang hari itu jadwal libur tetap dilewati',
    (bool) preg_match('/isLibur\(\$k,\s*\$tanggal\)/', $cron), true);
$check('karyawan belum connect Telegram tetap punya kode di DB (fallback dashboard)',
    $posisi($cron, 'barisHariIniUntuk') < $posisi($cron, 'telegram_chat_id'), true);
$check('satu karyawan error tidak menggagalkan sisanya (try/catch per karyawan)',
    (bool) preg_match('/foreach\s*\(\$karyawan as \$k\)\s*\{\s*try\s*\{/s', $cron), true);
$check('kunci rahasia cron tetap dicek (jangan jadi endpoint publik)',
    str_contains($cron, "canopi_cron_2026") && str_contains($cron, '403'), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
