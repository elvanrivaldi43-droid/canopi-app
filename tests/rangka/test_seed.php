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

// Seed 700x730, kotak 80, 2 arah, tiang 2, besi frame/support/tiang BEDA nama
$seed = $svc->seedDariKotak([
    'lebar_cm' => 700, 'panjang_cm' => 730, 'tinggi_cm' => 300,
    'kotak_cm' => 80, 'arah_support' => 2, 'jml_tiang' => 2,
    'mat_frame' => '5x10', 'mat_support' => '4x8', 'mat_tiang' => 'WF150',
]);

// Tiap member punya field wajib
$m0 = $seed['members'][0];
$check('member punya material', isset($m0['material']), true);
$check('member punya panjang', isset($m0['panjang']), true);
$check('denah diteruskan', isset($seed['denah']['v']), true);

// Hitung hasil seed -> angka yang SUDAH DIVERIFIKASI live (14 Juli):
// frame 5x10 = 8 batang / 6 sambungan; support 4x8 = 20 batang; tiang = 1.
//
// SAMBUNGAN support 4x8 diubah 16 -> 17 pada 28 Ags 2026. Angka 16 lama BUKAN
// bukti algoritma optimal, melainkan akibat bug: satu potongan panjang yang
// terpecah ke beberapa rangkaian membuat hitungan sambungan MENGECIL (under-count).
// Dibuktikan independen: 16 potongan 4x8 semuanya > 600cm, jadi minimum fisik = 16
// sambungan; daftar potong yang benar-benar dihasilkan memakai 17 (satu potongan
// terpecah 3 keping padahal cukup 2). Jadi mesin memang MEMBUAT 17, dulu cuma
// melaporkannya 16. Sekarang laporannya jujur.
// Jumlah BATANG tidak berubah (harga tidak bergeser) -- diverifikasi 1008 kombinasi
// ukuran kanopi: identik sebelum vs sesudah perbaikan.
// PELUANG PERBAIKAN (belum dikerjakan): 17 masih 1 lebih banyak dari optimum 16.
$r = $svc->hitung($seed['members']);
$get = function ($r, $mat) {
    foreach ($r['per_material'] as $x) if ($x['material'] === $mat) return $x;
    return null;
};
$check('frame 5x10 = 8 batang', $get($r, '5x10')['jumlah_batang'], 8);
$check('frame 5x10 = 6 sambungan', $get($r, '5x10')['sambungan'], 6);
$check('support 4x8 = 20 batang', $get($r, '4x8')['jumlah_batang'], 20);
$check('support 4x8 = 17 sambungan (optimum teoretis 16, lihat catatan)', $get($r, '4x8')['sambungan'], 17);
$check('tiang WF150 = 1 batang', $get($r, 'WF150')['jumlah_batang'], 1);

echo $fail ? "\n=== ADA FAIL ===\n" : "\n=== SEMUA PASS ===\n";
exit($fail ? 1 : 0);
