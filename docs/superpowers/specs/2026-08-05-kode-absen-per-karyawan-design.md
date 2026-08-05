# Spec — Kode Absen Per-Karyawan (Cegah Titip Absen)

> 2026-08-05. Status: disetujui, siap masuk tahap rencana implementasi.

## Latar belakang

Sejak awal, sistem kode absen pakai **satu kode bersama** untuk semua karyawan per hari (tabel `kode_absen`, satu baris per tanggal, tidak terikat ke user manapun). Kode ini dulu dikirim via WA (Fonnte) ke semua karyawan sekaligus, sekarang lewat Telegram (lihat `docs/superpowers/specs/2026-08-05-notifikasi-telegram-karyawan-design.md`) — tapi caranya masih sama: satu kode, disebar ke semua orang.

**Masalahnya:** siapapun yang tahu kode hari itu — dari siapapun — bisa dipakai buat absen atas nama siapapun. Karyawan yang bolos/telat bisa minta rekan kerja titip-absenkan cukup dengan kirim kode hari itu, karena validasi cuma cek "kode ini valid buat hari ini", tanpa peduli itu kode siapa dan yang mengetik siapa.

Sekarang momentnya pas untuk dibenahi sekalian karena migrasi Telegram baru saja bikin tiap karyawan (yang sudah connect) punya jalur pengiriman personal 1:1 — sebelumnya (WA grup/broadcast) susah pisahkan kode per orang secara praktis.

## Tujuan
- Tiap karyawan dapat kode absen masuk sendiri-sendiri per hari, dikirim personal ke Telegram masing-masing.
- Kode milik karyawan A **tidak bisa dipakai** untuk absen sebagai karyawan B, walau B tahu kodenya — validasi terikat ke user yang sedang login, bukan cuma "valid hari ini".
- Karyawan yang belum "Hubungkan Telegram" tetap bisa absen — kodenya tetap dibuat, ditampilkan di dashboard buat mandor/owner supaya bisa disampaikan manual.

## Non-tujuan
- Tidak mengubah validasi GPS, jam masuk, atau logika telat/potongan gaji — itu semua tetap seperti sekarang.
- Tidak menambah kode/validasi ke absen siang atau absen pulang — kode absen sekarang cuma dipakai di `absenMasuk`, tetap begitu.
- Tidak membangun ulang tabel dari nol — perluasan aditif dari `kode_absen` yang sudah ada, bukan tabel baru.
- Tidak menyentuh alur "Hubungkan Telegram" itu sendiri (Task 1-10 migrasi Telegram) — spec ini murni lanjutan yang memanfaatkannya.

## Arsitektur

### A. Skema data
Tabel `kode_absen` (sudah ada) dapat 1 kolom baru:
- `user_id` BIGINT UNSIGNED NULL — kalau NULL, itu baris kode lama (era "satu kode buat semua", sebelum fitur ini aktif), dibiarkan sebagai riwayat, tidak dipakai validasi lagi setelah fitur ini jalan.
- Unique index `(tanggal, user_id)` — cegah dobel baris kode buat orang yang sama di hari yang sama (aman di-hit ulang oleh cron).

`user_id` sengaja **nullable**, bukan wajib — supaya tidak perlu backfill data lama, dan supaya tidak mematahkan constraint kalau suatu saat ada kebutuhan generate kode umum lagi (di luar scope sekarang, tapi tidak menutup pintu).

### B. Generate & kirim (cron `public/cron-kode-absen.php`, tetap jalan 06:30 tiap hari)
1. Ambil semua karyawan aktif: `User::where('level', '!=', 1)->where('status', 'aktif')->get()` (filter sama seperti yang sudah dipakai cron ini sekarang untuk kirim notif).
2. Untuk tiap karyawan, kalau belum ada baris `kode_absen` dengan `tanggal = hari ini` dan `user_id = karyawan ini` → generate kode 6-karakter acak (format sama seperti sekarang: `strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6))`), simpan baris baru.
   - **Idempotent** — kalau cron ke-trigger ulang (manual test, retry, dll), kode yang sudah dibuat/dikirim untuk karyawan itu TIDAK berubah. Konsisten dengan prinsip SQL/cron idempotent yang sudah dipakai di project ini.
3. Kalau karyawan itu punya `telegram_chat_id` (sudah connect) → kirim pesan personal via `app(TelegramService::class)->kirim($k->telegram_chat_id, $pesan)`, isi pesan mirip sekarang tapi kodenya kode dia sendiri.
4. Kalau belum connect → kode tetap tersimpan di DB, tidak ada pesan terkirim (silent skip, pola sama seperti notifikasi lain yang sudah ada) — nongol di dashboard fallback (bagian D).

### C. Validasi absen masuk (`AbsensiController::absenMasuk`, `AbsensiController::validasiKode`)
Ganti query dari:
```php
KodeAbsen::whereDate('tanggal', today())->where('kode', $kode)->exists()
```
jadi:
```php
KodeAbsen::whereDate('tanggal', today())->where('user_id', $user->id)->where('kode', $kode)->exists()
```
`$user` = user yang sedang login (`Auth::user()`), sudah tersedia di kedua method itu. Kode punya orang lain otomatis tidak valid buat user yang login, walau kodenya benar-benar ada dan berlaku hari itu.

Method statis `KodeAbsen::kodeHariIni()` dan `KodeAbsen::validasi()` di model (saat ini tidak dipakai di manapun — controller dan cron pakai query langsung) ikut diperbarui ke pola per-user yang sama, supaya tidak jadi jebakan kalau ada yang mulai memakainya nanti.

### D. Dashboard fallback (Owner level 1 + Supervisor/Mandor level 3)
Halaman baru, bukan bagian dari dashboard utama yang sudah ada (biar tidak menambah beban ke blade dashboard yang sudah kompleks):
- Route baru, contoh `GET /absensi/kode-hari-ini`, middleware `['auth', 'level:1,3']` (pola sama seperti route Owner/Supervisor lain di `routes/web.php`).
- Isi: tabel nama karyawan + kode hari ini + status Telegram (✅ terkirim otomatis / ❌ belum connect, sampaikan manual).
- Dipakai mandor/owner untuk kasih tahu manual karyawan yang belum connect Telegram, dan sebagai cara ngecek cepat tanpa buka Telegram bot sendiri.
- Link ke halaman ini ditambahkan di sidebar Owner/Supervisor (pola menu yang sudah ada).

## Migrasi database
SQL manual via phpMyAdmin (idempotent, pola sama seperti kolom `telegram_chat_id`/`panjang_batang_cm` sebelumnya):
```sql
ALTER TABLE kode_absen ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE kode_absen ADD UNIQUE INDEX IF NOT EXISTS kode_absen_tanggal_user_unique (tanggal, user_id);
```
Catatan: `ADD UNIQUE INDEX IF NOT EXISTS` didukung MySQL 8.0.29+. Kalau phpMyAdmin menolak sintaks ini (versi lebih lama), fallback-nya jalankan tanpa `IF NOT EXISTS` di baris index saja (baris `ADD COLUMN` tetap idempotent seperti biasa) — dicatat sebagai langkah verifikasi di rencana implementasi, bukan blocker desain. Sama seperti migrasi Telegram sebelumnya, SQL ini **harus dijalankan sebelum** kode yang bergantung padanya di-deploy — urutan dicatat di rencana implementasi.

## File yang berubah

**Baru:**
- Route + controller method untuk dashboard fallback (nama file/method ditentukan saat implementasi, kemungkinan besar method baru di `AbsensiController` + blade baru, mengikuti pola controller yang sudah ada — tidak perlu controller terpisah untuk satu halaman kecil ini)
- Blade baru untuk halaman kode-hari-ini

**Diubah:**
- `database/migrations/2026_06_02_112746_create_kode_absen_table.php` — TIDAK diubah (migration lama dibiarkan apa adanya, kolom baru masuk lewat SQL manual seperti biasa di project ini, bukan migration baru — konsisten dengan cara kerja project ini yang tidak pakai `artisan migrate` di production)
- `app/Models/KodeAbsen.php` — `kodeHariIni()` dan `validasi()` diperbarui ke pola per-user (walau saat ini tidak dipakai, diperbaiki sekalian biar konsisten dan tidak jadi jebakan)
- `app/Http/Controllers/AbsensiController.php` — `absenMasuk()` dan `validasiKode()`, query kode ditambah filter `user_id`
- `public/cron-kode-absen.php` — loop per-karyawan untuk generate+kirim kode, bukan satu kode bersama
- `routes/web.php` — route baru untuk dashboard fallback
- Sidebar Owner/Supervisor — tambah link ke halaman kode-hari-ini

## Di luar scope / ditunda
- Tidak menyentuh validasi GPS atau jam kerja.
- Tidak ada UI untuk mandor/owner *mengubah* kode manual (kalau butuh regenerate satu kode karena kepencet salah kirim dsb, itu lewat DB langsung dulu — bisa ditambah kalau ternyata sering dibutuhkan).
- Baris `kode_absen` lama (`user_id` NULL, sebelum fitur ini aktif) tidak dibersihkan/dihapus — dibiarkan sebagai riwayat.
