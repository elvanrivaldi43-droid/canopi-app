<?php
// FILE: tests/keamanan/test_cron_auth.php
// Jalankan: php tests/keamanan/test_cron_auth.php
//
// Kunci cron TIDAK BOLEH tertulis di kode (repo GitHub PUBLIK: kunci lama `canopi_cron_2026`
// bisa dibaca siapa pun). Sekarang kunci ada di .env server (CRON_KEY) dan dicek lewat
// CronAuth. Dikunci di sini: (1) logika CronAuth — gagal TERTUTUP bila kunci server kosong/lemah,
// (2) tak ada kunci literal di skrip cron, (3) cek kunci dilakukan SETELAH Laravel dimuat
// (kunci butuh .env) dan SEBELUM pekerjaan apa pun.

require_once __DIR__ . '/../../app/Services/CronAuth.php';

use App\Services\CronAuth as C;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$kuat = str_repeat('a1b2', 6); // 24 karakter

// ── valid(): kunci server dikirim eksplisit (tanpa menyentuh environment)
check('kunci benar -> lolos', C::valid($kuat, $kuat), true);
check('kunci salah -> tolak', C::valid('salah', $kuat), false);
check('beda satu karakter -> tolak', C::valid(substr($kuat, 0, -1) . 'X', $kuat), false);
check('kunci lama yang bocor -> tolak', C::valid('canopi_cron_2026', $kuat), false);
check('kosong dikirim, kunci server kuat -> tolak', C::valid('', $kuat), false);
check('kunci server KOSONG + kiriman kosong -> tolak (gagal tertutup)', C::valid('', ''), false);
check('kunci server KOSONG + kiriman apa pun -> tolak', C::valid('apa-saja', ''), false);
check('kunci server terlalu pendek -> tolak walau cocok', C::valid('abc123', 'abc123'), false);
check('kunci server 16 karakter (batas) -> lolos', C::valid(str_repeat('z', 16), str_repeat('z', 16)), true);
check('kunci server 15 karakter -> tolak', C::valid(str_repeat('z', 15), str_repeat('z', 15)), false);

// ── skrip cron: tak ada kunci literal, cek SESUDAH bootstrap
$base = dirname(__DIR__, 2);
foreach (['cron-alpha', 'cron-kode-absen', 'cron-kpi'] as $nama) {
    $src = (string) file_get_contents("$base/public/$nama.php");
    check("$nama: tanpa kunci lama canopi_cron_2026", str_contains($src, 'canopi_cron_2026'), false);
    check("$nama: tanpa pembanding kunci literal (\$key !== '...')", (bool) preg_match('/\$key\s*[!=]==?\s*[\'"]/', $src), false);
}
foreach (['cron-alpha', 'cron-kode-absen'] as $nama) {
    $src = (string) file_get_contents("$base/public/$nama.php");
    $pBoot = strpos($src, '$kernel->bootstrap()');
    $pAuth = strpos($src, 'CronAuth::valid(');
    $pKerja = strpos($src, 'User::');
    check("$nama: memakai CronAuth::valid", $pAuth !== false, true);
    check("$nama: cek kunci SESUDAH Laravel dimuat", $pBoot !== false && $pAuth > $pBoot, true);
    check("$nama: cek kunci SEBELUM pekerjaan apa pun", $pAuth < $pKerja, true);
    check("$nama: penolakan 403 tetap ada", str_contains($src, 'http_response_code(403)'), true);
    check("$nama: ada mode cek (uji tanpa efek samping)", str_contains($src, "\$_GET['cek']"), true);
}
$kpi = (string) file_get_contents("$base/public/cron-kpi.php");
check('cron-kpi: dinonaktifkan tertutup (tak bisa dipicu siapa pun)', str_contains($kpi, 'http_response_code(503)'), true);

// ── .env.example mendokumentasikan CRON_KEY
check('.env.example memuat CRON_KEY', (bool) preg_match('/^CRON_KEY=/m', (string) file_get_contents("$base/.env.example")), true);

exit($fail ? 1 : 0);
