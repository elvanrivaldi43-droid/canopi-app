# Jadwal Libur Per-Karyawan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap karyawan punya jadwal libur mingguan (beda-beda per orang, ada yang tidak punya), plus ajuan tukar/skip/tambah libur per-tanggal lewat alur approval — dipakai untuk membenarkan 3 titik yang selama ini menganggap semua karyawan wajib kerja tiap hari kecuali izin resmi: `cron-alpha.php` (auto-Alpha), `cron-kode-absen.php` (kirim kode absen), dan `GajiService::hitungHariKerja()` (dasar persen kehadiran KPI).

**Architecture:** Satu sumber kebenaran (`LiburService::isLibur()`) dengan model data 2 lapis — jadwal default per-minggu di `users.hari_libur_default`, dan pengecualian per-tanggal (`jadwal_libur` table, alur approval mirip `IzinAbsen`) yang menang atas default. Logic penentuan tanggal libur dipisah jadi fungsi murni (testable tanpa DB) dari wrapper yang query database, mengikuti pola `DenahConv` (geometri murni) vs `DenahEditor` (DOM) yang sudah ada di proyek ini.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Blade (pola dark-theme standalone seperti `izin/*.blade.php`), Carbon.

## Global Constraints

- Level 1 (Owner) tidak pernah absen — jangan sertakan level 1 di query karyawan manapun (pola sudah ada di `cron-alpha.php`/`cron-kode-absen.php`: `User::where('level', '!=', 1)`).
- Approval jadwal libur untuk level 1 & 3 (Owner + Supervisor/Mandor) — pakai middleware `level:1,3` eksplisit di route (bukan pola lama `izin.approval` yang tidak punya middleware level sama sekali — itu gap pre-existing, di luar cakupan plan ini, JANGAN diperbaiki di sini).
- Reuse `TelegramService::kirim()` yang sudah ada — jangan bikin jalur kirim Telegram baru.
- Migration file dibuat untuk kelengkapan repo, TAPI deployment sebenarnya ke production lewat SQL manual idempotent di phpMyAdmin (lihat catatan akhir Task 1) — proyek ini shared hosting tanpa SSH/artisan di server, `php artisan migrate` TIDAK bisa dijalankan di production.
- VPS pengembangan ini tidak punya koneksi database — semua test yang butuh DB TIDAK bisa dijalankan otomatis di sini. Test yang bisa dijalankan: `php -l` (lint syntax) dan test standalone untuk logic murni (pola `tests/rangka/*.php`, `tests/telegram/*.php`). Bagian yang butuh DB/UI diverifikasi manual oleh Elvan di production, sama seperti fitur-fitur lain di proyek ini.

---

### Task 1: Migrasi database & model

**Files:**
- Create: `database/migrations/2026_08_11_000001_add_hari_libur_default_to_users_table.php`
- Create: `database/migrations/2026_08_11_000002_create_jadwal_libur_table.php`
- Create: `app/Models/JadwalLibur.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Produces: kolom `users.hari_libur_default` (nullable unsigned tinyint, 0=Minggu..6=Sabtu, konvensi `Carbon::dayOfWeek`). Tabel `jadwal_libur` dengan kolom `user_id`, `tanggal` (date), `jenis` (enum `tambah`/`batal`), `status` (enum `pending`/`approved`/`rejected`, default `pending`), `alasan` (nullable text), `diproses_oleh` (nullable FK users), `diproses_at` (nullable timestamp), timestamps. Model `App\Models\JadwalLibur` dengan constant `JENIS`, `WARNA_STATUS`, method `jenisLabel()`, `statusLabel()`, `warnaStatus()`, relasi `user()`/`diprosesOleh()`. `User::$fillable` menambah `hari_libur_default`.

- [ ] **Step 1: Buat migration nambah kolom di `users`**

```php
<?php
// FILE: database/migrations/2026_08_11_000001_add_hari_libur_default_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('hari_libur_default')->nullable()->after('tanggal_bergabung');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hari_libur_default');
        });
    }
};
```

- [ ] **Step 2: Buat migration tabel `jadwal_libur`**

```php
<?php
// FILE: database/migrations/2026_08_11_000002_create_jadwal_libur_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_libur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('tanggal');
            $table->enum('jenis', ['tambah', 'batal']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('alasan')->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_libur');
    }
};
```

- [ ] **Step 3: Buat model `JadwalLibur`**

```php
<?php
// FILE: app/Models/JadwalLibur.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalLibur extends Model
{
    protected $table = 'jadwal_libur';

    protected $fillable = [
        'user_id', 'tanggal', 'jenis', 'alasan',
        'status', 'diproses_oleh', 'diproses_at',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'diproses_at' => 'datetime',
    ];

    const JENIS = [
        'tambah' => '➕ Tambah Libur',
        'batal'  => '🚫 Batalkan Libur Default',
    ];

    const WARNA_STATUS = [
        'pending'  => '#F59E0B',
        'approved' => '#10B981',
        'rejected' => '#EF4444',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'  => '⏳ Menunggu',
            'approved' => '✅ Disetujui',
            'rejected' => '❌ Ditolak',
            default    => '-',
        };
    }

    public function warnaStatus(): string
    {
        return self::WARNA_STATUS[$this->status] ?? '#64748B';
    }
}
```

- [ ] **Step 4: Tambah `hari_libur_default` ke `$fillable` di `User.php`**

Di `app/Models/User.php`, ubah:

```php
    protected $fillable = [
        'name', 'email', 'password',
        'level', 'jabatan', 'no_hp', 'alamat', 'foto',
        'gaji_harian', 'uang_makan', 'gaji_bulanan',
        'jam_masuk', 'jam_pulang', 'status',
        'tgl_masuk_kerja', 'tipe_gaji',
        'nama_bank', 'no_rekening', 'atas_nama',
        'nama_kontak_darurat', 'no_kontak_darurat',
        'tanggal_bergabung',
    ];
```

jadi:

```php
    protected $fillable = [
        'name', 'email', 'password',
        'level', 'jabatan', 'no_hp', 'alamat', 'foto',
        'gaji_harian', 'uang_makan', 'gaji_bulanan',
        'jam_masuk', 'jam_pulang', 'status',
        'tgl_masuk_kerja', 'tipe_gaji',
        'nama_bank', 'no_rekening', 'atas_nama',
        'nama_kontak_darurat', 'no_kontak_darurat',
        'tanggal_bergabung', 'hari_libur_default',
    ];
```

- [ ] **Step 5: Lint semua file baru (tidak ada koneksi DB di VPS ini, jadi migration tidak bisa dijalankan — cukup pastikan tidak ada syntax error)**

Run:
```bash
php -l database/migrations/2026_08_11_000001_add_hari_libur_default_to_users_table.php
php -l database/migrations/2026_08_11_000002_create_jadwal_libur_table.php
php -l app/Models/JadwalLibur.php
php -l app/Models/User.php
```
Expected: `No syntax errors detected` untuk keempat file.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_11_000001_add_hari_libur_default_to_users_table.php database/migrations/2026_08_11_000002_create_jadwal_libur_table.php app/Models/JadwalLibur.php app/Models/User.php
git commit -m "feat: migrasi & model jadwal libur per-karyawan"
```

**Catatan buat sesi deploy nanti (bukan step, jangan dieksekusi sekarang):** karena production tidak bisa `php artisan migrate`, sebelum push ke `main`, siapkan SQL idempotent buat Elvan jalankan manual di phpMyAdmin, contoh:
```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS hari_libur_default TINYINT UNSIGNED NULL;
UPDATE users SET hari_libur_default = 0 WHERE hari_libur_default IS NULL;
CREATE TABLE IF NOT EXISTS jadwal_libur (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('tambah','batal') NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    alasan TEXT NULL,
    diproses_oleh BIGINT UNSIGNED NULL,
    diproses_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (diproses_oleh) REFERENCES users(id) ON DELETE SET NULL
);
```
Sama seperti SQL `kode_absen.user_id`/`telegram_chat_id` sebelumnya — jangan push ke `main` sebelum SQL ini dikonfirmasi jalan di production.

---

### Task 2: `LiburService` — logic murni + wrapper database

**Files:**
- Create: `app/Services/LiburService.php`
- Test: `tests/jadwal-libur/test_libur_service.php`

**Interfaces:**
- Consumes: `App\Models\JadwalLibur` (Task 1), `App\Models\User` (existing, dengan `hari_libur_default` dari Task 1).
- Produces: `LiburService::cocokLiburPada(?int $hariLiburDefault, array $overrides, Carbon $tanggal): bool` — logic murni, `$overrides` adalah array asosiatif `['tanggal' => 'Y-m-d', 'jenis' => 'tambah'|'batal']`. `LiburService::isLibur(User $user, Carbon $tanggal): bool` — wrapper database, dipakai Task 3 & 4. `LiburService::hitungHariKerjaPada(?int $hariLiburDefault, array $overrides, int $bulan, int $tahun): int` — logic murni. `LiburService::hitungHariKerja(User $user, int $bulan, int $tahun): int` — wrapper database, dipakai Task 4.

- [ ] **Step 1: Tulis test standalone dulu (logic murni, belum ada implementasinya)**

```php
<?php
// FILE: tests/jadwal-libur/test_libur_service.php
// Jalankan: php tests/jadwal-libur/test_libur_service.php
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

// ── cocokLiburPada ──────────────────────────────────────────
// 11 Agustus 2026 dipastikan hari Selasa (Carbon::dayOfWeek: 0=Minggu..6=Sabtu, Selasa=2)
$selasa = Carbon::create(2026, 8, 11);
$check('tanggal contoh memang Selasa (dayOfWeek=2)', $selasa->dayOfWeek, 2);

$check('default cocok hari, tanpa override -> true',
    $svc->cocokLiburPada(2, [], $selasa), true);

$check('default beda hari, tanpa override -> false',
    $svc->cocokLiburPada(6, [], $selasa), false);

$check('tanpa default (null), tanpa override -> selalu false',
    $svc->cocokLiburPada(null, [], $selasa), false);

$check('default cocok TAPI ada override batal di tanggal itu -> false (override menang)',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], $selasa), false);

$check('default TIDAK cocok TAPI ada override tambah di tanggal itu -> true',
    $svc->cocokLiburPada(6, [['tanggal' => '2026-08-11', 'jenis' => 'tambah']], $selasa), true);

$check('override ada tapi beda tanggal -> fallback ke default (cocok) -> true',
    $svc->cocokLiburPada(2, [['tanggal' => '2026-08-12', 'jenis' => 'batal']], $selasa), true);

// ── hitungHariKerjaPada ──────────────────────────────────────
// Agustus 2026 = 31 hari.
$jumlahSelasa = 0;
for ($i = 1; $i <= 31; $i++) {
    if (Carbon::create(2026, 8, $i)->dayOfWeek === 2) $jumlahSelasa++;
}

$check('tanpa default -> semua 31 hari kehitung hari kerja',
    $svc->hitungHariKerjaPada(null, [], 8, 2026), 31);

$check('default Selasa -> 31 dikurangi jumlah Selasa di Agustus 2026',
    $svc->hitungHariKerjaPada(2, [], 8, 2026), 31 - $jumlahSelasa);

$check('default Selasa + 1 override batal (1 Selasa dibatalkan) -> nambah 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(2, [['tanggal' => '2026-08-11', 'jenis' => 'batal']], 8, 2026),
    31 - $jumlahSelasa + 1);

$check('tanpa default + 1 override tambah (nambah 1 libur) -> ngurang 1 hari kerja dibanding tanpa override',
    $svc->hitungHariKerjaPada(null, [['tanggal' => '2026-08-05', 'jenis' => 'tambah']], 8, 2026),
    31 - 1);

$check('Februari 2026 (28 hari, bukan kabisat), tanpa default -> 28 hari kerja',
    $svc->hitungHariKerjaPada(null, [], 2, 2026), 28);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal (class belum ada)**

Run: `php tests/jadwal-libur/test_libur_service.php`
Expected: Fatal error `Class "App\Services\LiburService" not found`.

- [ ] **Step 3: Implementasi `LiburService`**

```php
<?php
// FILE: app/Services/LiburService.php

namespace App\Services;

use App\Models\JadwalLibur;
use App\Models\User;
use Carbon\Carbon;

class LiburService
{
    const HARI = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    // Logic murni — testable tanpa database.
    // $overrides: array asosiatif ['tanggal' => 'Y-m-d', 'jenis' => 'tambah'|'batal'][]
    public function cocokLiburPada(?int $hariLiburDefault, array $overrides, Carbon $tanggal): bool
    {
        $tglStr = $tanggal->format('Y-m-d');
        foreach ($overrides as $o) {
            if ($o['tanggal'] === $tglStr) {
                return $o['jenis'] === 'tambah';
            }
        }
        return $hariLiburDefault !== null && $tanggal->dayOfWeek === $hariLiburDefault;
    }

    public function hitungHariKerjaPada(?int $hariLiburDefault, array $overrides, int $bulan, int $tahun): int
    {
        $hariKerja  = 0;
        $akhirBulan = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= $akhirBulan; $i++) {
            $tgl = Carbon::createFromDate($tahun, $bulan, $i);
            if (!$this->cocokLiburPada($hariLiburDefault, $overrides, $tgl)) {
                $hariKerja++;
            }
        }
        return $hariKerja;
    }

    // Wrapper database — dipakai cron & GajiService.
    public function isLibur(User $user, Carbon $tanggal): bool
    {
        $overrides = $this->ambilOverride($user, $tanggal, $tanggal);
        return $this->cocokLiburPada($user->hari_libur_default, $overrides, $tanggal);
    }

    public function hitungHariKerja(User $user, int $bulan, int $tahun): int
    {
        $awal      = Carbon::createFromDate($tahun, $bulan, 1);
        $akhir     = $awal->copy()->endOfMonth();
        $overrides = $this->ambilOverride($user, $awal, $akhir);
        return $this->hitungHariKerjaPada($user->hari_libur_default, $overrides, $bulan, $tahun);
    }

    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        return JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('tanggal', '>=', $dari->format('Y-m-d'))
            ->whereDate('tanggal', '<=', $sampai->format('Y-m-d'))
            ->get(['tanggal', 'jenis'])
            ->map(fn($o) => ['tanggal' => $o->tanggal->format('Y-m-d'), 'jenis' => $o->jenis])
            ->toArray();
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php tests/jadwal-libur/test_libur_service.php`
Expected: Semua baris `PASS`, diakhiri `=== SEMUA TES LULUS ===`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/LiburService.php tests/jadwal-libur/test_libur_service.php
git commit -m "feat: LiburService (logic murni + wrapper) buat cek jadwal libur karyawan"
```

---

### Task 3: Integrasi ke `cron-alpha.php` dan `cron-kode-absen.php`

**Files:**
- Modify: `public/cron-alpha.php`
- Modify: `public/cron-kode-absen.php`

**Interfaces:**
- Consumes: `LiburService::isLibur(User $user, Carbon $tanggal): bool` (Task 2).

- [ ] **Step 1: Tambah pengecekan libur di `cron-alpha.php` (blok jam 13:00)**

Di `public/cron-alpha.php`, ubah `use` statement — dari:

```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use App\Services\TelegramService;
use Carbon\Carbon;
```

jadi:

```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;
```

Lalu di dalam blok `if ($jam >= '13:00' && $jam <= '13:15') { ... }`, ubah baris kondisi:

```php
        // Hanya alpha jika: belum masuk sama sekali + tidak ada izin + belum alpha
        if (!$sudahMasuk && !$adaIzin && !$sudahAlpha) {
```

jadi (tambah pengecekan libur SEBELUM baris `$sudahAlpha`, dan masukkan ke kondisi):

```php
        // Cek apakah hari ini jadwal libur karyawan itu
        $sedangLibur = app(LiburService::class)->isLibur($k, $tanggal);

        // Hanya alpha jika: belum masuk sama sekali + tidak ada izin + belum alpha + bukan hari libur
        if (!$sudahMasuk && !$adaIzin && !$sudahAlpha && !$sedangLibur) {
```

Blok jam 20:00 TIDAK perlu diubah — itu hanya menyentuh baris absensi yang SUDAH ada (`whereNotNull('jam_masuk')`), karyawan yang libur (tidak pernah absen masuk) otomatis tidak pernah masuk query itu.

- [ ] **Step 2: Tambah pengecekan libur di `cron-kode-absen.php`**

File ini sudah punya `$offHariIni` (exclude izin/sakit/cuti/dinas_luar) — itu 1 query SQL langsung ke tabel `absensi`. Libur TIDAK bisa digabung ke query yang sama karena sumbernya beda (kombinasi `users.hari_libur_default` + tabel `jadwal_libur`, dihitung per-user lewat `LiburService`, bukan 1 kolom status yang bisa di-`whereIn`). Jadi pengecekan libur ditambah TERPISAH, per-karyawan, di dalam loop.

Tambah `use App\Services\LiburService;` ke daftar `use` yang sudah ada, lalu ubah loop `foreach ($karyawan as $k) { try { ... } }` — tambahkan pengecekan libur sebagai baris PALING AWAL di dalam `try`, sebelum baris `$existing = KodeAbsen::...`:

```php
foreach ($karyawan as $k) {
    try {
        if (app(LiburService::class)->isLibur($k, $tanggal)) {
            $log[] = "⏭ Skip (jadwal libur): {$k->name}";
            $skip++;
            continue;
        }

        $existing = KodeAbsen::whereDate('tanggal', $tanggal)->where('user_id', $k->id)->first();
```

(baris-baris setelahnya tidak berubah)

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l public/cron-alpha.php
php -l public/cron-kode-absen.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add public/cron-alpha.php public/cron-kode-absen.php
git commit -m "fix: cron-alpha & cron-kode-absen skip karyawan yang lagi jadwal libur"
```

**Catatan verifikasi manual (setelah deploy, tidak bisa diuji dari VPS ini):** trigger `cron-alpha.php` dan `cron-kode-absen.php` manual dengan key production buat karyawan yang sudah di-set `hari_libur_default`-nya (lewat Task 7), pastikan dia tidak ke-Alpha / tidak dapat kode absen di hari itu.

---

### Task 4: Integrasi ke `GajiService::hitungHariKerja`

**Files:**
- Modify: `app/Services/GajiService.php`

**Interfaces:**
- Consumes: `LiburService::hitungHariKerja(User $user, int $bulan, int $tahun): int` (Task 2).

- [ ] **Step 1: Tambah import `LiburService`**

Di `app/Services/GajiService.php`, ubah:

```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\TabunganKaryawan;
use Carbon\Carbon;
```

jadi:

```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\SlipGaji;
use App\Models\Kasbon;
use App\Models\TabunganKaryawan;
use App\Services\LiburService;
use Carbon\Carbon;
```

- [ ] **Step 2: Ubah pemanggilan `hitungHariKerja` supaya kirim `$user`**

Ubah baris:

```php
        // Hari kerja bulan ini (Senin-Sabtu)
        $hariKerja = $this->hitungHariKerja($bulan, $tahun);
```

jadi:

```php
        // Hari kerja bulan ini (dikurangi jadwal libur karyawan ini)
        $hariKerja = app(LiburService::class)->hitungHariKerja($user, $bulan, $tahun);
```

(baris `$user = User::with('tunjangan')->findOrFail($userId);` di atasnya sudah ada duluan, jadi `$user` sudah tersedia di titik ini — tidak perlu perubahan lain.)

- [ ] **Step 3: Hapus method `hitungHariKerja` lama yang sudah tidak dipakai**

Hapus method private ini dari `GajiService.php` (sudah digantikan sepenuhnya oleh `LiburService::hitungHariKerja`):

```php
    private function hitungHariKerja(int $bulan, int $tahun): int
    {
        $hariKerja = 0;
        $hariAkhir = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        for ($i = 1; $i <= $hariAkhir; $i++) {
            $tgl = Carbon::createFromDate($tahun, $bulan, $i);
            if ($tgl->dayOfWeek !== Carbon::SUNDAY) $hariKerja++;
        }
        return $hariKerja;
    }
```

- [ ] **Step 4: Lint file**

Run: `php -l app/Services/GajiService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/GajiService.php
git commit -m "fix: hitungHariKerja pakai jadwal libur per-karyawan, bukan seragam Senin-Sabtu"
```

**Catatan verifikasi manual (setelah deploy):** generate slip gaji bulan berikutnya buat 2 karyawan berbeda `hari_libur_default`, pastikan `hari_kerja` di slip masing-masing beda sesuai jadwalnya.

---

### Task 5: `JadwalLiburController` + routes

**Files:**
- Create: `app/Http/Controllers/JadwalLiburController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Models\JadwalLibur` (Task 1), `App\Services\TelegramService::kirim()` (existing).
- Produces: route `jadwal-libur.index`, `jadwal-libur.create`, `jadwal-libur.store`, `jadwal-libur.approval`, `jadwal-libur.approve`, `jadwal-libur.reject` — dipakai Task 6 (views) dan Task 7 (link nav).

- [ ] **Step 1: Buat `JadwalLiburController`**

```php
<?php
// FILE: app/Http/Controllers/JadwalLiburController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;

class JadwalLiburController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // FORM AJUKAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function create()
    {
        $user       = Auth::user();
        $tanggalMin = today()->addDay()->format('Y-m-d');

        return view('jadwal-libur.create', compact('user', 'tanggalMin'));
    }

    // ═══════════════════════════════════════════════════════════
    // SIMPAN AJUAN (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal' => 'required|date|after:today',
            'jenis'   => 'required|in:tambah,batal',
            'alasan'  => 'nullable|string|max:500',
        ]);

        $sudahAda = JadwalLibur::where('user_id', $user->id)
                               ->whereDate('tanggal', $request->tanggal)
                               ->whereIn('status', ['pending', 'approved'])
                               ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya ajuan jadwal libur pada tanggal tersebut.');
        }

        $jadwal = JadwalLibur::create([
            'user_id' => $user->id,
            'tanggal' => $request->tanggal,
            'jenis'   => $request->jenis,
            'alasan'  => $request->alasan,
            'status'  => 'pending',
        ]);

        $this->kirimNotifPengajuan($user, $jadwal);

        return redirect()->route('jadwal-libur.index')
            ->with('success', 'Ajuan jadwal libur berhasil dikirim. Menunggu persetujuan Owner/Mandor.');
    }

    // ═══════════════════════════════════════════════════════════
    // RIWAYAT AJUAN SAYA (karyawan)
    // ═══════════════════════════════════════════════════════════

    public function index()
    {
        $user       = Auth::user();
        $jadwalList = JadwalLibur::where('user_id', $user->id)
                                 ->orderBy('tanggal', 'desc')
                                 ->limit(30)
                                 ->get();

        return view('jadwal-libur.index', compact('user', 'jadwalList'));
    }

    // ═══════════════════════════════════════════════════════════
    // DAFTAR PENDING (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function approval()
    {
        $pending = JadwalLibur::where('status', 'pending')
                              ->with('user')
                              ->orderBy('tanggal')
                              ->get();

        $riwayat = JadwalLibur::whereIn('status', ['approved', 'rejected'])
                              ->with('user')
                              ->orderBy('updated_at', 'desc')
                              ->limit(20)
                              ->get();

        return view('jadwal-libur.approval', compact('pending', 'riwayat'));
    }

    // ═══════════════════════════════════════════════════════════
    // APPROVE (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function approve(Request $request, JadwalLibur $jadwalLibur)
    {
        $jadwalLibur->update([
            'status'        => 'approved',
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        $this->kirimNotifHasil($jadwalLibur, 'approved');

        return back()->with('success', "Jadwal libur {$jadwalLibur->user->name} pada {$jadwalLibur->tanggal->format('d/m/Y')} disetujui.");
    }

    // ═══════════════════════════════════════════════════════════
    // REJECT (Owner/Mandor)
    // ═══════════════════════════════════════════════════════════

    public function reject(Request $request, JadwalLibur $jadwalLibur)
    {
        $jadwalLibur->update([
            'status'        => 'rejected',
            'diproses_oleh' => Auth::id(),
            'diproses_at'   => now(),
        ]);

        $this->kirimNotifHasil($jadwalLibur, 'rejected');

        return back()->with('success', "Jadwal libur {$jadwalLibur->user->name} ditolak.");
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    private function kirimNotifPengajuan(User $user, JadwalLibur $jadwal): void
    {
        $penerima = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();

        foreach ($penerima as $p) {
            $pesan = "🗓️ *AJUAN JADWAL LIBUR*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
                   . "Jenis: {$jadwal->jenisLabel()}\n"
                   . ($jadwal->alasan ? "Alasan: {$jadwal->alasan}\n" : '')
                   . "---\n"
                   . "Approve/tolak di: app.kanopibsd.co.id/jadwal-libur/approval";
            app(TelegramService::class)->kirim($p->telegram_chat_id, $pesan);
        }
    }

    private function kirimNotifHasil(JadwalLibur $jadwal, string $hasil): void
    {
        $user = $jadwal->user;

        $icon  = $hasil === 'approved' ? '✅' : '❌';
        $label = $hasil === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "{$icon} *JADWAL LIBUR {$label}*\n"
               . "Jenis: {$jadwal->jenisLabel()}\n"
               . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/jadwal-libur";
        app(TelegramService::class)->kirim($user->telegram_chat_id, $pesan);
    }
}
```

- [ ] **Step 2: Tambah routes**

Di `routes/web.php`, tambah import di bagian atas (dekat baris `use App\Http\Controllers\IzinAbsenController;`):

```php
use App\Http\Controllers\JadwalLiburController;
```

Lalu tambah blok route baru PERSIS SETELAH blok `// ─── IZIN KARYAWAN ─────...` yang sudah ada (setelah baris `});` penutup blok izin):

```php
// ─── JADWAL LIBUR KARYAWAN ──────────────────────────────────
Route::middleware('auth')->prefix('jadwal-libur')->name('jadwal-libur.')->group(function () {
    Route::get('/',                        [JadwalLiburController::class, 'index'])->name('index');
    Route::get('/ajukan',                  [JadwalLiburController::class, 'create'])->name('create');
    Route::post('/',                       [JadwalLiburController::class, 'store'])->name('store');
    Route::get('/approval',                [JadwalLiburController::class, 'approval'])->middleware('level:1,3')->name('approval');
    Route::patch('/{jadwalLibur}/approve', [JadwalLiburController::class, 'approve'])->middleware('level:1,3')->name('approve');
    Route::patch('/{jadwalLibur}/reject',  [JadwalLiburController::class, 'reject'])->middleware('level:1,3')->name('reject');
});
```

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l app/Http/Controllers/JadwalLiburController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/JadwalLiburController.php routes/web.php
git commit -m "feat: JadwalLiburController + routes ajuan/approval jadwal libur"
```

---

### Task 6: Views (create, index, approval)

**Files:**
- Create: `resources/views/jadwal-libur/create.blade.php`
- Create: `resources/views/jadwal-libur/index.blade.php`
- Create: `resources/views/jadwal-libur/approval.blade.php`

**Interfaces:**
- Consumes: route `jadwal-libur.*` (Task 5), variable `$user`/`$tanggalMin` (create), `$user`/`$jadwalList` (index), `$pending`/`$riwayat` (approval) — semua dari `JadwalLiburController`. Method `JadwalLibur::jenisLabel()`, `statusLabel()`, `warnaStatus()` (Task 1).

- [ ] **Step 1: Buat `resources/views/jadwal-libur/create.blade.php`**

```php
{{-- FILE: resources/views/jadwal-libur/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Ajukan Jadwal Libur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .content { padding:16px; padding-bottom:40px; max-width:480px; margin:0 auto; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .form-control { background:#0f172a !important; border:1px solid #475569 !important; color:#f1f5f9 !important; border-radius:8px; }
  .form-control:focus { border-color:#fbbf24 !important; box-shadow:none !important; }
  .jenis-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
  .jenis-item { background:#0f172a; border:2px solid #334155; border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:all 0.2s; }
  .jenis-item:hover { border-color:#fbbf24; }
  .jenis-item.selected { border-color:#fbbf24; background:rgba(251,191,36,0.05); }
  .jenis-item input { display:none; }
  .jenis-icon { font-size:24px; display:block; margin-bottom:6px; }
  .jenis-label { font-size:13px; font-weight:600; color:#f1f5f9; }
  .jenis-info { font-size:10px; color:#64748b; margin-top:3px; }
  .btn-submit { width:100%; padding:14px; border-radius:10px; border:none; font-weight:700; font-size:16px; background:#fbbf24; color:#0f172a; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .info-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:12px; font-size:12px; color:#a5b4fc; margin-bottom:16px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('jadwal-libur.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Ajukan Jadwal Libur</div>
    <div style="color:#64748b; font-size:12px;">{{ now()->translatedFormat('l, d F Y') }}</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div class="alert-box alert-error">{{ session('error') }}</div>
  @endif
  @if($errors->any())
  <div class="alert-box alert-error">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
  </div>
  @endif

  <div class="info-box">
    ℹ️ Pakai ini kalau mau <strong>menukar/skip jadwal libur default kamu</strong>, atau <strong>menambah</strong> libur di hari yang bukan jadwal default. Butuh persetujuan Owner/Mandor.
  </div>

  <form method="POST" action="{{ route('jadwal-libur.store') }}">
    @csrf

    {{-- Jenis --}}
    <div class="card-dark">
      <div class="section-label">Jenis Ajuan</div>
      <div class="jenis-grid">
        <label class="jenis-item {{ old('jenis')=='tambah'?'selected':'' }}" onclick="pilihJenis('tambah',this)">
          <input type="radio" name="jenis" value="tambah" {{ old('jenis')=='tambah'?'checked':'' }}>
          <span class="jenis-icon">➕</span>
          <div class="jenis-label">Tambah Libur</div>
          <div class="jenis-info">Libur di hari ini, walau bukan jadwal default</div>
        </label>
        <label class="jenis-item {{ old('jenis')=='batal'?'selected':'' }}" onclick="pilihJenis('batal',this)">
          <input type="radio" name="jenis" value="batal" {{ old('jenis')=='batal'?'checked':'' }}>
          <span class="jenis-icon">🚫</span>
          <div class="jenis-label">Batalkan Libur</div>
          <div class="jenis-info">Jadwal default dibatalkan buat tanggal ini (tetap kerja)</div>
        </label>
      </div>
    </div>

    {{-- Tanggal --}}
    <div class="card-dark">
      <div class="section-label">Tanggal</div>
      <input type="date" name="tanggal" class="form-control"
             min="{{ $tanggalMin }}"
             value="{{ old('tanggal', $tanggalMin) }}" required>
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Minimal besok ({{ \Carbon\Carbon::parse($tanggalMin)->translatedFormat('d F Y') }})</div>
    </div>

    {{-- Alasan --}}
    <div class="card-dark">
      <div class="section-label">Alasan <span style="color:#64748b; text-transform:none; letter-spacing:0;">(opsional)</span></div>
      <textarea name="alasan" class="form-control" rows="3"
                placeholder="Mis. tukar sama Budi, ada acara keluarga, dst."
                style="resize:none;">{{ old('alasan') }}</textarea>
    </div>

    <button type="submit" class="btn-submit">📤 Kirim Ajuan</button>
  </form>

</div>

<script>
function pilihJenis(jenis, el) {
  document.querySelectorAll('.jenis-item').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;
}
</script>
</body>
</html>
```

- [ ] **Step 2: Buat `resources/views/jadwal-libur/index.blade.php`**

```php
{{-- FILE: resources/views/jadwal-libur/index.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jadwal Libur Saya</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:480px; margin:0 auto; padding-bottom:40px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; }
  .btn-ajukan { background:#fbbf24; color:#0f172a; border:none; border-radius:10px; padding:12px 20px; font-weight:700; font-size:14px; text-decoration:none; display:block; text-align:center; margin-bottom:16px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('izin.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Jadwal Libur Saya</div>
    <div style="color:#64748b; font-size:12px;">Riwayat ajuan tukar/skip/tambah libur</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">{{ session('success') }}</div>
  @endif

  <a href="{{ route('jadwal-libur.create') }}" class="btn-ajukan">
    📤 Ajukan Jadwal Libur Baru
  </a>

  @forelse($jadwalList as $jadwal)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div style="font-size:15px; font-weight:700; color:#f1f5f9;">
          {{ $jadwal->jenisLabel() }}
        </div>
        <div style="font-size:12px; color:#64748b; margin-top:2px;">
          {{ $jadwal->tanggal->translatedFormat('l, d F Y') }}
        </div>
        @if($jadwal->alasan)
        <div style="font-size:12px; color:#94a3b8; margin-top:6px;">
          {{ $jadwal->alasan }}
        </div>
        @endif
      </div>
      <span style="font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; white-space:nowrap;
        background:{{ $jadwal->warnaStatus() }}20; color:{{ $jadwal->warnaStatus() }}; border:1px solid {{ $jadwal->warnaStatus() }}40;">
        {{ $jadwal->statusLabel() }}
      </span>
    </div>
  </div>
  @empty
  <div class="card-dark text-center" style="padding:40px;">
    <div style="font-size:32px; margin-bottom:12px;">🗓️</div>
    <div style="color:#64748b;">Belum ada riwayat ajuan jadwal libur</div>
  </div>
  @endforelse

</div>
</body>
</html>
```

- [ ] **Step 3: Buat `resources/views/jadwal-libur/approval.blade.php`**

```php
{{-- FILE: resources/views/jadwal-libur/approval.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Jadwal Libur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .content { padding:16px; max-width:600px; margin:0 auto; padding-bottom:40px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:12px; }
  .section-title { color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .btn-approve { background:#10b981; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .btn-reject { background:transparent; color:#ef4444; border:1px solid #ef4444; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; border-radius:10px; padding:12px; margin-bottom:12px; font-size:13px; }
  .badge-pending { background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); }
  .badge-approved { background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.3); }
  .badge-rejected { background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid rgba(239,68,68,0.3); }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ url('/dashboard') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div style="font-weight:700; color:#fbbf24; font-size:16px;">Approval Jadwal Libur</div>
    <div style="color:#64748b; font-size:12px;">{{ $pending->count() }} menunggu persetujuan</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-success">{{ session('success') }}</div>
  @endif

  <div class="section-title">⏳ Menunggu Persetujuan ({{ $pending->count() }})</div>

  @forelse($pending as $jadwal)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div style="font-size:15px; font-weight:700; color:#f1f5f9;">{{ $jadwal->user->name }}</div>
        <div style="font-size:12px; color:#64748b;">{{ $jadwal->user->jabatan }}</div>
      </div>
      <span class="badge badge-pending" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $jadwal->jenisLabel() }}
      </span>
    </div>

    <div style="font-size:13px; color:#94a3b8; margin-bottom:4px;">
      📅 {{ $jadwal->tanggal->translatedFormat('l, d F Y') }}
    </div>
    @if($jadwal->alasan)
    <div style="font-size:13px; color:#e2e8f0; margin-bottom:12px;">
      {{ $jadwal->alasan }}
    </div>
    @endif

    <div class="d-flex gap-2 mt-3">
      <form method="POST" action="{{ route('jadwal-libur.approve', $jadwal) }}" class="flex-fill">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn-approve" style="width:100%;">✅ Setujui</button>
      </form>
      <form method="POST" action="{{ route('jadwal-libur.reject', $jadwal) }}" class="flex-fill">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn-reject" style="width:100%;">❌ Tolak</button>
      </form>
    </div>
  </div>
  @empty
  <div class="card-dark text-center" style="padding:30px;">
    <div style="font-size:28px; margin-bottom:8px;">✅</div>
    <div style="color:#64748b; font-size:13px;">Tidak ada ajuan yang menunggu persetujuan</div>
  </div>
  @endforelse

  @if($riwayat->count() > 0)
  <div class="section-title mt-4">📋 Riwayat Terbaru</div>
  @foreach($riwayat as $jadwal)
  <div class="card-dark">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div style="font-size:13px; font-weight:600; color:#f1f5f9;">{{ $jadwal->user->name }}</div>
        <div style="font-size:11px; color:#64748b;">
          {{ $jadwal->jenisLabel() }} · {{ $jadwal->tanggal->format('d/m/Y') }}
        </div>
      </div>
      <span class="badge badge-{{ $jadwal->status }}" style="font-size:11px; padding:4px 10px; border-radius:20px;">
        {{ $jadwal->statusLabel() }}
      </span>
    </div>
  </div>
  @endforeach
  @endif

</div>
</body>
</html>
```

Catatan: approval izin (`izin/approval.blade.php`) pakai modal + JS buat isi field `catatan` sebelum submit. Approval jadwal libur ini SENGAJA dibikin lebih simpel (form langsung submit, tanpa modal/catatan) karena spec (`docs/superpowers/specs/2026-08-11-jadwal-libur-karyawan-design.md`) tidak mensyaratkan field catatan approval — kalau nanti dibutuhkan, tambahkan modal dengan pola yang sama seperti `izin/approval.blade.php`.

- [ ] **Step 4: Lint ketiga file**

Run:
```bash
php -l resources/views/jadwal-libur/create.blade.php
php -l resources/views/jadwal-libur/index.blade.php
php -l resources/views/jadwal-libur/approval.blade.php
```
Expected: `No syntax errors detected` untuk ketiganya. (Blade syntax `{{ }}`/`@if` dikompilasi Laravel saat runtime, `php -l` di sini hanya mengecek tidak ada PHP mentah yang rusak di dalam file.)

- [ ] **Step 5: Commit**

```bash
git add resources/views/jadwal-libur/
git commit -m "feat: halaman ajukan/riwayat/approval jadwal libur"
```

**Catatan verifikasi manual (setelah deploy):** buka `/jadwal-libur/ajukan` sebagai karyawan, submit ajuan, cek muncul di `/jadwal-libur` (riwayat) dan `/jadwal-libur/approval` (Owner), coba approve & reject, pastikan notif Telegram masuk ke kedua sisi.

---

### Task 7: Field "Hari Libur Default" di form karyawan + link navigasi

**Files:**
- Modify: `resources/views/karyawan/edit.blade.php`
- Modify: `app/Http/Controllers/KaryawanController.php`
- Modify: `resources/views/izin/index.blade.php`
- Modify: `resources/views/partials/sidebar-owner.blade.php`
- Modify: `resources/views/partials/sidebar-pipeline.blade.php`

**Interfaces:**
- Consumes: `LiburService::HARI` (Task 2) buat label hari. Route `jadwal-libur.create`, `jadwal-libur.approval` (Task 5).

- [ ] **Step 1: Tambah field "Hari Libur Default" di form edit karyawan**

Di `resources/views/karyawan/edit.blade.php`, tambahkan blok baru PERSIS SETELAH blok `{{-- Level --}}` yang sudah ada (setelah baris `@error('level')...@enderror` dan `</div>` penutupnya, sebelum `{{-- Jabatan --}}`):

```php
            {{-- Hari Libur Default --}}
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#94A3B8;margin-bottom:6px;">Hari Libur Default</label>
                <select name="hari_libur_default"
                        style="width:100%;padding:11px 14px;border-radius:10px;font-size:13px;outline:none;border:1.5px solid;background:transparent;cursor:pointer;"
                        :style="darkMode ? 'border-color:rgba(255,255,255,0.1);color:#E2E8F0;' : 'border-color:#E2E8F0;color:#1E293B;'">
                    <option value="">Tidak ada libur tetap</option>
                    @foreach(\App\Services\LiburService::HARI as $angka => $nama)
                    <option value="{{ $angka }}" {{ (string) old('hari_libur_default', $karyawan->hari_libur_default) === (string) $angka ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
                <div style="color:#64748b; font-size:11px; margin-top:6px;">Karyawan tetap bisa ajukan tukar/skip lewat menu Jadwal Libur kalau ada perubahan minggu tertentu.</div>
                @error('hari_libur_default')<div style="font-size:11px;color:#F87171;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

```

- [ ] **Step 2: Terima & simpan `hari_libur_default` di `KaryawanController::update()`**

Di `app/Http/Controllers/KaryawanController.php`, ubah validasi:

```php
        $request->validate([
            'level'           => 'required|integer|between:1,7',
            'jabatan'         => 'required|string|max:100',
            'tipe_gaji'       => 'required|in:harian,bulanan,project',
            'gaji_harian'     => 'nullable|numeric|min:0',
            'gaji_bulanan'    => 'nullable|numeric|min:0',
            'uang_makan'      => 'nullable|numeric|min:0',
            'uang_bonus'      => 'nullable|numeric|min:0',
            'jam_masuk'       => 'required',
            'jam_pulang'      => 'required',
            'tgl_masuk_kerja' => 'required|date',
        ]);
```

jadi:

```php
        $request->validate([
            'level'              => 'required|integer|between:1,7',
            'jabatan'            => 'required|string|max:100',
            'tipe_gaji'          => 'required|in:harian,bulanan,project',
            'gaji_harian'        => 'nullable|numeric|min:0',
            'gaji_bulanan'       => 'nullable|numeric|min:0',
            'uang_makan'         => 'nullable|numeric|min:0',
            'uang_bonus'         => 'nullable|numeric|min:0',
            'jam_masuk'          => 'required',
            'jam_pulang'         => 'required',
            'tgl_masuk_kerja'    => 'required|date',
            'hari_libur_default' => 'nullable|integer|between:0,6',
        ]);
```

Lalu ubah pemanggilan `$karyawan->update([...])`:

```php
        $karyawan->update([
            'name'              => $request->name ?? $karyawan->name,
            'no_hp'             => $request->no_hp,
            'level'             => $request->level,
            'jabatan'           => $request->jabatan,
            'tipe_gaji'         => $request->tipe_gaji,
            'gaji_harian'       => $request->gaji_harian  ?? 0,
            'gaji_bulanan'      => $request->gaji_bulanan ?? 0,
            'uang_makan'        => $request->uang_makan   ?? 0,
            'uang_bonus'        => $request->uang_bonus   ?? 0,
            'jam_masuk'         => $request->jam_masuk,
            'jam_pulang'        => $request->jam_pulang,
            'tgl_masuk_kerja'   => $request->tgl_masuk_kerja,
            'nama_bank'         => $request->nama_bank,
            'no_rekening'       => $request->no_rekening,
            'atas_nama'         => $request->atas_nama,
            'tanggal_bergabung' => $request->tanggal_bergabung,
            'alamat'            => $request->alamat,
        ]);
```

jadi:

```php
        $karyawan->update([
            'name'                => $request->name ?? $karyawan->name,
            'no_hp'               => $request->no_hp,
            'level'               => $request->level,
            'jabatan'             => $request->jabatan,
            'tipe_gaji'           => $request->tipe_gaji,
            'gaji_harian'         => $request->gaji_harian  ?? 0,
            'gaji_bulanan'        => $request->gaji_bulanan ?? 0,
            'uang_makan'          => $request->uang_makan   ?? 0,
            'uang_bonus'          => $request->uang_bonus   ?? 0,
            'jam_masuk'           => $request->jam_masuk,
            'jam_pulang'          => $request->jam_pulang,
            'tgl_masuk_kerja'     => $request->tgl_masuk_kerja,
            'nama_bank'           => $request->nama_bank,
            'no_rekening'         => $request->no_rekening,
            'atas_nama'           => $request->atas_nama,
            'tanggal_bergabung'   => $request->tanggal_bergabung,
            'alamat'              => $request->alamat,
            'hari_libur_default'  => $request->hari_libur_default,
        ]);
```

- [ ] **Step 3: Tambah link "Jadwal Libur" di halaman Izin Saya (karyawan)**

Di `resources/views/izin/index.blade.php`, ubah:

```php
  <a href="{{ route('izin.create') }}" class="btn-ajukan">
    📤 Ajukan Izin Baru
  </a>
```

jadi (tambah link kedua di bawahnya, style outline biar beda dari tombol utama):

```php
  <a href="{{ route('izin.create') }}" class="btn-ajukan">
    📤 Ajukan Izin Baru
  </a>

  <a href="{{ route('jadwal-libur.index') }}" style="display:block;text-align:center;border:1px solid #334155;color:#94a3b8;border-radius:10px;padding:12px 20px;font-weight:600;font-size:13px;text-decoration:none;margin-bottom:16px;">
    🗓️ Jadwal Libur Saya (tukar/skip/tambah)
  </a>
```

- [ ] **Step 4: Tambah link approval di sidebar Owner**

Di `resources/views/partials/sidebar-owner.blade.php`, ubah:

```php
<a href="{{ route('izin.approval') }}" class="nav-item {{ request()->routeIs('izin.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
    <span x-show="sidebarOpen">Izin</span>
</a>
```

jadi (tambah 1 link baru persis setelahnya, pakai icon kalender):

```php
<a href="{{ route('izin.approval') }}" class="nav-item {{ request()->routeIs('izin.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
    <span x-show="sidebarOpen">Izin</span>
</a>
<a href="{{ route('jadwal-libur.approval') }}" class="nav-item {{ request()->routeIs('jadwal-libur.*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    <span x-show="sidebarOpen">Jadwal Libur</span>
</a>
```

- [ ] **Step 5: Tambah link approval di sidebar Pipeline (level 3)**

Di `resources/views/partials/sidebar-pipeline.blade.php`, ubah blok yang sudah digating `@if($level == 3)`:

```php
@if($level == 3)
<a href="{{ route('absensi.kode-hari-ini') }}" class="nav-item {{ request()->routeIs('absensi.kode-hari-ini') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
    <span x-show="sidebarOpen">Kode Absen Hari Ini</span>
</a>
@endif
```

jadi (tambah link jadwal-libur.approval di dalam gating `@if` yang sama):

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
```

- [ ] **Step 6: Lint semua file yang diubah**

Run:
```bash
php -l app/Http/Controllers/KaryawanController.php
php -l resources/views/karyawan/edit.blade.php
php -l resources/views/izin/index.blade.php
php -l resources/views/partials/sidebar-owner.blade.php
php -l resources/views/partials/sidebar-pipeline.blade.php
```
Expected: `No syntax errors detected` untuk kelima file.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KaryawanController.php resources/views/karyawan/edit.blade.php resources/views/izin/index.blade.php resources/views/partials/sidebar-owner.blade.php resources/views/partials/sidebar-pipeline.blade.php
git commit -m "feat: field Hari Libur Default di form karyawan + link navigasi jadwal libur"
```

**Catatan verifikasi manual (setelah deploy):** Owner buka edit karyawan, set Hari Libur Default buat 1-2 karyawan, simpan, cek tersimpan. Cek link "Jadwal Libur" muncul di halaman Izin Saya (karyawan), sidebar Owner, dan sidebar Supervisor (level 3).

---

## Ringkasan urutan deploy (setelah semua 7 task selesai & direview)

1. Push branch ke `main` **HANYA SETELAH** SQL manual (catatan di akhir Task 1) sudah dikonfirmasi jalan di phpMyAdmin production — pola sama seperti migrasi Telegram & kode-absen sebelumnya.
2. Setelah deploy, jalankan checklist verifikasi manual yang tercantum di tiap task (Task 3, 4, 6, 7).
3. Setelah dikonfirmasi jalan, baru pertimbangkan Task 1D lama (kalibrasi ulang KPI/gaji) kalau ada dampak susulan yang perlu diamati di siklus penggajian berikutnya.
