<?php
// FILE: tests/kerja-hari-libur/test_kerja_hari_libur.php
// Jalankan: php tests/kerja-hari-libur/test_kerja_hari_libur.php
// Tes murni (tanpa database) untuk model otorisasi kerja hari libur + snapshot nominal.
require __DIR__ . '/../bootstrap.php';

use App\Models\KerjaHariLibur;
use App\Services\KerjaHariLiburService;
use Carbon\Carbon;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── Snapshot nominal (murni) ────────────────────────────────
// Snapshot dipakai supaya perubahan tarif karyawan di kemudian hari TIDAK mengubah histori.
$check('snapshot ambil gaji harian & uang makan apa adanya',
    $svc->snapshot(200000, 25000),
    ['gaji_harian_snapshot' => 200000.0, 'uang_makan_snapshot' => 25000.0]);

$check('snapshot: null dianggap 0 (bukan error/null)',
    $svc->snapshot(null, null),
    ['gaji_harian_snapshot' => 0.0, 'uang_makan_snapshot' => 0.0]);

$check('snapshot: nilai string dari DB dinormalkan jadi float',
    $svc->snapshot('180000.00', '20000.00'),
    ['gaji_harian_snapshot' => 180000.0, 'uang_makan_snapshot' => 20000.0]);

$check('snapshot: nominal negatif ditolak jadi 0 (data kotor tidak boleh bikin upah minus)',
    $svc->snapshot(-5000, -1000),
    ['gaji_harian_snapshot' => 0.0, 'uang_makan_snapshot' => 0.0]);

// ── Kunci unik user+tanggal ─────────────────────────────────
// Satu karyawan hanya boleh punya SATU otorisasi per tanggal.
// Kunci ini yang dipakai firstOrCreate di controller — kalau kuncinya salah,
// klik ulang bikin baris (dan notifikasi) kedua.
$check('kunciUnik dari string tanggal',
    KerjaHariLibur::kunciUnik(7, '2026-08-15'),
    ['user_id' => 7, 'tanggal' => '2026-08-15']);

$check('kunciUnik dari objek Carbon -> dinormalkan ke Y-m-d (bukan datetime)',
    KerjaHariLibur::kunciUnik(7, Carbon::create(2026, 8, 15, 13, 45, 0)),
    ['user_id' => 7, 'tanggal' => '2026-08-15']);

$check('kunciUnik: user beda -> kunci beda (otorisasi tidak bocor antar karyawan)',
    KerjaHariLibur::kunciUnik(8, '2026-08-15') === KerjaHariLibur::kunciUnik(7, '2026-08-15'),
    false);

// ── Konfigurasi model ───────────────────────────────────────
$m = new KerjaHariLibur();
$check('nama tabel', $m->getTable(), 'kerja_hari_libur');
$check('kolom wajib ada di fillable',
    array_values(array_intersect(
        ['user_id', 'tanggal', 'diaktifkan_oleh', 'gaji_harian_snapshot', 'uang_makan_snapshot'],
        $m->getFillable()
    )),
    ['user_id', 'tanggal', 'diaktifkan_oleh', 'gaji_harian_snapshot', 'uang_makan_snapshot']);
$check('tanggal di-cast date', $m->getCasts()['tanggal'] ?? null, 'date');

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
