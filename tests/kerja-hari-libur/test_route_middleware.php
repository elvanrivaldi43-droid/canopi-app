<?php
// FILE: tests/kerja-hari-libur/test_route_middleware.php
// Jalankan: php tests/kerja-hari-libur/test_route_middleware.php
//
// Tes PROTEKSI ROUTE — dibaca dari daftar route Laravel yang SEBENARNYA
// (php artisan route:list --json), bukan dari membaca routes/web.php pakai regex.
// Kalau proteksi hilang/dipindah, tes ini gagal.
//
// Kenapa penting: koreksi absen mengubah status + nominal gaji karyawan LAIN.
// Tanpa middleware level, karyawan biasa yang sudah login bisa POST langsung
// dengan userId siapa pun.
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

// ── Route yang WAJIB terkunci OWNER SAJA (level 1) ──────────
// Keputusan Bos (15 Agustus): koreksi mengubah NOMINAL GAJI karyawan lain, jadi
// hanya Owner. Mandor (level 3) tetap boleh melihat kode & mengaktifkan masuk
// hari libur, tapi tidak boleh mengubah angka gaji.
foreach ([
    'absensi.koreksi'        => 'koreksi absen (ubah status + nominal gaji)',
    'absensi.koreksi-manual' => 'koreksi manual (bikin baris absensi baru)',
] as $route => $ket) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` dikunci level:1 (Owner saja)", in_array('level:1', $mw($route), true), true);
    $check("route `$route` TIDAK lagi membuka level 3", in_array('level:1,3', $mw($route), true), false);
}

// ── Route yang tetap boleh Owner (1) & Supervisor/Mandor (3) ──
foreach ([
    'absensi.kode-hari-ini'    => 'lihat kode absen semua karyawan',
    'absensi.kerja-hari-libur' => 'aktifkan masuk hari libur',
] as $route => $ket) {
    $check("route `$route` terdaftar ($ket)", isset($byName[$route]), true);
    $check("route `$route` dilindungi auth", in_array('auth', $mw($route), true), true);
    $check("route `$route` dilindungi level:1,3", in_array('level:1,3', $mw($route), true), true);
}

// ── Regresi: route absen harian karyawan JANGAN ikut terkunci ──
// (kalau ikut terkunci, 14 karyawan tidak bisa absen sama sekali)
foreach (['absensi.index', 'absensi.masuk', 'absensi.lapor-progress', 'absensi.kembali-kerja', 'absensi.pulang'] as $route) {
    $adaLevel = false;
    foreach ($mw($route) as $m) {
        if (str_starts_with((string) $m, 'level:')) { $adaLevel = true; break; }
    }
    $check("route `$route` TIDAK dikunci level (semua karyawan tetap bisa absen)", $adaLevel, false);
}

// ── Metode HTTP koreksi tetap POST (CSRF berlaku lewat grup `web`) ──
$check('koreksi hanya POST',        $byName['absensi.koreksi']['method'] ?? null, 'POST');
$check('koreksi-manual hanya POST', $byName['absensi.koreksi-manual']['method'] ?? null, 'POST');
$check('koreksi lewat grup web (CSRF aktif)', in_array('web', $mw('absensi.koreksi'), true), true);
$check('koreksi-manual lewat grup web (CSRF aktif)', in_array('web', $mw('absensi.koreksi-manual'), true), true);

// ═══════════════════════════════════════════════════════════
// TAMPILAN: tombol/form koreksi tidak boleh muncul buat non-Owner
// ═══════════════════════════════════════════════════════════
// Middleware sudah menutup jalur POST-nya, tapi kalau tombolnya tetap tampil,
// Mandor akan mengisi form lalu ditolak 403 — bingung, dan modal "Simpan Koreksi"
// itu satu-satunya tempat halaman ini bisa mengubah gaji.
//
// Dicek dengan menelusuri nesting @if/@endif di blade-nya: elemen koreksi harus
// BERADA DI DALAM blok kondisi yang mengecek level Owner — bukan sekadar ada
// tulisan "level == 1" di suatu tempat di file.
$bladeRekap = $base . '/resources/views/absensi/rekap.blade.php';
$check('view absensi/rekap.blade.php ada', file_exists($bladeRekap), true);

$barisBlade = file_exists($bladeRekap) ? file($bladeRekap) : [];

// Stack kondisi @if yang sedang terbuka di tiap baris.
$stackPerBaris = [];
$stack = [];
foreach ($barisBlade as $i => $baris) {
    // Token @if(...) dan @endif diproses berurutan sesuai posisinya di baris.
    preg_match_all('/@(if|endif)\b/', $baris, $tok, PREG_OFFSET_CAPTURE);
    $stackPerBaris[$i] = $stack; // kondisi yang berlaku SAAT baris ini dirender
    foreach ($tok[1] as $t) {
        if ($t[0] === 'if') {
            // ambil isi kurung kondisinya (cukup sampai akhir baris)
            $sisa = substr($baris, $t[1]);
            $stack[] = $sisa;
            $stackPerBaris[$i] = $stack;
        } else {
            array_pop($stack);
        }
    }
}
$check('nesting @if/@endif di rekap.blade.php seimbang (parser tes valid)', count($stack), 0);

$diDalamGuardOwner = function (string $penanda) use ($barisBlade, $stackPerBaris): bool {
    $ketemu = false;
    foreach ($barisBlade as $i => $baris) {
        if (!str_contains($baris, $penanda)) continue;
        $ketemu = true;
        $terjaga = false;
        foreach ($stackPerBaris[$i] as $kondisi) {
            if (preg_match('/bolehKoreksi|level\s*==\s*1/', $kondisi)) { $terjaga = true; break; }
        }
        if (!$terjaga) return false; // ada kemunculan yang TIDAK terjaga
    }
    return $ketemu;
};

$check('tombol "Koreksi" (pemanggil bukaKoreksi) hanya dirender untuk Owner',
    $diDalamGuardOwner('bukaKoreksi('), true);
$check('modal koreksi (form pengubah gaji) hanya dirender untuk Owner',
    $diDalamGuardOwner('id="modalKoreksi"'), true);
$check('kolom "Aksi" hanya dirender untuk Owner (biar tabel tidak punya kolom kosong)',
    $diDalamGuardOwner('>Aksi<'), true);

$isiBlade = implode('', $barisBlade);
$check('penjaga tampilan dihitung dari level user yang login (auth), bukan variabel bebas',
    (bool) preg_match('/\$bolehKoreksi\s*=\s*[^;]*auth\(\)[^;]*level\s*==\s*1/', $isiBlade), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
