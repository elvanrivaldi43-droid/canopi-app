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

// A. Profil MENERUS 700+730+528 (satu besi) -> 4 batang (validasi cutting list PA-DUTA)
$profil = [
    ['nama' => 'depan', 'panjang' => 700, 'material' => '3x3'],
    ['nama' => 'kiri',  'panjang' => 730, 'material' => '3x3'],
    ['nama' => 'kanan', 'panjang' => 528, 'material' => '3x3'],
];
$r = $svc->hitung($profil);
$check('profil 3x3 [700,730,528] = 4 batang', $r['per_material'][0]['jumlah_batang'], 4);

// B. Dua besi berbeda tidak tercampur
$mix = [
    ['nama' => 'f1', 'panjang' => 500, 'material' => '5x10'],
    ['nama' => 's1', 'panjang' => 500, 'material' => '4x8'],
];
$r = $svc->hitung($mix);
$check('2 material -> 2 baris', count($r['per_material']), 2);

// C. Biaya = harga_pokok x jumlah_batang (owner)
$r = $svc->hitung([['nama' => 'a', 'panjang' => 300, 'material' => '5x10']], ['5x10' => 100000], true);
$check('subtotal = 100000 x 1', $r['per_material'][0]['subtotal_besi'], 100000.0);
$check('total biaya', $r['total_biaya_besi'], 100000.0);

// D. Harga kosong (owner) -> warn, subtotal null
$r = $svc->hitung([['nama' => 'a', 'panjang' => 300, 'material' => '5x10']], [], true);
$check('warn harga kosong', count($r['warn']) >= 1, true);

// E. Batang tanpa material / panjang <= 0 diabaikan
$r = $svc->hitung([['nama' => 'x', 'panjang' => 0, 'material' => '5x10'], ['nama' => 'y', 'panjang' => 300, 'material' => '']]);
$check('input invalid diabaikan', $r['total_batang'], 0);

echo $fail ? "\n=== ADA FAIL ===\n" : "\n=== SEMUA PASS ===\n";
exit($fail ? 1 : 0);
