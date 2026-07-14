# Perancang Rangka — Fase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun mesin "daftar batang → cutting per-besi + biaya" (product-agnostic) plus halaman baru terpisah untuk seed 1 kotak, edit besi/tambah/hapus batang, dan lihat total — tanpa menyentuh RAB live.

**Architecture:** Satu service PHP murni (`RangkaDesignService`) yang membungkus `CuttingService` (mesin potong yang sudah fix >600cm). Service ini: (1) `seedDariKotak()` mengubah 1 kotak jadi daftar batang rata + posisi via `hitungRangka`; (2) `hitung()` mengelompokkan batang per besi, memotong tiap grup, mengembalikan batang+sambungan+biaya. Halaman baru `/rangka-desain` (controller + blade) memakai service ini; denah read-only meminjam pola cutting-test. RAB lama (`/rab`), RAB Multi-Opsi (`/rab-opsi`), dan cutting-test (`/cutting-test`) TIDAK diubah.

**Tech Stack:** Laravel 13 / PHP 8.3. Frontend: Blade + vanilla JS (fetch), pola sama seperti `resources/views/cutting/index.blade.php`. Tes engine: skrip PHP standalone (VPS tidak punya `vendor/`; `CuttingService` & `RangkaDesignService` murni PHP tanpa DB, jadi bisa di-`require` langsung).

## Global Constraints

- PHP 8.3, Laravel 13.12. Ikuti pola controller yang ada di `app/Http/Controllers/CuttingController.php`.
- Akses halaman: **owner-only** (level 1) — pola `abort_if(Auth::user()->level != 1, 403)` seperti `CuttingController::bolehAkses()`.
- Stok besi = 600 cm/batang (`CuttingService::STOCK`), maks 1 sambungan/potong. JANGAN hardcode ulang; pakai `CuttingService`.
- Biaya besi = `harga_pokok × jumlah_batang`. Data kosong → peringatan (`warn[]`), BUKAN Rp0 diam-diam.
- Besi dari tabel `master_material` (kolom `id, nama, harga_pokok`), filter `kategori='rangka_besi' AND aktif=1`.
- JANGAN ubah `app/Services/CuttingService.php`, `CuttingController.php`, rute/`view` RAB lama/opsi/cutting-test.
- Emoji dilarang di blade (korup di server) — pakai teks/SVG.
- Deploy = `git push` → GitHub Actions FTP ke Niagahoster. Tes halaman = deploy lalu buka di browser (VPS tak punya vendor untuk `artisan serve`).
- Denah Fase 1 = **read-only** (snapshot seed). Denah interaktif = Fase 2 (di luar plan ini).

---

### Task 1: `RangkaDesignService::hitung()` — mesin daftar-batang → cutting per-besi

Inti fitur. Murni PHP, tanpa DB. Dites dengan skrip standalone.

**Files:**
- Create: `app/Services/RangkaDesignService.php`
- Test: `tests/rangka/test_hitung.php`

**Interfaces:**
- Consumes: `App\Services\CuttingService::potong(array $pieces): array` — tiap `$pieces[i] = ['label'=>string,'len'=>float]`; return `array` batang, tiap batang `['no'=>int,'sisa'=>float,'seg'=>[['label','len','jenis'=>'utuh'|'sambung','jid'?], ...]]`.
- Produces:
  - `RangkaDesignService::hitung(array $members, array $harga = [], bool $lihatHarga = false): array`
    - `$members[i]` minimal: `['nama'=>string, 'panjang'=>float, 'material'=>string]` (field lain diabaikan di sini).
    - `$harga` = `['<nama_material>' => <harga_pokok float>, ...]`.
    - Return: `['per_material'=>[['material'=>string,'jumlah_batang'=>int,'sambungan'=>int,'harga_pokok'=>?float,'subtotal_besi'=>?float,'jml_potong'=>int], ...], 'total_batang'=>int, 'total_biaya_besi'=>?float, 'warn'=>string[]]`.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_hitung.php`:

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

// A. Profil MENERUS 700+730+528 (satu besi) -> 4 batang (validasi cutting list PA-DUTA)
$profil = [
    ['nama' => 'depan', 'panjang' => 700, 'material' => '3x3'],
    ['nama' => 'kiri',  'panjang' => 730, 'material' => '3x3'],
    ['nama' => 'kanan', 'panjang' => 528, 'material' => '3x3'],
];
$r = $svc->hitung($profil);
$check('profil 3x3 [700,730,528] = 4 batang', $r['per_material'][0]['jumlah_batang'], 4);

// B. Dua besi berbeda tidak tercampur
$mix = [
    ['nama' => 'f1', 'panjang' => 500, 'material' => '5x10'],
    ['nama' => 's1', 'panjang' => 500, 'material' => '4x8'],
];
$r = $svc->hitung($mix);
$check('2 material -> 2 baris', count($r['per_material']), 2);

// C. Biaya = harga_pokok x jumlah_batang (owner)
$r = $svc->hitung([['nama' => 'a', 'panjang' => 300, 'material' => '5x10']], ['5x10' => 100000], true);
$check('subtotal = 100000 x 1', $r['per_material'][0]['subtotal_besi'], 100000.0);
$check('total biaya', $r['total_biaya_besi'], 100000.0);

// D. Harga kosong (owner) -> warn, subtotal null
$r = $svc->hitung([['nama' => 'a', 'panjang' => 300, 'material' => '5x10']], [], true);
$check('warn harga kosong', count($r['warn']) >= 1, true);

// E. Batang tanpa material / panjang <= 0 diabaikan
$r = $svc->hitung([['nama' => 'x', 'panjang' => 0, 'material' => '5x10'], ['nama' => 'y', 'panjang' => 300, 'material' => '']]);
$check('input invalid diabaikan', $r['total_batang'], 0);

echo $fail ? "\n=== ADA FAIL ===\n" : "\n=== SEMUA PASS ===\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `cd /root/projects/canopi-app && php tests/rangka/test_hitung.php`
Expected: FATAL error `Failed opening required '.../RangkaDesignService.php'` (file belum ada).

- [ ] **Step 3: Tulis implementasi minimal**

Create `app/Services/RangkaDesignService.php`:

```php
<?php

namespace App\Services;

/**
 * Perancang Rangka (Fase 1) — mesin "daftar batang -> cutting per besi + biaya".
 * Product-agnostic: menerima daftar batang apa pun. Membungkus CuttingService
 * (mesin potong 600cm/batang, sudah fix >600cm 14 Juli 2026).
 */
class RangkaDesignService
{
    public function __construct(
        private CuttingService $cutting = new CuttingService()
    ) {}

    /**
     * Hitung batang + sambungan + biaya per besi dari daftar batang.
     *
     * @param array $members  tiap: ['nama'=>string,'panjang'=>float,'material'=>string, ...]
     * @param array $harga    ['<material>' => <harga_pokok>]
     */
    public function hitung(array $members, array $harga = [], bool $lihatHarga = false): array
    {
        // Kelompokkan panjang per besi
        $byMat = [];
        foreach ($members as $m) {
            $mat = trim((string) ($m['material'] ?? ''));
            $len = (float) ($m['panjang'] ?? 0);
            if ($mat === '' || $len <= 0) continue;
            $byMat[$mat][] = ['label' => (string) ($m['nama'] ?? ''), 'len' => $len];
        }

        $per = [];
        $warn = [];
        $totalBatang = 0;
        $totalBiaya = 0.0;

        foreach ($byMat as $mat => $pieces) {
            $bars = $this->cutting->potong($pieces);
            $joins = 0;
            foreach ($bars as $b) {
                foreach ($b['seg'] as $s) {
                    if (($s['jenis'] ?? '') === 'sambung') $joins++;
                }
            }
            $joins = intdiv($joins, 2);
            $batang = count($bars);

            $h = ($lihatHarga && isset($harga[$mat])) ? (float) $harga[$mat] : null;
            $sub = $h !== null ? $h * $batang : null;
            if ($lihatHarga && ($h === null || $h <= 0)) {
                $warn[] = "Harga besi \"$mat\" belum diisi";
            }

            $per[] = [
                'material'      => $mat,
                'jumlah_batang' => $batang,
                'sambungan'     => $joins,
                'harga_pokok'   => $h,
                'subtotal_besi' => $sub,
                'jml_potong'    => count($pieces),
            ];
            $totalBatang += $batang;
            if ($sub !== null) $totalBiaya += $sub;
        }

        return [
            'per_material'     => $per,
            'total_batang'     => $totalBatang,
            'total_biaya_besi' => $lihatHarga ? $totalBiaya : null,
            'warn'             => array_values(array_unique($warn)),
        ];
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan LULUS**

Run: `cd /root/projects/canopi-app && php tests/rangka/test_hitung.php`
Expected: semua baris `PASS`, ditutup `=== SEMUA PASS ===`, exit 0.

- [ ] **Step 5: Commit**

```bash
cd /root/projects/canopi-app
git add app/Services/RangkaDesignService.php tests/rangka/test_hitung.php
git commit -m "feat(rangka): mesin daftar-batang -> cutting per besi + biaya (Fase 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: `RangkaDesignService::seedDariKotak()` — 1 kotak → daftar batang + denah

**Files:**
- Modify: `app/Services/RangkaDesignService.php` (tambah method)
- Test: `tests/rangka/test_seed.php`

**Interfaces:**
- Consumes: `CuttingService::hitungRangka(array $in): array` — return berisi `['denah'=>['L'=>float,'P'=>float,'T'=>float,'v'=>[['x'=>float,'tipe'=>'frame'|'support','nama'=>string], ...],'h'=>[['y'=>float,'tipe'=>...,'nama'=>string], ...],'tiang'=>[['x'=>float,'y'=>float,'nama'=>string], ...]], ...]`. Input `$in`: `lebar_cm,panjang_cm,tinggi_cm,kotak_cm,arah_support,jml_tiang,mat_frame,mat_support,mat_tiang` + toggle sisi `frame_depan/belakang/kiri/kanan/tengah` (bool).
- Produces: `seedDariKotak(array $in): array` → `['members'=>[['nama'=>string,'jenis'=>'frame'|'support'|'tiang','panjang'=>float,'arah'=>'vertikal'|'horizontal'|'tiang','posisi'=>array,'material'=>string], ...], 'denah'=>array]`. `denah` diteruskan apa adanya dari `hitungRangka` (untuk gambar read-only).

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_seed.php`:

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

// Seed 700x730, kotak 80, 2 arah, tiang 2, besi frame/support/tiang BEDA nama
$seed = $svc->seedDariKotak([
    'lebar_cm' => 700, 'panjang_cm' => 730, 'tinggi_cm' => 300,
    'kotak_cm' => 80, 'arah_support' => 2, 'jml_tiang' => 2,
    'mat_frame' => '5x10', 'mat_support' => '4x8', 'mat_tiang' => 'WF150',
]);

// Tiap member punya field wajib
$m0 = $seed['members'][0];
$check('member punya material', isset($m0['material']), true);
$check('member punya panjang', isset($m0['panjang']), true);
$check('denah diteruskan', isset($seed['denah']['v']), true);

// Hitung hasil seed -> angka yang SUDAH DIVERIFIKASI live (14 Juli):
// frame 5x10 = 8 batang / 6 sambungan; support 4x8 = 20 / 16; tiang = 1.
$r = $svc->hitung($seed['members']);
$get = function ($r, $mat) {
    foreach ($r['per_material'] as $x) if ($x['material'] === $mat) return $x;
    return null;
};
$check('frame 5x10 = 8 batang', $get($r, '5x10')['jumlah_batang'], 8);
$check('frame 5x10 = 6 sambungan', $get($r, '5x10')['sambungan'], 6);
$check('support 4x8 = 20 batang', $get($r, '4x8')['jumlah_batang'], 20);
$check('support 4x8 = 16 sambungan', $get($r, '4x8')['sambungan'], 16);
$check('tiang WF150 = 1 batang', $get($r, 'WF150')['jumlah_batang'], 1);

echo $fail ? "\n=== ADA FAIL ===\n" : "\n=== SEMUA PASS ===\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `cd /root/projects/canopi-app && php tests/rangka/test_seed.php`
Expected: FATAL `Call to undefined method App\Services\RangkaDesignService::seedDariKotak()`.

- [ ] **Step 3: Tulis implementasi**

Tambahkan method ini ke `app/Services/RangkaDesignService.php` (di dalam class, setelah `hitung()`):

```php
    /**
     * Ubah 1 kotak jadi daftar batang rata (frame/support/tiang) + posisi,
     * memakai hitungRangka. Hasil ini yang jadi seed awal untuk diedit.
     */
    public function seedDariKotak(array $in): array
    {
        $r = $this->cutting->hitungRangka($in);
        $matFrame   = trim((string) ($in['mat_frame']   ?? 'Frame'));
        $matSupport = trim((string) ($in['mat_support'] ?? 'Support'));
        $matTiang   = trim((string) ($in['mat_tiang']   ?? 'Tiang'));

        $L = (float) ($r['denah']['L'] ?? 0);
        $P = (float) ($r['denah']['P'] ?? 0);
        $T = (float) ($r['denah']['T'] ?? 0);

        $members = [];
        // Garis vertikal (membujur) panjang = P
        foreach ($r['denah']['v'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => $ln['tipe'],
                'panjang'  => $P,
                'arah'     => 'vertikal',
                'posisi'   => ['x' => $ln['x']],
                'material' => $ln['tipe'] === 'frame' ? $matFrame : $matSupport,
            ];
        }
        // Garis horizontal (melintang) panjang = L
        foreach ($r['denah']['h'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => $ln['tipe'],
                'panjang'  => $L,
                'arah'     => 'horizontal',
                'posisi'   => ['y' => $ln['y']],
                'material' => $ln['tipe'] === 'frame' ? $matFrame : $matSupport,
            ];
        }
        // Tiang panjang = T
        foreach ($r['denah']['tiang'] as $ln) {
            $members[] = [
                'nama'     => $ln['nama'],
                'jenis'    => 'tiang',
                'panjang'  => $T,
                'arah'     => 'tiang',
                'posisi'   => ['x' => $ln['x'], 'y' => $ln['y']],
                'material' => $matTiang,
            ];
        }

        return ['members' => $members, 'denah' => $r['denah']];
    }
```

- [ ] **Step 4: Jalankan tes, pastikan LULUS**

Run: `cd /root/projects/canopi-app && php tests/rangka/test_seed.php`
Expected: semua `PASS`, `=== SEMUA PASS ===`, exit 0. (Jika frame/support meleset, cek pemetaan `tipe`→material.)

- [ ] **Step 5: Commit**

```bash
cd /root/projects/canopi-app
git add app/Services/RangkaDesignService.php tests/rangka/test_seed.php
git commit -m "feat(rangka): seedDariKotak 1 kotak -> daftar batang + posisi (Fase 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Controller + rute `/rangka-desain`

**Files:**
- Create: `app/Http/Controllers/RangkaDesainController.php`
- Modify: `routes/web.php` (tambah 3 rute; taruh dekat blok rute `cutting-test`/`rab-blok`, sekitar baris 375–385)

**Interfaces:**
- Consumes: `RangkaDesignService::seedDariKotak()`, `RangkaDesignService::hitung()` (Task 1–2); tabel `master_material`.
- Produces (endpoint JSON):
  - `GET /rangka-desain` → view.
  - `POST /rangka-desain/seed` body `{lebar_cm,panjang_cm,tinggi_cm,kotak_cm,arah_support,jml_tiang,mat_frame,mat_support,mat_tiang}` → `{success,data:{members,denah}}`.
  - `POST /rangka-desain/hitung` body `{members:[...], harga:{...}}` → `{success,data:{per_material,total_batang,total_biaya_besi,warn}}`.

- [ ] **Step 1: Buat controller**

Create `app/Http/Controllers/RangkaDesainController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\RangkaDesignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RangkaDesainController extends Controller
{
    private function bolehAkses(): bool
    {
        return Auth::check() && (int) Auth::user()->level === 1; // owner-only
    }

    public function index()
    {
        abort_if(!$this->bolehAkses(), 403);

        $besi = collect();
        try {
            $besi = DB::table('master_material')
                ->where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'harga_pokok']);
        } catch (\Throwable $e) {
            $besi = collect();
        }

        $lihatHarga = (int) Auth::user()->level === 1;
        return view('rangka-desain.index', compact('besi', 'lihatHarga'));
    }

    public function seed(Request $request, RangkaDesignService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $in = [
            'lebar_cm'     => (float) $request->input('lebar_cm', 0),
            'panjang_cm'   => (float) $request->input('panjang_cm', 0),
            'tinggi_cm'    => (float) $request->input('tinggi_cm', 300),
            'kotak_cm'     => (float) $request->input('kotak_cm', 80),
            'arah_support' => (int) $request->input('arah_support', 2),
            'jml_tiang'    => (int) $request->input('jml_tiang', 2),
            'mat_frame'    => trim((string) $request->input('mat_frame', 'Frame')),
            'mat_support'  => trim((string) $request->input('mat_support', 'Support')),
            'mat_tiang'    => trim((string) $request->input('mat_tiang', 'Tiang')),
        ];
        return response()->json(['success' => true, 'data' => $svc->seedDariKotak($in)]);
    }

    public function hitung(Request $request, RangkaDesignService $svc)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lihatHarga = (int) Auth::user()->level === 1;
        $members = (array) $request->input('members', []);
        $harga   = (array) $request->input('harga', []);
        return response()->json(['success' => true, 'data' => $svc->hitung($members, $harga, $lihatHarga)]);
    }
}
```

- [ ] **Step 2: Tambah rute**

Di `routes/web.php`, tepat setelah baris rute `cutting-test` (cari `Route::post('/cutting-test/cetak', ...)`, sekitar baris 377), sisipkan di dalam grup middleware yang sama:

```php
    // Perancang Rangka (Fase 1) — halaman baru terpisah, owner-only
    Route::get('/rangka-desain',         [\App\Http\Controllers\RangkaDesainController::class, 'index']);
    Route::post('/rangka-desain/seed',   [\App\Http\Controllers\RangkaDesainController::class, 'seed']);
    Route::post('/rangka-desain/hitung', [\App\Http\Controllers\RangkaDesainController::class, 'hitung']);
```

- [ ] **Step 3: Cek sintaks PHP kedua file**

Run:
```bash
cd /root/projects/canopi-app
php -l app/Http/Controllers/RangkaDesainController.php && php -l routes/web.php
```
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 4: Commit**

```bash
cd /root/projects/canopi-app
git add app/Http/Controllers/RangkaDesainController.php routes/web.php
git commit -m "feat(rangka): controller + rute /rangka-desain (Fase 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Halaman blade — kotak input, tabel batang editable, denah read-only, total

**Files:**
- Create: `resources/views/rangka-desain/index.blade.php`

**Interfaces:**
- Consumes: `$besi` (collection `{id,nama,harga_pokok}`), `$lihatHarga` (bool) dari controller; endpoint `POST /rangka-desain/seed` & `/rangka-desain/hitung`.
- Produces: halaman HTML. JS menyimpan `members[]` di memori, kirim ke `/hitung` tiap edit, render ringkasan + tabel.

- [ ] **Step 1: Buat view**

Create `resources/views/rangka-desain/index.blade.php`. Gunakan layout yang sama seperti `resources/views/cutting/index.blade.php` (cek baris pertamanya: `@extends('layouts.app')` atau sejenis) dan CSRF meta. Isi:

```blade
@extends('layouts.app')
@section('title', 'Perancang Rangka')
@section('page-title', 'Perancang Rangka')

@section('content')
<div style="max-width:960px;margin:0 auto;padding:12px">
  <h1 style="font-size:20px;font-weight:800;margin:0 0 4px">Perancang Rangka <span style="font-size:12px;color:#f59e0b">(Fase 1 — uji coba)</span></h1>
  <p style="color:#64748b;font-size:13px;margin:0 0 16px">Isi kotak, sistem bikin daftar batang default. Ganti besi tiap batang, tambah/hapus, lalu lihat total.</p>

  {{-- 1. INPUT KOTAK --}}
  <div style="background:#0f172a0d;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
      <label>Lebar (cm)<input id="lebar" type="number" value="700" class="rd-in"></label>
      <label>Panjang (cm)<input id="panjang" type="number" value="730" class="rd-in"></label>
      <label>Tinggi tiang (cm)<input id="tinggi" type="number" value="300" class="rd-in"></label>
      <label>Kotak support (cm)<input id="kotak" type="number" value="80" class="rd-in"></label>
      <label>Arah support
        <select id="arah" class="rd-in"><option value="2">2 arah</option><option value="1">1 arah</option></select></label>
      <label>Jumlah tiang<input id="tiang" type="number" value="2" class="rd-in"></label>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:10px">
      <label>Besi Frame<select id="matFrame" class="rd-in rd-besi"></select></label>
      <label>Besi Support<select id="matSupport" class="rd-in rd-besi"></select></label>
      <label>Besi Tiang<select id="matTiang" class="rd-in rd-besi"></select></label>
    </div>
    <button id="btnSeed" class="rd-btn" style="margin-top:12px">Buat Denah Default</button>
  </div>

  {{-- 2. DENAH READ-ONLY + TABEL BATANG --}}
  <div id="hasil" style="display:none">
    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
      <div style="font-weight:700;margin-bottom:8px">Denah (tampak atas)</div>
      <div id="denah" style="overflow-x:auto"></div>
    </div>

    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:700">Daftar Batang</div>
        <button id="btnTambah" class="rd-btn rd-btn-sm">+ Tambah Batang</button>
      </div>
      <div style="overflow-x:auto"><table id="tblBatang" style="width:100%;border-collapse:collapse;font-size:13px"></table></div>
    </div>

    {{-- 3. RINGKASAN --}}
    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px">
      <div style="font-weight:700;margin-bottom:8px">Ringkasan Besi</div>
      <div id="ringkasan"></div>
      <div id="warn" style="color:#b45309;font-size:12px;margin-top:8px"></div>
    </div>
  </div>
</div>

<style>
  .rd-in{display:block;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;margin-top:4px;font-size:13px}
  label{font-size:12px;color:#475569;font-weight:600}
  .rd-btn{background:#1e40af;color:#fff;border:0;padding:10px 18px;border-radius:8px;font-size:14px;cursor:pointer}
  .rd-btn-sm{padding:6px 12px;font-size:13px}
  #tblBatang th{text-align:left;padding:6px;border-bottom:2px solid #e2e8f0;font-size:11px;color:#64748b;text-transform:uppercase}
  #tblBatang td{padding:6px;border-bottom:1px solid #f1f5f9}
  .rd-del{background:#fee2e2;color:#b91c1c;border:0;border-radius:6px;padding:4px 8px;cursor:pointer}
</style>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const LIHAT_HARGA = @json($lihatHarga);
const BESI = @json($besi);
let members = [];
let hargaMap = {};
BESI.forEach(b => hargaMap[b.nama] = Number(b.harga_pokok) || 0);

function besiOptions(sel){
  return BESI.map(b => `<option value="${b.nama}" ${b.nama===sel?'selected':''}>${b.nama}</option>`).join('');
}
// isi dropdown besi kotak
['matFrame','matSupport','matTiang'].forEach(id => { document.getElementById(id).innerHTML = besiOptions(); });

async function post(url, body){
  const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(body)});
  return res.json();
}

document.getElementById('btnSeed').onclick = async () => {
  const j = await post('{{ url('/rangka-desain/seed') }}', {
    lebar_cm:+lebar.value, panjang_cm:+panjang.value, tinggi_cm:+tinggi.value,
    kotak_cm:+kotak.value, arah_support:+arah.value, jml_tiang:+tiang.value,
    mat_frame:matFrame.value, mat_support:matSupport.value, mat_tiang:matTiang.value,
  });
  if(!j.success){ alert('Gagal seed'); return; }
  members = j.data.members;
  document.getElementById('hasil').style.display = 'block';
  gambarDenah(j.data.denah);
  renderTabel(); hitung();
};

document.getElementById('btnTambah').onclick = () => {
  members.push({nama:'Batang baru', jenis:'tambahan', panjang:100, arah:'-', posisi:{}, material:BESI[0]?BESI[0].nama:''});
  renderTabel(); hitung();
};

function renderTabel(){
  let h = '<tr><th>Nama</th><th>Jenis</th><th>Panjang (cm)</th><th>Besi</th><th></th></tr>';
  members.forEach((m,i) => {
    h += `<tr>
      <td>${m.nama}</td>
      <td>${m.jenis}</td>
      <td><input type="number" value="${m.panjang}" data-i="${i}" class="rd-in rd-len" style="width:90px;margin:0"></td>
      <td><select data-i="${i}" class="rd-in rd-mat" style="margin:0">${besiOptions(m.material)}</select></td>
      <td><button class="rd-del" data-i="${i}">hapus</button></td>
    </tr>`;
  });
  document.getElementById('tblBatang').innerHTML = h;
  document.querySelectorAll('.rd-len').forEach(el => el.onchange = e => { members[+e.target.dataset.i].panjang = +e.target.value; hitung(); });
  document.querySelectorAll('.rd-mat').forEach(el => el.onchange = e => { members[+e.target.dataset.i].material = e.target.value; hitung(); });
  document.querySelectorAll('.rd-del').forEach(el => el.onclick = e => { members.splice(+e.target.dataset.i,1); renderTabel(); hitung(); });
}

async function hitung(){
  const j = await post('{{ url('/rangka-desain/hitung') }}', {members, harga:hargaMap});
  if(!j.success) return;
  const d = j.data;
  let h = '<table style="width:100%;font-size:13px;border-collapse:collapse">';
  h += '<tr><th style="text-align:left">Besi</th><th style="text-align:right">Batang</th><th style="text-align:right">Sambungan</th>' + (LIHAT_HARGA?'<th style="text-align:right">Subtotal</th>':'') + '</tr>';
  d.per_material.forEach(m => {
    h += `<tr><td>${m.material}</td><td style="text-align:right">${m.jumlah_batang}</td><td style="text-align:right">${m.sambungan}</td>` +
      (LIHAT_HARGA?`<td style="text-align:right">${m.subtotal_besi!=null?('Rp '+Number(m.subtotal_besi).toLocaleString('id-ID')):'-'}</td>`:'') + '</tr>';
  });
  h += `<tr style="font-weight:800;border-top:2px solid #e2e8f0"><td>TOTAL</td><td style="text-align:right">${d.total_batang}</td><td></td>` +
    (LIHAT_HARGA?`<td style="text-align:right">${d.total_biaya_besi!=null?('Rp '+Number(d.total_biaya_besi).toLocaleString('id-ID')):'-'}</td>`:'') + '</tr>';
  h += '</table>';
  document.getElementById('ringkasan').innerHTML = h;
  document.getElementById('warn').innerHTML = (d.warn||[]).map(w=>'⚠ '+w).join('<br>');
}

// Denah read-only sederhana: gambar garis dari posisi (SVG)
function gambarDenah(dn){
  const L = dn.L, P = dn.P, pad = 30, sc = Math.min(520/L, 360/P);
  const W = L*sc+pad*2, H = P*sc+pad*2;
  let s = `<svg width="${W}" height="${H}" style="max-width:100%">`;
  s += `<rect x="${pad}" y="${pad}" width="${L*sc}" height="${P*sc}" fill="none" stroke="#94a3b8"/>`;
  (dn.v||[]).forEach(v => { const x = pad+v.x*sc; s += `<line x1="${x}" y1="${pad}" x2="${x}" y2="${pad+P*sc}" stroke="${v.tipe==='frame'?'#1e40af':'#60a5fa'}" stroke-width="${v.tipe==='frame'?2:1}"/>`; });
  (dn.h||[]).forEach(v => { const y = pad+v.y*sc; s += `<line x1="${pad}" y1="${y}" x2="${pad+L*sc}" y2="${y}" stroke="${v.tipe==='frame'?'#1e40af':'#60a5fa'}" stroke-width="${v.tipe==='frame'?2:1}"/>`; });
  (dn.tiang||[]).forEach(t => { s += `<circle cx="${pad+t.x*sc}" cy="${pad+t.y*sc}" r="4" fill="#b45309"/>`; });
  s += '</svg>';
  document.getElementById('denah').innerHTML = s;
}
</script>
@endsection
```

Catatan: samakan `@extends(...)`/`@section(...)` dengan yang dipakai `resources/views/cutting/index.blade.php` bila berbeda. Emoji `⚠` di JS aman karena tidak di-render Blade; jika ragu, ganti `'⚠ '` jadi `'! '`.

- [ ] **Step 2: Cek blade ter-compile (sintaks)**

Karena VPS tak punya `vendor/`, cek manual: pastikan tidak ada `@php`/`{{ }}` yang tak seimbang. Jalankan cek kurung kurawal dasar:
```bash
cd /root/projects/canopi-app
grep -c "@section" resources/views/rangka-desain/index.blade.php   # harus 2 (title/content) + page-title = cek >=2
php -r 'echo "blade file ada: "; echo file_exists("resources/views/rangka-desain/index.blade.php")?"ya\n":"tidak\n";'
```
Expected: file ada = ya.

- [ ] **Step 3: Commit**

```bash
cd /root/projects/canopi-app
git add resources/views/rangka-desain/index.blade.php
git commit -m "feat(rangka): halaman /rangka-desain (kotak, tabel batang, denah, total) Fase 1

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Menu sidebar + verifikasi end-to-end (deploy → browser)

**Files:**
- Modify: `resources/views/partials/sidebar-owner.blade.php` (tambah 1 link di bagian "RAB Penawaran")

**Interfaces:**
- Consumes: rute `/rangka-desain` (Task 3).

- [ ] **Step 1: Tambah link sidebar**

Di `resources/views/partials/sidebar-owner.blade.php`, cari blok link `/cutting-test` (sekitar baris 42). Tepat setelahnya, tambahkan link serupa (tiru struktur `<a>` yang ada persis, ganti url & label):

```blade
<a href="{{ url('/rangka-desain') }}"
   class="nav-item {{ request()->is('rangka-desain*') ? 'active' : '' }}">
    <span x-show="sidebarOpen">Perancang Rangka</span>
</a>
```
(Samakan ikon/markup dengan link `/cutting-test` di atasnya bila ada elemen SVG.)

- [ ] **Step 2: Commit**

```bash
cd /root/projects/canopi-app
git add resources/views/partials/sidebar-owner.blade.php
git commit -m "feat(rangka): link menu Perancang Rangka di sidebar owner

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 3: Deploy (Elvan konfirmasi push)**

Tunjukkan diff ke Elvan; setelah setuju, `git push` (memicu GitHub Actions FTP ke Niagahoster, ±1–2 menit). JANGAN push tanpa persetujuan.

- [ ] **Step 4: Verifikasi di browser (owner)**

Setelah deploy hijau, login owner di https://app.kanopibsd.co.id → sidebar **Perancang Rangka**. Cek alur:
1. Isi Lebar 700, Panjang 730, klik **Buat Denah Default** → denah + tabel batang muncul.
2. Ganti besi salah satu baris Support → ringkasan **berubah seketika**.
3. Klik **+ Tambah Batang**, ubah panjangnya → total batang naik.
4. Hapus satu baris → total turun.

Expected: seed 700×730 (besi frame/support/tiang beda) menampilkan Frame **8 batang / 6 sambungan**, Support **20 / 16**, Tiang **1** — sama dengan tes standalone Task 2.

- [ ] **Step 5: Catat progres**

Update bagian "Status Terkini" di `CLAUDE.md`: Fase 1 Perancang Rangka live (`/rangka-desain`, owner-only, terpisah dari RAB), engine `RangkaDesignService` terbukti (tes standalone + verifikasi browser). Commit.

---

## Self-Review (writing-plans)

**Spec coverage (Fase 1 saja):**
- Model data batang → Task 1/2 (struktur member: nama/jenis/panjang/arah/posisi/material). ✓
- Seed 1 kotak (`hitungRangka`) → Task 2. ✓
- Edit besi/tambah/hapus → Task 4 (tabel + JS). ✓
- Cutting per besi + biaya → Task 1. ✓
- Denah read-only → Task 4 (SVG dari posisi). ✓
- Halaman baru terpisah, RAB live tak disentuh → Task 3/5 + Global Constraints. ✓
- Owner-only, master_material, biaya = harga×batang → Global Constraints + Task 1/3. ✓
- Prinsip UX HP+komputer (spec §6): grid responsif + tabel scroll-x dipakai; **poles penuh (bottom-sheet dll) = Fase 2**, dicatat sebagai batasan Fase 1.

**Koreksi scope terhadap spec §8:** target "PA-DUTA 10/9/4/4" adalah bentuk berlekuk (butuh multi-kotak = Fase 3). Acceptance Fase 1 yang bisa dibuktikan otomatis diganti jadi: **profil [700,730,528]=4** (Task 1) + **seed 700×730 = frame 8/6, support 20/16** (Task 2), keduanya angka terverifikasi. Reproduksi PA-DUTA penuh menyusul di Fase 3 setelah daftar batang lengkap ditranskrip dari cutting list.

**Placeholder scan:** tidak ada TBD/TODO; semua step berisi kode/perintah nyata. ✓

**Type consistency:** `seedDariKotak()` → `{members,denah}`; `hitung(members,harga,lihatHarga)` → `{per_material,total_batang,total_biaya_besi,warn}`; member `{nama,jenis,panjang,arah,posisi,material}` konsisten di Task 1/2/3/4. ✓

**Catatan tes:** engine (Task 1–2) diuji skrip PHP standalone di VPS (tanpa vendor). Controller/view (Task 3–5) diuji deploy + browser (VPS tak punya vendor untuk `artisan serve`). Ini konsisten dengan cara verifikasi sepanjang proyek.
