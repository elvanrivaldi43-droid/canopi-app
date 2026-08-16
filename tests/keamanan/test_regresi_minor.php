<?php
// FILE: tests/keamanan/test_regresi_minor.php
// Jalankan: php tests/keamanan/test_regresi_minor.php
//
// TASK 7 — Tutup regresi & minor yang tersisa.
//
// 1. `absensi.rekap-bulanan` dikunci `level:1` di sesi hardening sebelumnya. Itu
//    KEBABLASAN: halaman ini juga dipakai karyawan biasa untuk melihat rekap
//    ABSENSINYA SENDIRI. Menguncinya ke Owner memutus akses self-service 13 orang.
//    Perbaikan yang benar: route kembali `auth`, tapi controller MEMAKSA self-only
//    untuk semua level kecuali Owner — jadi datanya aman tanpa memutus siapa pun.
//
// 2. Migrasi kolom kerja hari libur harus idempotent (`Schema::hasColumn`) karena
//    kolomnya dipasang manual lewat SQL production lebih dulu. Tanpa penjaga itu,
//    `php artisan migrate` di instalasi mana pun akan gagal "column already exists".
//
// 3. Profil sudah MENGHITUNG `kerja_libur` tapi tidak pernah menampilkannya —
//    karyawan tidak punya cara melihat hari libur yang dia masuki.
//
// 4. Slip "yatim" (baris slip yang user-nya sudah terhapus) membuat halaman detail
//    melempar TypeError, bukan 404 yang rapi.
require __DIR__ . '/../bootstrap.php';

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Buang komentar sebelum memeriksa "apakah pola ini masih dipakai" — kalau tidak,
// komentar yang MENJELASKAN kenapa pola lama dibuang malah terhitung sebagai
// pemakaian, dan tes menuduh kode yang sudah benar.
$tanpaKomentarPhp = function (string $kode): string {
    $bersih = '';
    foreach (token_get_all("<?php\n" . $kode) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $bersih .= is_array($t) ? $t[1] : $t;
    }
    return $bersih;
};
// Blade: buang {{-- komentar --}} (tidak pernah sampai ke HTML).
$tanpaKomentarBlade = fn(string $b): string => (string) preg_replace('/\{\{--.*?--\}\}/s', '', $b);

$srcAbsen  = file_get_contents($base . '/app/Http/Controllers/AbsensiController.php');
$srcSlip   = file_get_contents($base . '/app/Http/Controllers/PenggajianController.php');
$srcProfil = file_get_contents($base . '/resources/views/profil/index.blade.php');
$srcRekapB = file_get_contents($base . '/resources/views/absensi/rekap-bulanan.blade.php');

// ═══════════════════════════════════════════════════════════
// 1. rekap-bulanan: route `auth`, controller self-only kecuali Owner
// ═══════════════════════════════════════════════════════════
$json   = shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null');
$routes = json_decode((string) $json, true);
$byName = [];
foreach ((array) $routes as $r) {
    if (!empty($r['name'])) $byName[$r['name']] = $r;
}
$mw = fn(string $n): array => $byName[$n]['middleware'] ?? ['<ROUTE TIDAK ADA>'];

$check('route `absensi.rekap-bulanan` terdaftar', isset($byName['absensi.rekap-bulanan']), true);
$check('route `absensi.rekap-bulanan` tetap butuh login', in_array('auth', $mw('absensi.rekap-bulanan'), true), true);
$check('route `absensi.rekap-bulanan` TIDAK lagi dikunci level:1 (karyawan tetap bisa lihat rekapnya sendiri)',
    in_array('level:1', $mw('absensi.rekap-bulanan'), true), false);

// Rekap HARIAN tetap Owner-only — halaman itu memang lintas-karyawan + nominal,
// dan tidak punya mode self-service.
$check('route `absensi.rekap` (harian) TETAP Owner-only',
    in_array('level:1', $mw('absensi.rekap'), true), true);

// Pagar sebenarnya ada di controller.
$posRB  = strpos($srcAbsen, 'function rekapBulanan(');
$posRBE = $posRB === false ? false : strpos($srcAbsen, 'public function', $posRB + 20);
$bodyRB = $posRB === false ? '' : substr($srcAbsen, $posRB, ($posRBE ?: strlen($srcAbsen)) - $posRB);

$check('badan rekapBulanan() terbaca', strlen($bodyRB) > 400, true);
$kodeRB = $tanpaKomentarPhp($bodyRB);

// Ambang lama `level > 2` membiarkan Admin (level 2) melihat SEMUA karyawan.
$check('rekapBulanan() tidak lagi memakai ambang lama `level > 2`',
    (bool) preg_match('/level\s*>\s*2/', $kodeRB), false);
$check('rekapBulanan() tidak lagi memakai ambang lama `level <= 2`',
    (bool) preg_match('/level\s*<=\s*2/', $kodeRB), false);
$check('rekapBulanan() memakai helper Owner terpusat',
    str_contains($kodeRB, 'bolehRekapSemua('), true);

// Helper murni: hanya Owner yang boleh lintas-karyawan.
$check('helper bolehRekapSemua() ada di controller',
    method_exists(\App\Http\Controllers\AbsensiController::class, 'bolehRekapSemua'), true);

if (method_exists(\App\Http\Controllers\AbsensiController::class, 'bolehRekapSemua')) {
    $b = fn($lv) => \App\Http\Controllers\AbsensiController::bolehRekapSemua($lv);
    $check('Owner (1) boleh rekap semua karyawan', $b(1), true);
    $check('Owner level string "1" juga boleh (kolom level tanpa cast)', $b('1'), true);
    foreach ([2, 3, 4, 5, 6, 7] as $lv) {
        $check("level $lv DIPAKSA self-only", $b($lv), false);
    }
    $check('level null gagal TERTUTUP', $b(null), false);
    $check('level "" gagal TERTUTUP', $b(''), false);
}

// Kontrol lintas-user di view hanya untuk Owner.
$check('view rekap-bulanan memagari kontrol lintas-user dengan helper yang sama',
    str_contains($srcRekapB, 'bolehRekapSemua'), true);
$check('view rekap-bulanan tidak lagi memagari dengan `level <= 2`',
    (bool) preg_match('/level\s*<=\s*2/', $tanpaKomentarBlade($srcRekapB)), false);

// ═══════════════════════════════════════════════════════════
// 2. Migrasi idempotent — up DAN down
// ═══════════════════════════════════════════════════════════
$mig = file_get_contents($base . '/database/migrations/2026_08_15_000002_add_kerja_hari_libur_to_absensi_table.php');

$posUp   = strpos($mig, 'function up(');
$posDown = strpos($mig, 'function down(');
$bodyUp   = $posUp === false ? '' : substr($mig, $posUp, ($posDown ?: strlen($mig)) - $posUp);
$bodyDown = $posDown === false ? '' : substr($mig, $posDown);

foreach (['kerja_hari_libur', 'upah_hari_libur'] as $kolom) {
    $check("up(): kolom `$kolom` dijaga hasColumn (aman setelah SQL manual)",
        (bool) preg_match("/hasColumn\(\s*'absensi'\s*,\s*'" . $kolom . "'\s*\)/", $bodyUp), true);
    $check("down(): kolom `$kolom` dijaga hasColumn (tidak error kalau sudah tidak ada)",
        (bool) preg_match("/hasColumn\(\s*'absensi'\s*,\s*'" . $kolom . "'\s*\)/", $bodyDown), true);
}
$check('up() tetap menjaga keberadaan tabelnya', str_contains($bodyUp, "hasTable('absensi')"), true);

// ═══════════════════════════════════════════════════════════
// 3. Profil menampilkan jumlah kerja hari libur
// ═══════════════════════════════════════════════════════════
$check('ProfilController tetap menghitung kerja_libur',
    str_contains(file_get_contents($base . '/app/Http/Controllers/ProfilController.php'), "'kerja_libur'"), true);
$check('view profil MENAMPILKAN angka kerja_libur (bukan cuma dihitung diam-diam)',
    str_contains($srcProfil, "\$stats['kerja_libur']"), true);
$check('view profil memberi label yang bisa dimengerti karyawan',
    (bool) preg_match('/Kerja Hari Libur|Masuk Hari Libur/i', $srcProfil), true);

// ═══════════════════════════════════════════════════════════
// 4. Slip yatim: 404 rapi, bukan TypeError
// ═══════════════════════════════════════════════════════════
$posShow  = strpos($srcSlip, 'function show(');
$posShowE = $posShow === false ? false : strpos($srcSlip, 'public function', $posShow + 20);
$bodyShow = $posShow === false ? '' : substr($srcSlip, $posShow, ($posShowE ?: strlen($srcSlip)) - $posShow);

$check('badan show() terbaca', strlen($bodyShow) > 200, true);
$kodeShow = $tanpaKomentarPhp($bodyShow);
$check('show() menjaga slip yatim (user terhapus) dengan abort 404',
    (bool) preg_match('/abort_unless\(\s*\$slip->user\b/', $kodeShow), true);

// Penjaga 404 harus SETELAH cek hak akses (jangan bocorkan "slip ini ada tapi
// yatim" ke orang yang tidak berhak) tapi SEBELUM petaLiburBulan() yang
// menuntut objek User dan akan melempar TypeError kalau null.
$posOwner = strpos($kodeShow, 'bolehLihatSlip(');
$posYatim = strpos($kodeShow, 'abort_unless($slip->user');
$posPeta  = strpos($kodeShow, 'petaLiburBulan(');

$check('penjaga yatim ditempatkan SETELAH pemeriksaan hak akses',
    $posOwner !== false && $posYatim !== false && $posOwner < $posYatim, true);
$check('penjaga yatim ditempatkan SEBELUM petaLiburBulan() (sumber TypeError)',
    $posYatim !== false && $posPeta !== false && $posYatim < $posPeta, true);

// ═══════════════════════════════════════════════════════════
// 4b. Migrasi slip_gaji: down() juga harus per-kolom
//
// `up()` sudah memasang kolom satu per satu di balik hasColumn, tapi `down()`
// menjatuhkan keduanya sekaligus lewat satu dropColumn tanpa pemeriksaan.
// Kolom aslinya dipasang lewat SQL manual di production, jadi keadaan "satu kolom
// ada, satu tidak" benar-benar mungkin — dan rollback di keadaan itu berhenti dengan
// error di tengah jalan (kolom pertama sudah kejatuhan, kolom kedua bikin exception),
// menyisakan tabel setengah jadi. Sama seperti migrasi absensi di bagian 4.
// ═══════════════════════════════════════════════════════════
$migSlip = file_get_contents($base . '/database/migrations/2026_08_15_000003_add_kerja_hari_libur_to_slip_gaji_table.php');

$posUpS   = strpos($migSlip, 'function up(');
$posDownS = strpos($migSlip, 'function down(');
$bodyUpS   = $posUpS === false ? '' : substr($migSlip, $posUpS, ($posDownS ?: strlen($migSlip)) - $posUpS);
$bodyDownS = $posDownS === false ? '' : substr($migSlip, $posDownS);

foreach (['hari_kerja_libur', 'upah_hari_libur'] as $kolom) {
    $check("slip_gaji up(): kolom `$kolom` dijaga hasColumn",
        (bool) preg_match("/hasColumn\(\s*'slip_gaji'\s*,\s*'" . $kolom . "'\s*\)/", $bodyUpS), true);
    $check("slip_gaji down(): kolom `$kolom` dijaga hasColumn (tidak error kalau sudah tidak ada)",
        (bool) preg_match("/hasColumn\(\s*'slip_gaji'\s*,\s*'" . $kolom . "'\s*\)/", $bodyDownS), true);
}
$check('slip_gaji down(): tidak lagi menjatuhkan dua kolom sekaligus tanpa cek',
    (bool) preg_match('/dropColumn\(\s*\[/', $bodyDownS), false);
$check('slip_gaji down(): tetap dijaga hasTable',
    str_contains($bodyDownS, "hasTable('slip_gaji')"), true);

// ═══════════════════════════════════════════════════════════
// 4c. Komentar ProfilController tidak boleh menyesatkan
//
// Keputusan Bos sudah dibalik: aktivasi MEMBATALKAN libur, jadi tanggal itu hari
// kerja biasa dan ikut penyebut MAUPUN pembilang (lihat statistikKehadiran).
// Komentar lama di ProfilController masih menyatakan sebaliknya ("tidak ikut
// hitungan kehadiran reguler / bukan bagian dari penyebut"). Komentar yang salah
// di titik statistik seperti ini bukan sekadar kosmetik: orang berikutnya yang
// membaca akan menyimpulkan kode-nya bug lalu "memperbaikinya" balik ke rancangan
// lama — yang justru membuat karyawan mangkir di hari aktivasi hilang dari laporan.
// ═══════════════════════════════════════════════════════════
$profil = file_get_contents($base . '/app/Http/Controllers/ProfilController.php');

$komentarProfil = '';
foreach (token_get_all($profil) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) $komentarProfil .= $t[1] . "\n";
}
$komentarProfil = strtolower($komentarProfil);

$check('ProfilController: tidak ada lagi klaim "tidak ikut hitungan kehadiran"',
    str_contains($komentarProfil, 'tidak ikut hitungan kehadiran'), false);
$check('ProfilController: tidak ada lagi klaim "bukan bagian dari penyebut"',
    str_contains($komentarProfil, 'bukan bagian dari penyebut'), false);
$check('ProfilController: komentar statistik menyebut hari kerja biasa/normal',
    str_contains($komentarProfil, 'hari kerja biasa') || str_contains($komentarProfil, 'hari kerja normal'), true);

// ═══════════════════════════════════════════════════════════
// 5. Tidak ada data production yang disentuh oleh Task ini
// ═══════════════════════════════════════════════════════════
foreach (glob($base . '/docs/sql/*.sql') as $f) {
    $isi = strtoupper((string) preg_replace('/--[^\n]*/', '', (string) file_get_contents($f)));
    // Lookbehind mengecualikan "ON DELETE"/"ON UPDATE" — itu klausa FK constraint
    // (DDL, mis. "ON DELETE CASCADE"), bukan perintah UPDATE/DELETE yang menyentuh
    // data. $isi sudah di-uppercase di atas, jadi lookbehind-nya "ON " literal.
    $check('SQL `' . basename($f) . '` tidak mengandung UPDATE/DELETE data',
        (bool) preg_match('/\b(?<!ON )(UPDATE|DELETE|TRUNCATE)\b/', $isi), false);
}

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
