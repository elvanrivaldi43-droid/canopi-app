<?php
// FILE: tests/keamanan/test_akses_karyawan.php
// Jalankan: php tests/keamanan/test_akses_karyawan.php
//
// TASK 1 — Tutup privilege escalation modul Karyawan.
//
// Keputusan Bos (terkunci):
//   - Admin (level 2) boleh mengelola user level 3-7, DATA OPERASIONAL SAJA.
//   - Admin TIDAK boleh menyentuh target level 1-2 (termasuk dirinya sendiri),
//     data finansial, rekening, tunjangan, tanggal bergabung,
//     dan TIDAK boleh mengangkat level siapa pun ke 1-2.
//   - Hanya Owner boleh mengubah `tanggal_bergabung` (menentukan kelayakan Kasbon).
//
// Kenapa penting: modul karyawan dibuka level 1 DAN 2 (routes/web.php `level:1,2`).
// Sebelum perbaikan ini, form Edit menampilkan pilihan level 1-7 untuk semua yang
// bisa masuk — artinya Admin bisa mengangkat dirinya (atau orang lain) jadi Owner
// dengan satu dropdown, lalu membuka SELURUH modul keuangan. Menyembunyikan field
// di layar tidak cukup: POST manual tetap tembus kalau controllernya tidak menyaring.
//
// Tes MURNI (tanpa database) + pemeriksaan sumber controller/view.
require __DIR__ . '/../bootstrap.php';

use App\Services\KaryawanAksesService;

$base = dirname(__DIR__, 2);
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

const OWNER = 1;
const ADMIN = 2;

// ═══════════════════════════════════════════════════════════
// 1. Owner dapat mengelola level 1-7
// ═══════════════════════════════════════════════════════════
foreach ([1, 2, 3, 4, 5, 6, 7] as $target) {
    $check("Owner boleh mengelola target level $target",
        KaryawanAksesService::bolehKelola(OWNER, $target), true);
}
$check('Owner: daftar level target = 1..7',
    KaryawanAksesService::levelTargetDiizinkan(OWNER), [1, 2, 3, 4, 5, 6, 7]);

// ═══════════════════════════════════════════════════════════
// 2 & 3. Admin HANYA level 3-7; ditolak untuk 1-2 termasuk dirinya
// ═══════════════════════════════════════════════════════════
foreach ([3, 4, 5, 6, 7] as $target) {
    $check("Admin boleh mengelola target level $target",
        KaryawanAksesService::bolehKelola(ADMIN, $target), true);
}
$check('Admin DITOLAK mengelola Owner (level 1)',
    KaryawanAksesService::bolehKelola(ADMIN, 1), false);
$check('Admin DITOLAK mengelola sesama Admin (level 2) — termasuk dirinya sendiri',
    KaryawanAksesService::bolehKelola(ADMIN, 2), false);
$check('Admin: daftar level target = 3..7 (tanpa 1 & 2)',
    KaryawanAksesService::levelTargetDiizinkan(ADMIN), [3, 4, 5, 6, 7]);

// Level lain tidak punya akses modul ini sama sekali (route level:1,2), tapi
// policy tetap harus gagal TERTUTUP kalau nanti dipanggil dari tempat lain.
foreach ([3, 4, 5, 6, 7, null, 0, '', 'x'] as $aktor) {
    $check('level aktor ' . var_export($aktor, true) . ' tidak boleh mengelola siapa pun',
        KaryawanAksesService::bolehKelola($aktor, 5), false);
}
$check('level aktor bukan 1/2 -> daftar level target kosong',
    KaryawanAksesService::levelTargetDiizinkan(3), []);

// Level dari DB sering berupa string ("2") — tidak boleh diam-diam gagal/lolos.
$check('level string "2" diperlakukan sama dengan int 2 (boleh target 5)',
    KaryawanAksesService::bolehKelola('2', '5'), true);
$check('level string "2" tetap ditolak untuk target "1"',
    KaryawanAksesService::bolehKelola('2', '1'), false);

// ═══════════════════════════════════════════════════════════
// 4. Admin tidak dapat SUBMIT level 1-2
// ═══════════════════════════════════════════════════════════
$check('Admin tidak boleh set level target ke 1', KaryawanAksesService::bolehSetLevel(ADMIN, 1), false);
$check('Admin tidak boleh set level target ke 2', KaryawanAksesService::bolehSetLevel(ADMIN, 2), false);
$check('Admin boleh set level target ke 3', KaryawanAksesService::bolehSetLevel(ADMIN, 3), true);
$check('Owner boleh set level target ke 1', KaryawanAksesService::bolehSetLevel(OWNER, 1), true);

// Aturan validasi server-side harus MEMBATASI pilihan, bukan cuma between:1,7.
$check('aturan validasi level untuk Admin = in:3,4,5,6,7',
    KaryawanAksesService::aturanLevel(ADMIN), 'required|integer|in:3,4,5,6,7');
$check('aturan validasi level untuk Owner = between:1,7',
    KaryawanAksesService::aturanLevel(OWNER), 'required|integer|between:1,7');

// ═══════════════════════════════════════════════════════════
// 6. Payload Admin tidak dapat mengubah field finansial/rekening/tunjangan
//    walau dikirim lewat POST manual (bukan sekadar disembunyikan di layar).
// ═══════════════════════════════════════════════════════════
$fieldTerlarang = [
    'tipe_gaji', 'gaji_harian', 'gaji_bulanan', 'uang_makan', 'uang_bonus',
    'nama_bank', 'no_rekening', 'atas_nama', 'tunjangan',
    // Keputusan Bos: hanya Owner boleh mengubah tanggal bergabung —
    // kolom ini menentukan kelayakan Kasbon (lihat MasaKerjaService).
    'tanggal_bergabung',
];

$check('daftar field Owner-saja persis seperti keputusan Bos',
    KaryawanAksesService::FIELD_OWNER_SAJA, $fieldTerlarang);

$payloadJahat = [
    'jabatan'           => 'Teknisi',
    'level'             => 5,
    'tipe_gaji'         => 'bulanan',
    'gaji_harian'       => 999000,
    'gaji_bulanan'      => 50000000,
    'uang_makan'        => 999000,
    'uang_bonus'        => 999000,
    'nama_bank'         => 'BCA',
    'no_rekening'       => '1234567890',
    'atas_nama'         => 'Orang Lain',
    'tunjangan'         => [1 => 500000],
    'tanggal_bergabung' => '2019-01-01',
];

$hasilAdmin = KaryawanAksesService::saringPayload(ADMIN, $payloadJahat);
foreach ($fieldTerlarang as $f) {
    $check("payload Admin: field `$f` DIBUANG", array_key_exists($f, $hasilAdmin), false);
}
$check('payload Admin: field operasional `jabatan` tetap lolos',
    $hasilAdmin['jabatan'] ?? null, 'Teknisi');
$check('payload Admin: field `level` tetap lolos (nilainya divalidasi terpisah)',
    $hasilAdmin['level'] ?? null, 5);

$hasilOwner = KaryawanAksesService::saringPayload(OWNER, $payloadJahat);
foreach ($fieldTerlarang as $f) {
    $check("payload Owner: field `$f` TETAP ADA", array_key_exists($f, $hasilOwner), true);
}

$check('Owner boleh field finansial', KaryawanAksesService::bolehFinansial(OWNER), true);
$check('Admin TIDAK boleh field finansial', KaryawanAksesService::bolehFinansial(ADMIN), false);
$check('level tak dikenal TIDAK boleh field finansial', KaryawanAksesService::bolehFinansial(null), false);

// ═══════════════════════════════════════════════════════════
// 7. Create oleh Admin memaksa default finansial AMAN
// ═══════════════════════════════════════════════════════════
$aman = KaryawanAksesService::defaultFinansialAman();
$check('default aman: tipe gaji harian', $aman['tipe_gaji'] ?? null, 'harian');
foreach (['gaji_harian', 'gaji_bulanan', 'uang_makan', 'uang_bonus'] as $f) {
    $check("default aman: `$f` = 0", $aman[$f] ?? null, 0);
}
$check('default aman TIDAK mengandung rekening/tunjangan',
    array_intersect(['nama_bank', 'no_rekening', 'atas_nama', 'tunjangan'], array_keys($aman)), []);

// Admin membuat karyawan -> nominal apa pun yang dia kirim diganti default aman.
$hasilCreate = KaryawanAksesService::payloadCreate(ADMIN, $payloadJahat);
$check('create Admin: gaji_harian dipaksa 0', $hasilCreate['gaji_harian'] ?? null, 0);
$check('create Admin: gaji_bulanan dipaksa 0', $hasilCreate['gaji_bulanan'] ?? null, 0);
$check('create Admin: tipe_gaji dipaksa harian', $hasilCreate['tipe_gaji'] ?? null, 'harian');
$check('create Admin: tunjangan tidak ikut', array_key_exists('tunjangan', $hasilCreate), false);
$check('create Owner: nominal yang dikirim dipertahankan',
    KaryawanAksesService::payloadCreate(OWNER, $payloadJahat)['gaji_harian'] ?? null, 999000);

// Admin tidak boleh memasang tunjangan sama sekali.
$check('Admin tidak boleh mengelola tunjangan', KaryawanAksesService::bolehTunjangan(ADMIN), false);
$check('Owner boleh mengelola tunjangan', KaryawanAksesService::bolehTunjangan(OWNER), true);

// ═══════════════════════════════════════════════════════════
// 7b. ADMIN HARUS BISA MENYIMPAN FORMNYA
//
// Blocker yang ketemu waktu audit: field finansial (termasuk `tipe_gaji`) kini
// TIDAK dirender untuk Admin, tapi validasi store()/update() masih menuntutnya
// `required`. Akibatnya Admin tidak bisa menyimpan form sama sekali — pesan
// errornya pun menunjuk field yang tidak pernah dia lihat. Itu memutus justru
// kemampuan yang Task ini bangun (Admin mengelola level 3-7).
//
// Aturan validasi finansial harus IKUT hak aktor: hanya ditegakkan untuk Owner.
// Untuk Admin field-nya memang tidak ada, dan nilainya toh sudah dibuang
// saringPayload()/payloadCreate() — jadi tidak perlu (dan tidak boleh) divalidasi.
// ═══════════════════════════════════════════════════════════
$aturanOwner = KaryawanAksesService::aturanFinansial(OWNER);
$aturanAdmin = KaryawanAksesService::aturanFinansial(ADMIN);

$check('Owner: tipe_gaji tetap wajib diisi',
    $aturanOwner['tipe_gaji'] ?? null, 'required|in:harian,bulanan,project');
$check('Owner: nominal gaji tetap divalidasi',
    $aturanOwner['gaji_harian'] ?? null, 'nullable|numeric|min:0');
$check('Owner: seluruh field finansial punya aturan',
    count($aturanOwner), 5);

$check('Admin: TIDAK ada aturan finansial sama sekali (field-nya memang tidak dikirim)',
    $aturanAdmin, []);
$check('level tak dikenal juga tanpa aturan finansial',
    KaryawanAksesService::aturanFinansial(null), []);

// Controller memakai aturan itu, bukan daftar hardcode yang selalu `required`.
// ($ctrl baru dimuat di bagian bawah berkas ini, jadi dibaca lokal di sini.)
$ctrlAwal = file_get_contents($base . '/app/Http/Controllers/KaryawanController.php');
$check('store()/update() memakai aturanFinansial() dari service',
    substr_count($ctrlAwal, 'aturanFinansial('), 2);
$check('tidak ada lagi `tipe_gaji` required hardcode di controller',
    (bool) preg_match("/'tipe_gaji'\s*=>\s*'required\|in:harian,bulanan,project'/", $ctrlAwal), false);

// ═══════════════════════════════════════════════════════════
// 5. Index Admin HANYA query level 3-7
// ═══════════════════════════════════════════════════════════
$check('filter index Owner: tanpa batas level (null = semua)',
    KaryawanAksesService::levelUntukIndex(OWNER), null);
$check('filter index Admin: hanya level 3..7',
    KaryawanAksesService::levelUntukIndex(ADMIN), [3, 4, 5, 6, 7]);

// ═══════════════════════════════════════════════════════════
// GUARD DI CONTROLLER — bukan cuma di layar
// ═══════════════════════════════════════════════════════════
$ctrl = file_get_contents($base . '/app/Http/Controllers/KaryawanController.php');

// Setiap method yang memuat/mengubah 1 karyawan WAJIB lewat guard yang sama.
$metode = ['show', 'edit', 'update', 'resetPassword', 'nonaktifkan', 'aktifkan', 'kirimUlang'];
$check('guard dipanggil di semua method per-karyawan (' . count($metode) . 'x)',
    substr_count($ctrl, 'pastikanBolehKelola('), count($metode) + 1); // +1 = definisi methodnya

foreach ($metode as $m) {
    // Ambil badan method lalu pastikan guard dipanggil di dalamnya.
    $pos = strpos($ctrl, "function $m(");
    $potongan = $pos === false ? '' : substr($ctrl, $pos, 700);
    $check("method `$m()` memanggil guard pastikanBolehKelola",
        str_contains($potongan, 'pastikanBolehKelola('), true);
}

$check('index() memakai filter level dari service (bukan query polos)',
    str_contains($ctrl, 'levelUntukIndex('), true);
$check('store() memakai payloadCreate() dari service',
    str_contains($ctrl, 'payloadCreate('), true);
$check('update() memakai saringPayload() dari service',
    str_contains($ctrl, 'saringPayload('), true);
// Dua endpoint, dua aturan: update() memakai aturanLevel(), store() memakai
// aturanLevelCreate() (tanpa level 1) — lihat bagian 9.
$check('validasi level UPDATE memakai aturanLevel() dari service',
    substr_count($ctrl, 'aturanLevel('), 1);
$check('validasi level CREATE memakai aturanLevelCreate() dari service',
    substr_count($ctrl, 'aturanLevelCreate('), 1);
$check('tunjangan hanya disentuh kalau bolehTunjangan()',
    substr_count($ctrl, 'bolehTunjangan('), 2);

// Rumus lama yang membolehkan level apa pun harus benar-benar hilang.
$check('tidak ada lagi validasi level polos `between:1,7` hardcode di controller',
    str_contains($ctrl, "'required|integer|between:1,7'"), false);

// ═══════════════════════════════════════════════════════════
// 8. VIEW — field Owner-saja tidak dirender untuk Admin
//
// Blok Owner-saja ditandai sentinel {{-- OWNER-ONLY:MULAI --}} ... :SELESAI,
// dan tiap field terlarang WAJIB berada di dalam salah satu blok itu.
// ═══════════════════════════════════════════════════════════
$fieldView = ['tipe_gaji', 'gaji_harian', 'gaji_bulanan', 'uang_makan', 'uang_bonus',
              'nama_bank', 'no_rekening', 'atas_nama', 'tunjangan', 'tanggal_bergabung'];

foreach (['create', 'edit'] as $view) {
    $path = $base . "/resources/views/karyawan/$view.blade.php";
    $isi  = file_get_contents($path);

    // Kumpulkan rentang [mulai, selesai] tiap blok Owner-saja.
    preg_match_all('/\{\{--\s*OWNER-ONLY:MULAI\s*--\}\}/', $isi, $m1, PREG_OFFSET_CAPTURE);
    preg_match_all('/\{\{--\s*OWNER-ONLY:SELESAI\s*--\}\}/', $isi, $m2, PREG_OFFSET_CAPTURE);

    $check("$view.blade: sentinel MULAI & SELESAI berpasangan",
        count($m1[0]) === count($m2[0]) && count($m1[0]) > 0, true);

    $blok = [];
    foreach ($m1[0] as $i => $awal) {
        if (isset($m2[0][$i])) $blok[] = [$awal[1], $m2[0][$i][1]];
    }

    // Tiap blok WAJIB dibuka pagar level Owner lewat service (policy terpusat),
    // bukan `level == 1` yang ditulis ulang di tiap view.
    foreach ($blok as $i => [$awal, $akhir]) {
        $sebelum = substr($isi, max(0, $awal - 220), min(220, $awal));
        $check("$view.blade: blok Owner-saja #$i dipagari KaryawanAksesService",
            str_contains($sebelum, 'KaryawanAksesService::bolehFinansial'), true);
    }

    foreach ($fieldView as $f) {
        // Cari atribut name="field" / name="field[...]" di luar blok Owner-saja.
        preg_match_all('/name="' . preg_quote($f, '/') . '(?:\[|")/', $isi, $mm, PREG_OFFSET_CAPTURE);
        $diLuar = 0;
        foreach ($mm[0] as $hit) {
            $inside = false;
            foreach ($blok as [$awal, $akhir]) {
                if ($hit[1] > $awal && $hit[1] < $akhir) { $inside = true; break; }
            }
            if (!$inside) $diLuar++;
        }
        $check("$view.blade: field `$f` tidak dirender di luar blok Owner-saja", $diLuar, 0);
    }

    // Dropdown level tidak boleh lagi hardcode daftar levelnya.
    // Form TAMBAH memakai daftar khusus create (tanpa level 1), form EDIT memakai
    // daftar kelola biasa — lihat bagian 9 di bawah.
    $sumber = $view === 'create' ? 'levelTargetCreate' : 'levelTargetDiizinkan';
    $check("$view.blade: pilihan level diambil dari $sumber()",
        str_contains($isi, $sumber), true);
    $check("$view.blade: tidak ada lagi daftar level hardcode 1=>'Owner'",
        (bool) preg_match("/\[\s*1\s*=>\s*'Owner'/", $isi), false);
}

// ═══════════════════════════════════════════════════════════
// 9. TAMBAH karyawan tidak boleh melahirkan Owner kedua
//
// Owner boleh MENGUBAH level siapa pun 1-7 (mis. mengalihkan kepemilikan sistem
// atau memperbaiki salah isi) — itu tindakan sadar atas baris yang sudah ada.
// Tapi form TAMBAH karyawan tidak punya alasan menawarkan level 1: sistem ini
// milik satu orang (Elvan), dan "Owner kedua" yang lahir dari salah pilih dropdown
// langsung memegang seluruh modal/margin/keuangan tanpa jejak persetujuan siapa pun.
// Undangannya pun dikirim ke email yang baru diketik — salah ketik = Owner nyasar.
//
// Makanya aturan CREATE dipisah dari aturan UPDATE, bukan dipakai bersama.
// ═══════════════════════════════════════════════════════════
$check('policy punya daftar level khusus pembuatan: levelTargetCreate()',
    method_exists(KaryawanAksesService::class, 'levelTargetCreate'), true);
$check('policy punya aturan validasi khusus pembuatan: aturanLevelCreate()',
    method_exists(KaryawanAksesService::class, 'aturanLevelCreate'), true);

if (method_exists(KaryawanAksesService::class, 'levelTargetCreate')) {
    $check('Owner TAMBAH: pilihan level 2..7 (tanpa 1)',
        KaryawanAksesService::levelTargetCreate(OWNER), [2, 3, 4, 5, 6, 7]);
    $check('Admin TAMBAH: pilihan level tetap 3..7',
        KaryawanAksesService::levelTargetCreate(ADMIN), [3, 4, 5, 6, 7]);
    $check('level aktor lain -> daftar TAMBAH kosong (gagal tertutup)',
        KaryawanAksesService::levelTargetCreate(5), []);
    // EDIT tidak ikut dipersempit: Owner tetap bisa 1-7.
    $check('Owner EDIT tetap boleh seluruh level 1..7 (tidak ikut dipersempit)',
        KaryawanAksesService::levelTargetDiizinkan(OWNER), [1, 2, 3, 4, 5, 6, 7]);
}

if (method_exists(KaryawanAksesService::class, 'aturanLevelCreate')) {
    $aturanCreate = KaryawanAksesService::aturanLevelCreate(OWNER);
    $check('aturan TAMBAH Owner memakai in: (bukan between:1,7)',
        str_contains($aturanCreate, 'in:2,3,4,5,6,7'), true);
    $check('aturan TAMBAH Owner TIDAK memakai between:1,7',
        str_contains($aturanCreate, 'between:1,7'), false);
    $check('aturan TAMBAH Admin tetap 3..7',
        KaryawanAksesService::aturanLevelCreate(ADMIN), 'required|integer|in:3,4,5,6,7');
    $check('aturan TAMBAH level lain -> in: kosong (selalu gagal)',
        KaryawanAksesService::aturanLevelCreate(4), 'required|integer|in:');
    // Aturan UPDATE tidak berubah.
    $check('aturan EDIT Owner tetap between:1,7',
        KaryawanAksesService::aturanLevel(OWNER), 'required|integer|between:1,7');
}

if (method_exists(KaryawanAksesService::class, 'bolehSetLevelCreate')) {
    $check('Owner TIDAK boleh menetapkan level 1 lewat TAMBAH',
        KaryawanAksesService::bolehSetLevelCreate(OWNER, 1), false);
    $check('Owner boleh menetapkan level 2 lewat TAMBAH',
        KaryawanAksesService::bolehSetLevelCreate(OWNER, 2), true);
    $check('Admin TIDAK boleh menetapkan level 2 lewat TAMBAH',
        KaryawanAksesService::bolehSetLevelCreate(ADMIN, 2), false);
} else {
    $check('policy punya bolehSetLevelCreate()', false, true);
}

// Controller: store() WAJIB memakai aturan create, update() tetap aturan biasa.
$posStore  = strpos($ctrl, 'function store(');
$posShow   = strpos($ctrl, 'function show(');
$bodyStore = $posStore === false ? '' : substr($ctrl, $posStore, ($posShow ?: strlen($ctrl)) - $posStore);

$posUpdate  = strpos($ctrl, 'function update(');
$posSetelah = $posUpdate === false ? false : strpos($ctrl, 'public function', $posUpdate + 20);
$bodyUpdate = $posUpdate === false ? '' : substr($ctrl, $posUpdate, ($posSetelah ?: strlen($ctrl)) - $posUpdate);

$check('store() memakai aturanLevelCreate()',
    str_contains($bodyStore, 'aturanLevelCreate('), true);
$check('store() TIDAK lagi memakai aturanLevel() biasa',
    (bool) preg_match('/aturanLevel\(\s*\$aktor/', $bodyStore), false);
$check('update() tetap memakai aturanLevel() biasa (Owner masih bisa 1-7)',
    (bool) preg_match('/aturanLevel\(\s*\$aktor/', $bodyUpdate), true);
$check('update() TIDAK memakai aturan create',
    str_contains($bodyUpdate, 'aturanLevelCreate('), false);

// View tambah: level 1 benar-benar tidak pernah dirender sebagai opsi.
$isiCreate = file_get_contents($base . '/resources/views/karyawan/create.blade.php');
$check('create.blade: TIDAK memakai levelTargetDiizinkan() (itu daftar EDIT)',
    str_contains($isiCreate, 'levelTargetDiizinkan'), false);
$isiEdit = file_get_contents($base . '/resources/views/karyawan/edit.blade.php');
$check('edit.blade: tetap memakai levelTargetDiizinkan()',
    str_contains($isiEdit, 'levelTargetDiizinkan'), true);

echo $fail ? "\n❌ ADA YANG GAGAL\n" : "\n✅ SEMUA LULUS\n";
exit($fail ? 1 : 0);
