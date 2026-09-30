<?php
// FILE: tests/keamanan/test_kunci_keuangan.php
// Jalankan: php tests/keamanan/test_kunci_keuangan.php
//
// Kunci Keuangan (spec 2026-09-30-kunci-keuangan-design.md): data uang milik karyawan
// sendiri hanya terlihat setelah ketik ulang password, berlaku 300 detik; Owner bebas.
// Dikunci di sini: logika waktu/level (murni), validasi tujuan kembali (anti open-redirect),
// dan peta route (route uang WAJIB berbeban middleware `keuangan`).

require_once __DIR__ . '/../../app/Services/KunciKeuangan.php';

use App\Services\KunciKeuangan as K;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$now = 1_000_000;

// ── terbuka(): Owner bebas, karyawan butuh konfirmasi <= 300 dtk
check('Owner tanpa konfirmasi -> terbuka', K::terbuka(1, null, $now), true);
check('karyawan tanpa konfirmasi -> terkunci', K::terbuka(5, null, $now), false);
check('karyawan konfirmasi 5 dtk lalu -> terbuka', K::terbuka(5, $now - 5, $now), true);
check('tepat 300 dtk -> masih terbuka', K::terbuka(5, $now - 300, $now), true);
check('301 dtk -> terkunci', K::terbuka(5, $now - 301, $now), false);
check('konfirmasi di masa depan -> terkunci', K::terbuka(5, $now + 10, $now), false);
check('konfirmasi bukan angka -> terkunci', K::terbuka(5, 'abc', $now), false);
check('konfirmasi string angka (session) -> terbuka', K::terbuka(5, (string) ($now - 10), $now), true);
check('level null (tamu) -> terkunci', K::terbuka(null, $now, $now), false);
check('Admin (2) tetap dikunci', K::terbuka(2, null, $now), false);
check('DETIK = 300 (keputusan Elvan)', K::DETIK, 300);

// ── tujuanAman(): hanya path relatif satu-slash; selebihnya /dashboard
check('path biasa diterima', K::tujuanAman('/absensi?x=1'), '/absensi?x=1');
check('path slip diterima', K::tujuanAman('/penggajian/slip/12'), '/penggajian/slip/12');
foreach (['//evil.com', 'https://evil.com', '/\\evil.com', 'javascript:alert(1)', 'evil.com', '', null, "/ok\r\nSet-Cookie: x=1", '/\\/evil.com'] as $jahat) {
    check('ditolak: ' . var_export($jahat, true), K::tujuanAman($jahat), '/dashboard');
}

// ── Peta route: route uang WAJIB berbeban middleware (pintu belakang = bocor)
$json = shell_exec('cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && ' . escapeshellarg(PHP_BINARY) . ' artisan route:list --json 2>/dev/null');
$rute = json_decode((string) $json, true);
check('route:list terbaca', is_array($rute) && count($rute) > 50, true);

$peta = [];
foreach ($rute ?: [] as $r) {
    if (!empty($r['name']) && str_starts_with($r['method'], 'GET')) $peta[$r['name']] = $r['middleware'] ?? [];
}
foreach (['penggajian.slip-saya', 'penggajian.slip', 'kasbon.karyawan.index', 'kasbon.karyawan.surat',
          'absensi.rekap-bulanan', 'kpi.detail', 'kpi.ujian.hasil'] as $nama) {
    $mw = $peta[$nama] ?? [];
    check("dikunci penuh: $nama", in_array('keuangan', $mw, true) && !in_array('keuangan:samar', $mw, true), true);
}
foreach (['admin.dashboard', 'supervisor.dashboard', 'marketing.dashboard', 'teknisi.dashboard',
          'driver.dashboard', 'toko.dashboard', 'profil.index', 'absensi.index'] as $nama) {
    check("disamarkan: $nama", in_array('keuangan:samar', $peta[$nama] ?? [], true), true);
}
// POST yang menulis/membalas data uang juga WAJIB dikunci (temuan review: POST kasbon
// membocorkan "3x gaji = Rp X" lewat pesan error ke halaman mana pun).
$petaPost = [];
foreach ($rute ?: [] as $r) {
    if (!empty($r['name']) && str_starts_with($r['method'], 'POST')) $petaPost[$r['name']] = $r['middleware'] ?? [];
}
foreach (['kasbon.karyawan.store'] as $nama) {
    check("POST dikunci penuh: $nama", in_array('keuangan', $petaPost[$nama] ?? [], true), true);
}
// Halaman konfirmasi & tombol Lihat TIDAK boleh dikunci oleh dirinya sendiri (loop redirect).
foreach (['password.confirm', 'keuangan.buka'] as $nama) {
    $mw = $peta[$nama] ?? null;
    check("bebas kunci (anti-loop): $nama", $mw !== null && !in_array('keuangan', $mw, true) && !in_array('keuangan:samar', $mw, true), true);
}

exit($fail ? 1 : 0);
