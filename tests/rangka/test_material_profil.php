<?php
// FILE: tests/rangka/test_material_profil.php
// Jalankan: php tests/rangka/test_material_profil.php
// parseProfil = CADANGAN tebak-nama; kolom DB = sumber kebenaran (hollow banci!).
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\MasterMaterial;

$fail = false;
function check(string $n, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $n" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

check('Hollow 4x8 1mm', MasterMaterial::parseProfil('Hollow 4x8 1mm'), [4.0, 8.0]);
check('spasi & X besar', MasterMaterial::parseProfil('Hollow 5 X 10 tebal 1,2'), [5.0, 10.0]);
check('desimal koma', MasterMaterial::parseProfil('Hollow 3,5x7,5'), [3.5, 7.5]);
check('tanda kali ×', MasterMaterial::parseProfil('Hollow 4×8'), [4.0, 8.0]);
check('tanpa dimensi -> null', MasterMaterial::parseProfil('WF 150'), null);
check('null -> null', MasterMaterial::parseProfil(null), null);
// "1mm" tak boleh kebaca sbg dimensi: yang diambil pasangan pertama AxB
check('pasangan pertama yang diambil', MasterMaterial::parseProfil('Besi 4x8 grade 2x1'), [4.0, 8.0]);

// profilCm: kolom DB menang atas nama (hollow banci)
$m = new MasterMaterial(['nama' => 'Hollow 4x8 banci']);
$m->lebar_profil_cm = 3.5; $m->tinggi_profil_cm = 7.5;
check('kolom DB menang', $m->profilCm(), [3.5, 7.5]);
$m2 = new MasterMaterial(['nama' => 'Hollow 4x8 1mm']);
check('kolom kosong -> tebak nama', $m2->profilCm(), [4.0, 8.0]);
$m3 = new MasterMaterial(['nama' => 'WF 150']);
check('dua-duanya gagal -> null', $m3->profilCm(), null);

exit($fail ? 1 : 0);
