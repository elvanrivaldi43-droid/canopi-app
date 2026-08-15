<?php
// FILE: tests/kerja-hari-libur/test_cron_alpha_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_cron_alpha_hari_libur.php
//
// Aturan cron jam 13:00 (auto alpha untuk yang tidak masuk sama sekali):
//   - libur biasa            -> DILEWATI (tidak pernah alpha), perilaku lama
//   - libur + sudah diaktifkan masuk -> ikut alur alpha normal kalau tidak masuk
//     (karyawan sudah dijanjikan masuk & dikirimi kode; kalau menghilang harus ketahuan)
//   - baris alpha hari libur ditandai audit kerja hari libur dengan upah 0
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── Siapa yang dilewati cron alpha ──────────────────────────
$check('libur biasa (tanpa otorisasi) -> dilewati, tidak di-alpha',
    $svc->lewatiAlphaHariLibur(true, false), true);
$check('libur TAPI sudah diaktifkan masuk -> TIDAK dilewati, ikut alpha normal',
    $svc->lewatiAlphaHariLibur(true, true), false);
$check('hari kerja biasa -> tidak pernah dilewati (perilaku lama)',
    $svc->lewatiAlphaHariLibur(false, false), false);
$check('hari kerja biasa walau ada baris otorisasi nyasar -> tetap tidak dilewati',
    $svc->lewatiAlphaHariLibur(false, true), false);

// ── Isi baris alpha yang dibuat cron ────────────────────────
$check('alpha di hari libur yang diaktifkan: ditandai kerja hari libur, upah 0',
    $svc->atributAlphaHariLibur(true),
    ['kerja_hari_libur' => true, 'upah_hari_libur' => 0.0]);
$check('alpha hari kerja biasa: tidak ditandai, upah 0',
    $svc->atributAlphaHariLibur(false),
    ['kerja_hari_libur' => false, 'upah_hari_libur' => 0.0]);

// Konsistensi dengan aturan hitung hari kerja libur:
// baris alpha berpenanda TIDAK boleh ikut dihitung sebagai hari kerja libur yang dibayar.
$check('alpha berpenanda tetap upah 0 lewat upahHariLibur()',
    $svc->upahHariLibur(200000, 'alpha'), 0.0);

// ── Cron benar-benar memakai aturan di atas (bukan logic kembar di file cron) ──
$cron = file_get_contents(__DIR__ . '/../../public/cron-alpha.php');
$check('cron-alpha.php memanggil lewatiAlphaHariLibur()',
    str_contains($cron, 'lewatiAlphaHariLibur'), true);
$check('cron-alpha.php memakai atributAlphaHariLibur() saat membuat baris alpha',
    str_contains($cron, 'atributAlphaHariLibur'), true);
$check('cron-alpha.php mengecek otorisasi KerjaHariLibur',
    str_contains($cron, 'KerjaHariLibur'), true);
$check('cron-alpha.php TIDAK lagi melewati semua yang libur tanpa syarat',
    str_contains($cron, '&& !$sedangLibur'), false);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
