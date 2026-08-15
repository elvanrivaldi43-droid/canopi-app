<?php
// FILE: tests/keamanan/test_akses_payroll_rekap.php
// Jalankan: php tests/keamanan/test_akses_payroll_rekap.php
//
// Tes HARDENING AKSES payroll & rekap (keputusan Bos 15 Agustus, matriks terkunci
// di docs plan .hermes/plans/2026-08-15_091529-hardening-akses-payroll-rekap.md).
//
// Kenapa penting: halaman rekap & penggajian menampilkan NOMINAL GAJI seluruh
// karyawan, dan aksinya (generate/bayar/approve kasbon) mengubah uang. Menyembunyikan
// tombol di sidebar BUKAN keamanan — siapa pun yang sudah login bisa mengetik URL-nya.
//
// Proteksi route dibaca dari daftar route Laravel yang SEBENARNYA
// (`php artisan route:list --json`), bukan dari regex atas routes/web.php.
$base = dirname(__DIR__, 2);

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$json = shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null');
$routes = json_decode((string) $json, true);
if (!is_array($routes)) {
    echo "FAIL — tidak bisa membaca `php artisan route:list --json`\n";
    exit(1);
}

$byName = [];
foreach ($routes as $r) {
    if (!empty($r['name'])) $byName[$r['name']] = $r;
}

$mw = function (string $name) use ($byName): array {
    return $byName[$name]['middleware'] ?? ['<ROUTE TIDAK ADA>'];
};

// Apakah route punya middleware level: apa pun (dipakai untuk membuktikan sebuah
// route SENGAJA dibiarkan terbuka untuk semua user login).
$adaLevel = function (string $name) use ($mw): bool {
    foreach ($mw($name) as $m) {
        if (str_starts_with((string) $m, 'level:')) return true;
    }
    return false;
};

// ═══════════════════════════════════════════════════════════
// TASK 1 — Rekap LINTAS-KARYAWAN: OWNER SAJA
// ═══════════════════════════════════════════════════════════
// Halaman ini memuat data absensi + nominal gaji harian SEMUA karyawan.
// Matriks Bos: Owner Ya; Admin/Mandor/karyawan lain Tidak.
//
// CATATAN PERUBAHAN (15 Agustus, Task 7): `absensi.rekap-bulanan` SENGAJA
// dikeluarkan dari daftar ini. Mengunci route-nya `level:1` ternyata kebablasan —
// halaman itu juga dipakai karyawan biasa untuk melihat rekap ABSENSINYA SENDIRI,
// jadi menguncinya memutus akses self-service 13 orang. Pagarnya dipindah ke
// controller (self-only untuk semua level kecuali Owner), diuji terpisah di
// tests/keamanan/test_regresi_minor.php. Rekap HARIAN tetap Owner-only karena
// memang tidak punya mode self-service.
foreach ([
    'absensi.rekap'         => 'rekap absensi harian semua karyawan + nominal',
] as $route => $ket) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` lewat grup web", in_array('web', $mw($route), true), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` dikunci level:1 (Owner saja)", in_array('level:1', $mw($route), true), true);
    $check("route `$route` TIDAK membuka level 3", in_array('level:1,3', $mw($route), true), false);
}

// ── Regresi: yang memang boleh Owner + Mandor tetap level:1,3 ──
// Mandor kehilangan rekap penuh (disengaja), tapi WAJIB tetap punya kode absen
// harian & tombol aktivasi masuk hari libur.
foreach ([
    'absensi.kode-hari-ini'    => 'lihat kode absen semua karyawan',
    'absensi.kerja-hari-libur' => 'aktifkan masuk hari libur',
] as $route => $ket) {
    $check("route `$route` tetap dilindungi level:1,3 ($ket)", in_array('level:1,3', $mw($route), true), true);
}

// ── Regresi: absen harian karyawan tidak boleh ikut terkunci ──
foreach (['absensi.index', 'absensi.masuk', 'absensi.lapor-progress', 'absensi.kembali-kerja', 'absensi.pulang'] as $route) {
    $check("route `$route` TIDAK dikunci level (semua karyawan tetap bisa absen)", $adaLevel($route), false);
}

// ═══════════════════════════════════════════════════════════
// TASK 2 — Penggajian: operasi administratif OWNER SAJA,
//          slip sendiri tetap self-service
// ═══════════════════════════════════════════════════════════
// Route di bawah ini menampilkan/mengubah UANG karyawan lain (generate slip,
// bayar, kasbon approve/tolak/tunda, potongan insidental, tabungan lebaran).
$penggajianOwnerOnly = [
    'penggajian.index'             => 'dashboard penggajian semua karyawan',
    'penggajian.generate'          => 'generate slip karyawan mana pun',
    'penggajian.generate-semua'    => 'generate slip massal',
    'penggajian.konfirmasi'        => 'konfirmasi slip',
    'penggajian.bayar'             => 'proses bayar slip',
    'penggajian.bayar-semua'       => 'proses bayar massal',
    'penggajian.kasbon'            => 'layar kasbon Owner (semua karyawan)',
    'penggajian.kasbon.store'      => 'buat kasbon untuk karyawan',
    'penggajian.kasbon.tunda'      => 'tunda cicilan kasbon',
    'penggajian.insidental.store'  => 'potongan insidental',
    'penggajian.tabungan-lebaran'  => 'set tabungan lebaran',
    'penggajian.kasbon.approve'    => 'approve kasbon',
    'penggajian.kasbon.tolak'      => 'tolak kasbon',
];
foreach ($penggajianOwnerOnly as $route => $ket) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` lewat grup web", in_array('web', $mw($route), true), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` dikunci level:1 (Owner saja)", in_array('level:1', $mw($route), true), true);
}

// ── URL & nama route TIDAK boleh berubah (view lama memanggil route() ini) ──
$uri = fn(string $name) => $byName[$name]['uri'] ?? '<ROUTE TIDAK ADA>';
foreach ([
    'penggajian.index'            => 'penggajian',
    'penggajian.generate'         => 'penggajian/generate',
    'penggajian.generate-semua'   => 'penggajian/generate-semua',
    'penggajian.slip'             => 'penggajian/slip/{slip}',
    'penggajian.konfirmasi'       => 'penggajian/slip/{slip}/konfirmasi',
    'penggajian.bayar'            => 'penggajian/slip/{slip}/bayar',
    'penggajian.bayar-semua'      => 'penggajian/bayar-semua',
    'penggajian.slip-saya'        => 'penggajian/slip-saya',
    'penggajian.kasbon'           => 'penggajian/kasbon',
    'penggajian.kasbon.store'     => 'penggajian/kasbon',
    'penggajian.kasbon.tunda'     => 'penggajian/kasbon/{kasbon}/tunda',
    'penggajian.insidental.store' => 'penggajian/insidental',
    'penggajian.tabungan-lebaran' => 'penggajian/tabungan-lebaran',
    'penggajian.kasbon.approve'   => 'penggajian/kasbon/{kasbon}/approve',
    'penggajian.kasbon.tolak'     => 'penggajian/kasbon/{kasbon}/tolak',
] as $route => $urlHarusnya) {
    $check("URL route `$route` tidak berubah", $uri($route), $urlHarusnya);
}

// ── Self-service: slip sendiri TIDAK boleh dikunci level:1 ──
// slip-saya = daftar slip milik sendiri. slip = halaman detail; keamanannya
// bukan lewat level (Owner ATAU pemilik), tapi lewat ownership guard di controller
// (lihat blok TASK 3 di bawah).
foreach ([
    'penggajian.slip-saya' => 'daftar slip milik sendiri',
    'penggajian.slip'      => 'detail slip (Owner atau pemilik slip)',
] as $route => $ket) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` TIDAK dikunci level (self-service tetap jalan)", $adaLevel($route), false);
}

// ═══════════════════════════════════════════════════════════
// TASK 3 — Ownership guard detail slip (Owner ATAU pemilik)
// ═══════════════════════════════════════════════════════════
// Route detail slip sengaja hanya `auth` (biar karyawan bisa buka slip sendiri),
// jadi satu-satunya penghalang IDOR adalah guard di controller. Helper-nya murni
// (tanpa DB/Auth) supaya bisa diuji langsung di sini.
require_once dirname(__DIR__) . '/bootstrap.php';

$kelas = \App\Http\Controllers\PenggajianController::class;
$adaHelper = method_exists($kelas, 'bolehLihatSlip');
$check('helper `PenggajianController::bolehLihatSlip()` ada', $adaHelper, true);

if ($adaHelper) {
    $boleh = fn($level, $idAktor, $idPemilik) => $kelas::bolehLihatSlip($level, $idAktor, $idPemilik);

    $check('Owner (level 1) boleh buka slip karyawan lain', $boleh(1, 1, 7), true);
    $check('Owner boleh buka slip sendiri',                 $boleh(1, 1, 1), true);
    $check('Karyawan boleh buka slip sendiri',              $boleh(5, 7, 7), true);
    $check('Admin (2) TIDAK boleh buka slip orang lain',    $boleh(2, 3, 7), false);
    $check('Mandor (3) TIDAK boleh buka slip orang lain',   $boleh(3, 4, 7), false);
    $check('Teknisi (5) TIDAK boleh buka slip orang lain',  $boleh(5, 7, 8), false);
    $check('Driver (6) TIDAK boleh buka slip orang lain',   $boleh(6, 9, 2), false);

    // Nilai dari DB/route sering berupa string ("7"), harus dibandingkan numerik —
    // tapi JANGAN longgar sampai "7abc" atau null lolos jadi sama.
    $check('ID string "7" == int 7 (pemilik tetap dikenali)', $boleh('5', '7', 7), true);
    $check('level string "1" dikenali sebagai Owner',         $boleh('1', 1, 99), true);
    $check('ID beda walau string ("7" vs "8") ditolak',       $boleh('5', '7', '8'), false);
    $check('aktor null tidak pernah lolos',                   $boleh(5, null, 7), false);
    $check('pemilik null tidak pernah lolos',                 $boleh(5, 7, null), false);
    $check('null vs null tidak dianggap cocok',               $boleh(5, null, null), false);
}

// ── Guard harus dipanggil SEBELUM load()/query detail ──
// Kalau abort_unless ada di bawah $slip->load(...), data karyawan lain sudah
// terlanjur diambil dari DB sebelum ditolak.
$sumber = file_get_contents($base . '/app/Http/Controllers/PenggajianController.php');
$check('file PenggajianController terbaca', is_string($sumber) && $sumber !== '', true);

$posShow = strpos((string) $sumber, 'public function show(');
$check('method show() ada', $posShow !== false, true);
if ($posShow !== false) {
    // Ambil badan method show() saja (sampai method berikutnya).
    $posBerikut = strpos((string) $sumber, 'public function ', $posShow + 10);
    $badanShow  = substr((string) $sumber, $posShow, ($posBerikut === false ? strlen((string) $sumber) : $posBerikut) - $posShow);

    $posGuard = strpos($badanShow, 'bolehLihatSlip');
    $posLoad  = strpos($badanShow, '->load(');
    $posQuery = strpos($badanShow, 'Absensi::where');

    $check('show() memanggil bolehLihatSlip', $posGuard !== false, true);
    $check('show() menolak dengan abort_unless/abort 403',
        (bool) preg_match('/abort_unless\s*\(|abort\s*\(\s*403/', $badanShow), true);
    $check('guard dipanggil SEBELUM $slip->load()',
        $posGuard !== false && $posLoad !== false && $posGuard < $posLoad, true);
    $check('guard dipanggil SEBELUM query detail absensi',
        $posGuard !== false && $posQuery !== false && $posGuard < $posQuery, true);
}

// ═══════════════════════════════════════════════════════════
// TASK 4 — Kasbon karyawan (/kasbon-saya) tetap self-service
// ═══════════════════════════════════════════════════════════
// Hardening payroll TIDAK boleh ikut mengunci pengajuan/riwayat/surat kasbon
// milik user sendiri — ini modul yang dipakai semua karyawan.
foreach ([
    'kasbon.karyawan.index' => ['kasbon-saya', 'riwayat & form pengajuan sendiri'],
    'kasbon.karyawan.store' => ['kasbon-saya', 'ajukan kasbon sendiri'],
    'kasbon.karyawan.surat' => ['kasbon-saya/{kasbon}/surat', 'cetak surat pernyataan sendiri'],
] as $route => [$urlHarusnya, $ket]) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` TIDAK dikunci level:1 (semua karyawan tetap bisa)", in_array('level:1', $mw($route), true), false);
    $check("route `$route` TIDAK dikunci level apa pun", $adaLevel($route), false);
    $check("URL route `$route` tidak berubah", $uri($route), $urlHarusnya);
}

// ── surat() tetap menolak kalau kasbon bukan milik yang login ──
$sumberKasbon = file_get_contents($base . '/app/Http/Controllers/KasbonKaryawanController.php');
$check('file KasbonKaryawanController terbaca', is_string($sumberKasbon) && $sumberKasbon !== '', true);

$posSurat = strpos((string) $sumberKasbon, 'public function surat(');
$check('method surat() ada', $posSurat !== false, true);
if ($posSurat !== false) {
    $posBerikut  = strpos((string) $sumberKasbon, 'public function ', $posSurat + 10);
    $badanSurat  = substr((string) $sumberKasbon, $posSurat, ($posBerikut === false ? strlen((string) $sumberKasbon) : $posBerikut) - $posSurat);

    $check('surat() membandingkan kasbon->user_id dengan Auth::id()',
        (bool) preg_match('/\$kasbon->user_id\s*!==?\s*Auth::id\(\)/', $badanSurat), true);
    $check('surat() menolak non-pemilik dengan 403',
        (bool) preg_match('/abort\s*\(\s*403/', $badanSurat), true);

    $posGuardSurat = strpos($badanSurat, 'Auth::id()');
    $posIsiSurat   = strpos($badanSurat, '$user = $kasbon->user');
    $check('guard kepemilikan dijalankan SEBELUM surat dirakit',
        $posGuardSurat !== false && $posIsiSurat !== false && $posGuardSurat < $posIsiSurat, true);
}

// ── Halaman detail slip tidak boleh menjebak pemilik ke halaman Owner ──
// Karyawan yang membuka slipnya sendiri lewat /slip-saya harus punya jalan
// kembali; kalau tombol "Kembali" selalu ke penggajian.index (kini Owner-only),
// dia dilempar 403 dari halaman yang sah dia buka.
$bladeSlip = file_get_contents($base . '/resources/views/penggajian/slip.blade.php');
$check('view penggajian/slip.blade.php terbaca', is_string($bladeSlip) && $bladeSlip !== '', true);
$check('slip.blade.php menyediakan jalan kembali ke slip-saya untuk non-Owner',
    str_contains((string) $bladeSlip, "route('penggajian.slip-saya')"), true);
$check('link ke penggajian.index (Owner-only) tidak dirender tanpa syarat',
    (bool) preg_match("/@if[^\n]*\n?[^@]*route\('penggajian\.index'\)/", (string) $bladeSlip), true);

// Halaman detail slip juga tidak boleh memuat form aksi payroll (bayar/konfirmasi/
// generate) — itu milik layar Owner, bukan halaman yang dibuka karyawan.
foreach (['penggajian.bayar', 'penggajian.konfirmasi', 'penggajian.generate'] as $aksi) {
    $check("slip.blade.php tidak memuat form aksi `$aksi`",
        str_contains((string) $bladeSlip, "route('$aksi'"), false);
}

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
