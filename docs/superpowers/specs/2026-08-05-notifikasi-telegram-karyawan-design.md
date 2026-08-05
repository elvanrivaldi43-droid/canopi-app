# Spec — Ganti Notifikasi WA (Fonnte) ke Telegram Bot

> 2026-08-05. Status: disetujui, siap masuk tahap rencana implementasi.

## Latar belakang

Semua notifikasi WA di sistem ini lewat Fonnte, tersebar di 9 controller + 2 file cron, masing-masing punya fungsi `kirimWA()`/`kirimWa()` sendiri (kode diduplikasi berkali-kali). Ditemukan juga bug nyata: `KpiController.php` dan `public/cron-kpi.php` memanggil `App\Services\FonnteService` yang **filenya tidak pernah ada** di codebase — notif SP karyawan, hasil ujian, dan ringkasan KPI bulanan kemungkinan besar gagal total (fatal error "Class not found"), bukan cuma gagal kirim diam-diam.

Elvan mau pindah dari Fonnte (berbayar) ke Telegram bot dulu untuk semua notifikasi (karyawan maupun owner), dengan rencana ke depan pindah lagi ke WhatsApp Business API resmi kalau sudah siap.

**Urgensi:** akun Fonnte sudah **kena banned** — notifikasi WA ke 14 karyawan (izin, kasbon, gaji, absensi, dll) sudah mati total sejak sebelum 5 Agustus 2026, bukan cuma soal ganti provider. Migrasi ini memulihkan fungsi notifikasi yang sekarang benar-benar tidak jalan, bukan penundaan biasa.

**Riwayat penting yang diverifikasi ulang di sesi ini:** dugaan lama "app/Services tidak kebaca di Niagahoster" (alasan kenapa dulu kode Fonnte ditulis berulang di 9 file, bukan 1 service) **terbukti tidak akurat sebagai aturan umum** — diuji langsung di production (deploy class dummy baru ke `app/Services/`, dipanggil lewat endpoint sementara, berhasil di-autoload normal, lalu file tes dihapus). Kemungkinan besar akar masalah dulu adalah `FonnteService.php` memang tidak pernah selesai dibuat/ter-commit, bukan keterbatasan hosting. Karena itu, desain ini AMAN pakai 1 service class terpusat.

**Temuan keamanan penting (ubah desain penyimpanan token):** repo GitHub project ini **PUBLIC** (dicek via GitHub API: `private: false`). Token bot Telegram Owner yang sekarang (hardcode di `ApprovalController.php`) dan 2 token Fonnte (`config/services.php`, `AppServiceProvider.php`) **sudah ke-expose beneran** di histori commit publik — bukan cuma risiko teoretis. Karena itu, token bot Telegram karyawan yang baru **TIDAK boleh** ikut pola hardcode-di-kode yang sama — harus di `.env` (sudah dicek: `.env` masuk `.gitignore`, tidak pernah ter-commit sekali pun, aman). Rotasi token lama yang sudah expose ditangani terpisah di luar scope kode ini (lihat catatan di akhir dokumen).

## Tujuan
- Semua notifikasi yang sekarang lewat Fonnte (absensi, izin, kasbon, log bensin, luar kota, gaji, tugas harian, SP karyawan, hasil ujian, KPI, approval RAB) pindah ke Telegram.
- Hilangkan duplikasi kode `kirimWA()` di 9+ file jadi 1 service terpusat.
- Employee harus bisa "menghubungkan" akun Telegram-nya sendiri ke sistem — seringan mungkin (Telegram tidak izinkan bot memulai chat duluan, jadi minimal 1x aksi klik dari karyawan tidak bisa dihindari).

## Non-tujuan
- Tidak menyentuh bot Telegram Owner yang sudah ada di `ApprovalController.php` (approval nego RAB) — tetap terpisah.
- Tidak membangun WhatsApp Business API resmi sekarang (rencana masa depan, di luar scope ini).
- Tidak membangun UI admin untuk melihat status "siapa yang sudah connect Telegram" — kalau dibutuhkan, ini kerjaan susulan terpisah.
- **Tidak menangani notifikasi ke customer/lead** — `RabController::kirimNotifDeal()` kirim WA ke `$lead->no_hp` (customer, bukan `User` sistem, tidak bisa "Hubungkan Telegram"). Lihat catatan di "Di luar scope / ditunda".

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
  - Token bot dibaca dari `.env` (`getenv('TELEGRAM_KARYAWAN_TOKEN')`) — **BUKAN** hardcode di file PHP, karena repo ini public (lihat "Temuan keamanan" di atas). Tambahkan juga `TELEGRAM_KARYAWAN_TOKEN=` (kosong) di `.env.example` sebagai dokumentasi, tanpa nilai asli.

### C. Webhook & setup bot
1. Bot Telegram baru dibuat manual oleh Elvan via `@BotFather` (`/newbot`) — **terpisah dari bot Owner** (alasan: bot Owner dipakai untuk approval RAB yang sensitif finansial, tidak dicampur trafik 14 karyawan; juga supaya logic webhook baru tidak menyentuh kode approval yang sudah terbukti jalan)
2. Route baru: `POST /telegram/karyawan/webhook` → `TelegramWebhookController@handle`
   - Baca body JSON dari Telegram (`message.text`, `message.chat.id`)
   - Kalau `text` cocok pola `/start <token>`: cari user dengan `telegram_link_token` itu, simpan `chat_id`, kosongkan token, balas via `sendMessage` ke `chat.id` ("✅ Berhasil terhubung, Kak {nama}!")
   - Kalau tidak cocok/tidak ketemu: abaikan (return 200 OK tetap, supaya Telegram tidak retry terus)
3. Setelah bot dibuat & kode di-deploy, daftarkan webhook 1x manual (buka URL `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://app.kanopibsd.co.id/telegram/karyawan/webhook` sekali di browser)
4. Token bot ditaruh di `.env` di server (lewat File Manager cPanel/edit langsung, bukan lewat deploy FTP karena `.env` sengaja di-exclude dari deploy — lihat `deploy.yml`), **bukan** di file kode manapun

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
Semua call-site `$this->kirimWA($xxx->no_hp, $pesan)` → `app(TelegramService::class)->kirim($xxx, $pesan)` — parameter berubah dari string nomor HP jadi objek `User` (dicek sudah semua call-site memang sudah punya objek user yang di-resolve duluan sebelum manggil, jadi ini perubahan langsung tanpa perlu lookup tambahan). Guard lama yang mengecek `no_hp` sebelum kirim (mis. `if ($owner?->no_hp)`, `whereNotNull('no_hp')`) diganti jadi cek keberadaan user saja (`TelegramService::kirim()` sudah menangani skip internal kalau `telegram_chat_id` kosong) — beberapa query recipient (`IzinAbsenController::kirimNotifPengajuan`, `AbsensiController::kirimNotifKendala`) filter `whereNotNull('no_hp')` diganti jadi `whereNotNull('telegram_chat_id')` supaya hanya kirim ke yang sudah connect.

**Dikecualikan (tetap pakai kode lama untuk sementara, TIDAK dihapus):**
- `RabController::kirimNotifDeal()` — kirim WA ke `$lead->no_hp` (customer/lead, bukan `User` sistem). Karena Fonnte sudah banned, fungsi ini **dinonaktifkan (early return, skip diam-diam)**, bukan dipindah ke Telegram — customer tidak bisa "Hubungkan Telegram" seperti karyawan. Ditandai sebagai utang, ditangani nanti bareng WhatsApp Business API resmi (roadmap CLAUDE.md #5).

## Rollout (urutan)
1. Deploy kode (service + webhook + UI tombol profil) + jalankan SQL migrasi
2. Elvan bikin bot via @BotFather, isi token ke `.env` di server (bukan ke file kode), daftarkan webhook
3. Umumkan ke 14 karyawan untuk klik "Hubungkan Telegram" di halaman profil masing-masing (langkah operasional, di luar kode)
4. Sebelum karyawan connect, notifikasi untuk mereka di-skip diam-diam — tidak mengganggu fitur utama

## Di luar scope / ditunda
- Indikator admin "siapa yang sudah/belum connect Telegram" — susulan kalau dibutuhkan
- Tombol putus/reconnect Telegram — v1 cukup connect sekali; edit manual via DB kalau ada kasus ganti HP/akun Telegram
- WhatsApp Business API resmi — rencana masa depan terpisah
- **Rotasi token lama yang sudah expose** (bot Telegram Owner di `ApprovalController.php`, 2 token Fonnte) — dikerjakan Elvan langsung di luar sesi ini (bukan lewat kode/plan implementasi), tapi WAJIB dilakukan supaya token lama yang sudah publik itu tidak bisa disalahgunakan lagi. Setelah token diganti, `ApprovalController.php` perlu diupdate dengan token baru (commit terpisah, kecil).
