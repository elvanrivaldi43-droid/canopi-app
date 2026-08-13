# Libur Nasional / Libur Bersama Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Owner bisa input kalender libur bersama (Lebaran, Tahun Baru, 17 Agustus, dll) yang berlaku ke SEMUA karyawan sekaligus, lengkap dengan pengecualian per-karyawan per-tanggal ("piket") buat yang tetap harus kerja. Otomatis kecover di 4 titik yang selama ini menganggap tiap hari adalah hari kerja normal: `cron-alpha.php` (auto-Alpha), `cron-kode-absen.php` (kirim kode absen), `GajiService::hitungHariKerja()` (dasar persen kehadiran KPI), `ProfilController` (tampilan hari kerja bulan berjalan).

**Architecture:** 2 tabel baru (`libur_nasional`, `libur_nasional_piket`) diintegrasikan lewat `LiburService` yang SUDAH ADA — bukan jalur baru. Libur nasional "menyamar" jadi override tambahan berbentuk sama persis dengan override individual yang sudah ada (`['tanggal'=>..., 'jenis'=>'tambah']`), ditaruh PALING DEPAN di array overrides sebelum override pribadi karyawan. `cocokLiburPada()`/`hitungHariKerjaPada()` (logic murni, 25 assertion sudah lulus dari fitur sebelumnya) TIDAK diubah sama sekali — cuma nambah 1 method pure baru (`expandLiburNasional`, pola sama `expandTukar`) dan 1 wrapper database baru (`ambilLiburNasional`), lalu di-merge di `isLibur()`/`hitungHariKerja()`.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Blade (pola dark-theme standalone), Carbon, vanilla JS (kalender klik-tanggal, tanpa library eksternal).

## Global Constraints

- Level 1 (Owner) tidak pernah absen — dikecualikan dari daftar karyawan yang bisa dipilih buat piket (pola sudah ada: `User::where('level', '!=', 1)`).
- Kelola libur nasional (tambah/hapus/atur piket) HANYA level 1 (Owner) — middleware `level:1` eksplisit di route. Ini BEDA dari fitur jadwal-libur individual yang levelnya 1&3 (Owner+Mandor) — spec sudah kunci khusus Owner buat fitur ini (lihat `docs/superpowers/specs/2026-08-13-libur-nasional-design.md` keputusan #2).
- Melihat kalender (tanpa kelola) terbuka buat semua level yang login — link ditambah ke KEDUA sidebar (Owner & Pipeline).
- Reuse `TelegramService::kirim()` yang sudah ada — jangan bikin jalur kirim Telegram baru.
- TIDAK mengubah `LiburService::cocokLiburPada()`/`hitungHariKerjaPada()` — logic pure existing, sudah teruji 25 assertion di `tests/jadwal-libur/test_libur_service.php`, integrasi murni lewat override array tambahan yang di-merge SEBELUM dilempar ke method itu.
- 4 titik konsumen existing (`public/cron-alpha.php:62`, `public/cron-kode-absen.php:48`, `app/Services/GajiService.php:112`, `app/Http/Controllers/ProfilController.php:34`) TIDAK PERLU disentuh — mereka manggil `LiburService::isLibur()`/`hitungHariKerja()` yang sama, cuma hasilnya sekarang lebih lengkap.
- Migration file dibuat untuk kelengkapan repo, TAPI deployment sebenarnya ke production lewat SQL manual idempotent di phpMyAdmin (lihat catatan akhir Task 1) — proyek ini shared hosting tanpa SSH/artisan di server, `php artisan migrate` TIDAK bisa dijalankan di production.
- VPS pengembangan ini tidak punya koneksi database — semua test yang butuh DB TIDAK bisa dijalankan otomatis di sini. Test yang bisa dijalankan: `php -l` (lint syntax) dan test standalone untuk logic murni (pola `tests/jadwal-libur/*.php`). Bagian yang butuh DB/UI/Telegram diverifikasi manual oleh Elvan di production.

---

### Task 1: Migrasi database & model

**Files:**
- Create: `database/migrations/2026_08_13_000001_create_libur_nasional_table.php`
- Create: `database/migrations/2026_08_13_000002_create_libur_nasional_piket_table.php`
- Create: `app/Models/LiburNasional.php`
- Create: `app/Models/LiburNasionalPiket.php`

**Interfaces:**
- Produces: tabel `libur_nasional` (`id`, `nama`, `tanggal_mulai`, `tanggal_selesai`, `dibuat_oleh`, timestamps). Tabel `libur_nasional_piket` (`id`, `libur_nasional_id`, `user_id`, `tanggal`, timestamps). Model `App\Models\LiburNasional` — fillable `nama`,`tanggal_mulai`,`tanggal_selesai`,`dibuat_oleh`; cast `tanggal_mulai`/`tanggal_selesai` => `date`; relasi `dibuatOleh()`, `piket()` (hasMany). Model `App\Models\LiburNasionalPiket` — fillable `libur_nasional_id`,`user_id`,`tanggal`; cast `tanggal` => `date`; relasi `liburNasional()`, `user()`. Dipakai Task 2 (LiburService) dan Task 3 (Controller).

- [ ] **Step 1: Buat migration tabel `libur_nasional`**

```php
<?php
// FILE: database/migrations/2026_08_13_000001_create_libur_nasional_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libur_nasional', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libur_nasional');
    }
};
```

- [ ] **Step 2: Buat migration tabel `libur_nasional_piket`**

```php
<?php
// FILE: database/migrations/2026_08_13_000002_create_libur_nasional_piket_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libur_nasional_piket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('libur_nasional_id')->constrained('libur_nasional')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libur_nasional_piket');
    }
};
```

- [ ] **Step 3: Buat model `LiburNasional`**

```php
<?php
// FILE: app/Models/LiburNasional.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburNasional extends Model
{
    protected $table = 'libur_nasional';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'dibuat_oleh'];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function piket()
    {
        return $this->hasMany(LiburNasionalPiket::class);
    }

    public function labelRentang(): string
    {
        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->translatedFormat('d F Y');
        }
        return $this->tanggal_mulai->translatedFormat('d F').' - '.$this->tanggal_selesai->translatedFormat('d F Y');
    }
}
```

- [ ] **Step 4: Buat model `LiburNasionalPiket`**

```php
<?php
// FILE: app/Models/LiburNasionalPiket.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburNasionalPiket extends Model
{
    protected $table = 'libur_nasional_piket';

    protected $fillable = ['libur_nasional_id', 'user_id', 'tanggal'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function liburNasional()
    {
        return $this->belongsTo(LiburNasional::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Lint semua file baru (tidak ada koneksi DB di VPS ini, jadi migration tidak bisa dijalankan — cukup pastikan tidak ada syntax error)**

Run:
```bash
php -l database/migrations/2026_08_13_000001_create_libur_nasional_table.php
php -l database/migrations/2026_08_13_000002_create_libur_nasional_piket_table.php
php -l app/Models/LiburNasional.php
php -l app/Models/LiburNasionalPiket.php
```
Expected: `No syntax errors detected` untuk keempat file.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_13_000001_create_libur_nasional_table.php database/migrations/2026_08_13_000002_create_libur_nasional_piket_table.php app/Models/LiburNasional.php app/Models/LiburNasionalPiket.php
git commit -m "feat: migrasi & model libur nasional + piket"
```

**Catatan buat sesi deploy nanti (bukan step, jangan dieksekusi sekarang):** karena production tidak bisa `php artisan migrate`, sebelum push ke `main`, siapkan SQL idempotent buat Elvan jalankan manual di phpMyAdmin:
```sql
CREATE TABLE IF NOT EXISTS libur_nasional (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    dibuat_oleh BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS libur_nasional_piket (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libur_nasional_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (libur_nasional_id) REFERENCES libur_nasional(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
Sama seperti SQL fitur-fitur sebelumnya — jangan push ke `main` sebelum SQL ini dikonfirmasi jalan di production.

---

### Task 2: `LiburService` — integrasi libur nasional (pure + wrapper)

**Files:**
- Modify: `app/Services/LiburService.php`
- Create: `tests/libur-nasional/test_libur_nasional.php`

**Interfaces:**
- Consumes: `App\Models\LiburNasional`, `App\Models\LiburNasionalPiket` (Task 1).
- Produces: `LiburService::expandLiburNasional(string $mulai, string $selesai, array $piketTanggal): array` — logic murni, `$piketTanggal` adalah array string tanggal `Y-m-d` yang DIKECUALIKAN (karyawan piket, jadi BUKAN hari libur buat dia). Return array override berbentuk `['tanggal'=>'Y-m-d','jenis'=>'tambah']` buat tiap tanggal dalam rentang yang TIDAK ada di `$piketTanggal`. `isLibur()` dan `hitungHariKerja()` (wrapper, sudah ada) sekarang otomatis ikut mempertimbangkan libur nasional — dipakai Task 3 (Controller, tidak langsung, lewat cron/GajiService/ProfilController yang sudah ada).

- [ ] **Step 1: Tulis test standalone dulu (logic murni, belum ada implementasinya)**

```php
<?php
// FILE: tests/libur-nasional/test_libur_nasional.php
// Jalankan: php tests/libur-nasional/test_libur_nasional.php
require __DIR__ . '/../../vendor/autoload.php';

use App\Services\LiburService;
use Carbon\Carbon;

$svc = new LiburService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── expandLiburNasional ──────────────────────────────────────
$check('rentang 1 hari, tanpa piket -> 1 override tambah',
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', []),
    [['tanggal' => '2026-08-17', 'jenis' => 'tambah']]);

$check('rentang 3 hari, tanpa piket -> 3 override tambah berurutan',
    $svc->expandLiburNasional('2026-08-17', '2026-08-19', []),
    [
        ['tanggal' => '2026-08-17', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-18', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-19', 'jenis' => 'tambah'],
    ]);

$check('rentang 3 hari, 1 tanggal di-piket -> tanggal itu TIDAK ada di hasil (bukan libur buat dia)',
    $svc->expandLiburNasional('2026-08-17', '2026-08-19', ['2026-08-18']),
    [
        ['tanggal' => '2026-08-17', 'jenis' => 'tambah'],
        ['tanggal' => '2026-08-19', 'jenis' => 'tambah'],
    ]);

$check('semua tanggal di-piket -> hasil kosong',
    $svc->expandLiburNasional('2026-08-17', '2026-08-18', ['2026-08-17', '2026-08-18']),
    []);

// ── Precedence: libur nasional menang lawan override pribadi kalau tanggal sama ──
// cocokLiburPada() return di override PERTAMA yang cocok tanggalnya, jadi urutan array menentukan.
$liburNasionalDulu = array_merge(
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', []), // override nasional 'tambah'
    [['tanggal' => '2026-08-17', 'jenis' => 'batal']]           // override pribadi 'batal' (karyawan minta tetap kerja hari itu)
);
$check('libur nasional (tambah) ditaruh duluan -> menang lawan override pribadi (batal) di tanggal sama -> true (tetap libur)',
    $svc->cocokLiburPada(null, $liburNasionalDulu, Carbon::create(2026, 8, 17)), true);

$karyawanPiket = array_merge(
    $svc->expandLiburNasional('2026-08-17', '2026-08-17', ['2026-08-17']), // kosong, karena di-piket
    [] // tanpa override pribadi -> fallback ke default
);
$check('karyawan di-piket tanggal itu -> tidak ada override nasional yang di-generate -> fallback ke default (null -> false, kerja normal)',
    $svc->cocokLiburPada(null, $karyawanPiket, Carbon::create(2026, 8, 17)), false);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal (method belum ada)**

Run: `php tests/libur-nasional/test_libur_nasional.php`
Expected: Fatal error `Call to undefined method App\Services\LiburService::expandLiburNasional()`.

- [ ] **Step 3: Tambah `use` statement buat model baru**

Di `app/Services/LiburService.php`, ubah:

```php
namespace App\Services;

use App\Models\JadwalLibur;
use App\Models\User;
use Carbon\Carbon;
```

jadi:

```php
namespace App\Services;

use App\Models\JadwalLibur;
use App\Models\LiburNasional;
use App\Models\LiburNasionalPiket;
use App\Models\User;
use Carbon\Carbon;
```

- [ ] **Step 4: Tambah method pure `expandLiburNasional()`**

Tambahkan method baru PERSIS SETELAH `expandTukar()` yang sudah ada:

```php
    public function expandTukar(array $row): array
    {
        if ($row['jenis'] === 'tukar') {
            return [
                ['tanggal' => $row['tanggal'], 'jenis' => 'batal'],
                ['tanggal' => $row['tanggal_baru'], 'jenis' => 'tambah'],
            ];
        }
        return [['tanggal' => $row['tanggal'], 'jenis' => $row['jenis']]];
    }

    // $piketTanggal: array string 'Y-m-d' — tanggal dalam rentang ini yang DIKECUALIKAN (karyawan piket).
    public function expandLiburNasional(string $mulai, string $selesai, array $piketTanggal): array
    {
        $hasil = [];
        $cur   = Carbon::parse($mulai);
        $akhir = Carbon::parse($selesai);
        while ($cur->lte($akhir)) {
            $tglStr = $cur->format('Y-m-d');
            if (!in_array($tglStr, $piketTanggal, true)) {
                $hasil[] = ['tanggal' => $tglStr, 'jenis' => 'tambah'];
            }
            $cur->addDay();
        }
        return $hasil;
    }
```

- [ ] **Step 5: Tambah wrapper database `ambilLiburNasional()`**

Tambahkan method baru PERSIS SETELAH `ambilOverride()` yang sudah ada (di akhir class, sebelum kurung kurawal penutup):

```php
    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->format('Y-m-d');
        $sampaiStr = $sampai->format('Y-m-d');

        $rows = JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($dariStr, $sampaiStr) {
                $q->whereBetween('tanggal', [$dariStr, $sampaiStr])
                  ->orWhereBetween('tanggal_baru', [$dariStr, $sampaiStr]);
            })
            ->get(['tanggal', 'tanggal_baru', 'jenis']);

        $overrides = [];
        foreach ($rows as $o) {
            $overrides = array_merge($overrides, $this->expandTukar([
                'tanggal'      => $o->tanggal->format('Y-m-d'),
                'tanggal_baru' => $o->tanggal_baru?->format('Y-m-d'),
                'jenis'        => $o->jenis,
            ]));
        }
        return $overrides;
    }

    private function ambilLiburNasional(User $user, Carbon $dari, Carbon $sampai): array
    {
        $liburs = LiburNasional::where('tanggal_mulai', '<=', $sampai->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $dari->format('Y-m-d'))
            ->get(['id', 'tanggal_mulai', 'tanggal_selesai']);

        $overrides = [];
        foreach ($liburs as $lb) {
            $piketTanggal = LiburNasionalPiket::where('libur_nasional_id', $lb->id)
                ->where('user_id', $user->id)
                ->get()
                ->map(fn($p) => $p->tanggal->format('Y-m-d'))
                ->toArray();

            $overrides = array_merge($overrides, $this->expandLiburNasional(
                $lb->tanggal_mulai->format('Y-m-d'),
                $lb->tanggal_selesai->format('Y-m-d'),
                $piketTanggal
            ));
        }
        return $overrides;
    }
```

- [ ] **Step 6: Merge libur nasional ke `isLibur()` dan `hitungHariKerja()` — nasional PALING DEPAN**

Ubah:

```php
    // Wrapper database — dipakai cron & GajiService.
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = $this->ambilOverride($user, $tanggal, $tanggal);
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun, ?int $sampaiHari = null): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $sampaiHari ? $awal->copy()->day($sampaiHari) : $awal->copy()->endOfMonth();
        $overrides = $this->ambilOverride($user, $awal, $akhir);
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun, $sampaiHari);
    }
```

jadi:

```php
    // Wrapper database — dipakai cron & GajiService.
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = array_merge(
            $this->ambilLiburNasional($user, $tanggal, $tanggal),
            $this->ambilOverride($user, $tanggal, $tanggal)
        );
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun, ?int $sampaiHari = null): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $sampaiHari ? $awal->copy()->day($sampaiHari) : $awal->copy()->endOfMonth();
        $overrides = array_merge(
            $this->ambilLiburNasional($user, $awal, $akhir),
            $this->ambilOverride($user, $awal, $akhir)
        );
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun, $sampaiHari);
    }
```

Kenapa `ambilLiburNasional(...)` ditaruh PERTAMA di `array_merge`: `cocokLiburPada()` (tidak diubah) return di override pertama yang cocok tanggalnya — jadi libur nasional otomatis menang lawan override pribadi kalau kebetulan bentrok tanggal, sesuai keputusan spec #5.

- [ ] **Step 7: Jalankan test baru, pastikan semua lulus**

Run: `php tests/libur-nasional/test_libur_nasional.php`
Expected: Semua baris `PASS`, diakhiri `=== SEMUA TES LULUS ===`.

- [ ] **Step 8: Jalankan ULANG test lama, pastikan TIDAK ada regresi**

Run: `php tests/jadwal-libur/test_libur_service.php`
Expected: Semua baris `PASS` seperti sebelumnya (25 assertion) — membuktikan perubahan Task 2 tidak merusak logic jadwal libur individual yang sudah ada.

- [ ] **Step 9: Lint file**

Run: `php -l app/Services/LiburService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 10: Commit**

```bash
git add app/Services/LiburService.php tests/libur-nasional/test_libur_nasional.php
git commit -m "feat: LiburService integrasi libur nasional (expandLiburNasional + ambilLiburNasional)"
```

---

### Task 3: `LiburNasionalController` + routes + notifikasi Telegram

**Files:**
- Create: `app/Http/Controllers/LiburNasionalController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Models\LiburNasional`, `App\Models\LiburNasionalPiket` (Task 1), `App\Services\TelegramService::kirim()` (existing).
- Produces: route `libur-nasional.index`, `libur-nasional.store`, `libur-nasional.destroy`, `libur-nasional.piket.store`, `libur-nasional.piket.destroy` — dipakai Task 4 (view) dan Task 5 (link nav).

- [ ] **Step 1: Buat `LiburNasionalController`**

```php
<?php
// FILE: app/Http/Controllers/LiburNasionalController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LiburNasional;
use App\Models\LiburNasionalPiket;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;

class LiburNasionalController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // KALENDER (semua level login — read-only kecuali Owner)
    // ═══════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $bulan = (int) ($request->query('bulan', now()->month));
        $tahun = (int) ($request->query('tahun', now()->year));

        $awal  = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        $liburBulanIni = LiburNasional::where('tanggal_mulai', '<=', $akhir->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $awal->format('Y-m-d'))
            ->orderBy('tanggal_mulai')
            ->get();

        $piketBulanIni = LiburNasionalPiket::whereIn('libur_nasional_id', $liburBulanIni->pluck('id'))
            ->with('user')
            ->get()
            ->groupBy(fn($p) => $p->tanggal->format('Y-m-d'));

        $liburSemua = LiburNasional::orderBy('tanggal_mulai', 'desc')->get();

        $karyawan = User::where('level', '!=', 1)
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name', 'jabatan']);

        return view('libur-nasional.index', compact(
            'bulan', 'tahun', 'awal', 'akhir', 'liburBulanIni', 'piketBulanIni', 'liburSemua', 'karyawan'
        ));
    }

    // ═══════════════════════════════════════════════════════════
    // TAMBAH LIBUR NASIONAL (Owner)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $request->validate([
            'nama'             => 'required|string|max:100',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $libur = LiburNasional::create([
            'nama'             => $request->nama,
            'tanggal_mulai'    => $request->tanggal_mulai,
            'tanggal_selesai'  => $request->tanggal_selesai,
            'dibuat_oleh'      => Auth::id(),
        ]);

        $this->broadcastLiburBaru($libur);

        return back()->with('success', "Libur nasional \"{$libur->nama}\" berhasil ditambahkan.");
    }

    // ═══════════════════════════════════════════════════════════
    // HAPUS LIBUR NASIONAL (Owner)
    // ═══════════════════════════════════════════════════════════

    public function destroy(LiburNasional $liburNasional)
    {
        $nama = $liburNasional->nama;
        $liburNasional->delete();

        return back()->with('success', "Libur nasional \"{$nama}\" dihapus.");
    }

    // ═══════════════════════════════════════════════════════════
    // TAMBAH PIKET (Owner)
    // ═══════════════════════════════════════════════════════════

    public function piketStore(Request $request, LiburNasional $liburNasional)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal' => 'required|date',
        ]);

        $tanggal = Carbon::parse($request->tanggal);
        if ($tanggal->lt($liburNasional->tanggal_mulai) || $tanggal->gt($liburNasional->tanggal_selesai)) {
            return back()->with('error', 'Tanggal piket harus dalam rentang libur nasional ini.');
        }

        $piket = LiburNasionalPiket::firstOrCreate([
            'libur_nasional_id' => $liburNasional->id,
            'user_id'           => $request->user_id,
            'tanggal'           => $request->tanggal,
        ]);

        $this->notifPiket($piket, $liburNasional);

        return back()->with('success', 'Piket berhasil ditambahkan.');
    }

    // ═══════════════════════════════════════════════════════════
    // BATALKAN PIKET (Owner)
    // ═══════════════════════════════════════════════════════════

    public function piketDestroy(LiburNasionalPiket $liburNasionalPiket)
    {
        $liburNasionalPiket->delete();

        return back()->with('success', 'Piket dibatalkan.');
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function broadcastLiburBaru(LiburNasional $libur): void
    {
        $penerima = User::whereNotNull('telegram_chat_id')->get();

        $pesan = "🎉 *LIBUR NASIONAL BARU*\n"
               . "{$libur->nama}\n"
               . "Tanggal: {$libur->labelRentang()}\n"
               . "---\n"
               . "Kode absen otomatis tidak dikirim di tanggal ini, kecuali kamu ditunjuk piket.";

        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id, $pesan);
        }
    }

    private function notifPiket(LiburNasionalPiket $piket, LiburNasional $libur): void
    {
        $piket->loadMissing('user');

        $pesan = "📌 *JADWAL PIKET*\n"
               . "Kamu piket tanggal {$piket->tanggal->translatedFormat('l, d F Y')}\n"
               . "({$libur->nama}) — tetap masuk kerja ya.";

        app(TelegramService::class)->kirim($piket->user->telegram_chat_id, $pesan);
    }
}
```

- [ ] **Step 2: Tambah routes**

Di `routes/web.php`, tambah import di bagian atas (dekat baris `use App\Http\Controllers\JadwalLiburController;`):

```php
use App\Http\Controllers\LiburNasionalController;
```

Lalu tambah blok route baru PERSIS SETELAH blok `// ─── JADWAL LIBUR KARYAWAN ─────...` yang sudah ada (setelah baris `});` penutup blok itu):

```php
// ─── LIBUR NASIONAL ─────────────────────────────────────────
Route::middleware('auth')->prefix('libur-nasional')->name('libur-nasional.')->group(function () {
    Route::get('/',                     [LiburNasionalController::class, 'index'])->name('index');
    Route::post('/',                    [LiburNasionalController::class, 'store'])->middleware('level:1')->name('store');
    Route::delete('/{liburNasional}',   [LiburNasionalController::class, 'destroy'])->middleware('level:1')->name('destroy');
    Route::post('/{liburNasional}/piket',       [LiburNasionalController::class, 'piketStore'])->middleware('level:1')->name('piket.store');
    Route::delete('/piket/{liburNasionalPiket}', [LiburNasionalController::class, 'piketDestroy'])->middleware('level:1')->name('piket.destroy');
});
```

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l app/Http/Controllers/LiburNasionalController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LiburNasionalController.php routes/web.php
git commit -m "feat: LiburNasionalController + routes kelola libur nasional & piket"
```

**Catatan verifikasi manual (setelah deploy, tidak bisa diuji dari VPS ini):** Owner tambah libur nasional lewat form (bisa langsung test lewat `curl -X POST` sementara Task 4 belum ada, atau tunggu Task 4 selesai) — pastikan broadcast Telegram nyampe ke karyawan yang connect. Tunjuk 1 karyawan piket, pastikan dia dapat notif personal terpisah.

---

### Task 4: View kalender (`libur-nasional/index.blade.php`)

**Files:**
- Create: `resources/views/libur-nasional/index.blade.php`

**Interfaces:**
- Consumes: route `libur-nasional.*` (Task 3), variable `$bulan`,`$tahun`,`$awal`,`$akhir`,`$liburBulanIni`,`$piketBulanIni`,`$liburSemua`,`$karyawan` (dari `LiburNasionalController::index()`). Method `LiburNasional::labelRentang()` (Task 1).

- [ ] **Step 1: Buat `resources/views/libur-nasional/index.blade.php`**

```php
{{-- FILE: resources/views/libur-nasional/index.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Libur Nasional')

@section('sidebar-menu')
    @if(auth()->user()->level == 1)
        @include('partials.sidebar-owner')
    @else
        @include('partials.sidebar-pipeline')
    @endif
@endsection

@section('bottom-nav')
    @include('partials.bottomnav')
@endsection

@php
    $isOwner = auth()->user()->level == 1;

    // Tanggal -> nama libur (buat highlight + cari libur_nasional_id pas diklik)
    $liburPerTanggal = [];
    foreach ($liburBulanIni as $lb) {
        $cur = $lb->tanggal_mulai->copy();
        while ($cur->lte($lb->tanggal_selesai)) {
            $liburPerTanggal[$cur->format('Y-m-d')] = ['id' => $lb->id, 'nama' => $lb->nama];
            $cur->addDay();
        }
    }

    $namaHari = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $mulaiMinggu = (clone $awal)->startOfWeek(\Carbon\Carbon::SUNDAY);
@endphp

@section('content')
<div style="max-width:900px;margin:0 auto;">

    @if(session('success'))
    <div style="padding:14px;border-radius:10px;background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#6ee7b7;margin-bottom:16px;font-size:13px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="padding:14px;border-radius:10px;background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#fca5a5;margin-bottom:16px;font-size:13px;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Navigasi bulan --}}
    <div class="stat-card" style="padding:14px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
        <a href="{{ route('libur-nasional.index', ['bulan'=>$bulan==1?12:$bulan-1,'tahun'=>$bulan==1?$tahun-1:$tahun]) }}" style="color:#94a3b8;text-decoration:none;font-size:18px;">←</a>
        <div style="font-weight:700;color:#fbbf24;font-size:15px;">{{ $awal->translatedFormat('F Y') }}</div>
        <a href="{{ route('libur-nasional.index', ['bulan'=>$bulan==12?1:$bulan+1,'tahun'=>$bulan==12?$tahun+1:$tahun]) }}" style="color:#94a3b8;text-decoration:none;font-size:18px;">→</a>
    </div>

    @if($isOwner)
    <button type="button" onclick="bukaTambahLibur()" style="width:100%;background:#fbbf24;color:#0f172a;border:none;border-radius:10px;padding:12px;font-weight:700;margin-bottom:16px;cursor:pointer;">
        + Tambah Libur Nasional
    </button>
    @endif

    {{-- Kalender grid --}}
    <div class="stat-card" style="padding:12px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px;">
            @foreach($namaHari as $nh)
            <div style="text-align:center;font-size:11px;color:#64748b;font-weight:700;">{{ $nh }}</div>
            @endforeach
        </div>
        <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">
            @php $cur = $mulaiMinggu->copy(); @endphp
            @while($cur->lte($akhir->copy()->endOfWeek(\Carbon\Carbon::SATURDAY)))
                @php
                    $tglStr = $cur->format('Y-m-d');
                    $diBulanIni = $cur->month === $bulan;
                    $info = $liburPerTanggal[$tglStr] ?? null;
                    $piketHariItu = $piketBulanIni[$tglStr] ?? collect();
                @endphp
                <div class="cal-day{{ $info ? ' cal-day-libur' : '' }}"
                     data-tanggal="{{ $tglStr }}"
                     data-libur-id="{{ $info['id'] ?? '' }}"
                     data-libur-nama="{{ $info['nama'] ?? '' }}"
                     style="min-height:52px;border-radius:8px;padding:6px;font-size:11px;cursor:{{ $isOwner ? 'pointer' : 'default' }};
                        opacity:{{ $diBulanIni ? 1 : 0.3 }};
                        background:{{ $info ? 'rgba(251,191,36,0.15)' : '#0f172a' }};
                        border:1px solid {{ $info ? '#fbbf24' : '#334155' }};"
                     @if($isOwner) onclick="klikTanggal(this)" @endif>
                    <div style="font-weight:700;color:{{ $info ? '#fbbf24' : '#e2e8f0' }};">{{ $cur->day }}</div>
                    @if($info)
                    <div style="color:#fbbf24;font-size:9px;line-height:1.2;margin-top:2px;">{{ \Illuminate\Support\Str::limit($info['nama'], 14) }}</div>
                    @endif
                    @if($piketHariItu->count())
                    <div style="color:#38bdf8;font-size:9px;margin-top:2px;">📌 {{ $piketHariItu->count() }} piket</div>
                    @endif
                </div>
                @php $cur->addDay(); @endphp
            @endwhile
        </div>
    </div>

    {{-- Daftar semua libur nasional (manajemen, tidak terikat bulan yang ditampilkan) --}}
    <div class="stat-card" style="padding:16px;">
        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">📋 Semua Libur Nasional</div>
        @forelse($liburSemua as $lb)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #1e293b;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#f1f5f9;">{{ $lb->nama }}</div>
                <div style="font-size:11px;color:#64748b;">{{ $lb->labelRentang() }}</div>
            </div>
            @if($isOwner)
            <form method="POST" action="{{ route('libur-nasional.destroy', $lb) }}" onsubmit="return confirm('Hapus libur nasional \'{{ $lb->nama }}\'? Semua data piket di dalamnya ikut terhapus.');">
                @csrf
                @method('DELETE')
                <button type="submit" style="background:transparent;border:1px solid #ef4444;color:#ef4444;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;">Hapus</button>
            </form>
            @endif
        </div>
        @empty
        <div style="color:#64748b;font-size:13px;text-align:center;padding:20px;">Belum ada libur nasional yang ditambahkan.</div>
        @endforelse
    </div>

</div>

@if($isOwner)
{{-- Modal Tambah Libur Nasional --}}
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:400px;">
        <div style="font-weight:700;color:#fbbf24;font-size:15px;margin-bottom:16px;">+ Tambah Libur Nasional</div>
        <form method="POST" action="{{ route('libur-nasional.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Nama Libur</label>
                <input type="text" name="nama" required placeholder="mis. Lebaran 2026"
                    style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" id="inputMulai" required
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" id="inputSelesai" required
                        style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#fbbf24;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:700;cursor:pointer;">💾 Simpan</button>
                <button type="button" onclick="tutupModal('modalTambah')" style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">Batal</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Kelola Piket --}}
<div id="modalPiket" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;width:100%;max-width:400px;">
        <div style="font-weight:700;color:#fbbf24;font-size:15px;margin-bottom:4px;">📌 Kelola Piket</div>
        <div id="piketTanggalLabel" style="color:#64748b;font-size:13px;margin-bottom:16px;"></div>

        <div id="piketListExisting" style="margin-bottom:16px;"></div>

        <form id="formPiketTambah" method="POST">
            @csrf
            <input type="hidden" name="tanggal" id="piketInputTanggal">
            <div style="margin-bottom:12px;">
                <label style="color:#94a3b8;font-size:12px;display:block;margin-bottom:6px;">Tunjuk Karyawan Piket</label>
                <select name="user_id" required style="background:#0f172a;border:1px solid #475569;color:#f1f5f9;border-radius:8px;padding:10px;width:100%;font-size:13px;">
                    <option value="">-- Pilih Karyawan --</option>
                    @foreach($karyawan as $k)
                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->jabatan }})</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" style="flex:1;background:#38bdf8;color:#0f172a;border:none;border-radius:8px;padding:10px;font-weight:700;cursor:pointer;">+ Tambah Piket</button>
                <button type="button" onclick="tutupModal('modalPiket')" style="flex:1;background:#334155;color:#e2e8f0;border:none;border-radius:8px;padding:10px;font-weight:600;cursor:pointer;">Tutup</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data piket per tanggal dikirim dari server (dipakai isi modal tanpa reload)
const PIKET_DATA = {
    @foreach($piketBulanIni as $tgl => $list)
    "{{ $tgl }}": [
        @foreach($list as $p)
        {id: {{ $p->id }}, nama: "{{ $p->user->name }}"},
        @endforeach
    ],
    @endforeach
};

let modeTambah = false;
let tglMulaiPilih = null;

function bukaTambahLibur() {
    modeTambah = true;
    tglMulaiPilih = null;
    document.querySelectorAll('.cal-day').forEach(el => el.style.outline = '2px dashed #38bdf8');
    alert('Mode tambah aktif: klik tanggal MULAI, lalu klik tanggal SELESAI di kalender.');
}

function klikTanggal(el) {
    const tgl = el.dataset.tanggal;

    if (modeTambah) {
        if (!tglMulaiPilih) {
            tglMulaiPilih = tgl;
            el.style.background = 'rgba(56,189,248,0.3)';
            return;
        }
        let mulai = tglMulaiPilih, selesai = tgl;
        if (selesai < mulai) { [mulai, selesai] = [selesai, mulai]; }

        document.getElementById('inputMulai').value = mulai;
        document.getElementById('inputSelesai').value = selesai;
        document.querySelectorAll('.cal-day').forEach(e => e.style.outline = '');
        modeTambah = false;
        tglMulaiPilih = null;
        document.getElementById('modalTambah').style.display = 'flex';
        return;
    }

    // Bukan mode tambah: kalau tanggal itu sudah termasuk libur nasional -> buka kelola piket
    const liburId = el.dataset.liburId;
    if (!liburId) return;

    document.getElementById('piketTanggalLabel').textContent = el.dataset.liburNama + ' — ' + tgl;
    document.getElementById('piketInputTanggal').value = tgl;
    document.getElementById('formPiketTambah').action = `/libur-nasional/${liburId}/piket`;

    const existing = PIKET_DATA[tgl] || [];
    const listEl = document.getElementById('piketListExisting');
    if (existing.length === 0) {
        listEl.innerHTML = '<div style="color:#64748b;font-size:12px;">Belum ada yang piket tanggal ini.</div>';
    } else {
        listEl.innerHTML = existing.map(p => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #334155;">
                <span style="font-size:13px;color:#f1f5f9;">${p.nama}</span>
                <form method="POST" action="/libur-nasional/piket/${p.id}" style="display:inline;">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" style="background:transparent;border:1px solid #ef4444;color:#ef4444;border-radius:6px;padding:2px 8px;font-size:11px;cursor:pointer;">Batal</button>
                </form>
            </div>
        `).join('');
    }

    document.getElementById('modalPiket').style.display = 'flex';
}

function tutupModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>
@endif
@endsection
```

- [ ] **Step 2: Lint file**

Run: `php -l resources/views/libur-nasional/index.blade.php`
Expected: `No syntax errors detected` (blade file berisi PHP valid, cek sintaks dasar tetap jalan meski bukan compile blade penuh).

- [ ] **Step 3: Commit**

```bash
git add resources/views/libur-nasional/index.blade.php
git commit -m "feat: halaman kalender libur nasional (klik-tanggal, kelola piket)"
```

**Catatan verifikasi manual (setelah deploy, tidak bisa diuji dari VPS ini — perlu browser beneran):**
- Owner klik "+ Tambah Libur Nasional" → klik tanggal mulai → klik tanggal selesai → modal muncul terisi otomatis → isi nama → simpan → tanggal ter-highlight di kalender.
- Klik tanggal yang sudah jadi libur nasional → modal Kelola Piket muncul, nama libur + tanggal benar.
- Tambah piket 1 karyawan → badge "📌 1 piket" muncul di kalender, karyawan itu dapat notif Telegram.
- Hapus piket → badge hilang.
- Hapus libur nasional dari daftar bawah → tanggal di kalender kembali biasa, data piket di dalamnya ikut hilang (cascade).
- Login sebagai karyawan biasa (bukan Owner) → buka halaman ini → kalender kelihatan, TIDAK ada tombol "+ Tambah Libur Nasional" atau kemampuan klik tanggal.

---

### Task 5: Link navigasi di kedua sidebar

**Files:**
- Modify: `resources/views/partials/sidebar-owner.blade.php`
- Modify: `resources/views/partials/sidebar-pipeline.blade.php`

**Interfaces:**
- Consumes: route `libur-nasional.index` (Task 3).

- [ ] **Step 1: Tambah link di `sidebar-owner.blade.php`**

Tambahkan PERSIS SETELAH link "Jadwal Libur" yang sudah ada:

```php
<a href="{{ route('jadwal-libur.approval') }}" class="nav-item {{ request()->routeIs('jadwal-libur.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    <span x-show="sidebarOpen">Jadwal Libur</span>
</a>
<a href="{{ route('libur-nasional.index') }}" class="nav-item {{ request()->routeIs('libur-nasional.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m-15.432 0A8.959 8.959 0 013 12c0-.778.099-1.533.284-2.253"/></svg>
    <span x-show="sidebarOpen">Libur Nasional</span>
</a>
```

- [ ] **Step 2: Tambah link di `sidebar-pipeline.blade.php`**

Tambahkan PERSIS SETELAH `@endif` yang menutup blok `@if($level == 3)` (blok yang berisi link "Kode Absen Hari Ini" dan "Jadwal Libur") — link Libur Nasional ini DI LUAR blok `@if`, karena semua level (bukan cuma level 3) boleh lihat:

```php
@if($level == 3)
<a href="{{ route('absensi.kode-hari-ini') }}" class="nav-item {{ request()->routeIs('absensi.kode-hari-ini') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
    <span x-show="sidebarOpen">Kode Absen Hari Ini</span>
</a>
<a href="{{ route('jadwal-libur.approval') }}" class="nav-item {{ request()->routeIs('jadwal-libur.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    <span x-show="sidebarOpen">Jadwal Libur</span>
</a>
@endif
<a href="{{ route('libur-nasional.index') }}" class="nav-item {{ request()->routeIs('libur-nasional.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m-15.432 0A8.959 8.959 0 013 12c0-.778.099-1.533.284-2.253"/></svg>
    <span x-show="sidebarOpen">Libur Nasional</span>
</a>
```

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l resources/views/partials/sidebar-owner.blade.php
php -l resources/views/partials/sidebar-pipeline.blade.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add resources/views/partials/sidebar-owner.blade.php resources/views/partials/sidebar-pipeline.blade.php
git commit -m "feat: link navigasi Libur Nasional di kedua sidebar"
```

**Catatan verifikasi manual (setelah deploy):** link "Libur Nasional" muncul di sidebar Owner DAN sidebar semua karyawan (level 2,3,4,5,6,7), klik dari akun non-Owner buka halaman kalender read-only tanpa error.

---

## Ringkasan urutan deploy (setelah semua 5 task selesai & direview)

1. Push branch ke `main` **HANYA SETELAH** SQL manual (catatan di akhir Task 1) sudah dikonfirmasi jalan di phpMyAdmin production — pola sama seperti migrasi-migrasi sebelumnya.
2. Setelah deploy, jalankan checklist verifikasi manual yang tercantum di Task 3, 4, 5.
3. Setelah dikonfirmasi jalan, cek juga 4 titik konsumen existing di production dengan cara tidak langsung: tambah libur nasional buat tanggal BESOK (kalau ada), lalu cek besok paginya `cron-kode-absen.php` beneran skip kirim kode ke karyawan yang bukan piket.
