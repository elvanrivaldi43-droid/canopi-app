<?php
// FILE: tests/penggajian/test_rincian_potongan.php
// Jalankan: php tests/penggajian/test_rincian_potongan.php
//
// Rincian asal-usul absensi.potongan_telat (spec 2026-09-30-rincian-potongan-design.md).
// Yang dikunci: jumlah baris tampilan SELALU = total potongan (sisa jadi baris
// "Belum terinci"), koreksi tanpa perubahan angka tak menambah entri, dan
// pecahan per menit tak memunculkan baris sisa palsu.

require_once __DIR__ . '/../../app/Services/RincianPotongan.php';

use App\Services\RincianPotongan as R;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}
function jumlah(array $baris): float { return round(array_sum(array_column($baris, 'n')), 2); }

// ── tambah
check('nominal 0 diabaikan', R::tambah(null, 'telat_pagi', 0.0, 0), []);
check('entri telat pagi + menit', R::tambah(null, 'telat_pagi', 1333.333, 4), [['j' => 'telat_pagi', 'n' => 1333.33, 'm' => 4]]);
check('entri koreksi + alasan', R::tambah([], 'koreksi', -20000.0, null, 'salah hitung'), [['j' => 'koreksi', 'n' => -20000.0, 'a' => 'salah hitung']]);

// ── Kasus nyata Rizco 24/9: telat 4 mnt + lupa progress + skip siang = 41.333,33
$r = R::tambah(null, 'telat_pagi', 1333.33, 4);
$r = R::tambah($r, 'lupa_progress', 20000);
$r = R::tambah($r, 'skip_siang', 20000);
$b = R::baris($r, 41333.33);
check('Rizco: 3 baris, tanpa sisa', count($b), 3);
check('Rizco: jumlah = total', jumlah($b), 41333.33);
check('Rizco: label pertama', $b[0]['label'], 'Telat masuk');
check('Rizco: ket menit', $b[0]['ket'], '4 menit');

// ── Koreksi turun: 41.333,33 -> 21.333 (selisih dari TOTAL lama)
$r2 = R::tambah($r, 'koreksi', 21333 - 41333.33, null, 'sudah lapor lisan');
$b2 = R::baris($r2, 21333);
check('koreksi turun: jumlah = total baru', jumlah($b2), 21333.0);
check('koreksi turun: ket = alasan', $b2[3]['ket'], 'sudah lapor lisan');

// ── Koreksi yang tak mengubah angka (cuma ganti status/jam) -> tak ada entri
check('koreksi selisih 0 diabaikan', R::tambah($r, 'koreksi', 41333.33 - 41333.33, null, 'ganti jam'), $r);

// ── Data lama (NULL)
check('null + total 0 -> kosong', R::baris(null, 0), []);
$b3 = R::baris(null, 20000);
check('null + total -> 1 baris belum terinci', [$b3[0]['j'], $b3[0]['n']], ['belum_terinci', 20000.0]);

// ── Data lama lalu dikoreksi naik 20.000 -> 30.000
$b4 = R::baris(R::tambah(null, 'koreksi', 10000, null, 'x'), 30000);
check('lama+koreksi: jumlah = total baru', jumlah($b4), 30000.0);
check('lama+koreksi: sisa belum terinci 20rb', end($b4)['n'], 20000.0);

// ── Pecahan per menit: 3 x 333,33 vs total 1000 -> tak ada baris sisa
$r5 = R::tambah(R::tambah(R::tambah(null, 'telat_pagi', 333.33, 1), 'telat_siang', 333.33, 1), 'telat_siang', 333.33, 1);
check('pecahan: tak ada sisa palsu', count(R::baris($r5, 1000)), 3);

// ── Ringkasan bulan untuk slip
$bulan = [
    (object) ['rincian_potongan' => $r, 'potongan_telat' => 41333.33],
    (object) ['rincian_potongan' => R::tambah(null, 'telat_pagi', 5000, 15), 'potongan_telat' => 5000],
    (object) ['rincian_potongan' => null, 'potongan_telat' => 20000],
    (object) ['rincian_potongan' => null, 'potongan_telat' => 0],
];
$s = R::ringkasBulan($bulan);
check('ringkas telat pagi', $s['telat_pagi'], ['x' => 2, 'n' => 6333.33]);
check('ringkas lupa progress', $s['lupa_progress'], ['x' => 1, 'n' => 20000.0]);
check('ringkas belum terinci', $s['belum_terinci'], ['x' => 1, 'n' => 20000.0]);
check('ringkas: jumlah = total bulan', round(array_sum(array_column($s, 'n')), 2), 66333.33);

exit($fail ? 1 : 0);
