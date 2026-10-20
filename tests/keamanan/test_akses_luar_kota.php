<?php
// FILE: tests/keamanan/test_akses_luar_kota.php
// Jalankan: php tests/keamanan/test_akses_luar_kota.php
// Spec: docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md
$base = dirname(__DIR__, 2);

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$routes = json_decode((string) shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null'), true);
if (!is_array($routes)) { echo "FAIL — tidak bisa membaca route:list\n"; exit(1); }
$r = [];
foreach ($routes as $x) if (!empty($x['name'])) $r[$x['name']] = $x;
$mw = fn (string $n) => $r[$n]['middleware'] ?? ['<ROUTE TIDAK ADA>'];

// Mengajukan: semua karyawan login (aturan level dicek di server via LuarKota::bolehAjukan)
$check('ajukan-luar-kantor terdaftar', isset($r['absensi.ajukan-luar-kantor']), true);
$check('ajukan-luar-kantor POST', $r['absensi.ajukan-luar-kantor']['method'] ?? null, 'POST');
$check('ajukan-luar-kantor dilindungi auth', in_array('auth', $mw('absensi.ajukan-luar-kantor'), true), true);
$adaLevel = false;
foreach ($mw('absensi.ajukan-luar-kantor') as $m) if (str_starts_with((string) $m, 'level:')) $adaLevel = true;
$check('ajukan-luar-kantor TIDAK dikunci level', $adaLevel, false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
