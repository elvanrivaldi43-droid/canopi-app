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

$members = [['nama' => 'palang', 'panjang' => 1000, 'material' => 'WF 100']];

// tanpa stok map -> default 600 -> 1000cm = 2 batang
$r1 = $svc->hitung($members);
$check('WF 1000cm default 600 = 2 batang', $r1['per_material'][0]['jumlah_batang'], 2);

// stok WF 1200 -> 1 batang
$r2 = $svc->hitung($members, [], false, ['WF 100' => 1200]);
$check('WF 1000cm @stok 1200 = 1 batang', $r2['per_material'][0]['jumlah_batang'], 1);
$check('WF 1000cm @stok 1200 = 0 sambungan', $r2['per_material'][0]['sambungan'], 0);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
