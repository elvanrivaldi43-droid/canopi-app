<?php
// FILE: tests/swe/test_skill_karyawan_service.php
// Jalankan: php tests/swe/test_skill_karyawan_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\SkillKaryawanService;

$svc = new SkillKaryawanService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── deteksiKategori ──────────────────────────────────────
$check('mengandung "Tukang" -> tukang', $svc->deteksiKategori('Tukang Las'), 'tukang');
$check('mengandung "Kepala Tukang" -> tukang', $svc->deteksiKategori('Kepala Tukang'), 'tukang');
$check('mengandung "kenek" huruf kecil -> kenek', $svc->deteksiKategori('kenek cat'), 'kenek');
$check('mengandung "KENEK" huruf besar -> kenek (case-insensitive)', $svc->deteksiKategori('KENEK POTONG'), 'kenek');
$check('tidak mengandung keduanya -> null', $svc->deteksiKategori('Admin Sales'), null);
$check('tidak mengandung keduanya (Surveyor) -> null', $svc->deteksiKategori('Surveyor'), null);

// ── skillOtomatisUntukKategori ────────────────────────────
$rabSkill = [
    (object) ['id' => 1, 'default_role' => 'tukang'],
    (object) ['id' => 2, 'default_role' => 'kenek'],
    (object) ['id' => 3, 'default_role' => 'tukang_kenek'],
    (object) ['id' => 4, 'default_role' => 'manual'],
];
$check('kategori tukang -> ambil id 1 (tukang) & 3 (tukang_kenek), urut naik',
    $svc->skillOtomatisUntukKategori('tukang', $rabSkill), [1, 3]);
$check('kategori kenek -> ambil id 2 (kenek) & 3 (tukang_kenek), urut naik',
    $svc->skillOtomatisUntukKategori('kenek', $rabSkill), [2, 3]);
$check('kategori null -> tidak ada yang otomatis nempel',
    $svc->skillOtomatisUntukKategori(null, $rabSkill), []);
$check('skill default_role=manual TIDAK PERNAH otomatis, kategori apapun',
    in_array(4, $svc->skillOtomatisUntukKategori('tukang', $rabSkill)), false);

// ── susunUserSkill ─────────────────────────────────────────
// Karyawan tukang (userId=99), dicentang: id 1 (otomatis untuk tukang), id 3 (otomatis
// tukang_kenek), id 4 (skill manual, dicentang sendiri oleh Admin).
$hasil = $svc->susunUserSkill(99, [1, 3, 4], 'tukang', $rabSkill);
$check('susunUserSkill: 3 baris tersusun', count($hasil), 3);
$check('id 1 -> sumber default_role (otomatis untuk tukang)',
    collect($hasil)->firstWhere('rab_skill_id', 1)['sumber'], 'default_role');
$check('id 3 -> sumber default_role (tukang_kenek juga otomatis untuk tukang)',
    collect($hasil)->firstWhere('rab_skill_id', 3)['sumber'], 'default_role');
$check('id 4 -> sumber manual (default_role=manual, dicentang sendiri)',
    collect($hasil)->firstWhere('rab_skill_id', 4)['sumber'], 'manual');
$check('semua baris punya user_id yang benar',
    collect($hasil)->pluck('user_id')->unique()->all(), [99]);

// Skill id 2 (default_role=kenek) dicentang MANUAL oleh karyawan kategori tukang ->
// tetap tersimpan, tapi sumbernya manual (bukan otomatis untuk kategori dia).
$hasil2 = $svc->susunUserSkill(99, [2], 'tukang', $rabSkill);
$check('skill kenek dicentang manual oleh tukang -> sumber manual (bukan otomatis)',
    $hasil2[0]['sumber'], 'manual');

// Tidak dicentang sama sekali -> array kosong, bukan error.
$check('tidak ada yang dicentang -> array kosong', $svc->susunUserSkill(99, [], 'tukang', $rabSkill), []);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
