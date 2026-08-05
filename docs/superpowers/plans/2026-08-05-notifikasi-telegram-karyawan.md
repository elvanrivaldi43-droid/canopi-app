# Notifikasi Telegram Karyawan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti semua notifikasi WA (Fonnte, sekarang banned) ke Telegram bot baru khusus karyawan, konsolidasi 9+ implementasi `kirimWA()` yang terduplikasi jadi 1 `TelegramService`, dan tutup 2 celah keamanan (token hardcode di kode publik, `FonnteService` yang tidak pernah ada).

**Architecture:** 1 service class murni PHP (`TelegramService::kirim(?string $chatId, string $pesan): bool`, tanpa dependensi Eloquent/facade di jalur utama — testable via `php` langsung tanpa DB) dipanggil dari semua controller/cron. Karyawan hubungkan akun via tombol di halaman Profil → deep-link Telegram → webhook baru menyimpan `chat_id` ke tabel `users`. Bot baru terpisah dari bot Owner (approval RAB) yang sudah ada.

**Tech Stack:** Laravel 13 / PHP 8.3, curl langsung (bukan `Http::` facade — terbukti lebih andal di shared hosting Niagahoster), Telegram Bot API.

## Global Constraints

- Spec lengkap & disetujui: `docs/superpowers/specs/2026-08-05-notifikasi-telegram-karyawan-design.md` — baca dulu sebelum eksekusi, semua keputusan desain ada di sana.
- **Token/secret TIDAK BOLEH hardcode di file kode manapun** — repo GitHub project ini PUBLIC, sudah terbukti token lama ke-expose. Semua token baru wajib lewat `.env` (`getenv()`), bukan `config/services.php` atau ditulis literal di controller.
- **VPS tempat kerja ini TIDAK punya `vendor/`, `.env`, atau koneksi DB** — semua kode yang dites lokal harus pure-PHP tanpa dependensi Composer/Eloquent/Laravel facade di jalur yang dites (pola sama seperti `tests/rangka/test_*.php` yang sudah ada, cukup `require` langsung file classnya). Kode yang butuh DB/HTTP real (webhook, kirim actual ke Telegram) diverifikasi manual di production lewat deploy (±1-2 menit) — TIDAK bisa dites lokal, jangan coba paksa.
- Setiap selesai 1 task: commit sendiri dengan pesan jelas (jangan gabung banyak task tak berhubungan dalam 1 commit) — sesuai CLAUDE.md project.
- `RabController::kirimNotifDeal()` (notif WA ke customer/lead) **TIDAK dipindah ke Telegram** — dinonaktifkan sementara (lihat Task 8), customer bukan `User` sistem.
- Semua pesan lama pakai format `*teks tebal*` (gaya WA) — dipertahankan apa adanya, `TelegramService` kirim dengan `parse_mode=Markdown` supaya tetap render tebal di Telegram tanpa perlu ubah teks pesan manapun.

---

## Task 1: Migrasi database + bersihkan konfigurasi Fonnte lama

**Files:**
- Modify: `config/services.php` (hapus entry `fonnte`)
- Modify: `app/Providers/AppServiceProvider.php` (hapus `putenv('FONNTE_TOKEN=...')`)
- Modify: `.env.example` (tambah dokumentasi var baru, tanpa nilai asli)
- Manual (bukan file repo): jalankan SQL di phpMyAdmin production

**Interfaces:**
- Produces: kolom `users.telegram_chat_id` (VARCHAR 50, nullable) dan `users.telegram_link_token` (VARCHAR 64, nullable) — dipakai Task 2-6.

- [ ] **Step 1: Jalankan SQL migrasi di phpMyAdmin production (dilakukan Elvan, bukan lewat kode)**

SQL idempotent (aman dijalankan ulang, pola sama seperti kolom `panjang_batang_cm` sebelumnya):

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_link_token VARCHAR(64) NULL;
```

Verifikasi: jalankan `DESCRIBE users;` di phpMyAdmin, pastikan 2 kolom baru muncul.

- [ ] **Step 2: Hapus entry Fonnte dari `config/services.php`**

File saat ini:
```php
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
'fonnte' => [
    'token' => 'HWzyhsbFZVfawUfxXnoi',
],
];
```

Ganti jadi:
```php
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
```

- [ ] **Step 3: Hapus `putenv('FONNTE_TOKEN=...')` dari `AppServiceProvider.php`**

File saat ini:
```php
    public function boot(): void
    {
        putenv('FONNTE_TOKEN=ei3UqxbKS1rcxWsbGxqG');
    }
```

Ganti jadi:
```php
    public function boot(): void
    {
        //
    }
```

- [ ] **Step 4: Tambah dokumentasi var baru di `.env.example`**

Tambahkan di baris paling akhir file `.env.example`:
```
TELEGRAM_KARYAWAN_TOKEN=
TELEGRAM_KARYAWAN_BOT_USERNAME=
TELEGRAM_OWNER_TOKEN=
TELEGRAM_OWNER_CHAT_ID=
```

(Nilai asli TIDAK diisi di sini — ini cuma dokumentasi supaya developer lain tahu var apa yang dibutuhkan. Nilai asli diisi Elvan langsung di `.env` server, lihat Task 11 & 12.)

- [ ] **Step 5: Cek sintaks PHP**

Run: `php -l config/services.php && php -l app/Providers/AppServiceProvider.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 6: Commit**

```bash
git add config/services.php app/Providers/AppServiceProvider.php .env.example
git commit -m "Hapus config Fonnte lama, dokumentasikan env var Telegram baru"
```

---

## Task 2: Bangun `TelegramService`

**Files:**
- Create: `app/Services/TelegramService.php`
- Test: `tests/telegram/test_telegram_service.php`

**Interfaces:**
- Produces: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` — dipakai semua task berikutnya (3, 5-10).

- [ ] **Step 1: Tulis test (akan gagal karena class belum ada)**

Create `tests/telegram/test_telegram_service.php`:
```php
<?php
// Jalankan: php tests/telegram/test_telegram_service.php
require __DIR__ . '/../../app/Services/TelegramService.php';

use App\Services\TelegramService;

$svc = new TelegramService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$check('chat_id null -> skip, return false', $svc->kirim(null, 'tes'), false);
$check('chat_id string kosong -> skip, return false', $svc->kirim('', 'tes'), false);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal (class belum ada)**

Run: `php tests/telegram/test_telegram_service.php`
Expected: `Fatal error: Failed opening required '.../app/Services/TelegramService.php'`

- [ ] **Step 3: Buat `TelegramService`**

Create `app/Services/TelegramService.php`:
```php
<?php

namespace App\Services;

class TelegramService
{
    public function kirim(?string $chatId, string $pesan): bool
    {
        if (!$chatId) {
            return false;
        }

        $token = getenv('TELEGRAM_KARYAWAN_TOKEN');
        if (!$token) {
            return false;
        }

        try {
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'chat_id'    => $chatId,
                    'text'       => $pesan,
                    'parse_mode' => 'Markdown',
                ],
                CURLOPT_TIMEOUT => 20,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($result, true);
            return $data['ok'] ?? false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TelegramService gagal kirim: ' . $e->getMessage());
            return false;
        }
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php tests/telegram/test_telegram_service.php`
Expected:
```
PASS — chat_id null -> skip, return false (got false, exp false)
PASS — chat_id string kosong -> skip, return false (got false, exp false)

=== SEMUA TES LULUS ===
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/TelegramService.php tests/telegram/test_telegram_service.php
git commit -m "Tambah TelegramService, pengganti kirimWA Fonnte yang terduplikasi"
```

---

## Task 3: Webhook penghubung Telegram (`TelegramWebhookController`)

**Files:**
- Create: `app/Http/Controllers/TelegramWebhookController.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php` (kecualikan route ini dari CSRF — Telegram POST tanpa token CSRF Laravel)
- Test: `tests/telegram/test_webhook_parser.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)
- Produces: route `POST /telegram/karyawan/webhook` (nama `telegram.webhook.karyawan`); method statis `TelegramWebhookController::parseStartToken(string $text): ?string` (dipakai test saja).

- [ ] **Step 1: Tulis test parser (akan gagal karena class belum ada)**

Create `tests/telegram/test_webhook_parser.php`:
```php
<?php
// Jalankan: php tests/telegram/test_webhook_parser.php
require __DIR__ . '/../../app/Http/Controllers/Controller.php';
require __DIR__ . '/../../app/Http/Controllers/TelegramWebhookController.php';

use App\Http\Controllers\TelegramWebhookController;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$check('/start dengan token valid', TelegramWebhookController::parseStartToken('/start abc123XYZ'), 'abc123XYZ');
$check('/start tanpa token', TelegramWebhookController::parseStartToken('/start'), null);
$check('pesan bukan /start', TelegramWebhookController::parseStartToken('halo bot'), null);
$check('/start dengan spasi ekstra', TelegramWebhookController::parseStartToken('  /start   abc123XYZ  '), 'abc123XYZ');

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php tests/telegram/test_webhook_parser.php`
Expected: `Fatal error: Failed opening required '.../TelegramWebhookController.php'`

- [ ] **Step 3: Buat `TelegramWebhookController`**

Create `app/Http/Controllers/TelegramWebhookController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $text   = $request->input('message.text', '');
        $chatId = $request->input('message.chat.id');

        $token = self::parseStartToken($text);

        if ($token && $chatId) {
            $user = User::where('telegram_link_token', $token)->first();

            if ($user) {
                $user->telegram_chat_id    = (string) $chatId;
                $user->telegram_link_token = null;
                $user->save();

                app(TelegramService::class)->kirim(
                    (string) $chatId,
                    "✅ Berhasil terhubung, {$user->name}! Mulai sekarang notifikasi CanopiBSD akan masuk ke sini."
                );
            }
        }

        return response('OK', 200);
    }

    public static function parseStartToken(string $text): ?string
    {
        if (preg_match('/^\/start\s+(\S+)$/', trim($text), $m)) {
            return $m[1];
        }
        return null;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php tests/telegram/test_webhook_parser.php`
Expected: semua baris `PASS`, diakhiri `=== SEMUA TES LULUS ===`

- [ ] **Step 5: Daftarkan route**

Modify `routes/web.php` — tambah use statement setelah baris `use App\Http\Controllers\ProjectController;`:
```php
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TelegramWebhookController;
```

Tambah route baru setelah blok `// ─── REGISTRASI KARYAWAN (Publik, tanpa login) ─────────────` (dekat baris 28-30, di luar middleware `auth`):
```php
// ─── TELEGRAM WEBHOOK KARYAWAN (Publik, dipanggil server Telegram) ─────────────
Route::post('/telegram/karyawan/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook.karyawan');
```

- [ ] **Step 6: Kecualikan route ini dari verifikasi CSRF**

Modify `bootstrap/app.php` — Telegram mengirim POST tanpa token CSRF Laravel, kalau tidak dikecualikan webhook akan selalu ditolak 419.

File saat ini:
```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'level' => \App\Http\Middleware\CheckLevel::class,
        ]);
    })
```

Ganti jadi:
```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'level' => \App\Http\Middleware\CheckLevel::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'telegram/karyawan/webhook',
        ]);
    })
```

- [ ] **Step 7: Cek sintaks PHP semua file yang diubah**

Run: `php -l app/Http/Controllers/TelegramWebhookController.php && php -l routes/web.php && php -l bootstrap/app.php`
Expected: `No syntax errors detected` untuk ketiganya.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TelegramWebhookController.php routes/web.php bootstrap/app.php tests/telegram/test_webhook_parser.php
git commit -m "Tambah webhook Telegram untuk hubungkan chat_id karyawan"
```

> Verifikasi end-to-end webhook ini (beneran terima pesan dari Telegram) baru bisa dilakukan setelah bot dibuat & di-deploy — lihat Task 11.

---

## Task 4: Tombol "Hubungkan Telegram" di halaman Profil

**Files:**
- Modify: `app/Http/Controllers/ProfilController.php`
- Modify: `resources/views/profil/index.blade.php`

**Interfaces:**
- Consumes: kolom `users.telegram_chat_id`, `users.telegram_link_token` (Task 1)

- [ ] **Step 1: Generate token link & kirim ke view di `ProfilController::index()`**

File saat ini (`app/Http/Controllers/ProfilController.php`, awal file):
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Absensi;

class ProfilController extends Controller
{
    public function index()
    {
        $user     = Auth::user();
```

Ganti jadi:
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Absensi;
use App\Models\User;

class ProfilController extends Controller
{
    public function index()
    {
        $user     = Auth::user();

        if (!$user->telegram_chat_id && !$user->telegram_link_token) {
            $user->telegram_link_token = $this->generateTelegramLinkToken();
            $user->save();
        }

        $botUsername = getenv('TELEGRAM_KARYAWAN_BOT_USERNAME') ?: '';
```

Lalu cari baris `return view('profil.index', compact('user', 'stats'));` di akhir method `index()`, ganti jadi:
```php
        return view('profil.index', compact('user', 'stats', 'botUsername'));
```

Tambah method baru setelah method `index()` (sebelum method `update()`):
```php
    private function generateTelegramLinkToken(): string
    {
        do {
            $token = Str::random(32);
        } while (User::where('telegram_link_token', $token)->exists());

        return $token;
    }
```

- [ ] **Step 2: Tambah card "Hubungkan Telegram" di blade**

Modify `resources/views/profil/index.blade.php` — sisipkan card baru di antara card "Data Resmi" (berakhir `</div>` sebelum komentar `{{-- Form Edit Data Diri --}}`) dan card "Edit Data Diri".

Cari blok ini (akhir card Data Resmi):
```blade
    <div class="info-row">
      <span class="info-label">Email</span>
      <span class="info-value" style="font-size:12px;">{{ $user->email }}</span>
    </div>
  </div>

  {{-- Form Edit Data Diri --}}
```

Ganti jadi:
```blade
    <div class="info-row">
      <span class="info-label">Email</span>
      <span class="info-value" style="font-size:12px;">{{ $user->email }}</span>
    </div>
  </div>

  {{-- Hubungkan Telegram --}}
  <div class="card-dark">
    <div class="section-label">📲 Notifikasi Telegram</div>
    @if($user->telegram_chat_id)
      <div class="info-row" style="border-bottom:none;">
        <span class="info-value" style="color:#34d399;">✅ Sudah terhubung</span>
      </div>
    @else
      <p style="color:#94a3b8; font-size:12px; margin-bottom:12px;">
        Hubungkan akun Telegram kamu supaya notifikasi (izin, kasbon, gaji, dll) masuk ke Telegram.
      </p>
      <a href="https://t.me/{{ $botUsername }}?start={{ $user->telegram_link_token }}"
         style="display:block; text-align:center; padding:12px; background:#229ED9; color:#fff; border-radius:10px; font-weight:700; text-decoration:none; font-size:14px;">
        📲 Hubungkan Telegram
      </a>
    @endif
  </div>

  {{-- Form Edit Data Diri --}}
```

- [ ] **Step 3: Cek sintaks PHP**

Run: `php -l app/Http/Controllers/ProfilController.php`
Expected: `No syntax errors detected`

(Blade tidak bisa di-lint dengan `php -l` karena bukan PHP murni — verifikasi visual dilakukan Task 11 setelah deploy.)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ProfilController.php resources/views/profil/index.blade.php
git commit -m "Tambah tombol Hubungkan Telegram di halaman Profil"
```

---

## Task 5: Migrasi 3 controller (Kasbon, IzinAbsen, LogBensin) ke TelegramService

**Files:**
- Modify: `app/Http/Controllers/KasbonKaryawanController.php`
- Modify: `app/Http/Controllers/IzinAbsenController.php`
- Modify: `app/Http/Controllers/LogBensinController.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)

- [ ] **Step 1: `KasbonKaryawanController.php` — tambah import**

File saat ini:
```php
use App\Models\Kasbon;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\Kasbon;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 2: `KasbonKaryawanController.php` — ganti call site**

File saat ini (sekitar baris 128-143):
```php
        // Notif WA ke owner
        $owner = User::where('level', 1)->first();
        if ($owner?->no_hp) {
            $warningText = $isWarning ? "\n⚠️ PERHATIAN: Gaji bersih akan < Rp 500.000 setelah cicilan!" : '';
            $this->kirimWA($owner->no_hp,
                "💳 *PENGAJUAN KASBON*\n" .
                "Dari: {$user->name} ({$user->jabatan})\n" .
                "Kategori: {$kasbon->kategoriLabel()}\n" .
                "Nominal: Rp " . number_format($request->nominal,0,',','.') . "\n" .
                "Cicilan: {$request->jumlah_cicilan}x Rp " . number_format($cicilanPerBulan,0,',','.') . "/bulan\n" .
                "Alasan: {$request->keterangan}" .
                $warningText . "\n" .
                "---\n" .
                "Approve/Tolak di: app.kanopibsd.co.id/penggajian/kasbon"
            );
        }
```
Ganti jadi:
```php
        // Notif Telegram ke owner
        $owner = User::where('level', 1)->first();
        if ($owner) {
            $warningText = $isWarning ? "\n⚠️ PERHATIAN: Gaji bersih akan < Rp 500.000 setelah cicilan!" : '';
            app(TelegramService::class)->kirim($owner->telegram_chat_id,
                "💳 *PENGAJUAN KASBON*\n" .
                "Dari: {$user->name} ({$user->jabatan})\n" .
                "Kategori: {$kasbon->kategoriLabel()}\n" .
                "Nominal: Rp " . number_format($request->nominal,0,',','.') . "\n" .
                "Cicilan: {$request->jumlah_cicilan}x Rp " . number_format($cicilanPerBulan,0,',','.') . "/bulan\n" .
                "Alasan: {$request->keterangan}" .
                $warningText . "\n" .
                "---\n" .
                "Approve/Tolak di: app.kanopibsd.co.id/penggajian/kasbon"
            );
        }
```

- [ ] **Step 3: `KasbonKaryawanController.php` — hapus private method `kirimWA`**

Hapus blok ini di akhir file (sebelum `}` penutup class):
```php
    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;
        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
```

- [ ] **Step 4: `IzinAbsenController.php` — tambah import**

File saat ini:
```php
use App\Models\IzinAbsen;
use App\Models\Absensi;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\IzinAbsen;
use App\Models\Absensi;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 5: `IzinAbsenController.php` — ganti 3 call site**

Call site 1 (sekitar baris 221-229):
```php
        $karyawan = User::find($request->user_id);
        if ($karyawan?->no_hp) {
            $pesan = "📋 *INFO DINAS LUAR*\n"
                   . "Kamu dijadwalkan dinas luar pada ".($izin->tanggal->format('d/m/Y'))."\n"
                   . "Keterangan: {$request->alasan}\n"
                   . "GPS absen bebas pada hari tersebut.";
            $this->kirimWA($karyawan->no_hp, $pesan);
        }
```
Ganti jadi:
```php
        $karyawan = User::find($request->user_id);
        if ($karyawan) {
            $pesan = "📋 *INFO DINAS LUAR*\n"
                   . "Kamu dijadwalkan dinas luar pada ".($izin->tanggal->format('d/m/Y'))."\n"
                   . "Keterangan: {$request->alasan}\n"
                   . "GPS absen bebas pada hari tersebut.";
            app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
        }
```

Call site 2, method `kirimNotifPengajuan` (sekitar baris 258-270):
```php
    private function kirimNotifPengajuan(User $user, IzinAbsen $izin): void
    {
        // Kirim ke mandor (level 3) dan owner (level 1)
        $penerima = User::whereIn('level', [1, 3])->whereNotNull('no_hp')->get();

        foreach ($penerima as $p) {
            $pesan = "📋 *PENGAJUAN {$izin->tipeLabel()}*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
                   . "Alasan: {$izin->alasan}\n"
                   . "---\n"
                   . "Approve/tolak di: app.kanopibsd.co.id/izin/approval";
            $this->kirimWA($p->no_hp, $pesan);
        }
    }
```
Ganti jadi:
```php
    private function kirimNotifPengajuan(User $user, IzinAbsen $izin): void
    {
        // Kirim ke mandor (level 3) dan owner (level 1) yang sudah connect Telegram
        $penerima = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();

        foreach ($penerima as $p) {
            $pesan = "📋 *PENGAJUAN {$izin->tipeLabel()}*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
                   . "Alasan: {$izin->alasan}\n"
                   . "---\n"
                   . "Approve/tolak di: app.kanopibsd.co.id/izin/approval";
            app(TelegramService::class)->kirim($p->telegram_chat_id, $pesan);
        }
    }
```

Call site 3, method `kirimNotifHasilIzin` (sekitar baris 273-287):
```php
    private function kirimNotifHasilIzin(IzinAbsen $izin, string $hasil): void
    {
        $user = $izin->user;
        if (!$user->no_hp) return;

        $icon  = $hasil === 'approved' ? '✅' : '❌';
        $label = $hasil === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "{$icon} *IZIN {$label}*\n"
               . "Tipe: {$izin->tipeLabel()}\n"
               . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
               . ($izin->catatan_mandor ? "Catatan: {$izin->catatan_mandor}\n" : '')
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/izin";
        $this->kirimWA($user->no_hp, $pesan);
    }
```
Ganti jadi:
```php
    private function kirimNotifHasilIzin(IzinAbsen $izin, string $hasil): void
    {
        $user = $izin->user;

        $icon  = $hasil === 'approved' ? '✅' : '❌';
        $label = $hasil === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "{$icon} *IZIN {$label}*\n"
               . "Tipe: {$izin->tipeLabel()}\n"
               . "Tanggal: {$izin->tanggal->format('d/m/Y')}\n"
               . ($izin->catatan_mandor ? "Catatan: {$izin->catatan_mandor}\n" : '')
               . "---\n"
               . "Detail di: app.kanopibsd.co.id/izin";
        app(TelegramService::class)->kirim($user->telegram_chat_id, $pesan);
    }
```

- [ ] **Step 6: `IzinAbsenController.php` — hapus private method `kirimWA`**

Hapus blok (format identik dengan Step 3 di atas, hanya beda lokasi file):
```php
    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;

        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch   = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
```

- [ ] **Step 7: `LogBensinController.php` — tambah import**

File saat ini:
```php
use App\Models\LogBensin;
use App\Models\Kendaraan;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\LogBensin;
use App\Models\Kendaraan;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 8: `LogBensinController.php` — ganti call site & hapus kirimWA**

File saat ini (method `kirimNotifBoros`, sekitar baris 202-247):
```php
    private function kirimNotifBoros(LogBensin $log, float $aktual, float $standar): void
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $driver   = $log->driver->name ?? '-';
        $kend     = $log->kendaraan->nama ?? '-';
        $plat     = $log->kendaraan->plat ?? '-';
        $tujuan   = $log->tujuan;
        $tanggal  = $log->tanggal->format('d/m/Y');
        $selisih  = round($standar - $aktual, 2);

        $pesan = "⛽ *PERINGATAN BBM BOROS*\n\n"
               . "Driver: {$driver}\n"
               . "Kendaraan: {$kend} ({$plat})\n"
               . "Tujuan: {$tujuan}\n"
               . "Tanggal: {$tanggal}\n\n"
               . "Konsumsi aktual: *{$aktual} km/liter*\n"
               . "Standar: {$standar} km/liter\n"
               . "Selisih: -{$selisih} km/liter\n\n"
               . "_Cek kondisi kendaraan atau cara mengemudi._\n\n"
               . "_CanopiBSD v2_";

        $this->kirimWA($owner->no_hp, $pesan);
    }
```

```php
    private function kirimWA(string $noHp, string $pesan): void
    {
        try {
            $token = getenv('FONNTE_TOKEN');
            if (!$token) return;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
                CURLOPT_HTTPHEADER => ["Authorization: {$token}"],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // silent fail
        }
    }
```

Ganti kedua blok itu jadi satu method saja (hapus `kirimWA` sepenuhnya, ganti isi `kirimNotifBoros`):
```php
    private function kirimNotifBoros(LogBensin $log, float $aktual, float $standar): void
    {
        $owner = User::where('level', 1)->first();
        if (!$owner) return;

        $driver   = $log->driver->name ?? '-';
        $kend     = $log->kendaraan->nama ?? '-';
        $plat     = $log->kendaraan->plat ?? '-';
        $tujuan   = $log->tujuan;
        $tanggal  = $log->tanggal->format('d/m/Y');
        $selisih  = round($standar - $aktual, 2);

        $pesan = "⛽ *PERINGATAN BBM BOROS*\n\n"
               . "Driver: {$driver}\n"
               . "Kendaraan: {$kend} ({$plat})\n"
               . "Tujuan: {$tujuan}\n"
               . "Tanggal: {$tanggal}\n\n"
               . "Konsumsi aktual: *{$aktual} km/liter*\n"
               . "Standar: {$standar} km/liter\n"
               . "Selisih: -{$selisih} km/liter\n\n"
               . "_Cek kondisi kendaraan atau cara mengemudi._\n\n"
               . "_CanopiBSD v2_";

        app(TelegramService::class)->kirim($owner->telegram_chat_id, $pesan);
    }
```

- [ ] **Step 9: Cek sintaks PHP ketiga file**

Run: `php -l app/Http/Controllers/KasbonKaryawanController.php && php -l app/Http/Controllers/IzinAbsenController.php && php -l app/Http/Controllers/LogBensinController.php`
Expected: `No syntax errors detected` untuk ketiganya.

- [ ] **Step 10: Pastikan tidak ada sisa referensi Fonnte**

Run: `grep -n "kirimWA\|FONNTE\|fonnte" app/Http/Controllers/KasbonKaryawanController.php app/Http/Controllers/IzinAbsenController.php app/Http/Controllers/LogBensinController.php`
Expected: tidak ada output (kosong).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/KasbonKaryawanController.php app/Http/Controllers/IzinAbsenController.php app/Http/Controllers/LogBensinController.php
git commit -m "Migrasi KasbonKaryawan, IzinAbsen, LogBensin ke TelegramService"
```

---

## Task 6: Migrasi 3 controller (Absensi, LuarKota, Penggajian) ke TelegramService

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `app/Http/Controllers/LuarKotaController.php`
- Modify: `app/Http/Controllers/PenggajianController.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)

- [ ] **Step 1: `AbsensiController.php` — tambah import**

File saat ini:
```php
use App\Models\Absensi;
use App\Models\User;
use App\Models\LuarKota;
```
Ganti jadi:
```php
use App\Models\Absensi;
use App\Models\User;
use App\Models\LuarKota;
use App\Services\TelegramService;
```

- [ ] **Step 2: `AbsensiController.php` — ganti call site & hapus kirimWA**

File saat ini (sekitar baris 558-575):
```php
    private function kirimNotifKendala(User $user,Absensi $absen,Request $request): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('no_hp')->get();
        $jenisLabel=self::JENIS_KENDALA[$request->jenis_kendala]??$request->jenis_kendala;
        foreach ($penerima as $p) {
            $this->kirimWA($p->no_hp,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nKendala: {$jenisLabel}\nKeterangan: {$request->deskripsi_kendala}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }

    private function kirimWA(string $noHp,string $pesan): void
    {
        $token=env('FONNTE_TOKEN','');
        if (!$token) return;
        $noHp=preg_replace('/^0/','62',preg_replace('/[^0-9]/','',$noHp));
        $ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>'https://api.fonnte.com/send',CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['target'=>$noHp,'message'=>$pesan],CURLOPT_HTTPHEADER=>['Authorization: '.$token]]);
        curl_exec($ch); curl_close($ch);
    }
}
```
Ganti jadi:
```php
    private function kirimNotifKendala(User $user,Absensi $absen,Request $request): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('telegram_chat_id')->get();
        $jenisLabel=self::JENIS_KENDALA[$request->jenis_kendala]??$request->jenis_kendala;
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nKendala: {$jenisLabel}\nKeterangan: {$request->deskripsi_kendala}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }
}
```

- [ ] **Step 3: `LuarKotaController.php` — tambah import**

File saat ini:
```php
use App\Models\LuarKota;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\LuarKota;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 4: `LuarKotaController.php` — ganti 2 call site & hapus kirimWA**

Call site 1, method `kirimNotifAktivasi` (sekitar baris 160-182):
```php
        $pesan = "✈️ *MODE LUAR KOTA DIAKTIFKAN*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Kamu telah didaftarkan sebagai *Luar Kota*:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n"
               . "🗓️ Durasi: {$durasi} hari\n\n"
               . "Selama periode ini, absensi kamu tetap berjalan normal.\n"
               . "GPS akan dicatat tapi tidak mempengaruhi validasi.\n\n"
               . "_CanopiBSD v2_";

        $this->kirimWA($karyawan->no_hp, $pesan);
    }
```
Ganti jadi (ganti hanya baris terakhir):
```php
        $pesan = "✈️ *MODE LUAR KOTA DIAKTIFKAN*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Kamu telah didaftarkan sebagai *Luar Kota*:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n"
               . "🗓️ Durasi: {$durasi} hari\n\n"
               . "Selama periode ini, absensi kamu tetap berjalan normal.\n"
               . "GPS akan dicatat tapi tidak mempengaruhi validasi.\n\n"
               . "_CanopiBSD v2_";

        app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
    }
```

Call site 2, method `kirimNotifUpdate` (sekitar baris 184-198):
```php
        $pesan = "✈️ *UPDATE LUAR KOTA*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Jadwal luar kota kamu diperbarui:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n\n"
               . "_CanopiBSD v2_";

        $this->kirimWA($karyawan->no_hp, $pesan);
    }
```
Ganti jadi:
```php
        $pesan = "✈️ *UPDATE LUAR KOTA*\n\n"
               . "Halo {$karyawan->name}!\n\n"
               . "Jadwal luar kota kamu diperbarui:\n\n"
               . "📍 Lokasi: {$lk->lokasi}\n"
               . "📅 Mulai: {$mulai}\n"
               . "📅 Selesai: {$selesai}\n\n"
               . "_CanopiBSD v2_";

        app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
    }
```

Hapus method `kirimWA` (di akhir file):
```php
    private function kirimWA(string $noHp, string $pesan): void
    {
        try {
            $token = getenv('FONNTE_TOKEN');
            if (!$token) return;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
                CURLOPT_HTTPHEADER     => ["Authorization: {$token}"],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {}
    }
```

- [ ] **Step 5: `PenggajianController.php` — tambah import**

File saat ini:
```php
use App\Models\TabunganKaryawan;
use App\Services\GajiService;
```
Ganti jadi:
```php
use App\Models\TabunganKaryawan;
use App\Services\GajiService;
use App\Services\TelegramService;
```

- [ ] **Step 6: `PenggajianController.php` — ganti 3 call site & hapus kirimWA**

Call site 1, method `bayar` (sekitar baris 143-152):
```php
            $user = $slip->user;
            if ($user->no_hp) {
                $pesan = "💰 *SLIP GAJI*\n"
                       . "Hai {$user->name}!\n"
                       . "{$slip->periodeLabel()} {$slip->namaBulan()} {$slip->tahun}\n"
                       . "Gaji bersih: Rp " . number_format($slip->gaji_bersih, 0, ',', '.') . "\n"
                       . "---\n"
                       . "Lihat slip lengkap di: app.kanopibsd.co.id/penggajian/slip-saya";
                $this->kirimWA($user->no_hp, $pesan);
            }
```
Ganti jadi:
```php
            $user = $slip->user;
            if ($user) {
                $pesan = "💰 *SLIP GAJI*\n"
                       . "Hai {$user->name}!\n"
                       . "{$slip->periodeLabel()} {$slip->namaBulan()} {$slip->tahun}\n"
                       . "Gaji bersih: Rp " . number_format($slip->gaji_bersih, 0, ',', '.') . "\n"
                       . "---\n"
                       . "Lihat slip lengkap di: app.kanopibsd.co.id/penggajian/slip-saya";
                app(TelegramService::class)->kirim($user->telegram_chat_id, $pesan);
            }
```

Call site 2, method `kasbonSetujui` (sekitar baris 269-278):
```php
        $karyawan = $kasbon->user;
        if ($karyawan?->no_hp) {
            $this->kirimWA($karyawan->no_hp,
                "✅ *KASBON DISETUJUI*\n" .
                "Hai {$karyawan->name}, kasbon kamu disetujui!\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Cicilan: {$kasbon->jumlah_cicilan}x Rp " . number_format($kasbon->cicilan_per_bulan,0,',','.') . "/bulan\n" .
                "Cicilan mulai dipotong dari gaji bulan depan.\n" .
                "---\n" .
                "Detail: app.kanopibsd.co.id/kasbon-saya"
            );
        }
```
Ganti jadi:
```php
        $karyawan = $kasbon->user;
        if ($karyawan) {
            app(TelegramService::class)->kirim($karyawan->telegram_chat_id,
                "✅ *KASBON DISETUJUI*\n" .
                "Hai {$karyawan->name}, kasbon kamu disetujui!\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Cicilan: {$kasbon->jumlah_cicilan}x Rp " . number_format($kasbon->cicilan_per_bulan,0,',','.') . "/bulan\n" .
                "Cicilan mulai dipotong dari gaji bulan depan.\n" .
                "---\n" .
                "Detail: app.kanopibsd.co.id/kasbon-saya"
            );
        }
```

Call site 3, method `kasbonTolak` (sekitar baris 303-312):
```php
        $karyawan = $kasbon->user;
        if ($karyawan?->no_hp) {
            $this->kirimWA($karyawan->no_hp,
                "❌ *KASBON DITOLAK*\n" .
                "Hai {$karyawan->name}, kasbon kamu ditolak.\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Alasan: {$request->alasan}\n" .
                "---\n" .
                "Hubungi owner jika ada pertanyaan."
```
Ganti jadi:
```php
        $karyawan = $kasbon->user;
        if ($karyawan) {
            app(TelegramService::class)->kirim($karyawan->telegram_chat_id,
                "❌ *KASBON DITOLAK*\n" .
                "Hai {$karyawan->name}, kasbon kamu ditolak.\n" .
                "Nominal: Rp " . number_format($kasbon->nominal,0,',','.') . "\n" .
                "Alasan: {$request->alasan}\n" .
                "---\n" .
                "Hubungi owner jika ada pertanyaan."
```

(Baris `);` dan `}` penutup setelahnya TIDAK berubah, biarkan seperti semula.)

Hapus method `kirimWA` (di akhir file, sebelum `}` penutup class):
```php
    private function kirimWA(string $noHp, string $pesan): void
    {
        $token = env('FONNTE_TOKEN', '');
        if (!$token) return;
        $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
            CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
```

- [ ] **Step 7: Cek sintaks PHP ketiga file**

Run: `php -l app/Http/Controllers/AbsensiController.php && php -l app/Http/Controllers/LuarKotaController.php && php -l app/Http/Controllers/PenggajianController.php`
Expected: `No syntax errors detected` untuk ketiganya.

- [ ] **Step 8: Pastikan tidak ada sisa referensi Fonnte**

Run: `grep -n "kirimWA\|FONNTE\|fonnte" app/Http/Controllers/AbsensiController.php app/Http/Controllers/LuarKotaController.php app/Http/Controllers/PenggajianController.php`
Expected: kosong.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php app/Http/Controllers/LuarKotaController.php app/Http/Controllers/PenggajianController.php
git commit -m "Migrasi Absensi, LuarKota, Penggajian ke TelegramService"
```

---

## Task 7: Migrasi TugasHarian & Project ke TelegramService

**Files:**
- Modify: `app/Http/Controllers/TugasHarianController.php`
- Modify: `app/Http/Controllers/ProjectController.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)

- [ ] **Step 1: `TugasHarianController.php` — tambah import**

File saat ini:
```php
use App\Models\TugasHarian;
use App\Models\TugasAssignee;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\TugasHarian;
use App\Models\TugasAssignee;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 2: `TugasHarianController.php` — ganti 2 call site & hapus kirimWA**

Call site 1 (sekitar baris 251-259):
```php
            $pembuat  = $tugas->pembuat;
            if ($pembuat && $pembuat->no_hp) {
                $label  = $request->status === 'selesai' ? '✅ SELESAI' : '❌ TIDAK SELESAI';
                $catatan = $request->catatan_karyawan ? "\nCatatan: {$request->catatan_karyawan}" : '';
                $pesan  = "📋 *Update Tugas*\n\n*{$tugas->judul}*\n\n{$label} oleh {$user->name}{$catatan}\n\nTanggal: " . now()->format('d/m/Y H:i');
                $this->kirimWA($pembuat->no_hp, $pesan);
            }
```
Ganti jadi:
```php
            $pembuat  = $tugas->pembuat;
            if ($pembuat) {
                $label  = $request->status === 'selesai' ? '✅ SELESAI' : '❌ TIDAK SELESAI';
                $catatan = $request->catatan_karyawan ? "\nCatatan: {$request->catatan_karyawan}" : '';
                $pesan  = "📋 *Update Tugas*\n\n*{$tugas->judul}*\n\n{$label} oleh {$user->name}{$catatan}\n\nTanggal: " . now()->format('d/m/Y H:i');
                app(TelegramService::class)->kirim($pembuat->telegram_chat_id, $pesan);
            }
```

Call site 2, method `kirimNotifWA` (sekitar baris 279-300):
```php
    private function kirimNotifWA(User $karyawan, TugasHarian $tugas)
    {
        $tanggal = \Carbon\Carbon::parse($tugas->tanggal)->translatedFormat('l, d F Y');
        $jam     = $tugas->jam_mulai ? ' pukul ' . substr($tugas->jam_mulai, 0, 5) : '';
        $lokasi  = $tugas->lokasi ? "\nLokasi: {$tugas->lokasi}" : '';
        $desk    = $tugas->deskripsi ? "\nDetail: {$tugas->deskripsi}" : '';

        $prioritasEmoji = match($tugas->prioritas) {
            'tinggi' => '🔴',
            'sedang' => '🟡',
            'rendah' => '🟢',
            default  => '⚪',
        };

        $pesan = "📋 *TUGAS BARU*\n\nHalo {$karyawan->name}!\n\nKamu mendapat tugas baru:\n\n*{$prioritasEmoji} {$tugas->judul}*\n\nTanggal: {$tanggal}{$jam}{$lokasi}{$desk}\n\nSilakan buka aplikasi untuk update status tugas.\n\n_CanopiBSD v2_";

        $this->kirimWA($karyawan->no_hp, $pesan);
    }
```
Ganti baris terakhir jadi:
```php
        app(TelegramService::class)->kirim($karyawan->telegram_chat_id, $pesan);
    }
```

Hapus method `kirimWA` (setelah method di atas):
```php
    // ----------------------------------------------------------------
    // PRIVATE — kirim WA via Fonnte
    // ----------------------------------------------------------------
    private function kirimWA(string $noHp, string $pesan): void
    {
        try {
            $token = getenv('FONNTE_TOKEN');
            if (!$token) return;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'target'  => $noHp,
                    'message' => $pesan,
                ],
                CURLOPT_HTTPHEADER => ["Authorization: {$token}"],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // Silent fail — tidak mengganggu flow utama
        }
    }
```

- [ ] **Step 3: `ProjectController.php` — tambah import**

File saat ini:
```php
use App\Models\PipelineLead;
use App\Models\User;
```
Ganti jadi:
```php
use App\Models\PipelineLead;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 4: `ProjectController.php` — ganti 2 call site, hapus kirimWA**

File saat ini (method `notifKondisiKhusus` & `notifMelebihiRab` & `kirimWA`, sekitar baris 365-419):
```php
    // ============================================================
    // NOTIFIKASI WA (via Fonnte)
    // ============================================================
    private function notifKondisiKhusus(Project $project, RateKondisi $rate)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $pesan = "🔔 *Project Baru - Kondisi Khusus*\n\n"
            . "Project: *{$project->kode_project}*\n"
            . "Customer: {$project->nama_customer}\n"
            . "Jenis: {$project->jenis_project}\n"
            . "Nilai: Rp " . number_format($project->nilai_kontrak, 0, ',', '.') . "\n\n"
            . "⚠️ Kondisi Kerja: *{$rate->nama}* (×{$rate->multiplier})\n"
            . "Rate tukang: Rp " . number_format($rate->rate_tukang_final, 0, ',', '.') . "/hari\n"
            . "Rate kenek: Rp " . number_format($rate->rate_kenek_final, 0, ',', '.') . "/hari\n\n"
            . "Silakan login untuk approve kondisi kerja ini.";

        $this->kirimWA($owner->no_hp, $pesan);
    }

    private function notifMelebihiRab(Project $project, ProjectMaterial $material, $rabItem)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $rabNominal = $rabItem ? 'Rp ' . number_format($rabItem->total_pokok, 0, ',', '.') : '-';

        $pesan = "⚠️ *Pembelian Melebihi RAB*\n\n"
            . "Project: *{$project->kode_project}* - {$project->nama_customer}\n\n"
            . "Material: *{$material->nama_material}*\n"
            . "Pembelian baru: Rp " . number_format($material->total, 0, ',', '.') . "\n"
            . "Batas RAB: {$rabNominal}\n\n"
            . "Silakan login untuk approve atau tolak pembelian ini.";

        $this->kirimWA($owner->no_hp, $pesan);
    }
```
Ganti jadi:
```php
    // ============================================================
    // NOTIFIKASI TELEGRAM
    // ============================================================
    private function notifKondisiKhusus(Project $project, RateKondisi $rate)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner) return;

        $pesan = "🔔 *Project Baru - Kondisi Khusus*\n\n"
            . "Project: *{$project->kode_project}*\n"
            . "Customer: {$project->nama_customer}\n"
            . "Jenis: {$project->jenis_project}\n"
            . "Nilai: Rp " . number_format($project->nilai_kontrak, 0, ',', '.') . "\n\n"
            . "⚠️ Kondisi Kerja: *{$rate->nama}* (×{$rate->multiplier})\n"
            . "Rate tukang: Rp " . number_format($rate->rate_tukang_final, 0, ',', '.') . "/hari\n"
            . "Rate kenek: Rp " . number_format($rate->rate_kenek_final, 0, ',', '.') . "/hari\n\n"
            . "Silakan login untuk approve kondisi kerja ini.";

        app(TelegramService::class)->kirim($owner->telegram_chat_id, $pesan);
    }

    private function notifMelebihiRab(Project $project, ProjectMaterial $material, $rabItem)
    {
        $owner = User::where('level', 1)->first();
        if (!$owner) return;

        $rabNominal = $rabItem ? 'Rp ' . number_format($rabItem->total_pokok, 0, ',', '.') : '-';

        $pesan = "⚠️ *Pembelian Melebihi RAB*\n\n"
            . "Project: *{$project->kode_project}* - {$project->nama_customer}\n\n"
            . "Material: *{$material->nama_material}*\n"
            . "Pembelian baru: Rp " . number_format($material->total, 0, ',', '.') . "\n"
            . "Batas RAB: {$rabNominal}\n\n"
            . "Silakan login untuk approve atau tolak pembelian ini.";

        app(TelegramService::class)->kirim($owner->telegram_chat_id, $pesan);
    }
```

(Method `kirimWA` yang lama di file ini sudah tercakup dalam blok yang dihapus di atas — pastikan method `private function kirimWA($noHp, $pesan) { ... }` beserta isinya juga terhapus, tidak cuma 2 caller-nya.)

- [ ] **Step 5: Cek sintaks PHP kedua file**

Run: `php -l app/Http/Controllers/TugasHarianController.php && php -l app/Http/Controllers/ProjectController.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 6: Pastikan tidak ada sisa referensi Fonnte**

Run: `grep -n "kirimWA\|FONNTE\|fonnte" app/Http/Controllers/TugasHarianController.php app/Http/Controllers/ProjectController.php`
Expected: kosong.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TugasHarianController.php app/Http/Controllers/ProjectController.php
git commit -m "Migrasi TugasHarian, Project ke TelegramService"
```

---

## Task 8: Migrasi RabController (kecuali notif customer)

**Files:**
- Modify: `app/Http/Controllers/RabController.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)

- [ ] **Step 1: Tambah import**

File saat ini:
```php
use App\Models\RabTtd;
```
Cari baris itu di daftar `use`, tambahkan setelahnya:
```php
use App\Models\RabTtd;
use App\Services\TelegramService;
```

(Kalau `RabTtd` bukan `use` terakhir di file, cukup tambahkan baris `use App\Services\TelegramService;` di manapun dalam blok `use` yang sudah ada.)

- [ ] **Step 2: Ganti `kirimNotifApprovalWa` (ke Owner)**

File saat ini (sekitar baris 410-427):
```php
    private function kirimNotifApprovalWa(RabHeader $rab, float $diskon): void
    {
        $owner = \App\Models\User::where('level', 1)->first();
        if (!$owner || !$owner->no_hp) return;

        $pembuat = Auth::user();
        $lead = $rab->lead;
        $pesan = "🔔 *Request Diskon RAB*\n\n"
            . "No RAB: {$rab->nomor_rab}\n"
            . "Customer: " . ($lead->nama_customer ?? '-') . "\n"
            . "Harga Normal: " . $rab->hargaFinalFormatted() . "\n"
            . "Diskon Diminta: {$diskon}%\n"
            . "Harga Diminta: Rp " . number_format($rab->harga_sebelum_diskon * (1 - $diskon/100), 0, ',', '.') . "\n"
            . "Oleh: {$pembuat->name}\n\n"
            . "Approve/tolak di: " . url('/rab/approval');

        $this->kirimWa($owner->no_hp, $pesan);
    }
```
Ganti jadi:
```php
    private function kirimNotifApprovalWa(RabHeader $rab, float $diskon): void
    {
        $owner = \App\Models\User::where('level', 1)->first();
        if (!$owner) return;

        $pembuat = Auth::user();
        $lead = $rab->lead;
        $pesan = "🔔 *Request Diskon RAB*\n\n"
            . "No RAB: {$rab->nomor_rab}\n"
            . "Customer: " . ($lead->nama_customer ?? '-') . "\n"
            . "Harga Normal: " . $rab->hargaFinalFormatted() . "\n"
            . "Diskon Diminta: {$diskon}%\n"
            . "Harga Diminta: Rp " . number_format($rab->harga_sebelum_diskon * (1 - $diskon/100), 0, ',', '.') . "\n"
            . "Oleh: {$pembuat->name}\n\n"
            . "Approve/tolak di: " . url('/rab/approval');

        app(TelegramService::class)->kirim($owner->telegram_chat_id, $pesan);
    }
```

- [ ] **Step 3: Nonaktifkan `kirimNotifDeal` (notif customer, TIDAK bisa pindah Telegram)**

File saat ini (sekitar baris 429-444):
```php
    private function kirimNotifDeal(RabHeader $rab): void
    {
        $lead = $rab->lead;
        if (!$lead || !$lead->no_hp) return;

        $pesan = "Halo {$lead->nama_customer}, terima kasih sudah mempercayakan kanopi Anda kepada kami!\n\n"
            . "Detail pesanan:\n"
            . "No RAB: {$rab->nomor_rab}\n"
            . "Total: " . $rab->hargaFinalFormatted() . "\n\n"
            . "Silakan transfer DP ke:\n"
            . "Bank BCA: 1234567890\n"
            . "A/N: Pusat Kanopi BSD\n\n"
            . "Setelah transfer, mohon konfirmasi ke kami. Terima kasih!";

        $this->kirimWa($lead->no_hp, $pesan);
    }
```
Ganti jadi:
```php
    private function kirimNotifDeal(RabHeader $rab): void
    {
        // Dinonaktifkan sementara (2026-08-05) — customer/lead bukan User sistem,
        // tidak bisa "Hubungkan Telegram" seperti karyawan. Fonnte (WA) sudah banned.
        // Menunggu WhatsApp Business API resmi (roadmap CLAUDE.md #5).
        return;
    }
```

- [ ] **Step 4: Ganti `kirimNotifHasilApproval` (ke karyawan peminta diskon)**

File saat ini (sekitar baris 446-458):
```php
    private function kirimNotifHasilApproval(RabApprovalRequest $apr): void
    {
        $peminta = $apr->peminta;
        if (!$peminta || !$peminta->no_hp) return;

        $status = $apr->status === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "Info Request Diskon RAB {$apr->rab->nomor_rab}\n"
            . "Status: {$status}\n"
            . ($apr->catatan_owner ? "Catatan owner: {$apr->catatan_owner}" : '');

        $this->kirimWa($peminta->no_hp, $pesan);
    }
```
Ganti jadi:
```php
    private function kirimNotifHasilApproval(RabApprovalRequest $apr): void
    {
        $peminta = $apr->peminta;
        if (!$peminta) return;

        $status = $apr->status === 'approved' ? 'DISETUJUI' : 'DITOLAK';
        $pesan = "Info Request Diskon RAB {$apr->rab->nomor_rab}\n"
            . "Status: {$status}\n"
            . ($apr->catatan_owner ? "Catatan owner: {$apr->catatan_owner}" : '');

        app(TelegramService::class)->kirim($peminta->telegram_chat_id, $pesan);
    }
```

- [ ] **Step 5: Hapus method `kirimWa`**

File saat ini (sekitar baris 459-473):
```php
    private function kirimWa(string $noHp, string $pesan): void
    {
        try {
            $token = config('services.fonnte.token');
            if (!$token) return;
            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $token
            ])->post('https://api.fonnte.com/send', [
                'target'  => $noHp,
                'message' => $pesan,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WA RAB gagal: ' . $e->getMessage());
        }
    }
```
Hapus seluruh blok ini.

- [ ] **Step 6: Cek sintaks PHP**

Run: `php -l app/Http/Controllers/RabController.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Pastikan tidak ada sisa referensi Fonnte, dan `kirimNotifDeal` sudah early-return**

Run: `grep -n "kirimWa\|fonnte\|kirimNotifDeal" -A2 app/Http/Controllers/RabController.php`
Expected: tidak ada lagi `kirimWa`/`fonnte`; `kirimNotifDeal` muncul dengan isi `return;` di baris berikutnya.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/RabController.php
git commit -m "Migrasi RabController ke TelegramService, nonaktifkan notif WA customer"
```

---

## Task 9: Migrasi cron files (cron-kode-absen.php, cron-alpha.php)

**Files:**
- Modify: `public/cron-kode-absen.php`
- Modify: `public/cron-alpha.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2) — kedua file ini sudah bootstrap Laravel penuh (`bootstrap/app.php`), jadi `app(TelegramService::class)` bisa langsung dipakai.

- [ ] **Step 1: `cron-kode-absen.php` — ganti query recipient & call site**

File saat ini:
```php
use App\Models\User;
use App\Models\KodeAbsen;
use Carbon\Carbon;
```
Ganti jadi:
```php
use App\Models\User;
use App\Models\KodeAbsen;
use App\Services\TelegramService;
use Carbon\Carbon;
```

File saat ini:
```php
$karyawan = User::where('level', '!=', 1) // bukan owner
                ->where('status', 'aktif')
                ->whereNotNull('no_hp')
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

    $result = kirimWA($k->no_hp, $pesan);

    if ($result) {
        $terkirim++;
        $log[] = "✓ Terkirim ke: {$k->name} ({$k->no_hp})";
    } else {
        $gagal++;
        $log[] = "✗ Gagal ke: {$k->name} ({$k->no_hp})";
    }

    // Delay 1 detik antar kirim (hindari rate limit Fonnte)
    sleep(1);
}
```
Ganti jadi:
```php
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
```

(Baris `sleep(1)` dihapus — itu jaga-jaga rate limit Fonnte, Telegram Bot API tidak punya batasan seketat itu untuk volume 14 orang.)

- [ ] **Step 2: `cron-kode-absen.php` — hapus fungsi `kirimWA` di akhir file**

File saat ini:
```php
function kirimWA(string $noHp, string $pesan): bool
{
    $token = env('FONNTE_TOKEN', '');
    if (!$token) return false;

    $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);
    return $data['status'] ?? false;
}
```
Hapus seluruh fungsi ini (sudah digantikan `TelegramService`).

- [ ] **Step 3: `cron-alpha.php` — tambah import & ganti semua call site**

File saat ini:
```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use Carbon\Carbon;
```
Ganti jadi:
```php
use App\Models\User;
use App\Models\Absensi;
use App\Models\IzinAbsen;
use App\Services\TelegramService;
use Carbon\Carbon;
```

Call site 1 (blok jam 13:00, sekitar baris 68-79):
```php
            kirimWA($k->no_hp,
                "⚠️ *INFO ABSENSI*\n" .
                "Hai {$k->name}, kamu tercatat ALPHA hari ini ({$tanggal->format('d/m/Y')}) karena tidak absen masuk.\n" .
                "Jika ada kesalahan, hubungi mandor untuk koreksi."
            );

            $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('no_hp')->get();
            foreach ($mandorOwner as $m) {
                kirimWA($m->no_hp,
                    "❌ *ALPHA*\n" .
                    "{$k->name} ({$k->jabatan}) tidak masuk tanpa keterangan hari ini ({$tanggal->format('d/m/Y')})."
                );
            }
```
Ganti jadi:
```php
            app(TelegramService::class)->kirim($k->telegram_chat_id,
                "⚠️ *INFO ABSENSI*\n" .
                "Hai {$k->name}, kamu tercatat ALPHA hari ini ({$tanggal->format('d/m/Y')}) karena tidak absen masuk.\n" .
                "Jika ada kesalahan, hubungi mandor untuk koreksi."
            );

            $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();
            foreach ($mandorOwner as $m) {
                app(TelegramService::class)->kirim($m->telegram_chat_id,
                    "❌ *ALPHA*\n" .
                    "{$k->name} ({$k->jabatan}) tidak masuk tanpa keterangan hari ini ({$tanggal->format('d/m/Y')})."
                );
            }
```

Call site 2 (blok jam 20:00, sekitar baris 108-119):
```php
        kirimWA($k->no_hp,
            "⚠️ *INFO ABSENSI*\n" .
            "Hai {$k->name}, kamu tercatat ALPHA karena tidak absen pulang hari ini ({$tanggal->format('d/m/Y')}).\n" .
            "Hubungi mandor jika ada kesalahan."
        );

        $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('no_hp')->get();
        foreach ($mandorOwner as $m) {
            kirimWA($m->no_hp,
                "❌ *TIDAK ABSEN PULANG*\n" .
                "{$k->name} ({$k->jabatan}) tidak absen pulang hari ini — otomatis ALPHA."
            );
        }
```
Ganti jadi:
```php
        app(TelegramService::class)->kirim($k->telegram_chat_id,
            "⚠️ *INFO ABSENSI*\n" .
            "Hai {$k->name}, kamu tercatat ALPHA karena tidak absen pulang hari ini ({$tanggal->format('d/m/Y')}).\n" .
            "Hubungi mandor jika ada kesalahan."
        );

        $mandorOwner = User::whereIn('level', [1, 3])->whereNotNull('telegram_chat_id')->get();
        foreach ($mandorOwner as $m) {
            app(TelegramService::class)->kirim($m->telegram_chat_id,
                "❌ *TIDAK ABSEN PULANG*\n" .
                "{$k->name} ({$k->jabatan}) tidak absen pulang hari ini — otomatis ALPHA."
            );
        }
```

- [ ] **Step 4: `cron-alpha.php` — hapus fungsi `kirimWA` di akhir file**

File saat ini:
```php
function kirimWA(?string $noHp, string $pesan): void
{
    if (!$noHp) return;
    $token = env('FONNTE_TOKEN', '');
    if (!$token) return;

    $noHp = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHp));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['target' => $noHp, 'message' => $pesan],
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
    ]);
    curl_exec($ch);
}
```
Hapus seluruh fungsi ini.

- [ ] **Step 5: Cek sintaks PHP kedua file**

Run: `php -l public/cron-kode-absen.php && php -l public/cron-alpha.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 6: Pastikan tidak ada sisa referensi Fonnte**

Run: `grep -n "kirimWA\|FONNTE\|fonnte" public/cron-kode-absen.php public/cron-alpha.php`
Expected: kosong (fungsi global `kirimWA` sudah terhapus, semua call site sudah pakai `app(TelegramService::class)`).

- [ ] **Step 7: Commit**

```bash
git add public/cron-kode-absen.php public/cron-alpha.php
git commit -m "Migrasi cron kode-absen dan alpha ke TelegramService"
```

---

## Task 10: Fix bug FonnteService — migrasi KpiController & cron-kpi.php

**Files:**
- Modify: `app/Http/Controllers/KpiController.php`
- Modify: `public/cron-kpi.php`

**Interfaces:**
- Consumes: `App\Services\TelegramService::kirim(?string $chatId, string $pesan): bool` (Task 2)

- [ ] **Step 1: `KpiController.php` — ganti import**

File saat ini:
```php
use App\Models\User;
use App\Services\KpiService;
use App\Services\FonnteService;
use Carbon\Carbon;
```
Ganti jadi:
```php
use App\Models\User;
use App\Services\KpiService;
use App\Services\TelegramService;
use Carbon\Carbon;
```

- [ ] **Step 2: `KpiController.php` — ganti call site notif SP (method `konfirmasiSp`)**

File saat ini (sekitar baris 143-156):
```php
            // Notif WA ke karyawan
            $fonnte = new FonnteService();
            $user = $sp->user;
            if ($user && $user->no_hp) {
                $msg = "⚠️ *Surat Peringatan — " . strtoupper($sp->level_sp) . "*\n\n";
                $msg .= "Hai {$user->name},\n";
                $msg .= "Kamu menerima " . strtoupper($sp->level_sp) . " per tanggal " . now()->format('d/m/Y') . ".\n\n";
                $msg .= "Alasan: {$sp->alasan}\n\n";
                $msg .= "Pemulihan: Pertahankan poin kinerja ≥60 selama 3 bulan berturut untuk SP turun level.\n\n";
                $msg .= "Hubungi owner jika ada pertanyaan.\n";
                $msg .= "— Pusat Kanopi BSD";
                $fonnte->kirim($user->no_hp, $msg);
            }
```
Ganti jadi:
```php
            // Notif Telegram ke karyawan
            $user = $sp->user;
            if ($user) {
                $msg = "⚠️ *Surat Peringatan — " . strtoupper($sp->level_sp) . "*\n\n";
                $msg .= "Hai {$user->name},\n";
                $msg .= "Kamu menerima " . strtoupper($sp->level_sp) . " per tanggal " . now()->format('d/m/Y') . ".\n\n";
                $msg .= "Alasan: {$sp->alasan}\n\n";
                $msg .= "Pemulihan: Pertahankan poin kinerja ≥60 selama 3 bulan berturut untuk SP turun level.\n\n";
                $msg .= "Hubungi owner jika ada pertanyaan.\n";
                $msg .= "— Pusat Kanopi BSD";
                app(TelegramService::class)->kirim($user->telegram_chat_id, $msg);
            }
```

- [ ] **Step 3: `KpiController.php` — ganti call site notif hasil ujian**

File saat ini (sekitar baris 420-432):
```php
        // Notif WA hasil ujian
        if ($user && $user->no_hp) {
            $fonnte = new FonnteService();
            $periode = $sesi->periode === 'januari' ? 'Januari' : 'Juli';
            $msg = "📝 *Hasil Ujian {$periode} {$sesi->tahun}*\n\n";
            $msg .= "Hai {$user->name}!\n";
            $msg .= "Ujian kamu sudah selesai dinilai.\n\n";
            $msg .= "✅ Jawaban benar: {$benar}/{$sesi->jumlah_soal}\n";
            $msg .= "📊 Nilai: {$nilai}/100\n\n";
            $msg .= "Lihat detail rapor di: app.kanopibsd.co.id/kpi/ujian/hasil\n";
            $msg .= "— Pusat Kanopi BSD";
            $fonnte->kirim($user->no_hp, $msg);
        }
```
Ganti jadi:
```php
        // Notif Telegram hasil ujian
        if ($user) {
            $periode = $sesi->periode === 'januari' ? 'Januari' : 'Juli';
            $msg = "📝 *Hasil Ujian {$periode} {$sesi->tahun}*\n\n";
            $msg .= "Hai {$user->name}!\n";
            $msg .= "Ujian kamu sudah selesai dinilai.\n\n";
            $msg .= "✅ Jawaban benar: {$benar}/{$sesi->jumlah_soal}\n";
            $msg .= "📊 Nilai: {$nilai}/100\n\n";
            $msg .= "Lihat detail rapor di: app.kanopibsd.co.id/kpi/ujian/hasil\n";
            $msg .= "— Pusat Kanopi BSD";
            app(TelegramService::class)->kirim($user->telegram_chat_id, $msg);
        }
```

- [ ] **Step 4: `cron-kpi.php` — ganti import & call site notif karyawan**

File saat ini:
```php
use App\Services\KpiService;
use App\Services\FonnteService;
use App\Models\KpiKinerja;
use App\Models\User;
use Carbon\Carbon;
```
Ganti jadi:
```php
use App\Services\KpiService;
use App\Services\TelegramService;
use App\Models\KpiKinerja;
use App\Models\User;
use Carbon\Carbon;
```

File saat ini:
```php
    $namaBulan = Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY');
    $fonnte = new FonnteService();

    $kpiList = KpiKinerja::where('bulan', $bulan)
        ->where('tahun', $tahun)
        ->with('user')
        ->get();

    foreach ($kpiList as $kpi) {
        $user = $kpi->user;
        if (!$user || !$user->no_hp) continue;
```
Ganti jadi:
```php
    $namaBulan = Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY');

    $kpiList = KpiKinerja::where('bulan', $bulan)
        ->where('tahun', $tahun)
        ->with('user')
        ->get();

    foreach ($kpiList as $kpi) {
        $user = $kpi->user;
        if (!$user) continue;
```

File saat ini (akhir loop):
```php
        $fonnte->kirim($user->no_hp, $msg);
        echo "WA terkirim ke {$user->name}\n";
    }
```
Ganti jadi:
```php
        app(TelegramService::class)->kirim($user->telegram_chat_id, $msg);
        echo "Telegram terkirim ke {$user->name}\n";
    }
```

- [ ] **Step 5: `cron-kpi.php` — ganti call site notif owner**

File saat ini:
```php
    $owner = User::where('level', 1)->first();
    if ($owner && $owner->no_hp) {
```
Ganti jadi:
```php
    $owner = User::where('level', 1)->first();
    if ($owner) {
```

File saat ini (akhir blok owner):
```php
        $fonnte->kirim($owner->no_hp, $msgOwner);
        echo "WA ringkasan terkirim ke Owner\n";
    }
```
Ganti jadi:
```php
        app(TelegramService::class)->kirim($owner->telegram_chat_id, $msgOwner);
        echo "Telegram ringkasan terkirim ke Owner\n";
    }
```

- [ ] **Step 6: Cek sintaks PHP kedua file**

Run: `php -l app/Http/Controllers/KpiController.php && php -l public/cron-kpi.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 7: Pastikan tidak ada sisa referensi FonnteService**

Run: `grep -rn "FonnteService\|fonnte" app/Http/Controllers/KpiController.php public/cron-kpi.php`
Expected: kosong.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/KpiController.php public/cron-kpi.php
git commit -m "Fix bug FonnteService tidak pernah ada, migrasi KpiController+cron-kpi ke TelegramService"
```

---

## Task 11: Setup bot production & verifikasi end-to-end (aksi manual Elvan)

**Files:** tidak ada file kode baru — task ini murni setup manual + verifikasi lewat diagnostic endpoint sementara.

- [ ] **Step 1: Deploy semua task sebelumnya**

Pastikan Task 1-10 sudah di-commit & di-push ke `main` (auto-deploy ke production, ±1-2 menit). Cek: `git log --oneline -15` menunjukkan semua commit Task 1-10.

- [ ] **Step 2: Bikin bot baru via @BotFather (Elvan)**

1. Buka Telegram, chat ke `@BotFather`
2. `/newbot` → ikuti instruksi (nama bebas, username harus unik & berakhiran `bot`, misal `CanopiBSDKaryawanBot`)
3. BotFather balas dengan token (format `123456:ABC-DEF...`)
4. Catat token dan username bot ini untuk step berikutnya

- [ ] **Step 3: Isi `.env` di server (Elvan, lewat File Manager cPanel — bukan lewat deploy)**

Tambahkan baris ini ke `.env` production (jangan pernah taruh di file kode/commit):
```
TELEGRAM_KARYAWAN_TOKEN=<token_dari_botfather>
TELEGRAM_KARYAWAN_BOT_USERNAME=<username_bot_tanpa_@>
```

- [ ] **Step 4: Bersihkan cache Laravel supaya `.env` baru kebaca**

Buka: `https://app.kanopibsd.co.id/bersih-bersih.php?key=canopi2026`

- [ ] **Step 5: Daftarkan webhook (Elvan, 1x saja)**

Buka URL ini di browser (ganti `<TOKEN>` dengan token bot dari Step 2):
```
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://app.kanopibsd.co.id/telegram/karyawan/webhook
```
Expected response: `{"ok":true,"result":true,"description":"Webhook was set"}`

- [ ] **Step 6: Verifikasi alur hubung-Telegram end-to-end (Elvan, akun sendiri)**

1. Login ke `app.kanopibsd.co.id`, buka halaman **Profil**
2. Pastikan muncul card "📲 Notifikasi Telegram" dengan tombol "Hubungkan Telegram"
3. Tap tombol → Telegram terbuka ke bot yang benar → tap "Start"
4. Bot harus membalas "✅ Berhasil terhubung, ..."
5. Reload halaman Profil di browser → card harus berubah jadi "✅ Sudah terhubung"

- [ ] **Step 7: Verifikasi pengiriman notif nyata**

Trigger salah satu notifikasi yang sudah dimigrasi (paling gampang: ajukan kasbon dari akun test/sendiri, karena hanya butuh Owner sudah connect). Pastikan pesan masuk ke Telegram Owner dengan format `*tebal*` yang render benar (bukan asterisk mentah).

- [ ] **Step 8: Umumkan ke 14 karyawan (Elvan, di luar sistem)**

Setelah Step 6-7 terbukti jalan, informasikan ke semua karyawan untuk buka halaman Profil dan klik "Hubungkan Telegram". Sebelum mereka klik, notifikasi untuk mereka tetap di-skip diam-diam (tidak error) — sudah tervalidasi lewat desain `TelegramService::kirim()` di Task 2.

- [ ] **Step 9: Update `MEMORI_PROYEK.md` / `CLAUDE.md` dengan hasil verifikasi**

Catat tanggal, hasil tes, dan status rollout (berapa karyawan sudah connect) — bagian "STATUS TERKINI" di `CLAUDE.md` per instruksi project.

---

## Task 12: Rotasi & amankan token bot Telegram Owner (independen, kapan saja)

> Task ini terpisah dari migrasi utama — bisa dikerjakan kapan saja Elvan sudah revoke token lama via @BotFather. Tujuannya menutup token yang sudah ke-expose di histori commit publik (lihat spec, bagian "Temuan keamanan").

**Files:**
- Modify: `app/Http/Controllers/ApprovalController.php`

- [ ] **Step 1: Pastikan Elvan sudah revoke & dapat token baru**

Konfirmasi ke Elvan: token baru dari @BotFather (`/mybots` → bot approval RAB → API Token → Revoke current token) sudah dimasukkan ke `.env` production sebagai:
```
TELEGRAM_OWNER_TOKEN=<token_baru>
TELEGRAM_OWNER_CHAT_ID=8385647457
```
(Chat ID tidak berubah — cuma token yang di-rotate. Nilai chat ID di atas adalah yang sudah ada sekarang, dipindah ke `.env` sekalian untuk konsistensi, bukan rahasia yang perlu diganti.)

- [ ] **Step 2: Ganti hardcode di `ApprovalController.php` jadi baca dari `.env`**

File saat ini (sekitar baris 103-109):
```php
    // Kirim notifikasi ke Telegram owner (gratis, anti-banned)
    private function kirimTelegram($pesan)
    {
        try {
            $token  = '8812397501:AAFFLbGTmjmhgV2mSDEc233-6ReCJq_S4Ns';
            $chatId = '8385647457';
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
```
Ganti jadi:
```php
    // Kirim notifikasi ke Telegram owner (gratis, anti-banned)
    private function kirimTelegram($pesan)
    {
        try {
            $token  = getenv('TELEGRAM_OWNER_TOKEN');
            $chatId = getenv('TELEGRAM_OWNER_CHAT_ID');
            if (!$token || !$chatId) return;
            $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
```

- [ ] **Step 3: Cek sintaks PHP**

Run: `php -l app/Http/Controllers/ApprovalController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit & deploy**

```bash
git add app/Http/Controllers/ApprovalController.php
git commit -m "Rotasi token bot Telegram Owner, pindah ke .env (repo public, token lama expose)"
git push
```

- [ ] **Step 5: Verifikasi di production**

Trigger 1x approval RAB (approve/tolak permintaan diskon) dan pastikan notif Telegram tetap masuk ke Owner seperti biasa, dengan token baru.

- [ ] **Step 6: Regenerate token Fonnte (opsional, tidak mendesak)**

Karena akun Fonnte sudah banned (tidak bisa kirim apapun), regenerate token di dashboard Fonnte boleh dilakukan kapan saja tanpa risiko — tidak ada fitur live yang bergantung padanya lagi setelah Task 1-10 selesai.

---

## Ringkasan urutan eksekusi

Task 1 → 2 → 3 → 4 bisa berurutan (fondasi). Task 5-10 (migrasi controller/cron) saling independen satu sama lain setelah Task 2 selesai — bisa dikerjakan paralel oleh subagent berbeda kalau pakai `subagent-driven-development`. Task 11 baru bisa diverifikasi penuh setelah Task 1-10 semua deploy. Task 12 independen, bisa kapan saja.
