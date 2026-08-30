<?php
// FILE: tests/rangka/test_cutting_prioritas.php
// Jalankan: php tests/rangka/test_cutting_prioritas.php
//
// Prioritas mesin potong (keputusan Elvan 30 Ags): (1) batang paling sedikit,
// (2) las paling sedikit, (3) sisa terkumpul di potongan panjang.
//
// Bug nyata yang memicu (ditangkap ELVAN dari cutting list live, kanopi 4x3):
// potongan 400+300+400+300, batang 6m. Mesin lama menghasilkan 3 batang + 1 las
// (F2 dibelah 200+100) padahal 3 batang + NOL las mungkin: 400|400|300+300.
// Akarnya: aturan "kalau dua sisa digabung muat, belah saja" tak pernah mengecek
// apakah belahan itu benar-benar menghemat batang.

require_once __DIR__ . '/../../app/Services/CuttingService.php';

use App\Services\CuttingService;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$svc = new CuttingService();
$P = fn(array $lens) => array_map(fn ($l, $i) => ['label' => 'P' . $i, 'len' => $l], $lens, array_keys($lens));
$rekap = function (array $bars): array {
    $batang = count($bars);
    $chains = [];
    foreach ($bars as $b) foreach ($b['seg'] as $s)
        if (($s['jenis'] ?? '') === 'sambung') $chains[$s['jid']] = ($chains[$s['jid']] ?? 0) + 1;
    $las = 0;
    foreach ($chains as $c) $las += max(0, $c - 1);
    return ['batang' => $batang, 'las' => $las];
};

// ── Kasus Elvan: belahan yang tak menghemat batang DILARANG.
check('400+300+400+300 -> 3 batang', $rekap($svc->potong($P([400, 300, 400, 300]), 600))['batang'], 3);
check('400+300+400+300 -> NOL las (300+300 sekandang)', $rekap($svc->potong($P([400, 300, 400, 300]), 600))['las'], 0);

// ── Belahan yang MEMANG menghemat batang tetap boleh: 500+500+200.
// Tanpa belah: 3 batang 0 las. Dgn belah 200 -> 100+100: 2 batang 1 las. Batang menang.
$r = $rekap($svc->potong($P([500, 500, 200]), 600));
check('500+500+200 -> 2 batang (belahan menghemat, dipakai)', $r['batang'], 2);
check('500+500+200 -> 1 las', $r['las'], 1);

// ── Potongan > 1 batang tetap wajib disambung (batas fisik, bukan pilihan).
$r = $rekap($svc->potong($P([900]), 600));
check('900cm -> 2 batang', $r['batang'], 2);
check('900cm -> 1 las (wajib fisik)', $r['las'], 1);

// ── Kasus pas habis tak boleh rusak.
check('300+300 -> 1 batang 0 las', $rekap($svc->potong($P([300, 300]), 600)), ['batang' => 1, 'las' => 0]);

// ── Sapuan acak: varian pemenang TIDAK PERNAH lebih banyak batang dari varian
// mana pun, dan invarian dasar terjaga (material utuh, muatan tak lebih).
mt_srand(21);
$rusak = 0;
for ($t = 0; $t < 3000; $t++) {
    $stock = [600, 600, 400, 500][mt_rand(0, 3)];
    $k = mt_rand(1, 12); $pieces = []; $total = 0;
    for ($j = 0; $j < $k; $j++) { $len = mt_rand(50, 1700); $pieces[] = ['label' => "P$j", 'len' => $len]; $total += $len; }
    $bars = $svc->potong($pieces, $stock);
    $sum = 0;
    foreach ($bars as $b) {
        $isi = 0;
        foreach ($b['seg'] as $s) { $sum += $s['len']; $isi += $s['len']; }
        if ($isi > $stock + 1e-6) { $rusak++; break; }
    }
    if (abs($sum - $total) > 1e-6) $rusak++;
    // satu potongan tetap satu rangkaian (regresi fix 28 Ags)
    $perLabel = [];
    foreach ($bars as $b) foreach ($b['seg'] as $s)
        if (($s['jenis'] ?? '') === 'sambung') $perLabel[$s['label']][$s['jid']] = true;
    foreach ($perLabel as $jids) if (count($jids) > 1) { $rusak++; break; }
}
check('3000 acak: invarian material/muatan/rangkaian terjaga', $rusak, 0);

exit($fail ? 1 : 0);
