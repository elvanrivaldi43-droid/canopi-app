# Kode Absen Per-Karyawan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti kode absen bersama (satu kode buat semua karyawan) jadi kode per-karyawan, supaya kode milik satu orang tidak bisa dipakai orang lain untuk absen — menutup celah titip-absen.

**Architecture:** Perluas tabel `kode_absen` yang sudah ada dengan kolom `user_id` (bukan tabel baru). Cron `cron-kode-absen.php` generate + kirim 1 kode per karyawan aktif tiap pagi (bukan 1 kode global). Validasi di `AbsensiController` dicek terikat ke user yang login. Halaman baru khusus Owner/Supervisor menampilkan kode semua karyawan hari ini sebagai fallback buat yang belum connect Telegram.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, MySQL.

## Global Constraints

- Spec lengkap & disetujui: `docs/superpowers/specs/2026-08-05-kode-absen-per-karyawan-design.md` — baca dulu sebelum eksekusi, semua keputusan desain ada di sana.
- **VPS tempat kerja ini TIDAK punya `vendor/`, `.env`, atau koneksi DB** — kode yang menyentuh Eloquent/DB (semua task di plan ini) TIDAK BISA dites jalan beneran secara lokal. Verifikasi lokal terbatas pada `php -l` (cek sintaks). Verifikasi fungsional sebenarnya terjadi manual di production setelah deploy (pola sama seperti migrasi Telegram sebelumnya).
- Kode absen cuma dipakai di **absen masuk** (`AbsensiController::absenMasuk`, `validasiKode`) — TIDAK ada validasi kode di absen siang/pulang, jangan ditambahkan.
- Kode 6-karakter, format tetap `strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6)))` — sama seperti sekarang, jangan diubah.
- Baris `kode_absen` lama (`user_id` NULL, era sebelum fitur ini) dibiarkan sebagai riwayat, tidak dihapus/dibersihkan.
- Setiap selesai 1 task: commit sendiri dengan pesan jelas (jangan gabung banyak task tak berhubungan dalam 1 commit) — sesuai CLAUDE.md project.
- SQL harus idempotent (`IF NOT EXISTS`, aman kalau dijalankan ulang) — sesuai CLAUDE.md project.

---

## Task 1: Migrasi database + update model `KodeAbsen`

**Files:**
- Modify: `app/Models/KodeAbsen.php`
- Manual (bukan file repo): jalankan SQL di phpMyAdmin production

**Interfaces:**
- Produces: kolom `kode_absen.user_id` (BIGINT UNSIGNED, nullable) — dipakai Task 2-4. Method `KodeAbsen::kodeHariIniUntuk(User $user): string` dan `KodeAbsen::validasiUntuk(User $user, string $inputKode): bool` — tidak dipakai controller/cron manapun saat ini (diperbarui sekalian sesuai spec, cegah jadi jebakan kalau dipakai nanti), tapi method lama `kodeHariIni()`/`validasi()` (tanpa parameter user) harus dihapus, bukan dibiarkan nyampur dengan yang baru.

- [ ] **Step 1: Jalankan SQL migrasi di phpMyAdmin production (dilakukan Elvan, bukan lewat kode)**

SQL idempotent:
```sql
ALTER TABLE kode_absen ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE kode_absen ADD UNIQUE INDEX IF NOT EXISTS kode_absen_tanggal_user_unique (tanggal, user_id);
```

Kalau baris kedua (index) ditolak phpMyAdmin karena versi MySQL lebih lama dari 8.0.29, jalankan tanpa `IF NOT EXISTS` di baris itu saja:
```sql
ALTER TABLE kode_absen ADD UNIQUE INDEX kode_absen_tanggal_user_unique (tanggal, user_id);
```
(Kalau index sudah ada dari percobaan sebelumnya, MySQL akan tolak dengan error duplicate key name — aman diabaikan, sama seperti aturan error #1060 di CLAUDE.md.)

Verifikasi: jalankan `DESCRIBE kode_absen;` di phpMyAdmin, pastikan kolom `user_id` muncul.

- [ ] **Step 2: Update `app/Models/KodeAbsen.php`**

File saat ini:
```php
<?php
// FILE: app/Models/KodeAbsen.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeAbsen extends Model
{
    protected $table = 'kode_absen';
    protected $fillable = ['kode', 'tanggal'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Ambil kode hari ini, buat baru kalau belum ada
    public static function kodeHariIni(): string
    {
        $record = self::whereDate('tanggal', today())->first();
        if (!$record) {
            $kode   = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $record = self::create(['kode' => $kode, 'tanggal' => today()]);
        }
        return $record->kode;
    }

    // Validasi kode
    public static function validasi(string $inputKode): bool
    {
        return self::whereDate('tanggal', today())
                   ->where('kode', strtoupper(trim($inputKode)))
                   ->exists();
    }
}
```

Ganti jadi:
```php
<?php
// FILE: app/Models/KodeAbsen.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeAbsen extends Model
{
    protected $table = 'kode_absen';
    protected $fillable = ['kode', 'tanggal', 'user_id'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Ambil kode hari ini punya satu karyawan, buat baru kalau belum ada
    public static function kodeHariIniUntuk(User $user): string
    {
        $record = self::whereDate('tanggal', today())->where('user_id', $user->id)->first();
        if (!$record) {
            $kode   = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $record = self::create(['kode' => $kode, 'tanggal' => today(), 'user_id' => $user->id]);
        }
        return $record->kode;
    }

    // Validasi kode milik satu karyawan tertentu
    public static function validasiUntuk(User $user, string $inputKode): bool
    {
        return self::whereDate('tanggal', today())
                   ->where('user_id', $user->id)
                   ->where('kode', strtoupper(trim($inputKode)))
                   ->exists();
    }
}
```

- [ ] **Step 3: Cek sintaks PHP**

Run: `php -l app/Models/KodeAbsen.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Models/KodeAbsen.php
git commit -m "Kode absen per-karyawan: tambah user_id ke KodeAbsen, method jadi per-user"
```

---

## Task 2: Cron generate + kirim kode per-karyawan

**Files:**
- Modify: `public/cron-kode-absen.php`

**Interfaces:**
- Consumes: kolom `kode_absen.user_id` (Task 1), `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (sudah ada dari migrasi Telegram sebelumnya)
- Produces: satu baris `kode_absen` per karyawan aktif per hari — dipakai Task 3 (validasi) dan Task 4 (dashboard fallback)

- [ ] **Step 1: Ganti isi `public/cron-kode-absen.php`**

File saat ini:
```php
<?php
// FILE: public_html/app/public/cron-kode-absen.php
// Dijalankan via cron job jam 06:30 WIB (23:30 UTC hari sebelumnya)
// Otomatis generate kode harian + kirim WA ke semua karyawan

$key = $argv[1] ?? $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\KodeAbsen;
use App\Services\TelegramService;
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE KODE HARIAN
// ═══════════════════════════════════════════════════════

// Cek apakah kode hari ini sudah ada
$kodeHariIni = KodeAbsen::whereDate('tanggal', $tanggal)->first();

if (!$kodeHariIni) {
    // Generate kode 6 karakter alphanumeric
    $kode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
    $kodeHariIni = KodeAbsen::create([
        'kode'    => $kode,
        'tanggal' => $tanggal,
    ]);
    $log[] = "Kode hari ini: {$kode}";
} else {
    $kode = $kodeHariIni->kode;
    $log[] = "Kode sudah ada: {$kode}";
}

// ═══════════════════════════════════════════════════════
// KIRIM WA KE SEMUA KARYAWAN WAJIB ABSEN
// ═══════════════════════════════════════════════════════

$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->whereNotNull('telegram_chat_id')
                ->get();

$terkirim = 0;
$gagal    = 0;

foreach ($karyawan as $k) {
    $pesan = "🏠 *PUSAT KANOPI BSD*\n"
           . "━━━━━━━━━━━━━━━━━━\n"
           . "📅 " . $tanggal->translatedFormat('l, d F Y') . "\n\n"
           . "🔑 *KODE ABSEN HARI INI:*\n"
           . "┌─────────────┐\n"
           . "│   *{$kode}*   │\n"
           . "└─────────────┘\n\n"
           . "⏰ Absen masuk mulai jam *06:30*\n"
           . "📍 Pastikan kamu berada di lokasi kerja\n\n"
           . "Kode berlaku untuk hari ini saja.\n"
           . "_CanopiBSD System_";

    $result = app(TelegramService::class)->kirim($k->telegram_chat_id, $pesan);

    if ($result) {
        $terkirim++;
        $log[] = "✓ Terkirim ke: {$k->name}";
    } else {
        $gagal++;
        $log[] = "✗ Gagal ke: {$k->name}";
    }
}

// ═══════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════

echo '<pre style="background:#1a1a2e;color:lime;padding:20px;font-family:monospace;">';
echo "=== KODE ABSEN HARIAN ===\n";
echo "Waktu  : " . now()->format('d/m/Y H:i:s') . "\n";
echo "Kode   : {$kode}\n";
echo "Tanggal: " . $tanggal->format('d/m/Y') . "\n\n";
echo "--- HASIL PENGIRIMAN ---\n";
foreach ($log as $l) echo $l . "\n";
echo "\n✅ Terkirim : {$terkirim}\n";
echo "❌ Gagal    : {$gagal}\n";
echo "\n=== SELESAI ===";
echo '</pre>';
```

Ganti jadi:
```php
<?php
// FILE: public_html/app/public/cron-kode-absen.php
// Dijalankan via cron job jam 06:30 WIB (23:30 UTC hari sebelumnya)
// Otomatis generate kode harian PER KARYAWAN + kirim ke Telegram masing-masing

$key = $argv[1] ?? $_GET['key'] ?? '';
if ($key !== 'canopi_cron_2026') {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\KodeAbsen;
use App\Services\TelegramService;
use Carbon\Carbon;

$log = [];
$tanggal = today();

// ═══════════════════════════════════════════════════════
// GENERATE + KIRIM KODE PER KARYAWAN
// ═══════════════════════════════════════════════════════

$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->get();

$terkirim = 0;
$gagal    = 0;
$sudahAda = 0;

foreach ($karyawan as $k) {
    $existing = KodeAbsen::whereDate('tanggal', $tanggal)->where('user_id', $k->id)->first();

    if ($existing) {
        $kode = $existing->kode;
        $sudahAda++;
    } else {
        $kode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        KodeAbsen::create([
            'kode'    => $kode,
            'tanggal' => $tanggal,
            'user_id' => $k->id,
        ]);
    }

    if (!$k->telegram_chat_id) {
        $log[] = "⏭ Skip (belum connect Telegram): {$k->name} — kode: {$kode}";
        continue;
    }

    $pesan = "🏠 *PUSAT KANOPI BSD*\n"
           . "━━━━━━━━━━━━━━━━━━\n"
           . "📅 " . $tanggal->translatedFormat('l, d F Y') . "\n\n"
           . "🔑 *KODE ABSEN KAMU HARI INI:*\n"
           . "┌─────────────┐\n"
           . "│   *{$kode}*   │\n"
           . "└─────────────┘\n\n"
           . "⏰ Absen masuk mulai jam *06:30*\n"
           . "📍 Pastikan kamu berada di lokasi kerja\n\n"
           . "Kode ini cuma buat kamu, jangan dibagikan ke orang lain.\n"
           . "Berlaku untuk hari ini saja.\n"
           . "_CanopiBSD System_";

    $result = app(TelegramService::class)->kirim($k->telegram_chat_id, $pesan);

    if ($result) {
        $terkirim++;
        $log[] = "✓ Terkirim ke: {$k->name}";
    } else {
        $gagal++;
        $log[] = "✗ Gagal ke: {$k->name}";
    }
}

// ═══════════════════════════════════════════════════════
// OUTPUT
// ═══════════════════════════════════════════════════════

echo '<pre style="background:#1a1a2e;color:lime;padding:20px;font-family:monospace;">';
echo "=== KODE ABSEN HARIAN (PER KARYAWAN) ===\n";
echo "Waktu  : " . now()->format('d/m/Y H:i:s') . "\n";
echo "Tanggal: " . $tanggal->format('d/m/Y') . "\n\n";
echo "--- HASIL ---\n";
foreach ($log as $l) echo $l . "\n";
echo "\n✅ Terkirim   : {$terkirim}\n";
echo "❌ Gagal      : {$gagal}\n";
echo "♻️  Sudah ada  : {$sudahAda}\n";
echo "\n=== SELESAI ===";
echo '</pre>';
```

- [ ] **Step 2: Cek sintaks PHP**

Run: `php -l public/cron-kode-absen.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add public/cron-kode-absen.php
git commit -m "Kode absen per-karyawan: cron generate+kirim kode personal, bukan 1 kode global"
```

---

## Task 3: Validasi absen masuk terikat ke user login

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`

**Interfaces:**
- Consumes: kolom `kode_absen.user_id` (Task 1)

- [ ] **Step 1: Ganti validasi kode di `absenMasuk()`**

File saat ini (sekitar baris 125-132):
```php
        // Validasi kode absen (tetap wajib walau luar kota)
        $kode      = strtoupper(trim($request->kode));
        $kodeValid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                          ->where('kode', $kode)
                                          ->exists();
        if (!$kodeValid) {
            return response()->json(['success'=>false,'message'=>'❌ Kode absen salah! Cek kode di WhatsApp kamu.']);
        }
```

Ganti jadi:
```php
        // Validasi kode absen (tetap wajib walau luar kota) — harus kode milik user ini sendiri
        $kode      = strtoupper(trim($request->kode));
        $kodeValid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                          ->where('user_id', $user->id)
                                          ->where('kode', $kode)
                                          ->exists();
        if (!$kodeValid) {
            return response()->json(['success'=>false,'message'=>'❌ Kode absen salah! Cek kode di Telegram kamu.']);
        }
```

(Variabel `$user` sudah ada di method ini dari baris `$user = Auth::user();` di awal `absenMasuk()` — tidak perlu ditambah.)

- [ ] **Step 2: Ganti `validasiKode()`**

File saat ini:
```php
    public function validasiKode(Request $request)
    {
        $kode  = strtoupper(trim($request->kode ?? ''));
        $valid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                      ->where('kode', $kode)
                                      ->exists();
        return response()->json(['valid' => $valid]);
    }
```

Ganti jadi:
```php
    public function validasiKode(Request $request)
    {
        $user  = Auth::user();
        $kode  = strtoupper(trim($request->kode ?? ''));
        $valid = \App\Models\KodeAbsen::whereDate('tanggal', today())
                                      ->where('user_id', $user->id)
                                      ->where('kode', $kode)
                                      ->exists();
        return response()->json(['valid' => $valid]);
    }
```

- [ ] **Step 3: Cek sintaks PHP**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Pastikan tidak ada query kode absen yang lupa di-scope ke user**

Run: `grep -n "KodeAbsen::" app/Http/Controllers/AbsensiController.php`
Expected: 2 baris muncul (di `absenMasuk` dan `validasiKode`), keduanya mengandung `where('user_id',`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "Kode absen per-karyawan: validasi absen masuk terikat ke user login, cegah titip absen"
```

---

## Task 4: Halaman fallback kode hari ini (Owner + Supervisor)

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar-owner.blade.php`
- Create: `resources/views/absensi/kode-hari-ini.blade.php`

**Interfaces:**
- Consumes: kolom `kode_absen.user_id` (Task 1), baris kode yang dibuat cron (Task 2)
- Produces: route `GET /absensi/kode-hari-ini` (nama `absensi.kode-hari-ini`), dibatasi middleware `level:1,3`

- [ ] **Step 1: Tambah method `kodeHariIni()` di `AbsensiController.php`**

Tambahkan method baru setelah method `validasiKode()` (yang diubah di Task 3):
```php
    public function kodeHariIni()
    {
        $tanggal  = today();
        $karyawan = User::where('level', '!=', 1)
                        ->where('status', 'aktif')
                        ->orderBy('name')
                        ->get();

        $kodeList = \App\Models\KodeAbsen::whereDate('tanggal', $tanggal)
                                         ->whereNotNull('user_id')
                                         ->get()
                                         ->keyBy('user_id');

        $data = $karyawan->map(function ($k) use ($kodeList) {
            return [
                'nama'      => $k->name,
                'jabatan'   => $k->jabatan,
                'kode'      => $kodeList[$k->id]->kode ?? null,
                'connected' => (bool) $k->telegram_chat_id,
            ];
        });

        return view('absensi.kode-hari-ini', ['tanggal' => $tanggal, 'data' => $data]);
    }
```

(`User` sudah di-import di awal file (`use App\Models\User;`) — tidak perlu ditambah. `KodeAbsen` dipakai fully-qualified `\App\Models\KodeAbsen::`, konsisten dengan cara method lain di file ini memakainya.)

- [ ] **Step 2: Tambah route**

File saat ini (grup route absensi, `routes/web.php`):
```php
Route::middleware('auth')->prefix('absensi')->name('absensi.')->group(function () {
    Route::get('/',                         [AbsensiController::class, 'index'])->name('index');
    Route::get('/masuk',                    [AbsensiController::class, 'formMasuk'])->name('form-masuk');
    Route::post('/masuk',                   [AbsensiController::class, 'absenMasuk'])->name('masuk');
    Route::get('/siang',                    [AbsensiController::class, 'formSiang'])->name('form-siang');
    Route::post('/siang',                   [AbsensiController::class, 'absenSiang'])->name('siang');
    Route::get('/pulang',                   [AbsensiController::class, 'formPulang'])->name('form-pulang');
    Route::post('/pulang',                  [AbsensiController::class, 'absenPulang'])->name('pulang');
    Route::post('/cek-gps',                 [AbsensiController::class, 'cekGps'])->name('cek-gps');
    Route::get('/rekap',                    [AbsensiController::class, 'rekap'])->name('rekap');
    Route::post('/{id}/koreksi',            [AbsensiController::class, 'koreksi'])->name('koreksi');
    Route::post('/koreksi-baru/{userId}',   [AbsensiController::class, 'koreksiManual'])->name('koreksi-manual');
    Route::post('/validasi-kode',           [AbsensiController::class, 'validasiKode'])->name('validasi-kode');
    Route::get('/rekap-bulanan',            [AbsensiController::class, 'rekapBulanan'])->name('rekap-bulanan');
});
```

Ganti jadi (tambah 1 baris sebelum `});`):
```php
Route::middleware('auth')->prefix('absensi')->name('absensi.')->group(function () {
    Route::get('/',                         [AbsensiController::class, 'index'])->name('index');
    Route::get('/masuk',                    [AbsensiController::class, 'formMasuk'])->name('form-masuk');
    Route::post('/masuk',                   [AbsensiController::class, 'absenMasuk'])->name('masuk');
    Route::get('/siang',                    [AbsensiController::class, 'formSiang'])->name('form-siang');
    Route::post('/siang',                   [AbsensiController::class, 'absenSiang'])->name('siang');
    Route::get('/pulang',                   [AbsensiController::class, 'formPulang'])->name('form-pulang');
    Route::post('/pulang',                  [AbsensiController::class, 'absenPulang'])->name('pulang');
    Route::post('/cek-gps',                 [AbsensiController::class, 'cekGps'])->name('cek-gps');
    Route::get('/rekap',                    [AbsensiController::class, 'rekap'])->name('rekap');
    Route::post('/{id}/koreksi',            [AbsensiController::class, 'koreksi'])->name('koreksi');
    Route::post('/koreksi-baru/{userId}',   [AbsensiController::class, 'koreksiManual'])->name('koreksi-manual');
    Route::post('/validasi-kode',           [AbsensiController::class, 'validasiKode'])->name('validasi-kode');
    Route::get('/rekap-bulanan',            [AbsensiController::class, 'rekapBulanan'])->name('rekap-bulanan');
    Route::get('/kode-hari-ini',            [AbsensiController::class, 'kodeHariIni'])->middleware('level:1,3')->name('kode-hari-ini');
});
```

- [ ] **Step 3: Buat blade `resources/views/absensi/kode-hari-ini.blade.php`**

```blade
{{-- FILE: resources/views/absensi/kode-hari-ini.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Kode Absen Hari Ini')

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

@section('content')
<div style="max-width:700px;margin:0 auto;">

    <div class="stat-card" style="padding:16px;margin-bottom:16px;">
        <div style="font-size:13px;color:#94a3b8;">{{ $tanggal->translatedFormat('l, d F Y') }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:4px;">Kode ini cuma bisa dipakai oleh karyawan yang bersangkutan. Yang belum "Sudah terhubung" perlu dikasih tahu manual.</div>
    </div>

    <div class="stat-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#0f172a;">
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Nama</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Kode</th>
                    <th style="padding:10px 14px;text-align:left;color:#94a3b8;">Telegram</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $d)
                <tr style="border-top:1px solid #1e293b;">
                    <td style="padding:10px 14px;">
                        {{ $d['nama'] }}
                        <div style="font-size:11px;color:#64748b;">{{ $d['jabatan'] }}</div>
                    </td>
                    <td style="padding:10px 14px;font-family:monospace;font-weight:700;letter-spacing:1px;">
                        {{ $d['kode'] ?? '—' }}
                    </td>
                    <td style="padding:10px 14px;">
                        @if($d['connected'])
                            <span style="color:#34d399;">✅ Sudah terhubung</span>
                        @else
                            <span style="color:#f87171;">❌ Belum connect</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
```

- [ ] **Step 4: Tambah link di sidebar**

File saat ini (`resources/views/partials/sidebar-owner.blade.php`, sekitar baris 86-89):
```blade
<a href="{{ route('absensi.index') }}" class="nav-item {{ request()->routeIs('absensi.index') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    <span x-show="sidebarOpen">Absensi</span>
</a>
```

Ganti jadi (tambah 1 link baru setelahnya):
```blade
<a href="{{ route('absensi.index') }}" class="nav-item {{ request()->routeIs('absensi.index') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
    <span x-show="sidebarOpen">Absensi</span>
</a>
<a href="{{ route('absensi.kode-hari-ini') }}" class="nav-item {{ request()->routeIs('absensi.kode-hari-ini') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
    <span x-show="sidebarOpen">Kode Absen Hari Ini</span>
</a>
```

- [ ] **Step 5: Cek sintaks PHP**

Run: `php -l app/Http/Controllers/AbsensiController.php && php -l routes/web.php`
Expected: `No syntax errors detected` untuk keduanya.

(Blade tidak bisa di-lint dengan `php -l` — verifikasi visual dilakukan manual setelah deploy.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php routes/web.php resources/views/partials/sidebar-owner.blade.php resources/views/absensi/kode-hari-ini.blade.php
git commit -m "Kode absen per-karyawan: halaman fallback Owner/Supervisor buat kode hari ini"
```

---

## Verifikasi manual setelah deploy (Elvan, tidak bisa dites lokal)

1. Pastikan Task 1 (SQL kolom `user_id` + unique index) sudah jalan sebelum push — kalau belum, cron di Task 2 dan validasi di Task 3 akan gagal (query ke kolom yang belum ada).
2. Push ke `main` → auto-deploy.
3. Trigger cron manual: buka `https://app.kanopibsd.co.id/cron-kode-absen.php?key=canopi_cron_2026` — cek output, harus muncul kode berbeda per karyawan di log (bukan 1 kode yang sama diulang).
4. Buka `https://app.kanopibsd.co.id/absensi/kode-hari-ini` (login sebagai Owner) — cek tabel muncul, kode & status Telegram tampil benar.
5. Tes absen masuk pakai kode sendiri (harus berhasil) lalu coba pakai kode karyawan lain kalau ada 2 akun buat tes (harus ditolak dengan pesan "Kode absen salah").
6. Cek Telegram — pesan kode masuk personal, format rapi.

## Ringkasan urutan eksekusi

Task 1 harus duluan (fondasi kolom DB). Task 2, 3, 4 saling independen satu sama lain setelah Task 1 selesai (beda file, beda tanggung jawab) — bisa dikerjakan berurutan atau via subagent-driven-development.
