<?php
// FILE: tests/jadwal-libur/test_libur_service.php
// Jalankan: php tests/jadwal-libur/test_libur_service.php
require __DIR__ . '/../../vendor/autoload.php';

use App\Services\LiburService;
use Carbon\Carbon;

$svc = new LiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── cocokLiburPada ──────────────────────────────────────────
// 11 Agustus 2026 dipastikan hari Selasa (Carbon::dayOfWeek: 0=Minggu..6=Sabtu, Selasa=2)
$selasa = Carbon::create(2026, 8, 11);
$check('tanggal contoh memang Selasa (dayOfWeek=2)', $selasa->dayOfWeek, 2);

$check('default cocok hari, tanpa override -> true',
    $svc->cocokLiburPada(2, [], $selasa), true);

$check('default beda hari, tanpa override -> false',
    $svc->cocokLiburPada(6, [], $selasa), false);

$check('tanpa default (null), tanpa override -> selalu false',
    $svc->cocokLiburPada(null, [], $selasa), false);

$check('default cocok TAPI ada override batal di tanggal itu -> false (override menang)',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], $selasa), false);

$check('default TIDAK cocok TAPI ada override tambah di tanggal itu -> true',
    $svc->cocokLiburPada(6, [['tanggal' => '2026-08-11', 'jenis' => 'tambah']], $selasa), true);

$check('override ada tapi beda tanggal -> fallback ke default (cocok) -> true',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-12', 'jenis' => 'batal']], $selasa), true);

// ── hitungHariKerjaPada ──────────────────────────────────────
// Agustus 2026 = 31 hari.
$jumlahSelasa = 0;
for ($i = 1; $i <= 31; $i++) {
    if (Carbon::create(2026, 8, $i)->dayOfWeek === 2) $jumlahSelasa++;
}

$check('tanpa default -> semua 31 hari kehitung hari kerja',
    $svc->hitungHariKerjaPada(null, [], 8, 2026), 31);

$check('default Selasa -> 31 dikurangi jumlah Selasa di Agustus 2026',
    $svc->hitungHariKerjaPada(2, [], 8, 2026), 31 - $jumlahSelasa);

$check('default Selasa + 1 override batal (1 Selasa dibatalkan) -> nambah 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], 8, 2026),
    31 - $jumlahSelasa + 1);

$check('tanpa default + 1 override tambah (nambah 1 libur) -> ngurang 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(null, [['tanggal' => '2026-08-05', 'jenis' => 'tambah']], 8, 2026),
    31 - 1);

$check('Februari 2026 (28 hari, bukan kabisat), tanpa default -> 28 hari kerja',
    $svc->hitungHariKerjaPada(null, [], 2, 2026), 28);

$check('hitungHariKerjaPada dengan cap $sampaiHari=15, tanpa default -> 15 hari kehitung (bukan 31)',
    $svc->hitungHariKerjaPada(null, [], 8, 2026, 15), 15);

// ── expandTukar ──────────────────────────────────────────
$check('expandTukar: jenis tambah -> 1 entry apa adanya',
    $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => null, 'jenis' => 'tambah']),
    [['tanggal' => '2026-08-11', 'jenis' => 'tambah']]);

$check('expandTukar: jenis batal -> 1 entry apa adanya',
    $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => null, 'jenis' => 'batal']),
    [['tanggal' => '2026-08-11', 'jenis' => 'batal']]);

$check('expandTukar: jenis tukar -> 2 entry (lama jadi batal, baru jadi tambah)',
    $svc->expandTukar(['tanggal' => '2026-08-12', 'tanggal_baru' => '2026-08-19', 'jenis' => 'tukar']),
    [['tanggal' => '2026-08-12', 'jenis' => 'batal'], ['tanggal' => '2026-08-19', 'jenis' => 'tambah']]);

$overridesTukar = $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => '2026-08-14', 'jenis' => 'tukar']);
$check('tukar: tanggal lama (default cocok) jadi TIDAK libur (dipakai cocokLiburPada)',
    $svc->cocokLiburPada(2, $overridesTukar, Carbon::create(2026, 8, 11)), false);
$check('tukar: tanggal baru (default gak cocok) jadi LIBUR (dipakai cocokLiburPada)',
    $svc->cocokLiburPada(2, $overridesTukar, Carbon::create(2026, 8, 14)), true);

// Tukar dengan tanggal baru LEBIH DULU dari tanggal lama (skenario Kamis-minggu-ini -> Rabu-minggu-depan)
$overridesTukarMundur = $svc->expandTukar(['tanggal' => '2026-08-19', 'tanggal_baru' => '2026-08-13', 'jenis' => 'tukar']);
$check('tukar mundur: tanggal baru (13 Agustus) jadi libur walau lebih dulu dari tanggal lama',
    $svc->cocokLiburPada(3, $overridesTukarMundur, Carbon::create(2026, 8, 13)), true);
$check('tukar mundur: tanggal lama (19 Agustus) jadi TIDAK libur',
    $svc->cocokLiburPada(3, $overridesTukarMundur, Carbon::create(2026, 8, 19)), false);

// ── jendelaTukarSkip ──────────────────────────────────────
// 11 Agustus 2026 = Selasa (dipastikan di atas). Minggu ini: Senin 10 - Minggu 16 Agustus. Minggu depan: Senin 17 - Minggu 23 Agustus.
[$jAwal1, $jAkhir1] = $svc->jendelaTukarSkip(Carbon::create(2026, 8, 11));
$check('jendelaTukarSkip dari Selasa 11 Agustus: awal = besok (12 Agustus)', $jAwal1->format('Y-m-d'), '2026-08-12');
$check('jendelaTukarSkip dari Selasa 11 Agustus: akhir = akhir minggu depan (23 Agustus)', $jAkhir1->format('Y-m-d'), '2026-08-23');

// 9 Agustus 2026 = Minggu (akhir "minggu ini" Senin 3 - Minggu 9). Besok (10 Agustus) sudah masuk "minggu depan" (Senin 10 - Minggu 16).
[$jAwal2, $jAkhir2] = $svc->jendelaTukarSkip(Carbon::create(2026, 8, 9));
$check('jendelaTukarSkip dari Minggu 9 Agustus: awal = besok (10 Agustus)', $jAwal2->format('Y-m-d'), '2026-08-10');
$check('jendelaTukarSkip dari Minggu 9 Agustus: akhir = 16 Agustus (cuma minggu depan, sisa minggu ini sudah 0 hari)', $jAkhir2->format('Y-m-d'), '2026-08-16');

// ── tanggalKandidatLibur ──────────────────────────────────
// Default Selasa (2), rentang 12-23 Agustus 2026. Selasa yang jatuh di rentang ini cuma 18 Agustus (11 Agustus sebelum rentang, 25 Agustus sesudah rentang).
$kandidat = $svc->tanggalKandidatLibur(2, Carbon::create(2026, 8, 12), Carbon::create(2026, 8, 23));
$check('tanggalKandidatLibur: Selasa dalam rentang 12-23 Agustus 2026', $kandidat, ['2026-08-18']);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
