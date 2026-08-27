<?php
// FILE: tests/rab/test_snapshot_guard.php
// Jalankan: php tests/rab/test_snapshot_guard.php
//
// Guard konflik dua tab autosave RAB (Utang #9, kasus nyata 24 Ags: tab kedua yang
// megang data lama menimpa data baru di server). Aturan:
// - base_md5 null  -> TIDAK konflik (klien lama / kompat mundur — perilaku lama jalan terus)
// - snapshot tersimpan kosong/null -> TIDAK konflik (save pertama selalu boleh)
// - md5(tersimpan) != base_md5 -> KONFLIK (ada penulis lain sejak klien memuat)
// - md5(tersimpan) == base_md5 -> aman

require_once __DIR__ . '/../../app/Services/SnapshotGuard.php';

use App\Services\SnapshotGuard;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

$snapA = '{"panes":[{"nama":"Opsi 1"}]}';
$snapB = '{"panes":[{"nama":"Opsi 2 dari tab lain"}]}';

check('base_md5 null -> tidak konflik (klien lama)', SnapshotGuard::conflict($snapA, null), false);
check('tersimpan null -> tidak konflik (save pertama)', SnapshotGuard::conflict(null, md5($snapA)), false);
check('tersimpan string kosong -> tidak konflik', SnapshotGuard::conflict('', md5($snapA)), false);
check('md5 cocok -> aman', SnapshotGuard::conflict($snapA, md5($snapA)), false);
check('md5 beda (tab lain sudah nulis) -> KONFLIK', SnapshotGuard::conflict($snapB, md5($snapA)), true);
check('base_md5 string kosong dianggap null -> tidak konflik', SnapshotGuard::conflict($snapA, ''), false);

exit($fail ? 1 : 0);
