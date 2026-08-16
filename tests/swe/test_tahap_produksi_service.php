<?php
// FILE: tests/swe/test_tahap_produksi_service.php
// Jalankan: php tests/swe/test_tahap_produksi_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\TahapProduksiService;

$svc = new TahapProduksiService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Template dilewatkan sebagai array asosiatif (pure — tidak sentuh DB sama sekali)
$templates = collect([
    ['id' => 1, 'jenis_project' => 'Kanopi Standar', 'is_active' => true],
    ['id' => 2, 'jenis_project' => 'Pagar',          'is_active' => true],
    ['id' => 3, 'jenis_project' => 'Kanopi Standar', 'is_active' => false], // nonaktif, harus dilewati
]);

$hasil = $svc->pilihTemplateCocok($templates, 'Kanopi Standar');
$check('cocok & aktif -> ketemu id 1', $hasil['id'] ?? null, 1);

$hasil2 = $svc->pilihTemplateCocok($templates, 'Mezzanine');
$check('tidak ada yang cocok -> null', $hasil2, null);

$hasil3 = $svc->pilihTemplateCocok($templates, 'Pagar');
$check('cocok satu-satunya -> id 2', $hasil3['id'] ?? null, 2);

// Dua template aktif cocok jenis_project sama -> pilih id TERBESAR (paling baru dibuat)
$templatesDobel = collect([
    ['id' => 5, 'jenis_project' => 'Tralis', 'is_active' => true],
    ['id' => 9, 'jenis_project' => 'Tralis', 'is_active' => true],
]);
$hasil4 = $svc->pilihTemplateCocok($templatesDobel, 'Tralis');
$check('dua kandidat aktif -> pilih id terbesar', $hasil4['id'] ?? null, 9);

// Nonaktif semua -> null, bukan ke-skip jadi ambil yang nonaktif
$templatesNonaktif = collect([
    ['id' => 7, 'jenis_project' => 'Awning', 'is_active' => false],
]);
$check('cuma ada template nonaktif -> null',
    $svc->pilihTemplateCocok($templatesNonaktif, 'Awning'), null);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
