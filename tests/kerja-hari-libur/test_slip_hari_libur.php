<?php
// FILE: tests/kerja-hari-libur/test_slip_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_slip_hari_libur.php
//
// Slip gaji (rincian uang makan tanggal 1-15) dulu menebak "Minggu = libur".
// Padahal jadwal libur sudah per-karyawan (bisa Selasa), plus ada libur nasional
// dan hari libur yang DIMASUKI. Akibat tebakan itu:
//   - karyawan yang liburnya Selasa: barisnya salah label
//   - kerja di hari Minggu: statusnya ketutup label "Libur"
// Kebijakan KPI/penyebut hari kerja TIDAK diubah di sini — ini murni tampilan.
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Argumen: (isLiburJadwal, status absensi atau null, kerja_hari_libur)

// ── Hari libur menurut JADWAL karyawan (bukan cuma Minggu) ──
$check('hari libur tanpa absensi -> label libur, baris diredupkan',
    $svc->barisHariSlip(true, null, false),
    ['status' => 'libur', 'redup' => true, 'kerja_libur' => false]);

$check('hari kerja tanpa absensi -> strip, tidak diredupkan',
    $svc->barisHariSlip(false, null, false),
    ['status' => '-', 'redup' => false, 'kerja_libur' => false]);

$check('hari kerja + hadir -> status hadir',
    $svc->barisHariSlip(false, 'hadir', false),
    ['status' => 'hadir', 'redup' => false, 'kerja_libur' => false]);

// ── Hari libur yang DIMASUKI ────────────────────────────────
$check('kerja di hari libur -> status kerjanya yang tampil, BUKAN label "Libur"',
    $svc->barisHariSlip(true, 'hadir', true),
    ['status' => 'hadir', 'redup' => false, 'kerja_libur' => true]);

$check('telat di hari libur -> tetap tampil telat + ditandai kerja hari libur',
    $svc->barisHariSlip(true, 'telat', true),
    ['status' => 'telat', 'redup' => false, 'kerja_libur' => true]);

$check('diaktifkan tapi tidak masuk (alpha berpenanda) -> alpha tetap kelihatan, tidak disamarkan jadi Libur',
    $svc->barisHariSlip(true, 'alpha', true),
    ['status' => 'alpha', 'redup' => false, 'kerja_libur' => true]);

// ── Data lama / kasus campur ────────────────────────────────
$check('hari libur dengan absensi lama tanpa penanda -> status aslinya yang tampil',
    $svc->barisHariSlip(true, 'izin', false),
    ['status' => 'izin', 'redup' => true, 'kerja_libur' => false]);

$check('penanda kerja libur tapi status kosong -> tidak crash',
    $svc->barisHariSlip(true, null, true),
    ['status' => '-', 'redup' => false, 'kerja_libur' => true]);

// ── Blade slip benar-benar memakai jadwal, bukan hardcode Minggu ──
$bladePath = __DIR__ . '/../../resources/views/penggajian/slip.blade.php';
$blade     = file_get_contents($bladePath);

$check("slip tidak lagi memutuskan libur dari \$dayName === 'Sunday'",
    (bool) preg_match("/\\\$isLibur\s*=\s*\\\$dayName\s*===\s*'Sunday'/", $blade), false);
$check('slip memakai peta libur per-karyawan dari controller',
    str_contains($blade, 'petaLibur'), true);
$check('slip memakai aturan baris dari service (satu sumber, bukan logic kembar di view)',
    str_contains($blade, 'barisHariSlip'), true);

$penggajian = file_get_contents(__DIR__ . '/../../app/Http/Controllers/PenggajianController.php');
$check('PenggajianController mengirim peta libur ke view slip',
    str_contains($penggajian, 'petaLiburBulan') && str_contains($penggajian, 'petaLibur'), true);

// ── Blade beneran bisa dikompilasi (artisan view:cache terhalang extension dom) ──
$compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
$php      = $compiler->compileString($blade);
$tmp      = tempnam(sys_get_temp_dir(), 'slip_') . '.php';
file_put_contents($tmp, $php);
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
@unlink($tmp);
$check('slip.blade.php hasil kompilasi lolos php -l', $rc, 0);
if ($rc !== 0) echo "   " . implode("\n   ", $out) . "\n";

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
