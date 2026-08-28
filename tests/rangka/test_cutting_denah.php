<?php
// FILE: tests/rangka/test_cutting_denah.php
// Jalankan: php tests/rangka/test_cutting_denah.php
//
// Jumlah batang untuk legend editor denah HARUS memakai mesin cutting yang sama
// dengan perhitungan harga (RangkaDesignService -> CuttingService), BUKAN rumus
// kasar "total panjang / 600" yang dipakai DenahConv.hitungBatangWF di editor.
//
// Kenapa penting: rumus kasar MELESET KE BAWAH kalau potongannya panjang —
// surveyor bisa kurang beli besi di lapangan. Test ini mengunci selisih itu.

require_once __DIR__ . '/../../app/Services/CuttingService.php';
require_once __DIR__ . '/../../app/Services/RangkaDesignService.php';

use App\Services\RangkaDesignService;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$svc = new RangkaDesignService();
// Ambil jumlah batang per material dari hasil hitung (bentuk yang akan dibalas endpoint).
$batang = function (array $members, array $stok = []) use ($svc): array {
    $out = [];
    foreach ($svc->hitung($members, [], false, $stok)['per_material'] as $m) {
        $out[$m['material']] = $m['jumlah_batang'];
    }
    return $out;
};
$m = fn(string $mat, float $len, string $nama = 'X') => ['nama' => $nama, 'material' => $mat, 'panjang' => $len];

// ── Kasus inti: rumus kasar MELESET KE BAWAH. Potongan 460+449+540+298 = 1747cm,
// ceil(1747/600) = 3 batang. Kenyataan 4: tiga potong besar tak bisa berbagi batang
// (460+449 > 600), sisanya 140/151/60 — potong 298 tak muat, dan sambungan dibatasi
// maks 1 (cuma boleh dari 2 sumber: 151+140 = 291 < 298) -> buka batang keempat.
// Uji acak 6000 kombinasi ukuran kanopi wajar: rumus kasar TIDAK PERNAH lebih besar
// dari kebutuhan nyata, jadi selisihnya selalu ke arah KURANG BELI (~8% kasus).
check('460+449+540+298 -> 4 batang (rumus kasar cuma bilang 3)',
    $batang([$m('Hollow 5x10', 460), $m('Hollow 5x10', 449), $m('Hollow 5x10', 540), $m('Hollow 5x10', 298)]),
    ['Hollow 5x10' => 4]);

// Sambungan memang diizinkan (maks 1 per potong) — 3 potong 400 CUKUP 2 batang, bukan 3.
// Dikunci biar tak ada yang "memperbaiki" jadi 3 karena mengira sambungan tak boleh.
check('3 potong 400cm -> 2 batang (boleh disambung)',
    $batang([$m('Hollow 5x10', 400), $m('Hollow 5x10', 400), $m('Hollow 5x10', 400)]),
    ['Hollow 5x10' => 2]);

// ── Potongan yang PAS habis: 2 potong 300 muat dalam 1 batang 600.
check('2 potong 300cm -> 1 batang (pas habis)',
    $batang([$m('Hollow 4x8', 300), $m('Hollow 4x8', 300)]),
    ['Hollow 4x8' => 1]);

// ── Beberapa material dihitung sendiri-sendiri, tidak dicampur.
check('dua material dihitung terpisah',
    $batang([$m('Hollow 5x10', 500), $m('Hollow 4x8', 500)]),
    ['Hollow 5x10' => 1, 'Hollow 4x8' => 1]);

// ── Batang TIDAK selalu 6m: panjang batang per material dibaca dari master_material
// (kolom panjang_batang_cm). Rumus lama di editor mengasumsikan 600 untuk semuanya.
check('batang 4m: 3 potong 300cm -> 3 batang',
    $batang([$m('WF 150', 300), $m('WF 150', 300), $m('WF 150', 300)], ['WF 150' => 400]),
    ['WF 150' => 3]);

// ── Potongan lebih panjang dari 1 batang harus disambung, bukan bikin sisa negatif
// (regresi fix 14 Juli 2026 yang divalidasi ke cutting list PA-DUTA).
check('potong 900cm (> 1 batang 6m) -> 2 batang',
    $batang([$m('Hollow 5x10', 900)]),
    ['Hollow 5x10' => 2]);

// ── Masukan sampah tidak boleh bikin material hantu / batang palsu.
check('material kosong & panjang 0 diabaikan',
    $batang([$m('', 300), $m('Hollow 5x10', 0), $m('Hollow 5x10', 600)]),
    ['Hollow 5x10' => 1]);
check('daftar kosong -> tak ada material', $batang([]), []);

exit($fail ? 1 : 0);
