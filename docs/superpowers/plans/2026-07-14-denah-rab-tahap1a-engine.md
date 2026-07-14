# Denah Rangka di RAB — Tahap 1A (Mesin/Backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Buat jalur perhitungan blok RAB berbasis **daftar batang (members)** dari denah — memakai ulang `RangkaDesignService` + `CuttingService` — dengan **stok potong per-material**, sampai angka batang proyek **PA-DUTA kereproduksi** oleh tes.

**Architecture:** Tambah jalur baru `tipe: 'denah'` di `CuttingController::hitungSatuBlok` (di samping `'kanopi'`/`'manual'` yang TIDAK diubah). Jalur ini menyuapi `members[]` + `luas_m2` ke `RangkaDesignService::hitung`, yang membungkus `CuttingService::potong` (cutting-stock >600 + sambungan, sudah divalidasi). `potong` dan `hitung` diparameter agar stok potong bisa per-material (hollow 600, WF s/d 1200). Semua pipa biaya per-blok (upah, consumable, finishing, atap, addon, margin-di-opsi) dipakai ulang; hanya sumber rangka & luas yang berubah.

**Tech Stack:** Laravel 13 / PHP 8.3. Tes = skrip PHP standalone (pola `tests/rangka/test_*.php`, dijalankan `php tests/rangka/xxx.php`, tanpa Composer/DB). DB via `DB::table`. SQL idempotent (phpMyAdmin).

## Global Constraints

- **JANGAN ubah perilaku jalur `'kanopi'` & `'manual'`** di `hitungSatuBlok` — hanya menambah cabang `'denah'`. Backward-compat wajib. (verifikasi: tes lama `php tests/rangka/test_hitung.php` tetap lulus.)
- **Tes standalone**: tiap file diawali `require __DIR__ . '/../../app/Services/<Service>.php';`, pakai helper `$check(nama, got, exp)` bergaya `tests/rangka/test_hitung.php`, cetak `PASS/FAIL`. Tanpa framework, tanpa Composer, tanpa DB.
- **SQL idempotent**: pakai `ADD COLUMN IF NOT EXISTS` (MariaDB Niagahoster; error #1060 aman dilewati).
- **Kontrak `per_material`** yang dipakai hilir: tiap baris punya `material`, `jumlah_batang`, `sambungan`, `harga_pokok`, `subtotal_besi`. Jangan diubah namanya.
- **Stok default 600 cm** bila data panjang batang kosong/absen (jangan bikin 0).
- Angka resmi PA-DUTA (stok 600, dari Cutting Optimization Pro): **5x10 = 10 · 4x8 = 9 · 3x3 = 4 · 4x6 = 4**.

---

### Task 1: Parameter stok potong di `CuttingService::potong`

**Files:**
- Modify: `app/Services/CuttingService.php:19` (signature `potong` + badan yang memakai `self::STOCK`)
- Test: `tests/rangka/test_stok.php` (create)

**Interfaces:**
- Produces: `CuttingService::potong(array $pieces, ?float $stock = null): array` — `$stock` null → pakai `self::STOCK` (600). Semua pemakai lama (argumen tunggal) tetap jalan.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_stok.php`:

```php
<?php
require __DIR__ . '/../../app/Services/CuttingService.php';

use App\Services\CuttingService;

$svc = new CuttingService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// 1 potong 1000cm: stok 600 -> 2 batang (600 + 400, 1 sambungan)
$b600 = $svc->potong([['label' => 'X', 'len' => 1000]]);
$check('1000cm @stok default(600) = 2 batang', count($b600), 2);

// stok 1200 (WF) -> muat utuh, 1 batang, 0 sambungan
$b1200 = $svc->potong([['label' => 'X', 'len' => 1000]], 1200);
$check('1000cm @stok 1200 = 1 batang', count($b1200), 1);
$sambung = 0;
foreach ($b1200 as $bar) foreach ($bar['seg'] as $sg) if (($sg['jenis'] ?? '') === 'sambung') $sambung++;
$check('1000cm @stok 1200 = 0 sambungan', $sambung, 0);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `php tests/rangka/test_stok.php`
Expected: baris `@stok 1200` **FAIL** (karena argumen kedua belum ada → `potong` masih patok 600 → 1000cm jadi 2 batang, bukan 1).

- [ ] **Step 3: Parameter `potong`**

Di `app/Services/CuttingService.php`, ubah signature dan tambahkan default di awal method:

```php
public function potong(array $pieces, ?float $stock = null): array
{
    $stock = $stock ?? self::STOCK;
    $jid = 0;
```

Lalu ganti **setiap** `self::STOCK` **di dalam badan `potong()`** menjadi `$stock`. Baris yang terpengaruh (nilai persisnya):

- `if ($len <= self::STOCK) {` → `if ($len <= $stock) {`
- `while ($rem > self::STOCK + 1e-9) {` → `while ($rem > $stock + 1e-9) {`
- `$segs[] = ['label' => $p['label'], 'len' => (float) self::STOCK, ...]` → `(float) $stock`
- `$rem -= self::STOCK;` → `$rem -= $stock;`
- `$bars[] = ['sisa' => self::STOCK - $len, 'seg' => [$mkSeg($len)]];` → `'sisa' => $stock - $len,`

(Konstanta `const STOCK = 600;` TETAP ada sebagai default. `hitungRangka` yang memanggil `potong($pieces)` tanpa argumen kedua tetap memakai 600 — tidak berubah.)

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `php tests/rangka/test_stok.php`
Expected: `SEMUA LULUS`

- [ ] **Step 5: Pastikan tes lama tidak rusak**

Run: `php tests/rangka/test_hitung.php`
Expected: semua `PASS` (potong default 600 tak berubah).

- [ ] **Step 6: Commit**

```bash
git add app/Services/CuttingService.php tests/rangka/test_stok.php
git commit -m "feat(cutting): parameter stok potong per-panggilan (default 600)"
```

---

### Task 2: Stok per-material di `RangkaDesignService::hitung`

**Files:**
- Modify: `app/Services/RangkaDesignService.php:22` (signature `hitung`) dan `:39` (panggilan `potong`)
- Test: `tests/rangka/test_stok_material.php` (create)

**Interfaces:**
- Consumes: `CuttingService::potong($pieces, $stock)` (Task 1).
- Produces: `RangkaDesignService::hitung(array $members, array $harga = [], bool $lihatHarga = false, array $stok = []): array` — `$stok` = map `['<material>' => panjang_cm]`; material tak terdaftar → default 600.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_stok_material.php`:

```php
<?php
require __DIR__ . '/../../app/Services/CuttingService.php';
require __DIR__ . '/../../app/Services/RangkaDesignService.php';

use App\Services\RangkaDesignService;

$svc = new RangkaDesignService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$members = [['nama' => 'palang', 'panjang' => 1000, 'material' => 'WF 100']];

// tanpa stok map -> default 600 -> 1000cm = 2 batang
$r1 = $svc->hitung($members);
$check('WF 1000cm default 600 = 2 batang', $r1['per_material'][0]['jumlah_batang'], 2);

// stok WF 1200 -> 1 batang
$r2 = $svc->hitung($members, [], false, ['WF 100' => 1200]);
$check('WF 1000cm @stok 1200 = 1 batang', $r2['per_material'][0]['jumlah_batang'], 1);
$check('WF 1000cm @stok 1200 = 0 sambungan', $r2['per_material'][0]['sambungan'], 0);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `php tests/rangka/test_stok_material.php`
Expected: FAIL — `hitung` belum terima argumen ke-4, WF 1000 selalu 2 batang.

- [ ] **Step 3: Tambah parameter `$stok` + teruskan ke `potong`**

Di `app/Services/RangkaDesignService.php`, ubah signature `hitung` (baris 22):

```php
public function hitung(array $members, array $harga = [], bool $lihatHarga = false, array $stok = []): array
```

Lalu di dalam loop `foreach ($byMat as $mat => $pieces)`, ubah pemanggilan `potong` (baris 39):

```php
$bars = $this->cutting->potong($pieces, $stok[$mat] ?? null);
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `php tests/rangka/test_stok_material.php`
Expected: `SEMUA LULUS`

- [ ] **Step 5: Regresi**

Run: `php tests/rangka/test_hitung.php && php tests/rangka/test_stok.php`
Expected: semua `PASS` / `SEMUA LULUS`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/RangkaDesignService.php tests/rangka/test_stok_material.php
git commit -m "feat(rangka): stok potong per-material di hitung() (WF s/d 1200)"
```

---

### Task 3: Kolom stok di `master_material` + helper `stokMap()`

**Files:**
- SQL: dijalankan di phpMyAdmin (produksi) — tidak ada migrasi lokal.
- Modify: `app/Http/Controllers/CuttingController.php` (tambah private `stokMap()`)

**Interfaces:**
- Produces: `CuttingController::stokMap(): array` → map `['<nama_material>' => (float) panjang_cm]` dari `master_material` (aktif). Dipakai Task 4.

- [ ] **Step 1: SQL idempotent (jalankan di phpMyAdmin)**

```sql
ALTER TABLE master_material
  ADD COLUMN IF NOT EXISTS panjang_batang_cm INT NOT NULL DEFAULT 600;
```
Catatan: kolom baru default 600 untuk SEMUA besi (hollow). Nanti besi WF diubah manual ke 1200 lewat UI Master Material / phpMyAdmin. Kalau muncul error #1060 (kolom sudah ada), aman diabaikan.

- [ ] **Step 2: Tambah helper `stokMap()`**

Di `app/Http/Controllers/CuttingController.php`, tambahkan method private (mis. setelah `bolehAkses()`):

```php
private function stokMap(): array
{
    try {
        return DB::table('master_material')
            ->where('aktif', 1)
            ->get(['nama', 'panjang_batang_cm'])
            ->mapWithKeys(fn ($m) => [$m->nama => (float) ($m->panjang_batang_cm ?: 600)])
            ->toArray();
    } catch (\Throwable $e) {
        return []; // kolom belum ada / DB error -> semua default 600 di hilir
    }
}
```

- [ ] **Step 3: Verifikasi (integrasi, saat deploy)**

Karena baca DB, verifikasi bukan di VPS ini. Setelah deploy, buka Tinker/route diagnostik dan panggil sekali, atau cek via query: pastikan `stokMap()` mengembalikan array `nama => panjang` dan besi WF (setelah diisi 1200) muncul `... => 1200.0`. Selama kolom kosong → `try` tetap aman (kembali `[]`), tidak nge-crash.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/CuttingController.php
git commit -m "feat(rab): helper stokMap() baca panjang_batang_cm master_material"
```

---

### Task 4: Cabang `tipe: 'denah'` di `CuttingController::hitungSatuBlok`

**Files:**
- Modify: `app/Http/Controllers/CuttingController.php:339` (`hitungSatuBlok`) — 3 tempat: penetapan `$tipe`, cabang rangka, sumber `$luas`.
- Test: `tests/rangka/test_denah_blok.php` (create — menguji unit murni yang dipakai cabang ini)

**Interfaces:**
- Consumes: `RangkaDesignService::hitung($members, $harga, $lihatHarga, $stok)` (Task 2), `stokMap()` (Task 3).
- Blok payload `'denah'`: `{ tipe:'denah', nama, luas_m2:float, members:[{nama,jenis,panjang,material}], harga:{<material>:harga_pokok}, besi_extra?:[], jenis_kerja_id?, kondisi_ids?, atap_*?, addon_* }`.
- Produces: bentuk return `hitungSatuBlok` TIDAK berubah (tetap `per_material`, `pokok_blok`, dst.), sehingga `hitungProject` (penjumlah + margin opsi) tak perlu diubah.

- [ ] **Step 1: Tulis tes yang gagal (unit murni jalur members)**

Cabang controller ini bergantung DB (upah/consumable), jadi yang dites standalone = **logika inti members→per_material→besi** yang identik dengan yang dipanggil controller. Create `tests/rangka/test_denah_blok.php`:

```php
<?php
require __DIR__ . '/../../app/Services/CuttingService.php';
require __DIR__ . '/../../app/Services/RangkaDesignService.php';

use App\Services\RangkaDesignService;

$svc = new RangkaDesignService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Blok denah: 1 frame 5x10 keliling (4 sisi) + 2 support 4x8, dengan harga -> besi benar
$members = [
    ['nama' => 'sisi depan', 'jenis' => 'frame', 'panjang' => 400, 'material' => '5x10'],
    ['nama' => 'sisi kanan', 'jenis' => 'frame', 'panjang' => 300, 'material' => '5x10'],
    ['nama' => 'sisi blk',   'jenis' => 'frame', 'panjang' => 400, 'material' => '5x10'],
    ['nama' => 'sisi kiri',  'jenis' => 'frame', 'panjang' => 300, 'material' => '5x10'],
    ['nama' => 'S1', 'jenis' => 'support', 'panjang' => 400, 'material' => '4x8'],
    ['nama' => 'S2', 'jenis' => 'support', 'panjang' => 400, 'material' => '4x8'],
];
$harga = ['5x10' => 200000, '4x8' => 130000];
$r = $svc->hitung($members, $harga, true, []);

// 5x10: total 1400cm -> 3 batang (600+600+200). 4x8: 800cm -> 2 batang.
$byMat = [];
foreach ($r['per_material'] as $m) $byMat[$m['material']] = $m;
$check('5x10 = 3 batang', $byMat['5x10']['jumlah_batang'], 3);
$check('4x8 = 2 batang',  $byMat['4x8']['jumlah_batang'], 2);
$check('total biaya besi = 3*200000 + 2*130000', (int) $r['total_biaya_besi'], 860000);

echo $fail ? "\nADA FAIL\n" : "\nSEMUA LULUS\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes**

Run: `php tests/rangka/test_denah_blok.php`
Expected: **LULUS** (Task 2 sudah bikin `hitung` benar). Tes ini mengunci kontrak yang dipakai controller sebelum kita menyolokkannya.

- [ ] **Step 3: Ubah penetapan `$tipe` (izinkan 'denah')**

Di `hitungSatuBlok`, ganti baris:

```php
$tipe = ($b['tipe'] ?? 'kanopi') === 'manual' ? 'manual' : 'kanopi';
```
menjadi:
```php
$tipe = $b['tipe'] ?? 'kanopi';
if (!in_array($tipe, ['kanopi', 'manual', 'denah'], true)) $tipe = 'kanopi';
```

- [ ] **Step 4: Tambah cabang `'denah'` (blok rangka)**

Struktur sekarang `if ($tipe === 'kanopi') { ... } else { /* manual */ ... }`. Ubah jadi `if kanopi { ... } elseif denah { ... } else { manual }`. Sisipkan cabang **denah** ini sebelum `else`:

```php
} elseif ($tipe === 'denah') {
    $members = array_map(fn ($m) => (array) $m, (array) ($b['members'] ?? []));
    $harga   = (array) ($b['harga'] ?? []);
    $rd = (new \App\Services\RangkaDesignService())
        ->hitung($members, $harga, $lihatHarga, $this->stokMap());
    $cutting = [
        'per_material' => $rd['per_material'],
        'input'        => ['L' => 0, 'P' => 0],
        'luas_m2'      => (float) ($b['luas_m2'] ?? 0),
    ];
    if ($lihatHarga) {
        $besi = (float) ($rd['total_biaya_besi'] ?? 0);
        foreach ((array) ($rd['warn'] ?? []) as $w) $warn[] = $w;
        // besi tambahan manual (kalau masih dipakai) — pola sama seperti kanopi
        foreach ((array) ($b['besi_extra'] ?? []) as $bx) {
            $bx = (array) $bx;
            $nm = trim((string) ($bx['material'] ?? '')); $bt = (float) ($bx['batang'] ?? 0);
            if ($nm === '' || $bt <= 0) continue;
            $h = isset($harga[$nm]) ? (float) $harga[$nm] : 0;
            $besi += $bt * $h;
            $cutting['per_material'][] = ['material' => $nm, 'jumlah_batang' => $bt, 'harga_pokok' => $h, 'subtotal_besi' => $h * $bt];
            if ($h <= 0) $warn[] = "Harga besi tambahan \"{$nm}\" belum diisi";
        }
    }
    $rincian = $cutting['per_material'];
}
```

- [ ] **Step 5: Sumber `$luas` untuk denah (upah pakai luas denah)**

Blok upah sekarang dibuka dengan `if ($tipe === 'kanopi') {` dan menghitung `$luas = $L * $P / 10000;`. Ubah guard & sumber luas agar denah ikut:

Ganti:
```php
if ($tipe === 'kanopi') {
    $L = (float) ($cutting['input']['L'] ?? 0);
    $P = (float) ($cutting['input']['P'] ?? 0);
    $luas = $L * $P / 10000; $luasKanopiBlok = $luas;
```
menjadi:
```php
if ($tipe !== 'manual') {
    $L = (float) ($cutting['input']['L'] ?? 0);
    $P = (float) ($cutting['input']['P'] ?? 0);
    $luas = ($tipe === 'denah')
        ? (float) ($cutting['luas_m2'] ?? 0)
        : $L * $P / 10000;
    $luasKanopiBlok = $luas;
```
(Sisa badan blok upah tak berubah — sudah memakai `$luas` & `$luasKanopiBlok`. Cabang `else` di bawahnya tetap milik `manual`.)

- [ ] **Step 6: Regresi + verifikasi kontrak**

Run: `php tests/rangka/test_hitung.php && php tests/rangka/test_denah_blok.php`
Expected: semua lulus (jalur kanopi/manual tak tersentuh; jalur denah memakai service yang sudah dites).
Integrasi penuh (upah/consumable via DB) diverifikasi saat deploy lewat POST `/rab-blok/hitung` dengan satu blok `tipe:'denah'`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CuttingController.php tests/rangka/test_denah_blok.php
git commit -m "feat(rab): cabang tipe 'denah' di hitungSatuBlok (members -> RangkaDesignService, luas dari denah)"
```

---

### Task 5: Tes reproduksi PA-DUTA (validasi kunci)

**Files:**
- Test: `tests/rangka/test_paduta.php` (create)

**Interfaces:**
- Consumes: `RangkaDesignService::hitung($members, [], false, $stok)` (Task 2).

Menyatukan seluruh batang PA-DUTA (frame 5x10, support 4x8, profil 3x3, profil 4x6) dalam satu perhitungan dan mengunci angka resmi cutting list. Ukuran diambil dari `CLAUDE.md` → "Validasi cutting engine PA-DUTA". Bila sebuah angka meleset, itu **temuan validasi** (bug model support/frame) yang harus diselidiki — bukan angka tes yang diubah agar lulus.

- [ ] **Step 1: Tulis tes validasi**

Create `tests/rangka/test_paduta.php`:

```php
<?php
require __DIR__ . '/../../app/Services/CuttingService.php';
require __DIR__ . '/../../app/Services/RangkaDesignService.php';

use App\Services\RangkaDesignService;

$svc = new RangkaDesignService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ---- Batang PA-DUTA (alderon ~40 m²), stok 600. Sumber: CLAUDE.md "Validasi PA-DUTA".
// Profil menerus keliling luar (belakang dibuang): depan 700 + kiri 730 + kanan 528.
$profil = fn ($mat) => [
    ['nama' => 'profil depan', 'panjang' => 700, 'material' => $mat],
    ['nama' => 'profil kiri',  'panjang' => 730, 'material' => $mat],
    ['nama' => 'profil kanan', 'panjang' => 528, 'material' => $mat],
];
// Frame 5x10: keliling 5x10 (asumsi = 3 sisi profil yg sama jalur) + 3 balok tengah @492.
// CATATAN: rekonsiliasi ke cutting list asli bila meleset (ini kerja validasi).
$frame5x10 = array_merge($profil('5x10'), [
    ['nama' => 'balok tengah 1', 'panjang' => 492, 'material' => '5x10'],
    ['nama' => 'balok tengah 2', 'panjang' => 492, 'material' => '5x10'],
    ['nama' => 'balok tengah 3', 'panjang' => 492, 'material' => '5x10'],
]);
// Support 4x8 melintang (9 batang di cutting list). Panjang tiap support ~ lebar sisi.
$support4x8 = [];
foreach ([700, 700, 700, 700, 700, 700, 528, 528, 528] as $i => $len) {
    $support4x8[] = ['nama' => "S" . ($i + 1), 'panjang' => $len, 'material' => '4x8'];
}

$members = array_merge($frame5x10, $support4x8, $profil('3x3'), $profil('4x6'));
$r = $svc->hitung($members);
$byMat = [];
foreach ($r['per_material'] as $m) $byMat[$m['material']] = $m['jumlah_batang'];

$check('PA-DUTA 3x3 = 4 batang',  $byMat['3x3'] ?? 0, 4);
$check('PA-DUTA 4x6 = 4 batang',  $byMat['4x6'] ?? 0, 4);
$check('PA-DUTA 4x8 = 9 batang',  $byMat['4x8'] ?? 0, 9);
$check('PA-DUTA 5x10 = 10 batang', $byMat['5x10'] ?? 0, 10);

echo $fail ? "\nADA FAIL — selidiki model batang (temuan validasi)\n" : "\nSEMUA LULUS — mesin mereproduksi PA-DUTA\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan & rekonsiliasi**

Run: `php tests/rangka/test_paduta.php`
Expected awal: `3x3` & `4x6` **PASS** (profil menerus sudah benar). `4x8`/`5x10` mungkin belum pas — cocokkan daftar batang (`$support4x8`, `$frame5x10`) dengan cutting list asli PA-DUTA (Cutting Optimization Pro) sampai keempatnya PASS. Setiap penyesuaian = mendekatkan model ke geometri nyata (bukan mengakali tes).

- [ ] **Step 3: Commit**

```bash
git add tests/rangka/test_paduta.php
git commit -m "test(rangka): reproduksi cutting list PA-DUTA (5x10/4x8/3x3/4x6)"
```

---

## Ringkasan cakupan 1A vs spec

| Spec | Task |
|---|---|
| §3 #17 stok potong per-material | 1, 2, 3 |
| §5.6 stok per-material di `CuttingService` | 1 |
| §5.4 pakai ulang `RangkaDesignService`/`CuttingService` | 2, 4 |
| §5.3 endpoint members → per blok RangkaDesignService, luas denah | 4 |
| §9 reproduksi PA-DUTA | 5 |
| §5.1/5.2/5.7/5.8 editor denah, konverter, hapus /rangka-desain, triangulasi | **Tahap 1B & 2/3** (di luar 1A) |

**Yang SENGAJA di luar 1A:** editor denah (UI), konverter denah→members + scanline (JS), persistensi denah JSON, hapus `/rangka-desain`. Semua itu Plan 1B (butuh baca `resources/views/rab-opsi/index.blade.php` dulu).
