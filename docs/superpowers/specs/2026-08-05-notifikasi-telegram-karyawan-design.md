# Spec — Ganti Notifikasi WA (Fonnte) ke Telegram Bot

> 2026-08-05. Status: disetujui, siap masuk tahap rencana implementasi.

## Latar belakang

Semua notifikasi WA di sistem ini lewat Fonnte, tersebar di 9 controller + 2 file cron, masing-masing punya fungsi `kirimWA()`/`kirimWa()` sendiri (kode diduplikasi berkali-kali). Ditemukan juga bug nyata: `KpiController.php` dan `public/cron-kpi.php` memanggil `App\Services\FonnteService` yang **filenya tidak pernah ada** di codebase — notif SP karyawan, hasil ujian, dan ringkasan KPI bulanan kemungkinan besar gagal total (fatal error "Class not found"), bukan cuma gagal kirim diam-diam.

Elvan mau pindah dari Fonnte (berbayar) ke Telegram bot dulu untuk semua notifikasi (karyawan maupun owner), dengan rencana ke depan pindah lagi ke WhatsApp Business API resmi kalau sudah siap.

**Riwayat penting yang diverifikasi ulang di sesi ini:** dugaan lama "app/Services tidak kebaca di Niagahoster" (alasan kenapa dulu kode Fonnte ditulis berulang di 9 file, bukan 1 service) **terbukti tidak akurat sebagai aturan umum** — diuji langsung di production (deploy class dummy baru ke `app/Services/`, dipanggil lewat endpoint sementara, berhasil di-autoload normal, lalu file tes dihapus). Kemungkinan besar akar masalah dulu adalah `FonnteService.php` memang tidak pernah selesai dibuat/ter-commit, bukan keterbatasan hosting. Karena itu, desain ini AMAN pakai 1 service class terpusat.

## Tujuan
- Semua notifikasi yang sekarang lewat Fonnte (absensi, izin, kasbon, log bensin, luar kota, gaji, tugas harian, SP karyawan, hasil ujian, KPI, approval RAB) pindah ke Telegram.
- Hilangkan duplikasi kode `kirimWA()` di 9+ file jadi 1 service terpusat.
- Employee harus bisa "menghubungkan" akun Telegram-nya sendiri ke sistem — seringan mungkin (Telegram tidak izinkan bot memulai chat duluan, jadi minimal 1x aksi klik dari karyawan tidak bisa dihindari).

## Non-tujuan
- Tidak menyentuh bot Telegram Owner yang sudah ada di `ApprovalController.php` (approval nego RAB) — tetap terpisah.
- Tidak membangun WhatsApp Business API resmi sekarang (rencana masa depan, di luar scope ini).
- Tidak membangun UI admin untuk melihat status "siapa yang sudah connect Telegram" — kalau dibutuhkan, ini kerjaan susulan terpisah.

## Arsitektur

### A. Alur "Hubungkan Telegram" (karyawan)
1. Tabel `users` dapat 2 kolom baru:
   - `telegram_chat_id` VARCHAR(50) NULL — dipakai untuk kirim pesan
   - `telegram_link_token` VARCHAR(64) NULL — kode sekali-pakai untuk proses penghubungan
2. Halaman **Profil** dapat tombol baru:
   - Belum connect → tombol "Hubungkan Telegram", link ke `https://t.me/<username_bot>?start=<telegram_link_token>` (token digenerate saat pertama kali tombol dirender kalau belum ada)
   - Sudah connect (`telegram_chat_id` terisi) → tampil status "✅ Sudah terhubung"
3. Karyawan tap tombol → Telegram app terbuka otomatis ke bot yang benar → tap "Start"
4. Bot menerima pesan `/start <token>` lewat webhook baru (lihat bagian C) → sistem cocokkan `telegram_link_token` ke user, simpan `chat_id`-nya, kosongkan token (sekali pakai), balas pesan konfirmasi ke chat itu
5. Kalau karyawan belum connect, notifikasi untuknya **di-skip diam-diam** (pola sama seperti sekarang kalau `no_hp` kosong) — tidak melempar error

### B. Service terpusat pengganti Fonnte
- File baru: `app/Services/TelegramService.php`, method `kirim(User $user, string $pesan): void`
  - Ambil `$user->telegram_chat_id`; kalau kosong → return (skip diam-diam)
  - Kirim via `curl` ke `https://api.telegram.org/bot<token>/sendMessage`, `parse_mode=Markdown` (supaya `*teks tebal*` yang sudah dipakai di semua pesan lama tetap render tebal, bukan tampil sebagai asterisk mentah)
  - Bungkus try/catch, silent-fail + `Log::error()` — pola sama persis seperti kode Fonnte lama (jangan sampai gagal kirim notif bikin fitur utama ikut error)
  - Token bot disimpan di `config/services.php` → `'telegram_karyawan' => ['token' => '...']` (hardcode di kode, bukan `.env`, konsisten dengan pola bot Owner yang sudah ada)

### C. Webhook & setup bot
1. Bot Telegram baru dibuat manual oleh Elvan via `@BotFather` (`/newbot`) — **terpisah dari bot Owner** (alasan: bot Owner dipakai untuk approval RAB yang sensitif finansial, tidak dicampur trafik 14 karyawan; juga supaya logic webhook baru tidak menyentuh kode approval yang sudah terbukti jalan)
2. Route baru: `POST /telegram/karyawan/webhook` → `TelegramWebhookController@handle`
   - Baca body JSON dari Telegram (`message.text`, `message.chat.id`)
   - Kalau `text` cocok pola `/start <token>`: cari user dengan `telegram_link_token` itu, simpan `chat_id`, kosongkan token, balas via `sendMessage` ke `chat.id` ("✅ Berhasil terhubung, Kak {nama}!")
   - Kalau tidak cocok/tidak ketemu: abaikan (return 200 OK tetap, supaya Telegram tidak retry terus)
3. Setelah bot dibuat & kode di-deploy, daftarkan webhook 1x manual (buka URL `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://app.kanopibsd.co.id/telegram/karyawan/webhook` sekali di browser)

## Migrasi database
SQL manual via phpMyAdmin (idempotent, pola sama seperti `panjang_batang_cm` sebelumnya — bukan lewat `artisan migrate`, konsisten dengan cara kerja yang sudah biasa dipakai di project ini):
```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS telegram_link_token VARCHAR(64) NULL;
```

## File yang berubah

**Baru:**
- `app/Services/TelegramService.php`
- `app/Http/Controllers/TelegramWebhookController.php`
- Route baru di `routes/web.php` (atau `api.php`, ditentukan saat implementasi)
- Perubahan di `resources/views/profil/*.blade.php` (tombol Hubungkan Telegram) — file blade persis ditentukan saat implementasi

**Dihapus (kode Fonnte lama, tuntas bukan dikomentari):**
- Private method `kirimWA()`/`kirimWa()` di: `KasbonKaryawanController`, `IzinAbsenController`, `LogBensinController`, `AbsensiController`, `LuarKotaController`, `PenggajianController`, `TugasHarianController`, `ProjectController`, `RabController`
- Fungsi `kirimWA()` di `public/cron-kode-absen.php`, `public/cron-alpha.php`
- `use App\Services\FonnteService;` + pemakaiannya di `KpiController.php`, `public/cron-kpi.php`
- Entry `'fonnte' => [...]` di `config/services.php`
- Baris `putenv('FONNTE_TOKEN=...')` di `app/Providers/AppServiceProvider.php`

**Diubah (ganti pemanggilan):**
Semua call-site `$this->kirimWA($xxx->no_hp, $pesan)` → `app(TelegramService::class)->kirim($xxx, $pesan)` — parameter berubah dari string nomor HP jadi objek `User` (dicek sudah semua call-site memang sudah punya objek user yang di-resolve duluan sebelum manggil, jadi ini perubahan langsung tanpa perlu lookup tambahan).

## Rollout (urutan)
1. Deploy kode (service + webhook + UI tombol profil) + jalankan SQL migrasi
2. Elvan bikin bot via @BotFather, isi token ke `config/services.php`, daftarkan webhook
3. Umumkan ke 14 karyawan untuk klik "Hubungkan Telegram" di halaman profil masing-masing (langkah operasional, di luar kode)
4. Sebelum karyawan connect, notifikasi untuk mereka di-skip diam-diam — tidak mengganggu fitur utama

## Di luar scope / ditunda
- Indikator admin "siapa yang sudah/belum connect Telegram" — susulan kalau dibutuhkan
- Tombol putus/reconnect Telegram — v1 cukup connect sekali; edit manual via DB kalau ada kasus ganti HP/akun Telegram
- WhatsApp Business API resmi — rencana masa depan terpisah
