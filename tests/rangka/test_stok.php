<?php
require __DIR__ . '/../../app/Services/CuttingService.php';

use App\Services\CuttingService;

$svc = new CuttingService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// 1 potong 1000cm: stok 600 -> 2 batang (600 + 400, 1 sambungan)
$b600 = $svc->potong([['label' => 'X', 'len' => 1000]]);
$check('1000cm @stok default(600) = 2 batang', count($b600), 2);

// stok 1200 (WF) -> muat utuh, 1 batang, 0 sambungan
$b1200 = $svc->potong([['label' => 'X', 'len' => 1000]], 1200);
$check('1000cm @stok 1200 = 1 batang', count($b1200), 1);
$sambung = 0;
foreach ($b1200 as $bar) foreach ($bar['seg'] as $sg) if (($sg['jenis'] ?? '') === 'sambung') $sambung++;
$check('1000cm @stok 1200 = 0 sambungan', $sambung, 0);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
