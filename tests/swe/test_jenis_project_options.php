<?php
// FILE: tests/swe/test_jenis_project_options.php
// Jalankan: php tests/swe/test_jenis_project_options.php
require __DIR__ . '/../bootstrap.php';

use App\Models\Project;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Sama persis peta match() lama di RabController::approve() — memastikan tidak ada
// satu kode pun yang kelewat/berubah pas dipindah ke konstanta ini.
$expected = [
    'KANOPI_STD'     => 'Kanopi Standar',
    'KANOPI_DINDING' => 'Kanopi + Dinding',
    'MEZZANINE'      => 'Mezzanine',
    'PAGAR'          => 'Pagar',
    'TRALIS'         => 'Tralis',
    'TENDA_MEMBRANE' => 'Tenda Membrane',
    'AWNING'         => 'Awning',
    'CARPORT'        => 'Carport',
];

foreach ($expected as $kode => $namaHarusnya) {
    $check("produk_kode $kode", Project::$jenisProjectOptions[$kode] ?? null, $namaHarusnya);
}

$check('jumlah kode persis 8 (tidak lebih tidak kurang)', count(Project::$jenisProjectOptions), 8);

// Pola fallback lama: default => $rab->produk_kode (dipakai RabController::approve())
$kodeAsing = 'XYZ_TIDAK_DIKENAL';
$check('kode tak dikenal -> fallback ke kode mentah (pola RabController)',
    Project::$jenisProjectOptions[$kodeAsing] ?? $kodeAsing, $kodeAsing);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
