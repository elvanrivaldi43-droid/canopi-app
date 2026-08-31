<?php
// FILE: tests/rangka/test_cutting_snapshot.php
// Jalankan: php tests/rangka/test_cutting_snapshot.php
//
// Cutting list produksi/kalibrasi dibaca dari rab_snapshot lead (JSON hasil autosave
// RAB Multi-Opsi), BUKAN dari editor yang sedang terbuka. Dua pintu memakainya:
// halaman Cutting List (kalibrasi, semua opsi) dan halaman Project (produksi,
// hanya opsi yang di-deal). Fungsi ekstraksinya murni supaya bisa dites tanpa DB.

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

$snap = [
    'panes' => [
        [
            'nama' => 'Opsi Standar',
            'blok' => [
                ['tipe' => 'denah', 'nama' => 'Kanopi Depan', 'aktif' => true, 'members' => [
                    ['nama' => 'F1', 'jenis' => 'frame', 'panjang' => 400, 'material' => 'Hollow 5x10'],
                    ['nama' => 'S1', 'jenis' => 'support', 'panjang' => 300, 'material' => 'Hollow 4x8'],
                ], 'denah_warns' => ['tapak besi "X" belum diketahui']],
                ['tipe' => 'manual', 'nama' => 'Item Lain', 'aktif' => true],
                // blok denah nonaktif tidak ikut diproduksi
                ['tipe' => 'denah', 'nama' => 'Blok Mati', 'aktif' => false, 'members' => [
                    ['nama' => 'F1', 'jenis' => 'frame', 'panjang' => 100, 'material' => 'Hollow 5x10'],
                ]],
            ],
        ],
        [
            'nama' => 'Opsi Premium',
            'blok' => [
                ['tipe' => 'denah', 'nama' => 'Kanopi Premium', 'aktif' => true, 'members' => [
                    ['nama' => 'F1', 'jenis' => 'frame', 'panjang' => 700, 'material' => 'WF 150'],
                ]],
            ],
        ],
    ],
];

// ── Tanpa filter opsi (pintu kalibrasi): semua opsi, hanya blok denah AKTIF.
$out = $svc->blokDenahDariSnapshot($snap);
check('2 blok denah aktif (blok mati & manual dilewati)', count($out), 2);
check('opsi tercatat', $out[0]['opsi'], 'Opsi Standar');
check('nama blok tercatat', $out[0]['blok'], 'Kanopi Depan');
check('members diteruskan utuh', count($out[0]['members']), 2);
check('opsi kedua ikut', $out[1]['opsi'], 'Opsi Premium');
check('warns diteruskan dari denah_warns', $out[0]['warns'][0] ?? null, 'tapak besi "X" belum diketahui');
check('blok tanpa denah_warns -> warns kosong', $out[1]['warns'], []);

// ── Dengan filter opsi deal (pintu produksi): hanya opsi itu.
$deal = $svc->blokDenahDariSnapshot($snap, 'Opsi Premium');
check('filter deal: hanya blok opsi itu', count($deal), 1);
check('filter deal: bloknya benar', $deal[0]['blok'], 'Kanopi Premium');

// ── Nama opsi deal tak ketemu (data lama/berubah) -> JANGAN diam-diam kosong,
// kembalikan semua supaya produksi tetap dapat sesuatu (pemanggil menandai).
$miss = $svc->blokDenahDariSnapshot($snap, 'Opsi Sudah Diganti');
check('opsi deal tak ketemu -> semua opsi dikembalikan', count($miss), 2);

// ── Masukan rusak aman.
check('snapshot kosong', $svc->blokDenahDariSnapshot([]), []);
check('panes bukan array', $svc->blokDenahDariSnapshot(['panes' => 'rusak']), []);
check('blok tanpa members dilewati', $svc->blokDenahDariSnapshot(['panes' => [[
    'nama' => 'X', 'blok' => [['tipe' => 'denah', 'nama' => 'Kosong', 'aktif' => true]],
]]]), []);

// ── hitung() sekarang WAJIB menyertakan daftar potong per batang (bars) —
// inilah data yang dirender halaman cetak; dulu dibuang setelah dihitung.
$r = $svc->hitung($out[0]['members']);
check('per_material membawa bars', isset($r['per_material'][0]['bars']), true);
$bar0 = $r['per_material'][0]['bars'][0];
check('bar punya seg & sisa', isset($bar0['seg']) && array_key_exists('sisa', $bar0), true);

exit($fail ? 1 : 0);
