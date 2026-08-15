<?php
// FILE: tests/kerja-hari-libur/test_sql_deploy.php
// Jalankan: php tests/kerja-hari-libur/test_sql_deploy.php
//
// Deploy ke Niagahoster cuma FTP sync + clear cache — TIDAK menjalankan migration.
// Jadi tiap tabel/kolom baru WAJIB punya SQL manual idempotent yang dijalankan Bos di
// phpMyAdmin SEBELUM push. Tes ini menjaga file SQL itu tidak ketinggalan satu kolom pun:
// daftar kolomnya diambil langsung dari migration, bukan diketik ulang di sini.
require __DIR__ . '/../bootstrap.php';

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$sqlPath = __DIR__ . '/../../docs/sql/2026-08-15-kerja-hari-libur.sql';
$check('file SQL production ada (docs/sql/2026-08-15-kerja-hari-libur.sql)', file_exists($sqlPath), true);
if (!file_exists($sqlPath)) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }

$sql     = file_get_contents($sqlPath);
$sqlLower = strtolower($sql);

// ── Tabel baru ──────────────────────────────────────────────
$check('tabel kerja_hari_libur dibuat dengan CREATE TABLE IF NOT EXISTS',
    (bool) preg_match('/create table if not exists\s+`?kerja_hari_libur`?/', $sqlLower), true);
$check('unique (user_id, tanggal) ikut dibuat di tabel baru',
    (bool) preg_match('/unique[^\n]*user_id[^\n]*tanggal|unique[^\n]*tanggal[^\n]*user_id/', $sqlLower), true);
foreach (['user_id', 'tanggal', 'diaktifkan_oleh', 'gaji_harian_snapshot', 'uang_makan_snapshot'] as $kolom) {
    $check("kolom `$kolom` ada di CREATE TABLE kerja_hari_libur", str_contains($sqlLower, $kolom), true);
}

// ── Kolom baru di tabel lama — daftar diambil dari migration ─
// `kode_absen` SENGAJA dikecualikan: kolom + unique-nya sudah dipasang manual di
// production 5 Agustus. Migrasi 000004 cuma untuk instalasi baru, dan menjalankan
// ulang ALTER-nya di production justru berisiko (MySQL <8.0.29 tidak punya
// ADD INDEX IF NOT EXISTS). Dicek terpisah di bawah.
$wajib = [];
foreach (glob(__DIR__ . '/../../database/migrations/2026_08_15_*.php') as $f) {
    $isi = file_get_contents($f);
    if (!preg_match("/Schema::table\('([a-z_]+)'/", $isi, $m)) continue; // lewati CREATE TABLE
    $tabel = $m[1];
    if ($tabel === 'kode_absen') continue;
    if (preg_match_all("/\\\$table->(?:boolean|decimal|integer|unsignedBigInteger|string|date)\('([a-z_]+)'/", $isi, $mm)) {
        foreach ($mm[1] as $kolom) $wajib[] = [$tabel, $kolom];
    }
}
$check('migration kolom tambahan terbaca (bukan daftar kosong)', count($wajib) >= 4, true);

foreach ($wajib as [$tabel, $kolom]) {
    $pola = '/alter table\s+`?' . $tabel . '`?\s+add column if not exists\s+`?' . $kolom . '`?/';
    $check("SQL menambah `$tabel`.`$kolom` dengan ADD COLUMN IF NOT EXISTS",
        (bool) preg_match($pola, $sqlLower), true);
}

// ── Migrasi tidak boleh menghentikan `php artisan migrate` ──
// Skema production dibangun lewat SQL manual, jadi beberapa tabel lama (mis. slip_gaji)
// TIDAK punya migrasi create di repo ini. Kalau migrasi fitur langsung Schema::table()
// ke tabel yang belum ada, `migrate` di instalasi baru (atau di mesin dev/tes) berhenti
// dengan error di migrasi FITUR ini — bukan di penyebab aslinya — dan semua migrasi
// sesudahnya ikut tidak jalan.
// Komentar dibuang dulu: tes ini menilai URUTAN KODE, dan komentar yang kebetulan
// menyebut `getIndexes()`/`Schema::table()` bisa bikin posisinya salah baca.
$tanpaKomentar = function (string $src): string {
    $out = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t)) {
            // komentar diganti spasi sepanjang aslinya supaya offset lain tidak bergeser
            $out .= in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)
                ? str_repeat(' ', strlen($t[1]))
                : $t[1];
        } else {
            $out .= $t;
        }
    }
    return $out;
};

$tabelPunyaMigrasiCreate = function (string $tabel): bool {
    foreach (glob(__DIR__ . '/../../database/migrations/*.php') as $f) {
        if (preg_match("/Schema::create\('" . preg_quote($tabel, '/') . "'/", (string) file_get_contents($f))) return true;
    }
    return false;
};
$check('slip_gaji memang tidak punya migrasi create (itu sebab penjaga dibutuhkan)',
    $tabelPunyaMigrasiCreate('slip_gaji'), false);

foreach (glob(__DIR__ . '/../../database/migrations/2026_08_15_*.php') as $f) {
    $isi  = $tanpaKomentar((string) file_get_contents($f));
    $nama = basename($f);
    if (!preg_match_all("/Schema::table\('([a-z_]+)'/", $isi, $mm)) continue;

    foreach (array_unique($mm[1]) as $tabel) {
        $posGuard = strpos($isi, "Schema::hasTable('$tabel')");
        $posTable = strpos($isi, "Schema::table('$tabel'");
        $check("$nama menjaga tabel `$tabel` dengan Schema::hasTable sebelum diubah",
            $posGuard !== false && $posTable !== false && $posGuard < $posTable, true);
    }
}

// Migrasi kode_absen juga MEMBACA skema (hasColumn/getIndexes) — kalau tabelnya belum
// ada, pembacaan itu sendiri yang meledak, jadi penjaganya harus paling depan.
$m4  = $tanpaKomentar((string) file_get_contents(__DIR__ . '/../../database/migrations/2026_08_15_000004_add_user_unique_to_kode_absen_table.php'));
$pos = fn(string $jarum) => (($p = strpos($m4, $jarum)) === false ? PHP_INT_MAX : $p);
$check('migrasi kode_absen memeriksa keberadaan tabel sebelum membaca kolom/index',
    $pos("Schema::hasTable('kode_absen')") < min($pos("hasColumn('kode_absen'"), $pos('getIndexes(')), true);

// ── Idempotensi & keamanan ──────────────────────────────────
$check('tidak ada DROP TABLE/DATABASE di file deploy',
    (bool) preg_match('/\bdrop\s+(table|database)\b/', $sqlLower), false);
$check('tidak ada TRUNCATE/DELETE massal di file deploy',
    (bool) preg_match('/\b(truncate|delete\s+from)\b/', $sqlLower), false);
// ── kode_absen: verifikasi index WAJIB, bukan opsional ──────
// Keatomikan KodeAbsen::barisHariIniUntuk()/createOrFirst() BERGANTUNG pada unique
// (tanggal, user_id). Index itu dipasang manual 5 Agustus dan tidak pernah masuk
// migrasi, jadi tidak ada satu pun mekanisme otomatis yang memastikan dia masih ada
// di production. Kalau ternyata hilang/tidak pernah jadi, createOrFirst diam-diam
// berubah jadi "cek lalu insert" biasa: dua kode valid untuk satu karyawan di satu
// hari, dan cron pagi bisa mengirim ulang. Karena itu SHOW INDEX harus jadi langkah
// yang dijalankan, bukan saran yang bisa dilewati.
$barisAktif = [];
foreach (explode("\n", $sqlLower) as $baris) {
    $b = trim($baris);
    if ($b === '' || str_starts_with($b, '--')) continue;
    $barisAktif[] = $b;
}
$barisKodeAbsen = array_values(array_filter($barisAktif, fn($b) => str_contains($b, 'kode_absen')));

$check('SHOW INDEX kode_absen adalah langkah AKTIF (bukan cuma disebut di komentar)',
    (bool) preg_match('/show\s+index\s+from\s+`?kode_absen`?/', implode("\n", $barisKodeAbsen)), true);
$check('langkah SHOW INDEX ditandai WAJIB, bukan opsional',
    (bool) preg_match('/wajib[^\n]*(show\s+index|verifikasi index)|(show\s+index|verifikasi index)[^\n]*wajib/i', $sql), true);
$check('langkah verifikasi index dikaitkan eksplisit dengan "sebelum push"',
    (bool) preg_match('/(show\s+index[\s\S]{0,1500}?sebelum push)|(sebelum push[\s\S]{0,1500}?show\s+index)/i', $sql), true);
$check('ada instruksi berhenti (jangan push) kalau index-nya tidak ada',
    (bool) preg_match('/kosong[\s\S]{0,200}?(stop|jangan push)/i', $sql), true);
$check('langkah kode_absen tidak lagi dilabeli "opsional"/"HANYA kalau"',
    (bool) preg_match('/(opsional|hanya kalau)[^\n]*kode_absen|kode_absen[^\n]*(opsional|hanya kalau)/i', $sql), false);

// Yang aktif terhadap kode_absen boleh HANYA pembacaan. ALTER/CREATE/DROP tetap
// dilarang jalan buta-buta: MySQL < 8.0.29 tidak punya ADD INDEX IF NOT EXISTS,
// jadi menambah unique yang sudah ada akan error.
$mutasiKodeAbsen = array_values(array_filter($barisKodeAbsen,
    fn($b) => (bool) preg_match('/\b(alter|create|drop|insert|update|delete)\b/', $b)));
$check('tidak ada pernyataan aktif yang MENGUBAH kode_absen (index production dipertahankan)',
    $mutasiKodeAbsen, []);
$check('perintah perbaikan (ALTER ADD UNIQUE) tetap tersedia sebagai cadangan terkomentar',
    (bool) preg_match('/--[^\n]*alter table[^\n]*kode_absen[^\n]*unique/i', $sql), true);

$check('file menyebut WAJIB dijalankan sebelum push',
    str_contains($sqlLower, 'sebelum push'), true);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
