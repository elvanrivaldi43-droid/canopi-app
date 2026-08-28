<?php
// FILE: tests/rangka/test_cutting_sambungan.php
// Jalankan: php tests/rangka/test_cutting_sambungan.php
//
// Cutting list dipakai DUA hal: hitung harga DAN panduan potong di produksi.
// Untuk produksi, satu potongan panjang yang harus disambung wajib tampil sebagai
// SATU rangkaian (jid) yang sama — kalau terpecah jadi beberapa rangkaian, tukang
// membacanya sebagai beberapa batang terpisah.
//
// Bug nyata 28 Ags 2026 (kanopi 3x10 m, "Frame tengah (membujur)" 1000cm):
//   600 cm [rangkaian #3] · 200 cm [rangkaian #4] · 200 cm [rangkaian #4]
// dibaca tukang sebagai batang 600 + batang 400, bukan satu batang 1000.
// Penyebab: saat segmen hasil pra-proses (sudah punya rangkaian) terpaksa displit
// lagi, ia diberi nomor rangkaian BARU — memutus hubungan dengan rangkaian asalnya.

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

// Kumpulkan segmen per label. Label dibuat unik di tiap kasus uji.
function segPerLabel(array $bars): array {
    $out = [];
    foreach ($bars as $b) foreach ($b['seg'] as $s) $out[$s['label']][] = $s;
    return $out;
}
// Berapa nomor rangkaian berbeda yang dipakai satu potongan.
function jumlahRangkaian(array $segs): int {
    $jid = [];
    foreach ($segs as $s) if (($s['jenis'] ?? '') === 'sambung') $jid[$s['jid']] = true;
    return count($jid);
}

// ── Kasus dari bug nyata: frame membujur 1000cm bareng potongan lain yang memaksa split.
$bars = $svc->potong([
    ['label' => 'kiri',   'len' => 1000],
    ['label' => 'kanan',  'len' => 1000],
    ['label' => 'tengah', 'len' => 1000],
    ['label' => 'depan',  'len' => 300],
    ['label' => 'belakang', 'len' => 300],
], 600);
$per = segPerLabel($bars);
check('potongan 1000cm tampil sebagai SATU rangkaian (tengah)', jumlahRangkaian($per['tengah']), 1);
check('potongan 1000cm tampil sebagai SATU rangkaian (kiri)', jumlahRangkaian($per['kiri']), 1);
check('potongan 1000cm tampil sebagai SATU rangkaian (kanan)', jumlahRangkaian($per['kanan']), 1);

// Total panjang tiap potongan harus tetap utuh — jangan sampai fix malah menghilangkan material.
foreach (['kiri', 'kanan', 'tengah'] as $lab) {
    check("panjang \"$lab\" tetap 1000cm", (int) array_sum(array_column($per[$lab], 'len')), 1000);
}

// ── Sapuan acak: TIDAK BOLEH ADA potongan yang terpecah ke >1 rangkaian, dan
// invarian dasar (material utuh, batang tak kelebihan muatan) harus tetap terjaga.
mt_srand(11);
$pecah = 0; $materialSalah = 0; $lebihMuatan = 0;
for ($t = 0; $t < 3000; $t++) {
    $stock = [600, 600, 400, 500][mt_rand(0, 3)];
    $k = mt_rand(1, 12); $pieces = []; $total = 0;
    for ($j = 0; $j < $k; $j++) { $len = mt_rand(50, 1800); $pieces[] = ['label' => "P$j", 'len' => $len]; $total += $len; }
    $bars = $svc->potong($pieces, $stock);

    $sum = 0;
    foreach ($bars as $b) {
        $isi = 0;
        foreach ($b['seg'] as $s) { $sum += $s['len']; $isi += $s['len']; }
        if ($isi > $stock + 1e-6) $lebihMuatan++;
    }
    if (abs($sum - $total) > 1e-6) $materialSalah++;
    foreach (segPerLabel($bars) as $segs) if (jumlahRangkaian($segs) > 1) { $pecah++; break; }
}
check('3000 acak: tak ada potongan terpecah >1 rangkaian', $pecah, 0);
check('3000 acak: material tidak hilang/dobel', $materialSalah, 0);
check('3000 acak: tak ada batang kelebihan muatan', $lebihMuatan, 0);

// ── Potongan 1000cm dari batang 600 = 600+400 (2 potong, 1 sambungan) kalau sisanya muat.
$bars = $svc->potong([['label' => 'A', 'len' => 1000], ['label' => 'B', 'len' => 200], ['label' => 'C', 'len' => 200]], 600);
$per = segPerLabel($bars);
check('potongan 1000cm = 2 potong (600+400)', count($per['A']), 2);
check('potongan 1000cm tetap 1 rangkaian', jumlahRangkaian($per['A']), 1);

// Kalau sisa memaksa segmen 400 itu dipecah lagi, potongan tetap SATU rangkaian
// walau jadi 3 potong — inilah kasus bug kanopi 3x10 m.
$bars = $svc->potong([
    ['label' => 'kiri', 'len' => 1000], ['label' => 'kanan', 'len' => 1000], ['label' => 'A', 'len' => 1000],
], 600);
$per = segPerLabel($bars);
check('segmen yang dipecah lagi tetap satu rangkaian', jumlahRangkaian($per['A']), 1);
check('panjang "A" tetap utuh 1000cm', (int) array_sum(array_column($per['A'], 'len')), 1000);

exit($fail ? 1 : 0);
