<?php
// FILE: tests/kerja-hari-libur/test_koreksi_status.php
// Jalankan: php tests/kerja-hari-libur/test_koreksi_status.php
//
// FIX WAVE — status koreksi absen harus dibatasi daftar yang dikenal mesin gaji.
//
// Cacatnya: `koreksi()` dan `koreksiManual()` memvalidasi status dengan
// `required|string` — apa pun diterima. Padahal `nominalKoreksi()` mengembalikan
// null untuk status yang tidak dikenal, dan controller lalu MEMPERTAHANKAN nominal
// lama sambil tetap MENYIMPAN status ngawurnya. Hasilnya baris absensi berstatus
// mis. "hadirr" yang: tidak terhitung hadir, tidak terhitung alpha, tidak terhitung
// izin — hilang dari semua statistik & KPI, tapi tetap membawa nominal rupiah dari
// status sebelumnya. Salah ketik / POST manual = data gaji yang diam-diam sesat.
//
// Aturan barunya: daftar status ditulis SATU kali sebagai konstanta, dipakai oleh
// aturan validasi KEDUA endpoint dan oleh dropdown di layar rekap — jadi opsi UI
// dan aturan server tidak bisa lagi berbeda diam-diam ("cuti" selama ini ada di
// mesin gaji tapi tidak pernah ditawarkan di dropdown).
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$DIHARAPKAN = ['hadir', 'telat', 'setengah_hari', 'sakit', 'izin', 'cuti', 'dinas_luar', 'alpha'];

// ═══════════════════════════════════════════════════════════
// 1. Daftar status koreksi = satu konstanta
// ═══════════════════════════════════════════════════════════
$check('KerjaHariLiburService punya konstanta STATUS_KOREKSI',
    defined(KerjaHariLiburService::class . '::STATUS_KOREKSI'), true);
$check('STATUS_KOREKSI berisi 8 status yang dikenal mesin gaji',
    defined(KerjaHariLiburService::class . '::STATUS_KOREKSI') ? KerjaHariLiburService::STATUS_KOREKSI : null,
    $DIHARAPKAN);

// Tiap status di daftar itu WAJIB dikenal nominalKoreksi() — kalau ada yang tidak,
// aturan validasinya justru meloloskan status yang bikin nominal ngambang.
$svc = new KerjaHariLiburService();
foreach ($DIHARAPKAN as $st) {
    $check("nominalKoreksi() mengenal status `$st` (tidak null)",
        $svc->nominalKoreksi($st, 200000, 20000, 0) !== null, true);
}
$check('status ngawur tetap ditolak mesin gaji (null)',
    $svc->nominalKoreksi('hadirr', 200000, 20000, 0), null);

// ═══════════════════════════════════════════════════════════
// 2. Aturan validasi Laravel dibangun dari konstanta itu
// ═══════════════════════════════════════════════════════════
if (method_exists(KerjaHariLiburService::class, 'aturanStatusKoreksi')) {
    $check('aturanStatusKoreksi() = required|in:<daftar>',
        KerjaHariLiburService::aturanStatusKoreksi(),
        'required|in:' . implode(',', $DIHARAPKAN));
} else {
    $check('KerjaHariLiburService punya aturanStatusKoreksi()', false, true);
}

// ═══════════════════════════════════════════════════════════
// 3. KEDUA endpoint koreksi memakai aturan itu
// ═══════════════════════════════════════════════════════════
$srcCtrl = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');

$badan = function (string $src, string $tanda): string {
    $pos = strpos($src, $tanda);
    if ($pos === false) return '';
    $end  = strpos($src, 'public function', $pos + 20);
    $end2 = strpos($src, 'private function', $pos + 20);
    if ($end2 !== false && ($end === false || $end2 < $end)) $end = $end2;
    return substr($src, $pos, ($end ?: strlen($src)) - $pos);
};

foreach (['koreksi' => 'function koreksi(', 'koreksiManual' => 'function koreksiManual('] as $nama => $tanda) {
    $body = $badan($srcCtrl, $tanda);
    $check("badan $nama() terbaca", strlen($body) > 200, true);
    $check("$nama(): status TIDAK lagi divalidasi `required|string` polos",
        (bool) preg_match("/'status'\s*=>\s*'required\|string'/", $body), false);
    $check("$nama(): status divalidasi dari daftar terpusat",
        str_contains($body, 'aturanStatusKoreksi('), true);
}

// ═══════════════════════════════════════════════════════════
// 4. Dropdown di layar rekap = daftar yang sama persis
//
// Kalau UI menawarkan status yang ditolak server, Owner dapat error tanpa sebab
// yang jelas. Kalau server menerima status yang tidak ada di UI (kasus `cuti`
// selama ini), ada jalur data yang tidak pernah terlihat/teruji dari layar.
// ═══════════════════════════════════════════════════════════
$srcView = file_get_contents($base . '/resources/views/absensi/rekap.blade.php');

$posSelect = strpos($srcView, 'name="status"');
$posTutup  = $posSelect === false ? false : strpos($srcView, '</select>', $posSelect);
$blokSelect = $posSelect === false ? '' : substr($srcView, $posSelect, ($posTutup ?: strlen($srcView)) - $posSelect);

preg_match_all('/<option value="([a-z_]+)"/', $blokSelect, $m);
$opsi = $m[1] ?? [];

$check('dropdown status koreksi terbaca dari view', count($opsi) > 0, true);
$check('opsi dropdown = STATUS_KOREKSI (urutan & isi sama persis)', $opsi, $DIHARAPKAN);
$check('opsi `cuti` kini ditawarkan di layar (dulu hanya diterima diam-diam)',
    in_array('cuti', $opsi, true), true);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
