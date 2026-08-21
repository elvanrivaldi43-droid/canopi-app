<?php
// FILE: tests/swe/test_rekomendasi_pic_service.php
// Jalankan: php tests/swe/test_rekomendasi_pic_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\RekomendasiPicService;

$svc = new RekomendasiPicService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── hitungJumlahDisarankan ───────────────────────────────
// qty=40, produktivitas=8/hari -> estimasi default 5 hari. Tim default 2 orang.
// Target 5 hari (sama persis estimasi) -> multiplier 1 -> tetap 2 orang.
$check('target = estimasi default -> jumlah tim default',
    $svc->hitungJumlahDisarankan(40, 8, 2, 5), 2);

// Target dipepetin jadi 2.5 hari (setengah dari estimasi) -> butuh 2x orang -> 4.
$check('target setengah dari estimasi -> 2x tim default',
    $svc->hitungJumlahDisarankan(40, 8, 2, 3), 4); // 5/3=1.67 * 2 = 3.33 -> ceil 4

// Target lebih longgar dari estimasi (10 hari, estimasi cuma 5) -> multiplier < 1,
// tapi minimal tetap 1 orang, tidak boleh 0.
$check('target sangat longgar -> minimal 1 orang, tidak 0',
    $svc->hitungJumlahDisarankan(40, 8, 2, 20), 1); // 5/20=0.25 * 2 = 0.5 -> ceil 1, bukan 0

// Input tidak lengkap -> null, bukan division by zero / exception.
$check('qty null -> null', $svc->hitungJumlahDisarankan(null, 8, 2, 5), null);
$check('produktivitas null -> null', $svc->hitungJumlahDisarankan(40, null, 2, 5), null);
$check('produktivitas 0 -> null (hindari division by zero)', $svc->hitungJumlahDisarankan(40, 0, 2, 5), null);
$check('targetHari null -> null', $svc->hitungJumlahDisarankan(40, 8, 2, null), null);
$check('targetHari 0 -> null (hindari division by zero)', $svc->hitungJumlahDisarankan(40, 8, 2, 0), null);
$check('targetHari negatif (target sudah lewat) -> null', $svc->hitungJumlahDisarankan(40, 8, 2, -1), null);
$check('timDefault null -> null', $svc->hitungJumlahDisarankan(40, 8, null, 5), null);

// ── urutkanKandidat ────────────────────────────────────────
$kandidat = [
    ['user_id' => 1, 'cocok' => false, 'sibuk' => false], // tidak cocok
    ['user_id' => 2, 'cocok' => true,  'sibuk' => true],  // cocok, sibuk
    ['user_id' => 3, 'cocok' => true,  'sibuk' => false], // cocok, kosong
    ['user_id' => 4, 'cocok' => false, 'sibuk' => true],  // tidak cocok
    ['user_id' => 5, 'cocok' => true,  'sibuk' => false], // cocok, kosong (urutan dipertahankan vs id 3)
];
$urut = $svc->urutkanKandidat($kandidat);
$check('urutan: cocok&kosong dulu (3,5), lalu cocok&sibuk (2), lalu tidak cocok (1,4)',
    array_column($urut, 'user_id'), [3, 5, 2, 1, 4]);

$check('urutkanKandidat array kosong -> array kosong', $svc->urutkanKandidat([]), []);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
