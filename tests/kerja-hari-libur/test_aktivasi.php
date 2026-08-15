<?php
// FILE: tests/kerja-hari-libur/test_aktivasi.php
// Jalankan: php tests/kerja-hari-libur/test_aktivasi.php
// Tes murni aturan boleh/tidaknya mengaktifkan "masuk hari libur" untuk 1 karyawan.
require __DIR__ . '/../bootstrap.php';

use App\Services\KerjaHariLiburService;

$svc  = new KerjaHariLiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};
// null = boleh; string = alasan tolak
// Argumen: (levelAktor, statusUser, levelUser, isLibur, adaIzin, gajiHarian, idAktor, idTarget)
//
// Panggilan lama yang cuma mengirim 6 argumen otomatis dilengkapi ID aktor & target
// yang BERBEDA (99 vs 5) — artinya "aktor mengaktifkan ORANG LAIN", kasus normal.
// Larangan mengaktifkan diri sendiri diuji terpisah di bagian "self-activation".
$alasan = function (...$a) use ($svc) {
    if (count($a) === 6) { $a[] = 99; $a[] = 5; }
    return $svc->alasanTolakAktivasi(...$a);
};
$boleh = fn(...$a) => $alasan(...$a) === null;

// Tarif harian karyawan yang datanya sudah benar.
const GAJI_OK = 200000;

// ── Siapa yang boleh mengaktifkan ───────────────────────────
$check('Owner (level 1) boleh mengaktifkan',      $boleh(1, 'aktif', 5, true, false, GAJI_OK), true);
$check('Mandor (level 3) boleh mengaktifkan',     $boleh(3, 'aktif', 5, true, false, GAJI_OK), true);
$check('Admin (level 2) DITOLAK',                 $boleh(2, 'aktif', 5, true, false, GAJI_OK), false);
$check('Marketing (level 4) DITOLAK',             $boleh(4, 'aktif', 5, true, false, GAJI_OK), false);
$check('Teknisi (level 5) DITOLAK',               $boleh(5, 'aktif', 5, true, false, GAJI_OK), false);
$check('level aktor bertipe string "1" tetap boleh (kolom level tanpa cast)',
    $boleh('1', 'aktif', 5, true, false, GAJI_OK), true);

// ── Syarat karyawan yang diaktifkan ─────────────────────────
$check('karyawan nonaktif DITOLAK',               $boleh(1, 'nonaktif', 5, true, false, GAJI_OK), false);
$check('Owner tidak bisa diaktifkan (tidak pernah absen masuk)',
    $boleh(1, 'aktif', 1, true, false, GAJI_OK), false);
$check('karyawan yang HARI INI bukan libur DITOLAK (kode normal sudah otomatis)',
    $boleh(1, 'aktif', 5, false, false, GAJI_OK), false);
$check('karyawan libur tapi ada izin/sakit/cuti/dinas luar DITOLAK',
    $boleh(1, 'aktif', 5, true, true, GAJI_OK), false);

// ── Alasan tolak harus jelas & spesifik ─────────────────────
$check('alasan tolak akses bukan null',           is_string($alasan(2, 'aktif', 5, true, false, GAJI_OK)), true);
$check('alasan tolak bukan-libur menyebut "libur"',
    str_contains(strtolower((string) $alasan(1, 'aktif', 5, false, false, GAJI_OK)), 'libur'), true);
$check('alasan tolak izin menyebut "izin"',
    str_contains(strtolower((string) $alasan(1, 'aktif', 5, true, true, GAJI_OK)), 'izin'), true);
$check('kasus boleh -> null (bukan string kosong)',
    $alasan(1, 'aktif', 5, true, false, GAJI_OK), null);

// ── Gaji harian belum diisi -> JANGAN aktifkan ──────────────
// Upah kerja hari libur = 1x `users.gaji_harian` (snapshot saat diaktifkan).
// Kalau kolom itu 0/kosong, karyawan disuruh masuk di hari liburnya lalu dibayar
// Rp 0 — jatah libur terpakai, upah tidak ada. Ditolak di depan, bukan dibiarkan
// jadi baris audit bernilai nol yang baru ketahuan pas slip gaji keluar.
$check('gaji harian belum diisi (0) DITOLAK',      $boleh(1, 'aktif', 5, true, false, 0), false);
$check('gaji harian null (kolom kosong) DITOLAK',  $boleh(1, 'aktif', 5, true, false, null), false);
$check('gaji harian minus (data rusak) DITOLAK',   $boleh(1, 'aktif', 5, true, false, -50000), false);
$check('gaji harian "0.00" dari DB (string) DITOLAK',
    $boleh(1, 'aktif', 5, true, false, '0.00'), false);
$check('gaji harian terisi -> boleh',              $boleh(1, 'aktif', 5, true, false, 150000), true);
$check('gaji harian "150000.00" dari DB (string) -> boleh',
    $boleh(1, 'aktif', 5, true, false, '150000.00'), true);
$check('alasan tolak gaji kosong menyebut "gaji harian"',
    str_contains(strtolower((string) $alasan(1, 'aktif', 5, true, false, 0)), 'gaji harian'), true);
$check('Mandor juga tidak bisa menerobos gaji kosong',
    $boleh(3, 'aktif', 5, true, false, 0), false);

// Berlaku untuk SEMUA tipe gaji: upah hari libur selalu dihitung dari field
// `gaji_harian`, bukan dari gaji_bulanan atau nilai project. Jadi pegawai bulanan
// /project yang `gaji_harian`-nya kosong pun harus dilengkapi datanya dulu.
$karyawanTipe = [
    'harian  (gaji_harian = tarif hariannya)'      => 175000,
    'bulanan (gaji_harian = turunan gaji bulanan)' => 250000,
    'project (gaji_harian = tarif hari kerjanya)'  => 300000,
];
foreach ($karyawanTipe as $tipe => $gaji) {
    $check("pegawai $tipe dengan gaji harian terisi -> boleh",
        $boleh(1, 'aktif', 5, true, false, $gaji), true);
    $check("pegawai $tipe dengan gaji harian KOSONG -> ditolak",
        $boleh(1, 'aktif', 5, true, false, 0), false);
}

// Bukti kenapa penolakan ini penting: tanpa gaji harian, upahnya memang nol.
$check('upah hari libur dari gaji harian 0 = Rp 0 (kerja tanpa bayaran)',
    $svc->upahHariLibur(0, 'hadir'), 0.0);

// ── Aturan tarif nol = SATU helper murni ────────────────────
// Ada DUA pintu yang bisa melahirkan baris kerja hari libur berupah: tombol Aktivasi
// dan koreksi manual di tanggal libur (AbsensiController::koreksiManual). Kalau
// aturannya ditulis dua kali, satu pintu bisa diperbaiki dan yang lain ketinggalan —
// karyawan tetap bisa tercatat kerja hari libur dengan bayaran Rp 0 lewat pintu itu.
$check('helper alasanTolakTarif() ada di service',
    method_exists($svc, 'alasanTolakTarif'), true);

if (method_exists($svc, 'alasanTolakTarif')) {
    $check('tarif terisi -> null (boleh)',            $svc->alasanTolakTarif(200000), null);
    $check('tarif "150000.00" dari DB -> null',       $svc->alasanTolakTarif('150000.00'), null);
    $check('tarif 0 -> alasan tolak (string)',        is_string($svc->alasanTolakTarif(0)), true);
    $check('tarif null (kolom kosong) -> ditolak',    is_string($svc->alasanTolakTarif(null)), true);
    $check('tarif minus (data rusak) -> ditolak',     is_string($svc->alasanTolakTarif(-50000)), true);
    $check('tarif "0.00" dari DB -> ditolak',         is_string($svc->alasanTolakTarif('0.00')), true);
    $check('alasan tolak tarif menyebut "gaji harian"',
        str_contains(strtolower((string) $svc->alasanTolakTarif(0)), 'gaji harian'), true);

    // Kalimatnya harus PERSIS sama, bukan cuma mirip — itu bukti alasanTolakAktivasi()
    // memakai helper ini, bukan menyimpan salinan kalimatnya sendiri.
    $check('alasanTolakAktivasi() memakai kalimat helper apa adanya',
        $alasan(1, 'aktif', 5, true, false, 0), $svc->alasanTolakTarif(0));
}

// Jalur pemakaiannya benar-benar lewat helper (bukan helper yang menganggur di samping
// aturan lama yang masih dikopi).
$srcSvc = file_get_contents(__DIR__ . '/../../app/Services/KerjaHariLiburService.php');
$posAktivasi = strpos($srcSvc, 'function alasanTolakAktivasi(');
$bodyAktivasi = $posAktivasi === false ? '' : substr($srcSvc, $posAktivasi, strpos($srcSvc, 'function snapshot(') - $posAktivasi);
$check('alasanTolakAktivasi() memanggil alasanTolakTarif()',
    str_contains($bodyAktivasi, 'alasanTolakTarif('), true);

// ── Urutan cek: akses dulu sebelum bocorin kondisi karyawan ─
$check('aktor tanpa akses ditolak duluan walau data karyawan tidak valid',
    str_contains(strtolower((string) $alasan(4, 'nonaktif', 1, false, true, 0)), 'akses'), true);

// ═══════════════════════════════════════════════════════════
// SELF-ACTIVATION — Mandor tidak boleh mengaktifkan DIRINYA SENDIRI
//
// Keputusan Bos (terkunci): Mandor boleh mengaktifkan karyawan lain, tetapi tidak
// dirinya sendiri. Kenapa: aktivasi membuat baris berupah (1x gaji harian + uang
// makan) atas nama orang yang menekan tombolnya. Tanpa pagar ini, Mandor bisa
// memberi dirinya sendiri hari kerja berbayar di hari liburnya, kapan saja, tanpa
// persetujuan siapa pun. Yang boleh mengaktifkan Mandor adalah Owner.
//
// Owner menargetkan dirinya sendiri sudah tertutup lebih dulu lewat aturan
// "Owner tidak ikut absen masuk" (levelUser == 1).
// ═══════════════════════════════════════════════════════════
$check('Mandor mengaktifkan karyawan LAIN -> boleh',
    $svc->alasanTolakAktivasi(3, 'aktif', 5, true, false, GAJI_OK, 7, 12) === null, true);
$check('Mandor mengaktifkan DIRINYA SENDIRI -> DITOLAK',
    $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, 7, 7) === null, false);
$check('alasan tolak self-activation menyebut "sendiri"',
    str_contains(strtolower((string) $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, 7, 7)), 'sendiri'), true);

// ID dari route/DB sering berupa string ("7") — perbandingan harus numerik,
// kalau tidak pagar ini bisa ditembus cuma karena beda tipe.
$check('self-activation dengan ID string "7" vs int 7 tetap DITOLAK',
    $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, '7', 7) === null, false);
$check('self-activation dengan dua-duanya string tetap DITOLAK',
    $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, '7', '7') === null, false);

// Owner tetap boleh mengaktifkan Mandor (level 3) — yang dilarang cuma DIRI SENDIRI,
// bukan "level 3 tidak boleh diaktifkan".
$check('Owner mengaktifkan Mandor (level 3) -> boleh',
    $svc->alasanTolakAktivasi(1, 'aktif', 3, true, false, GAJI_OK, 1, 7) === null, true);

// Mandor menargetkan Mandor LAIN tetap boleh — pagarnya soal identitas, bukan level.
$check('Mandor mengaktifkan Mandor lain -> boleh',
    $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, 7, 8) === null, true);

// Helper murninya berdiri sendiri supaya bisa dipakai/diuji terpisah.
$check('helper aktivasiDiriSendiri() ada di service',
    method_exists($svc, 'aktivasiDiriSendiri'), true);
if (method_exists($svc, 'aktivasiDiriSendiri')) {
    $check('aktivasiDiriSendiri(7,7) = true',  $svc->aktivasiDiriSendiri(7, 7), true);
    $check('aktivasiDiriSendiri(7,8) = false', $svc->aktivasiDiriSendiri(7, 8), false);
    $check('aktivasiDiriSendiri("7",7) = true (beda tipe tetap orang yang sama)',
        $svc->aktivasiDiriSendiri('7', 7), true);
    // ID kosong/null tidak boleh dianggap "cocok" (dua null bukan orang yang sama),
    // TAPI juga tidak boleh diam-diam meloloskan — controller wajib kirim ID asli.
    $check('aktivasiDiriSendiri(null,null) = false', $svc->aktivasiDiriSendiri(null, null), false);
}

// ── Batas LEVEL target: Mandor hanya boleh ke bawahnya ──────
//
// Sebelum ini pagarnya cuma "target bukan Owner". Artinya Mandor (level 3) bisa
// mengaktifkan ADMIN OPERASIONAL (level 2) — orang yang secara struktur di ATAS dia,
// dan yang jadwal/upahnya bukan urusan lapangan. Aktivasi itu melahirkan baris
// berupah (1x gaji harian + uang makan) atas nama Admin tanpa persetujuan Owner.
//
// Aturannya sekarang: Mandor -> level 3-7 saja. Owner -> semua target non-Owner.
$check('Mandor mengaktifkan ADMIN (level 2) DITOLAK',
    $boleh(3, 'aktif', 2, true, false, GAJI_OK), false);
$check('alasan tolak Mandor->Admin menyebut level',
    str_contains(strtolower((string) $alasan(3, 'aktif', 2, true, false, GAJI_OK)), 'level'), true);

foreach ([3, 4, 5, 6, 7] as $target) {
    $check("Mandor mengaktifkan karyawan lain level $target -> boleh",
        $svc->alasanTolakAktivasi(3, 'aktif', $target, true, false, GAJI_OK, 7, 8) === null, true);
}
foreach ([2, 3, 4, 5, 6, 7] as $target) {
    $check("Owner mengaktifkan level $target -> boleh",
        $boleh(1, 'aktif', $target, true, false, GAJI_OK), true);
}
$check('Owner mengaktifkan Owner tetap DITOLAK (Owner tidak absen masuk)',
    $boleh(1, 'aktif', 1, true, false, GAJI_OK), false);
$check('Mandor mengaktifkan Owner tetap DITOLAK',
    $boleh(3, 'aktif', 1, true, false, GAJI_OK), false);
// Level target dari DB bisa berupa string — jangan diam-diam lolos/gagal.
$check('Mandor -> target level string "2" tetap DITOLAK',
    $boleh(3, 'aktif', '2', true, false, GAJI_OK), false);
$check('Mandor -> target level string "5" tetap boleh',
    $svc->alasanTolakAktivasi(3, 'aktif', '5', true, false, GAJI_OK, 7, 8) === null, true);
// Larangan diri sendiri tetap menang lebih dulu (Mandor level 3 masuk 3-7).
$check('Mandor mengaktifkan DIRINYA SENDIRI tetap DITOLAK',
    $svc->alasanTolakAktivasi(3, 'aktif', 3, true, false, GAJI_OK, 7, 7) === null, false);

$check('helper levelTargetAktivasi() ada di service',
    method_exists($svc, 'levelTargetAktivasi'), true);
if (method_exists($svc, 'levelTargetAktivasi')) {
    $check('Owner: target aktivasi = 2..7', $svc->levelTargetAktivasi(1), [2, 3, 4, 5, 6, 7]);
    $check('Mandor: target aktivasi = 3..7', $svc->levelTargetAktivasi(3), [3, 4, 5, 6, 7]);
    $check('level lain: target aktivasi kosong (gagal tertutup)', $svc->levelTargetAktivasi(2), []);
}

// Layar ikut mengikuti aturan yang sama: tombol yang PASTI ditolak server tidak
// boleh dirender. Kalau tetap dirender, Mandor menekan "Aktifkan" pada baris Admin
// (atau barisnya sendiri) lalu cuma dapat pesan error — jebakan klik, sama seperti
// tombol pada karyawan yang sedang izin.
$srcViewKode = file_get_contents(__DIR__ . '/../../resources/views/absensi/kode-hari-ini.blade.php');
$check('view kode-hari-ini memakai penanda boleh_aktivasi dari controller',
    str_contains($srcViewKode, "\$d['boleh_aktivasi']"), true);

$posGate = strpos($srcViewKode, "\$d['boleh_aktivasi']");
$posForm = strpos($srcViewKode, "route('absensi.kerja-hari-libur'");
$check('penanda diperiksa SEBELUM form aktivasi dirender',
    $posGate !== false && $posForm !== false && $posGate < $posForm, true);

$srcCtrlKode = file_get_contents(__DIR__ . '/../../app/Http/Controllers/AbsensiController.php');
$posKodeHariIni = strpos($srcCtrlKode, 'function kodeHariIni(');
$posSetelahKode = $posKodeHariIni === false ? false : strpos($srcCtrlKode, 'private function', $posKodeHariIni + 20);
$bodyKodeHariIni = $posKodeHariIni === false ? '' : substr($srcCtrlKode, $posKodeHariIni, ($posSetelahKode ?: strlen($srcCtrlKode)) - $posKodeHariIni);

$check('controller menghitung boleh_aktivasi lewat policy yang sama (bukan level == 1 ditulis ulang)',
    str_contains($bodyKodeHariIni, 'bolehTargetAktivasi('), true);
$check('controller ikut memperhitungkan larangan mengaktifkan diri sendiri',
    str_contains($bodyKodeHariIni, 'aktivasiDiriSendiri('), true);

// Controller WAJIB mengoper ID aktor & target — kalau lupa, helper-nya benar tapi
// pagarnya tidak pernah menyala (spoof request tetap tembus).
$srcCtrl = file_get_contents(__DIR__ . '/../../app/Http/Controllers/AbsensiController.php');
$posPanggil = strpos($srcCtrl, '$svc->alasanTolakAktivasi(');
// Ambil seluruh ekspresi panggilan sampai penutup `);`, bukan jumlah karakter tetap.
// Daftar argumen sekarang punya level target + komentar keamanan yang cukup panjang;
// batas lama 600 karakter berhenti sebelum dua ID walau keduanya benar-benar dikirim.
$posTutupPanggil = $posPanggil === false ? false : strpos($srcCtrl, ');', $posPanggil);
$argPanggil = ($posPanggil === false || $posTutupPanggil === false)
    ? ''
    : substr($srcCtrl, $posPanggil, $posTutupPanggil + 2 - $posPanggil);
$check('controller mengoper ID aktor ke alasanTolakAktivasi()',
    str_contains($argPanggil, '$aktor->id'), true);
$check('controller mengoper ID karyawan target ke alasanTolakAktivasi()',
    str_contains($argPanggil, '$karyawan->id'), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
