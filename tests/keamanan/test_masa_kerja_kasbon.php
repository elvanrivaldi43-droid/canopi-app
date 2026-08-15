<?php
// FILE: tests/keamanan/test_masa_kerja_kasbon.php
// Jalankan: php tests/keamanan/test_masa_kerja_kasbon.php
//
// TASK 0 — Konsistenkan sumber masa kerja Kasbon TANPA menyentuh data.
//
// Kenapa penting: syarat Kasbon adalah masa kerja >= 1 tahun. Sampai sekarang
// KasbonKaryawanController menghitungnya dari `tanggal_bergabung`, dan kalau kolom
// itu kosong LANGSUNG lompat ke `created_at` — padahal `created_at` adalah tanggal
// baris user dibuat di sistem, bukan tanggal orangnya mulai bekerja. Karyawan lama
// yang barisnya baru dibuat waktu sistem ini dipasang jadi terlihat "baru", dan
// pengajuan kasbonnya ditolak padahal berhak.
//
// Keputusan Bos (terkunci): urutan sumber = tanggal_bergabung -> tgl_masuk_kerja
// -> created_at. `tgl_masuk_kerja` WAJIB dicoba sebelum jatuh ke created_at.
//
// Tes ini MURNI (tanpa database) + pemeriksaan sumber kode; tidak ada data
// production yang dibaca maupun diubah.
require __DIR__ . '/../bootstrap.php';

use App\Services\MasaKerjaService;
use Carbon\Carbon;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Tanggal acuan tetap supaya hasil tes tidak berubah tiap hari dijalankan.
$sekarang = Carbon::parse('2026-08-15');

// ═══════════════════════════════════════════════════════════
// 1. `tanggal_bergabung` terisi -> SELALU jadi sumber utama
// ═══════════════════════════════════════════════════════════
$check('sumber: tanggal_bergabung terisi -> dipakai walau 2 kolom lain juga terisi',
    MasaKerjaService::sumberEfektif('2023-01-10', '2024-05-01', '2025-07-01'),
    ['kolom' => 'tanggal_bergabung', 'tanggal' => '2023-01-10']);

$check('masa kerja: tanggal_bergabung menang (2023-01-10 -> 2026-08-15 = 43 bulan)',
    MasaKerjaService::masaKerjaBulan('2023-01-10', '2024-05-01', '2025-07-01', $sekarang),
    43);

// Objek Carbon (yang keluar dari model karena cast 'date') harus diterima juga.
$check('sumber: menerima objek Carbon, bukan cuma string',
    MasaKerjaService::sumberEfektif(Carbon::parse('2023-01-10'), null, null),
    ['kolom' => 'tanggal_bergabung', 'tanggal' => '2023-01-10']);

// ═══════════════════════════════════════════════════════════
// 2. `tanggal_bergabung` null -> pakai `tgl_masuk_kerja`
//    (inilah bug lama: dulu langsung lompat ke created_at)
// ═══════════════════════════════════════════════════════════
$check('sumber: tanggal_bergabung null -> tgl_masuk_kerja',
    MasaKerjaService::sumberEfektif(null, '2024-05-01', '2025-07-01'),
    ['kolom' => 'tgl_masuk_kerja', 'tanggal' => '2024-05-01']);

$check('masa kerja: jatuh ke tgl_masuk_kerja (2024-05-01 -> 2026-08-15 = 27 bulan)',
    MasaKerjaService::masaKerjaBulan(null, '2024-05-01', '2025-07-01', $sekarang),
    27);

// String kosong dari form HTML setara dengan null — kalau tidak, karyawan dengan
// kolom '' akan dihitung dari epoch dan masa kerjanya jadi puluhan tahun.
$check('sumber: string kosong dianggap kosong, bukan tanggal',
    MasaKerjaService::sumberEfektif('', '2024-05-01', '2025-07-01'),
    ['kolom' => 'tgl_masuk_kerja', 'tanggal' => '2024-05-01']);

// MySQL lama bisa menyimpan '0000-00-00' — itu bukan tanggal sah.
$check('sumber: 0000-00-00 dianggap kosong',
    MasaKerjaService::sumberEfektif('0000-00-00', '2024-05-01', null),
    ['kolom' => 'tgl_masuk_kerja', 'tanggal' => '2024-05-01']);

// ═══════════════════════════════════════════════════════════
// 3. Keduanya null -> BARU pakai `created_at`
// ═══════════════════════════════════════════════════════════
$check('sumber: dua kolom pertama kosong -> created_at',
    MasaKerjaService::sumberEfektif(null, null, '2025-07-01 09:30:00'),
    ['kolom' => 'created_at', 'tanggal' => '2025-07-01']);

$check('masa kerja: jatuh ke created_at (2025-07-01 -> 2026-08-15 = 13 bulan)',
    MasaKerjaService::masaKerjaBulan(null, null, '2025-07-01 09:30:00', $sekarang),
    13);

$check('sumber: ketiganya kosong -> null (tidak menebak tanggal apa pun)',
    MasaKerjaService::sumberEfektif(null, null, null),
    null);

// Tanpa sumber apa pun, masa kerja 0 bulan -> pengajuan ditolak, BUKAN diloloskan.
$check('masa kerja: tanpa sumber apa pun = 0 bulan (gagal tertutup, bukan terbuka)',
    MasaKerjaService::masaKerjaBulan(null, null, null, $sekarang),
    0);

// Tanggal di masa depan (salah input) tidak boleh jadi masa kerja negatif.
$check('masa kerja: tanggal masa depan tidak jadi negatif',
    MasaKerjaService::masaKerjaBulan('2027-01-01', null, null, $sekarang),
    0);

// Tanggal ngawur tidak boleh melempar exception dan bikin /kasbon-saya 500.
$check('sumber: tanggal ngawur dilewati, bukan bikin error',
    MasaKerjaService::sumberEfektif('bukan-tanggal', '2024-05-01', null),
    ['kolom' => 'tgl_masuk_kerja', 'tanggal' => '2024-05-01']);

// Ambang syarat 12 bulan (tepat di batas) — ini angka yang menentukan lolos/tidak.
$check('masa kerja: tepat 12 bulan (2025-08-15 -> 2026-08-15)',
    MasaKerjaService::masaKerjaBulan('2025-08-15', null, null, $sekarang),
    12);
$check('masa kerja: kurang 1 hari dari 12 bulan = 11 bulan (belum lolos)',
    MasaKerjaService::masaKerjaBulan('2025-08-16', null, null, $sekarang),
    11);

// ═══════════════════════════════════════════════════════════
// 4. index() DAN store() memakai helper yang SAMA
//    (bukan dua salinan rumus yang bisa menyimpang satu sama lain)
// ═══════════════════════════════════════════════════════════
$ctrl = file_get_contents($base . '/app/Http/Controllers/KasbonKaryawanController.php');

$check('controller memanggil MasaKerjaService tepat 2x (index + store)',
    substr_count($ctrl, 'MasaKerjaService::masaKerjaBulan('),
    2);

// Salinan rumus lama harus BENAR-BENAR hilang, bukan cuma ditambahi helper di sebelahnya.
$check('tidak ada lagi salinan rumus diffInMonths di controller',
    str_contains($ctrl, 'diffInMonths'),
    false);
// Kolom-kolomnya memang masih disebut — sebagai ARGUMEN ke helper. Yang harus hilang
// adalah rumus inline lamanya: Carbon::parse($user->tanggal_bergabung) dengan
// fallback ternary langsung ke created_at.
$check('tidak ada lagi Carbon::parse inline atas tanggal_bergabung/created_at di controller',
    (bool) preg_match('/Carbon::parse\(\s*\$user->(tanggal_bergabung|created_at)\s*\)/', $ctrl),
    false);

// Ketiga kolom WAJIB ikut dioper ke helper — kalau `tgl_masuk_kerja` lupa dioper,
// helper-nya benar tapi controller tetap melewati kolom itu (bug lama kembali,
// dan tes murni di atas tidak akan menangkapnya).
foreach (['tanggal_bergabung', 'tgl_masuk_kerja', 'created_at'] as $kolom) {
    $check("controller mengoper kolom `$kolom` ke helper",
        substr_count($ctrl, '$user->' . $kolom), 2);
}

// ═══════════════════════════════════════════════════════════
// 5. SQL audit: READ-ONLY. Ini pagar keras — file ini akan
//    ditempel Bos ke phpMyAdmin production.
// ═══════════════════════════════════════════════════════════
$sqlPath = $base . '/docs/sql/2026-08-15-audit-tanggal-bergabung.sql';
$check('file SQL audit ada', file_exists($sqlPath), true);

$sql = file_exists($sqlPath) ? file_get_contents($sqlPath) : '';

// Buang komentar dulu supaya kata "UPDATE" di dalam penjelasan tidak salah tuduh,
// DAN supaya perintah berbahaya tidak bisa disembunyikan di balik komentar.
$sqlKode = preg_replace('/--[^\n]*/', '', $sql);
$sqlKode = preg_replace('#/\*.*?\*/#s', '', (string) $sqlKode);
$sqlKode = strtoupper((string) $sqlKode);

foreach (['UPDATE', 'ALTER', 'DELETE', 'INSERT', 'DROP', 'TRUNCATE', 'REPLACE', 'CREATE', 'GRANT'] as $terlarang) {
    $check("SQL audit TIDAK mengandung `$terlarang`",
        (bool) preg_match('/\b' . $terlarang . '\b/', $sqlKode),
        false);
}

$check('SQL audit mengandung SELECT', str_contains($sqlKode, 'SELECT'), true);

// Kolom yang wajib ditampilkan supaya Bos bisa memutuskan backfill dengan mata sendiri.
foreach (['NAME', 'STATUS', 'TANGGAL_BERGABUNG', 'TGL_MASUK_KERJA', 'CREATED_AT'] as $kolom) {
    $check("SQL audit menampilkan kolom `$kolom`", str_contains($sqlKode, $kolom), true);
}

// Sumber efektif harus ikut ditampilkan — daftar tanpa ini tidak menjawab
// "kalau urutan fallback diterapkan, tanggal mana yang sebenarnya dipakai?".
$check('SQL audit menampilkan sumber efektif (COALESCE/CASE)',
    (bool) preg_match('/\b(COALESCE|CASE)\b/', $sqlKode),
    true);

// ═══════════════════════════════════════════════════════════
// 6. Tidak ada data production yang diubah:
//    dilarang ada file backfill UPDATE di repo ini.
// ═══════════════════════════════════════════════════════════
$adaBackfill = false;
foreach (glob($base . '/docs/sql/*.sql') as $f) {
    $isi = strtoupper((string) preg_replace('/--[^\n]*/', '', (string) file_get_contents($f)));
    if (preg_match('/\bUPDATE\s+\w*USERS\b/', $isi)) {
        echo "  -> ditemukan UPDATE users di: " . basename($f) . "\n";
        $adaBackfill = true;
    }
}
$check('tidak ada SQL backfill `UPDATE users` di docs/sql (butuh izin terpisah Bos)',
    $adaBackfill, false);

echo $fail ? "\n❌ ADA YANG GAGAL\n" : "\n✅ SEMUA LULUS\n";
exit($fail ? 1 : 0);
