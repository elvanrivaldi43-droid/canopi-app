<?php
require __DIR__ . '/../../app/Services/CuttingService.php';
require __DIR__ . '/../../app/Services/RangkaDesignService.php';

use App\Services\RangkaDesignService;

$svc = new RangkaDesignService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Blok denah: 1 frame 5x10 keliling (4 sisi) + 2 support 4x8, dengan harga -> besi benar
$members = [
    ['nama' => 'sisi depan', 'jenis' => 'frame', 'panjang' => 400, 'material' => '5x10'],
    ['nama' => 'sisi kanan', 'jenis' => 'frame', 'panjang' => 300, 'material' => '5x10'],
    ['nama' => 'sisi blk',   'jenis' => 'frame', 'panjang' => 400, 'material' => '5x10'],
    ['nama' => 'sisi kiri',  'jenis' => 'frame', 'panjang' => 300, 'material' => '5x10'],
    ['nama' => 'S1', 'jenis' => 'support', 'panjang' => 400, 'material' => '4x8'],
    ['nama' => 'S2', 'jenis' => 'support', 'panjang' => 400, 'material' => '4x8'],
];
$harga = ['5x10' => 200000, '4x8' => 130000];
$r = $svc->hitung($members, $harga, true, []);

// 5x10: total 1400cm -> 3 batang (600+600+200). 4x8: 800cm -> 2 batang.
$byMat = [];
foreach ($r['per_material'] as $m) $byMat[$m['material']] = $m;
$check('5x10 = 3 batang', $byMat['5x10']['jumlah_batang'], 3);
$check('4x8 = 2 batang',  $byMat['4x8']['jumlah_batang'], 2);
$check('total biaya besi = 3*200000 + 2*130000', (int) $r['total_biaya_besi'], 860000);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
