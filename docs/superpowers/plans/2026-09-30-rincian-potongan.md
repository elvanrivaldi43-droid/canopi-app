# Rincian Potongan Absensi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap angka `absensi.potongan_telat` punya rincian asal-usul yang terbaca di riwayat karyawan, rekap harian Owner, dan slip gaji — tanpa mengubah angka uang apa pun.

**Architecture:** Satu kolom TEXT (`rincian_potongan`, cast `array`) di `absensi` dan `slip_gaji`. Satu kelas murni `App\Services\RincianPotongan` (tanpa DB) memegang semua logika: menambah entri, menyusun baris tampilan (termasuk sisa "Belum terinci"), dan meringkas sebulan. Titik tulis yang sudah ada cukup menambah satu key di array update-nya.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, MariaDB/MySQL shared hosting (skema production via SQL manual phpMyAdmin).

**Spec:** `docs/superpowers/specs/2026-09-30-rincian-potongan-design.md`

## Global Constraints

- `potongan_telat` tetap SATU-SATUNYA patokan uang. Jangan ubah rumus `GajiService`, `nominalKoreksi`, `gajiLupaPulang`, `kurangiDenda`.
- Kolom baru bertipe `TEXT NULL`, bukan JSON.
- SQL production wajib idempotent (`ADD COLUMN IF NOT EXISTS`) dan dijalankan Elvan SEBELUM kode di-push.
- Tanpa emoji di blade baru (aturan deploy).
- Jangan `git add -A`; stage per nama file.
- Test baru WAJIB didaftarkan di `tests/guardrail/manifest.json` di commit yang sama.
- JANGAN push sampai Task 6 (gate SQL).
- Jenis entri tepat: `telat_pagi`, `telat_siang`, `lupa_progress`, `skip_siang`, `koreksi`; kunci sisa: `belum_terinci`.

## Review Focus

1. Kode ter-deploy sebelum kolom ada → `Unknown column rincian_potongan` → absen masuk semua karyawan gagal. Dijaga Task 6 (gate SQL + verifikasi kolom).
2. Koreksi Owner yang TIDAK mengubah angka potongan (cuma ganti status/jam) → tak boleh menambah entri koreksi Rp0. Dikunci test "nominal 0 diabaikan" + "koreksi selisih 0" (Task 1).
3. Baris lama (rincian NULL) lalu dikoreksi → rincian `[koreksi −X]` + sisa "Belum terinci" harus tetap berjumlah = total baru. Test Task 1.
4. Potongan pecahan per menit (333,33 × 3 vs total 1.000) → tak boleh muncul baris "Belum terinci Rp0" palsu. Test Task 1 (ambang ≥ Rp1).
5. Perbaikan SQL manual di masa depan yang menolkan `potongan_telat` tanpa mengosongkan `rincian_potongan` → baris "Belum terinci" negatif. Tidak dikode; dicatat di CLAUDE.md (Task 6): skrip perbaikan harus ikut `SET rincian_potongan = NULL`.

---

### Task 1: Kelas murni `RincianPotongan` + test

**Files:**
- Create: `app/Services/RincianPotongan.php`
- Create: `tests/penggajian/test_rincian_potongan.php`
- Modify: `tests/guardrail/manifest.json` (tambah 1 entri)

**Interfaces:**
- Produces:
  - `RincianPotongan::LABEL` (array jenis → label)
  - `RincianPotongan::tambah(?array $rincian, string $jenis, float $nominal, ?int $menit = null, ?string $alasan = null): array`
  - `RincianPotongan::baris(?array $rincian, float $total): array` → list `['j'=>string,'label'=>string,'n'=>float,'ket'=>?string]`
  - `RincianPotongan::ringkasBulan(iterable $absensi): array` → `[jenis => ['x'=>int,'n'=>float]]`; tiap item `$a` dibaca via `$a->rincian_potongan` dan `$a->potongan_telat` (model Eloquent atau stdClass)

- [ ] **Step 1: Tulis test yang gagal**

`tests/penggajian/test_rincian_potongan.php`:

```php
<?php
// FILE: tests/penggajian/test_rincian_potongan.php
// Jalankan: php tests/penggajian/test_rincian_potongan.php
//
// Rincian asal-usul absensi.potongan_telat (spec 2026-09-30-rincian-potongan-design.md).
// Yang dikunci: jumlah baris tampilan SELALU = total potongan (sisa jadi baris
// "Belum terinci"), koreksi tanpa perubahan angka tak menambah entri, dan
// pecahan per menit tak memunculkan baris sisa palsu.

require_once __DIR__ . '/../../app/Services/RincianPotongan.php';

use App\Services\RincianPotongan as R;

$fail = false;
function check(string $nama, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $nama" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}
function jumlah(array $baris): float { return round(array_sum(array_column($baris, 'n')), 2); }

// ── tambah
check('nominal 0 diabaikan', R::tambah(null, 'telat_pagi', 0.0, 0), []);
check('entri telat pagi + menit', R::tambah(null, 'telat_pagi', 1333.333, 4), [['j' => 'telat_pagi', 'n' => 1333.33, 'm' => 4]]);
check('entri koreksi + alasan', R::tambah([], 'koreksi', -20000.0, null, 'salah hitung'), [['j' => 'koreksi', 'n' => -20000.0, 'a' => 'salah hitung']]);

// ── Kasus nyata Rizco 24/9: telat 4 mnt + lupa progress + skip siang = 41.333,33
$r = R::tambah(null, 'telat_pagi', 1333.33, 4);
$r = R::tambah($r, 'lupa_progress', 20000);
$r = R::tambah($r, 'skip_siang', 20000);
$b = R::baris($r, 41333.33);
check('Rizco: 3 baris, tanpa sisa', count($b), 3);
check('Rizco: jumlah = total', jumlah($b), 41333.33);
check('Rizco: label pertama', $b[0]['label'], 'Telat masuk');
check('Rizco: ket menit', $b[0]['ket'], '4 menit');

// ── Koreksi turun: 41.333,33 -> 21.333 (selisih dari TOTAL lama)
$r2 = R::tambah($r, 'koreksi', 21333 - 41333.33, null, 'sudah lapor lisan');
$b2 = R::baris($r2, 21333);
check('koreksi turun: jumlah = total baru', jumlah($b2), 21333.0);
check('koreksi turun: ket = alasan', $b2[3]['ket'], 'sudah lapor lisan');

// ── Koreksi yang tak mengubah angka (cuma ganti status/jam) -> tak ada entri
check('koreksi selisih 0 diabaikan', R::tambah($r, 'koreksi', 41333.33 - 41333.33, null, 'ganti jam'), $r);

// ── Data lama (NULL)
check('null + total 0 -> kosong', R::baris(null, 0), []);
$b3 = R::baris(null, 20000);
check('null + total -> 1 baris belum terinci', [$b3[0]['j'], $b3[0]['n']], ['belum_terinci', 20000.0]);

// ── Data lama lalu dikoreksi naik 20.000 -> 30.000
$b4 = R::baris(R::tambah(null, 'koreksi', 10000, null, 'x'), 30000);
check('lama+koreksi: jumlah = total baru', jumlah($b4), 30000.0);
check('lama+koreksi: sisa belum terinci 20rb', end($b4)['n'], 20000.0);

// ── Pecahan per menit: 3 x 333,33 vs total 1000 -> tak ada baris sisa
$r5 = R::tambah(R::tambah(R::tambah(null, 'telat_pagi', 333.33, 1), 'telat_siang', 333.33, 1), 'telat_siang', 333.33, 1);
check('pecahan: tak ada sisa palsu', count(R::baris($r5, 1000)), 3);

// ── Ringkasan bulan untuk slip
$bulan = [
    (object) ['rincian_potongan' => $r, 'potongan_telat' => 41333.33],
    (object) ['rincian_potongan' => R::tambah(null, 'telat_pagi', 5000, 15), 'potongan_telat' => 5000],
    (object) ['rincian_potongan' => null, 'potongan_telat' => 20000],
    (object) ['rincian_potongan' => null, 'potongan_telat' => 0],
];
$s = R::ringkasBulan($bulan);
check('ringkas telat pagi', $s['telat_pagi'], ['x' => 2, 'n' => 6333.33]);
check('ringkas lupa progress', $s['lupa_progress'], ['x' => 1, 'n' => 20000.0]);
check('ringkas belum terinci', $s['belum_terinci'], ['x' => 1, 'n' => 20000.0]);
check('ringkas: jumlah = total bulan', round(array_sum(array_column($s, 'n')), 2), 66333.33);

exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php tests/penggajian/test_rincian_potongan.php`
Expected: fatal error `Failed opening required ... RincianPotongan.php`.

- [ ] **Step 3: Implementasi minimal**

`app/Services/RincianPotongan.php`:

```php
<?php
// FILE: app/Services/RincianPotongan.php
//
// Rincian asal-usul absensi.potongan_telat — kolom itu menampung telat pagi, telat
// absen siang, denda Lapor Progress, denda tidak absen Kembali Kerja, dan koreksi
// Owner sekaligus (spec 2026-09-30-rincian-potongan-design.md).
//
// MURNI, tanpa DB. Ini PENJELASAN saja: potongan_telat tetap patokan uang. Selisih
// total vs jumlah rincian (data sebelum fitur, perbaikan SQL manual) tampil sebagai
// baris "Belum terinci" — jadi jumlah yang tampil selalu sama dengan total.

namespace App\Services;

class RincianPotongan
{
    const LABEL = [
        'telat_pagi'    => 'Telat masuk',
        'telat_siang'   => 'Telat absen siang',
        'lupa_progress' => 'Lupa Lapor Progress',
        'skip_siang'    => 'Tidak absen Kembali Kerja',
        'koreksi'       => 'Koreksi Owner',
        'belum_terinci' => 'Belum terinci (data lama)',
    ];

    public static function tambah(?array $rincian, string $jenis, float $nominal, ?int $menit = null, ?string $alasan = null): array
    {
        $rincian = $rincian ?? [];
        $n = round($nominal, 2);
        if ($n == 0.0) return $rincian;

        $e = ['j' => $jenis, 'n' => $n];
        if ($menit !== null) $e['m'] = $menit;
        if ($alasan !== null && $alasan !== '') $e['a'] = $alasan;
        $rincian[] = $e;
        return $rincian;
    }

    public static function baris(?array $rincian, float $total): array
    {
        $out = [];
        $sum = 0.0;
        foreach ($rincian ?? [] as $e) {
            $sum  += $e['n'];
            $out[] = [
                'j'     => $e['j'],
                'label' => self::LABEL[$e['j']] ?? $e['j'],
                'n'     => (float) $e['n'],
                'ket'   => isset($e['m']) ? $e['m'] . ' menit' : ($e['a'] ?? null),
            ];
        }
        $sisa = round($total - $sum, 2);
        if (abs($sisa) >= 1) {
            $out[] = ['j' => 'belum_terinci', 'label' => self::LABEL['belum_terinci'], 'n' => $sisa, 'ket' => null];
        }
        return $out;
    }

    public static function ringkasBulan(iterable $absensi): array
    {
        $r = [];
        foreach ($absensi as $a) {
            foreach (self::baris($a->rincian_potongan ?? null, (float) ($a->potongan_telat ?? 0)) as $b) {
                $r[$b['j']]['x'] = ($r[$b['j']]['x'] ?? 0) + 1;
                $r[$b['j']]['n'] = round(($r[$b['j']]['n'] ?? 0) + $b['n'], 2);
            }
        }
        return $r;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php tests/penggajian/test_rincian_potongan.php`
Expected: semua baris `PASS`, exit 0.

- [ ] **Step 5: Daftarkan di manifest**

Tambah ke array `tests` di `tests/guardrail/manifest.json` (ikuti format entri lain):

```json
    {
      "path": "tests/penggajian/test_rincian_potongan.php",
      "runner": "php",
      "requires_db": false,
      "manual": false
    },
```

Run: `php scripts/canopi-check`
Expected: lulus (test baru ikut jalan).

- [ ] **Step 6: Commit**

```bash
git add app/Services/RincianPotongan.php tests/penggajian/test_rincian_potongan.php tests/guardrail/manifest.json
git commit -m "feat(absensi): kelas murni RincianPotongan + test"
```

---

### Task 2: Skema — SQL manual, migration, model

**Files:**
- Create: `docs/sql/2026-09-30-rincian-potongan.sql`
- Create: `database/migrations/2026_09_30_000001_add_rincian_potongan.php`
- Modify: `app/Models/Absensi.php` (`$fillable`, `$casts`)
- Modify: `app/Models/SlipGaji.php` (`$fillable`, `$casts`)

**Interfaces:**
- Produces: `$absensi->rincian_potongan` dan `$slip->rincian_potongan` terbaca sebagai `?array`.

- [ ] **Step 1: SQL idempotent**

`docs/sql/2026-09-30-rincian-potongan.sql`:

```sql
-- =====================================================================
-- RINCIAN POTONGAN — SQL PRODUCTION (30 September 2026)
--
-- WAJIB dijalankan di phpMyAdmin production SEBELUM push kode fitur ini.
-- Kalau kode naik duluan: absen masuk, denda checkpoint, absen siang, dan
-- koreksi akan error "Unknown column rincian_potongan".
--
-- Cara: klik nama database di sidebar kiri -> tab SQL -> tempel SELURUH isi
-- file -> jalankan. Aman diulang (IF NOT EXISTS).
-- Hasil 2 SHOW COLUMNS di bawah HARUS masing-masing 1 baris. Kalau kosong,
-- JANGAN push.
-- =====================================================================

ALTER TABLE `absensi`   ADD COLUMN IF NOT EXISTS `rincian_potongan` TEXT NULL AFTER `potongan_telat`;
ALTER TABLE `slip_gaji` ADD COLUMN IF NOT EXISTS `rincian_potongan` TEXT NULL AFTER `potongan_telat`;

SHOW COLUMNS FROM `absensi`   LIKE 'rincian_potongan';
SHOW COLUMNS FROM `slip_gaji` LIKE 'rincian_potongan';
```

- [ ] **Step 2: Migration padanan (dijaga hasTable/hasColumn, pola migrasi 2026_08_15)**

`database/migrations/2026_09_30_000001_add_rincian_potongan.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Production dipasang lewat docs/sql/2026-09-30-rincian-potongan.sql (auto-deploy
// tidak menjalankan migration) — penjaga hasColumn mencegah "column already exists".
return new class extends Migration
{
    public function up(): void
    {
        foreach (['absensi', 'slip_gaji'] as $t) {
            if (!Schema::hasTable($t) || Schema::hasColumn($t, 'rincian_potongan')) continue;
            Schema::table($t, fn (Blueprint $table) => $table->text('rincian_potongan')->nullable()->after('potongan_telat'));
        }
    }

    public function down(): void
    {
        foreach (['absensi', 'slip_gaji'] as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'rincian_potongan')) continue;
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('rincian_potongan'));
        }
    }
};
```

- [ ] **Step 3: Model**

`app/Models/Absensi.php` — di `$fillable`, setelah `'kerja_hari_libur','upah_hari_libur',` tambah:

```php
        // Rincian asal potongan_telat (30 September) — lihat App\Services\RincianPotongan
        'rincian_potongan',
```

dan di `$casts` tambah `'rincian_potongan' => 'array',`.

`app/Models/SlipGaji.php` — di `$fillable` setelah `'potongan_telat', 'potongan_kasbon', 'potongan_insidental',` tambah `'rincian_potongan',`; di `$casts` tambah `'rincian_potongan' => 'array',`.

- [ ] **Step 4: Verifikasi**

Run: `php -l app/Models/Absensi.php && php -l app/Models/SlipGaji.php && php -l database/migrations/2026_09_30_000001_add_rincian_potongan.php && php scripts/canopi-check`
Expected: `No syntax errors` ×3, canopi-check lulus.

- [ ] **Step 5: Commit (JANGAN push)**

```bash
git add docs/sql/2026-09-30-rincian-potongan.sql database/migrations/2026_09_30_000001_add_rincian_potongan.php app/Models/Absensi.php app/Models/SlipGaji.php
git commit -m "feat(absensi): kolom rincian_potongan (SQL manual + migration + cast)"
```

---

### Task 3: Titik tulis di `AbsensiController`

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (use + 5 array update)

**Interfaces:**
- Consumes: `RincianPotongan::tambah(...)` (Task 1), cast `rincian_potongan` array (Task 2).

- [ ] **Step 1: Import**

Setelah `use App\Services\R2Service;` tambah:

```php
use App\Services\RincianPotongan;
```

- [ ] **Step 2: Denda Lapor Progress (`index()`, blok `potongan_progress_dicatat`)**

Di array `$absenHariIni->update([...])` yang berisi `'potongan_progress_dicatat' => true,` tambah:

```php
                'rincian_potongan'          => RincianPotongan::tambah($absenHariIni->rincian_potongan, 'lupa_progress', $potongan),
```

- [ ] **Step 3: Denda skip siang (`index()`, blok `potongan_siang_dicatat`)**

Di array update yang berisi `'potongan_siang_dicatat' => true,` (blok "Checkpoint 2") tambah:

```php
                'rincian_potongan'       => RincianPotongan::tambah($absenHariIni->rincian_potongan, 'skip_siang', $potongan),
```

- [ ] **Step 4: Absen masuk (`updateOrCreate`, `'potongan_telat' => $potongan`)**

Semantik TIMPA (total juga ditimpa). Setelah `'potongan_telat'  => $potongan,` tambah:

```php
                'rincian_potongan' => RincianPotongan::tambah(null, 'telat_pagi', $potongan, $menitTelat),
```

- [ ] **Step 5: Telat absen siang (`kembaliKerja`, array dgn `'potongan_siang_dicatat'  => true,`)**

Setelah `'potongan_telat'          => ($absen->potongan_telat??0) + $potongan,` tambah:

```php
            'rincian_potongan'        => RincianPotongan::tambah($absen->rincian_potongan, 'telat_siang', $potongan, $menitTelat),
```

- [ ] **Step 6: Koreksi Owner (`koreksi()`, `$absen->update([...])`)**

Array dievaluasi SEBELUM update, jadi `$absen->potongan_telat` masih nilai lama. Setelah `'potongan_telat'      => $potonganTelat,` tambah:

```php
            // Selisih dari TOTAL lama -> jumlah rincian tetap = total baru. Selisih 0 (cuma
            // ganti status/jam) tidak menambah entri.
            'rincian_potongan'    => RincianPotongan::tambah($absen->rincian_potongan, 'koreksi', $potonganTelat - (float) ($absen->potongan_telat ?? 0), null, $request->alasan),
```

- [ ] **Step 7: Verifikasi tak ada titik tulis terlewat**

Run: `grep -n "'potongan_telat'" app/Http/Controllers/AbsensiController.php`
Expected: tiap baris yang MENULIS (`=> $potongan`, `+ $potongan`, `=> $potonganTelat`) punya baris `rincian_potongan` di array yang sama. Baris lain (validasi `'potongan_telat' => 'nullable|...'`) boleh tanpa.

Run: `php -l app/Http/Controllers/AbsensiController.php && php tests/penggajian/test_rincian_potongan.php && php scripts/canopi-check`
Expected: lulus semua.

- [ ] **Step 8: Commit (JANGAN push)**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "feat(absensi): catat rincian di 4 titik potongan + koreksi Owner"
```

---

### Task 4: Slip gaji — simpan ringkasan & tampilkan

**Files:**
- Modify: `app/Services/GajiService.php` (`generateGajiBulanan`, `SlipGaji::create`)
- Modify: `resources/views/penggajian/slip.blade.php` (di bawah baris "Potongan Telat")

**Interfaces:**
- Consumes: `RincianPotongan::ringkasBulan`, `RincianPotongan::LABEL`, cast slip (Task 2).

- [ ] **Step 1: Simpan ringkasan di slip**

`GajiService::generateGajiBulanan`, di `SlipGaji::create([...])` setelah `'potongan_telat'        => $potonganTelat,` tambah:

```php
            // Penjelasan saja (dibekukan bersama slip) — angka tetap dari potongan_telat.
            'rincian_potongan'      => RincianPotongan::ringkasBulan($absensi),
```

(`GajiService` ada di namespace `App\Services`, tak perlu `use`.) `$absensi` di sini = absensi SEBULAN yang juga dipakai `$potonganTelat` — pastikan dengan membaca baris `$absensi = Absensi::where(...)` di fungsi itu; JANGAN pakai variabel lain.

- [ ] **Step 2: Tampilkan di slip**

`resources/views/penggajian/slip.blade.php`, tepat setelah baris `<div class="info-row"><span class="info-label">Potongan Telat</span>...</div>` (masih di dalam `@if($slip->potongan_telat > 0)`) tambah:

```blade
    @foreach(\App\Services\RincianPotongan::LABEL as $j => $label)
    @if(abs($slip->rincian_potongan[$j]['n'] ?? 0) >= 1)
    <div class="info-row" style="font-size:12px;padding-left:14px;"><span class="info-label">&middot; {{ $label }} ({{ $slip->rincian_potongan[$j]['x'] }}x)</span><span class="info-value" style="color:#94a3b8;">Rp {{ number_format($slip->rincian_potongan[$j]['n'],0,',','.') }}</span></div>
    @endif
    @endforeach
```

Slip lama (`rincian_potongan` null) → `?? 0` → tak tampil apa-apa. JANGAN tambah form/aksi apa pun di file ini (test keamanan menolaknya).

- [ ] **Step 3: Verifikasi**

Run: `php -l app/Services/GajiService.php && php scripts/canopi-check --full`
Expected: lulus (termasuk kompilasi Blade & test keamanan slip).

- [ ] **Step 4: Commit (JANGAN push)**

```bash
git add app/Services/GajiService.php resources/views/penggajian/slip.blade.php
git commit -m "feat(gaji): slip menyimpan & menampilkan rincian potongan per jenis"
```

---

### Task 5: Riwayat karyawan & rekap harian Owner

**Files:**
- Create: `resources/views/absensi/_rincian-potongan.blade.php`
- Modify: `resources/views/absensi/index.blade.php` (riwayat per hari)
- Modify: `resources/views/absensi/rekap.blade.php` (kolom status per karyawan)

**Interfaces:**
- Consumes: `RincianPotongan::baris(?array, float)` (Task 1). Partial menerima `$rincian` (?array) dan `$total` (float).

Catatan deviasi spec: spec menyebut "modal Koreksi"; rincian cukup ditampilkan di baris rekap yang sama dengan tombol Koreksi (modal diisi via argumen JS, menambah rincian ke sana = kode JS baru tanpa manfaat tambahan). Spec diperbarui di commit ini.

- [ ] **Step 1: Partial**

`resources/views/absensi/_rincian-potongan.blade.php`:

```blade
{{-- Rincian asal potongan_telat — lihat App\Services\RincianPotongan. Butuh $rincian, $total. --}}
<div style="font-size:10px;color:#94a3b8;margin-top:4px;line-height:1.5;text-align:left;">
    @foreach(\App\Services\RincianPotongan::baris($rincian, $total) as $b)
    <div>{{ $b['label'] }}@if($b['ket']) ({{ $b['ket'] }})@endif: Rp{{ number_format($b['n'],0,',','.') }}</div>
    @endforeach
</div>
```

- [ ] **Step 2: Riwayat karyawan**

`resources/views/absensi/index.blade.php`, di blok `{{-- Info --}}`, tepat setelah `</div>` yang menutup `<div style="font-size:12px;color:#94A3B8;">` (baris jam Masuk/Siang/Pulang / "Tidak ada catatan") tambah:

```blade
                @if(($r->potongan_telat ?? 0) > 0)
                    @include('absensi._rincian-potongan', ['rincian' => $r->rincian_potongan, 'total' => (float) $r->potongan_telat])
                @endif
```

- [ ] **Step 3: Rekap harian Owner**

`resources/views/absensi/rekap.blade.php`, di sel status: setelah blok `@if($absen->dikoreksi ?? false) ... @endif` (yang berisi "dikoreksi"), masih di dalam `@if($absen)`, tambah:

```blade
                            @if(($absen->potongan_telat ?? 0) > 0)
                                @include('absensi._rincian-potongan', ['rincian' => $absen->rincian_potongan, 'total' => (float) $absen->potongan_telat])
                            @endif
```

- [ ] **Step 4: Perbarui spec**

Di `docs/superpowers/specs/2026-09-30-rincian-potongan-design.md` bagian Tampilan, ganti baris `absensi/rekap.blade.php` rekap harian + modal Koreksi: daftar `baris()`.` menjadi:

```
- `absensi/rekap.blade.php` rekap harian: daftar `baris()` di sel status, sebaris
  dengan tombol Koreksi (modal Koreksi tak diubah).
```

- [ ] **Step 5: Verifikasi**

Run: `php scripts/canopi-check --full`
Expected: lulus (kompilasi Blade).

- [ ] **Step 6: Commit (JANGAN push)**

```bash
git add resources/views/absensi/_rincian-potongan.blade.php resources/views/absensi/index.blade.php resources/views/absensi/rekap.blade.php docs/superpowers/specs/2026-09-30-rincian-potongan-design.md
git commit -m "feat(absensi): tampilkan rincian potongan di riwayat & rekap harian"
```

---

### Task 6: Gate SQL → push → verifikasi live → status

**Files:**
- Modify: `CLAUDE.md` (Status Terkini / Utang aktif)

- [ ] **Step 1: Serahkan SQL ke Elvan & TUNGGU**

Minta Elvan menjalankan `docs/sql/2026-09-30-rincian-potongan.sql` di phpMyAdmin dan menempel hasil 2 `SHOW COLUMNS`. Lanjut HANYA kalau keduanya 1 baris. Kalau kosong/error: STOP, jangan push.

- [ ] **Step 2: Push**

```bash
git pull --rebase && php scripts/canopi-check --full && git push
```

- [ ] **Step 3: Verifikasi deploy nyata (bukan cuma Actions hijau)**

Run: `gh run list --limit 2` sampai deploy selesai, lalu bandingkan satu file publik:
`curl -s https://app.kanopibsd.co.id/<path file statis yang berubah, bila ada>` — kalau tak ada file publik yang berubah, minta Elvan buka riwayat absensi & 1 slip draft (Hitung Ulang) dan konfirmasi rincian muncul. Kalau deploy merah karena FTP: tunggu 20-30 menit, re-trigger (lihat CLAUDE.md #2b).

- [ ] **Step 4: Update CLAUDE.md**

Di "Utang aktif", tambah butir ringkas:
- Rincian potongan LIVE (tanggal), spec/plan path.
- Data sebelum 30 Sep tampil "Belum terinci (data lama)" — disengaja.
- Skrip SQL perbaikan potongan ke depan WAJIB ikut `SET rincian_potongan = NULL` saat menolkan `potongan_telat` (kalau tidak, muncul baris "Belum terinci" negatif).
- Temuan terbuka: denda Lapor Progress/skip siang hanya tercatat saat karyawan membuka halaman Absensi (tak ada cron) — keputusan kebijakan Elvan.
- Checklist validasi Elvan: (1) riwayat absen hari ini menampilkan rincian telat; (2) rekap harian Owner menampilkan rincian; (3) Koreksi angka potongan → muncul entri "Koreksi Owner"; (4) Hitung Ulang slip draft → ringkasan per jenis di bawah Potongan Telat.

```bash
git add CLAUDE.md && git commit -m "docs: status rincian potongan" && git push
```
