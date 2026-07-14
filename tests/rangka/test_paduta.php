<?php
// Reproduksi cutting list ASLI PA-DUTA (Cutting Optimization Pro, ~40 m²).
// Sumber potongan: foto cutting list di /root/inbox (kalibarasi 1&2 dari 2) + statistik.
// Angka resmi Utilized bars: 5x10=10 · 4x8=9 · 3x3=4 · 4x6=4 (stok 600).
// Prinsip: potongan diambil dari cutting list nyata; kalau meleset = temuan model, bukan tes diakali.
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
$batang = function (array $lens, string $mat) use ($svc) {
    $members = array_map(fn ($l, $i) => ['nama' => "$mat-$i", 'panjang' => $l, 'material' => $mat], $lens, array_keys($lens));
    return $svc->hitung($members)['per_material'][0]['jumlah_batang'] ?? 0;
};

// ---- 5x10 (bar 1–9 cutting list): Frame A1..A8 + Tengah rangka b1/A1/B1, potongan disambung ----
// A1=730(600+130) A2=700(600+100) A3=528 A4=149×2 A5=238 A6=587 A7=170×2 A8=92
// Tengah: b1=600, A1=492×3, B1=100
$m5x10 = [730, 700, 528, 149, 149, 238, 587, 170, 170, 92, 600, 492, 492, 492, 100];
$check('5x10 = 10 batang', $batang($m5x10, '5x10'), 10);

// ---- Profil menerus keliling luar (belakang dibuang): depan 700 + kiri 730 + kanan 528 ----
$check('3x3 = 4 batang', $batang([700, 730, 528], '3x3'), 4);
$check('4x6 = 4 batang', $batang([700, 730, 528], '4x6'), 4);

// ---- 4x8 support (target 9) — DATA BELUM LENGKAP: bar #12 tak terekam di screenshot cutting list.
// Potongan yang TERLIHAT: A1=730(600+130), 4×600, A2=492×2, B2=149  -> baru 8 batang.
// Bar #12 (batang ke-9) isinya belum kelihatan. Nilai di bawah INFORMASI, bukan assert.
$m4x8_terlihat = [730, 600, 600, 600, 600, 492, 492, 149];
echo "\nINFO 4x8 (potongan terlihat saja, bar #12 hilang): " . $batang($m4x8_terlihat, '4x8')
   . " batang — target 9 butuh detail bar #12 dari Elvan.\n\n";

echo $fail ? "ADA FAIL — selidiki model batang (temuan validasi)\n"
           : "SEMUA LULUS — engine mereproduksi PA-DUTA (5x10/3x3/4x6). 4x8 nunggu data bar #12.\n";
exit($fail ? 1 : 0);
