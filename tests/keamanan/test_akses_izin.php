<?php
// FILE: tests/keamanan/test_akses_izin.php
// Jalankan: php tests/keamanan/test_akses_izin.php
//
// Ditemukan 20 Okt 2026: approval izin & input dinas luar cuma dijaga `auth`,
// jadi karyawan mana pun bisa menyetujui izinnya sendiri / mencatat dinas luar
// (hari dibayar) untuk siapa saja. Kini Owner (1) & Mandor (3) saja — sama dengan
// penerima notif pengajuan izin. Dibaca dari route:list asli.
$base = dirname(__DIR__, 2);

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$routes = json_decode((string) shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null'), true);
if (!is_array($routes)) {
    echo "FAIL — tidak bisa membaca `php artisan route:list --json`\n";
    exit(1);
}
$mw = [];
foreach ($routes as $r) {
    if (!empty($r['name'])) $mw[$r['name']] = $r['middleware'] ?? [];
}

foreach (['izin.approval', 'izin.approve', 'izin.reject', 'izin.dinas-luar', 'izin.pulang-cepat.setujui', 'izin.pulang-cepat.tolak'] as $route) {
    $check("`$route` dikunci level:1,3", in_array('level:1,3', $mw[$route] ?? [], true), true);
}

// Regresi: karyawan tetap bisa mengajukan & melihat izinnya sendiri
foreach (['izin.index', 'izin.create', 'izin.store'] as $route) {
    $adaLevel = false;
    foreach ($mw[$route] ?? ['level:<ROUTE TIDAK ADA>'] as $m) {
        if (str_starts_with((string) $m, 'level:')) $adaLevel = true;
    }
    $check("`$route` TIDAK dikunci level", $adaLevel, false);
}

// Ajukan pulang cepat = semua karyawan (cek level di server, bukan middleware)
$adaLevel = false;
foreach ($mw['absensi.ajukan-pulang-cepat'] ?? ['level:<ROUTE TIDAK ADA>'] as $m) {
    if (str_starts_with((string) $m, 'level:')) $adaLevel = true;
}
$check('`absensi.ajukan-pulang-cepat` TIDAK dikunci level', $adaLevel, false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
