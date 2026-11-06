# Penugasan Harian Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mandor Bertugas menyusun rencana kerja besok tiap malam, tiap tukang/kenek/driver mengonfirmasi Hadir/Tidak Hadir lewat tombol Telegram sampai 06:00 (menentukan kode absen), dan sore tiap orang melaporkan kerja nyatanya (sumber data orang-hari).

**Architecture:** Semua aturan yang menyangkut uang/absen ditaruh di satu kelas logika murni `App\Services\PenugasanAturan` (tanpa DB, diuji standalone). Data di 4 tabel baru (SQL manual). Alur: halaman web Rencana Besok → `konfirmasi_hadir` + pesan Telegram bertombol → webhook menerima jawaban → `cron-penugasan.php` mengirim pengingat → `cron-kode-absen.php` memfinalkan jawaban (catat `tidak_hadir`, lewati yang tak menjawab) → laporan sore di halaman absen pulang.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL (shared hosting Niagahoster), Telegram Bot API via `curl_init`, crontab VPS, tes PHP standalone (`php tests/...`).

**Spec:** `docs/superpowers/specs/2026-11-06-penugasan-harian-design.md` (baca dulu, terutama bagian 3, 5, 9).

## Global Constraints

- Timezone app `Asia/Jakarta`; crontab VPS dalam **UTC** (WIB = UTC+7).
- Laravel `Http::` facade dilarang → `curl_init`. Env dibaca dengan `getenv()`.
- Skema baru = **SQL idempotent manual** di `docs/sql/`, dijalankan Elvan di phpMyAdmin SEBELUM push kode yang memakainya. Jangan `UPDATE/DELETE` data di file SQL.
- Tiap file test baru WAJIB ditambahkan ke `tests/guardrail/manifest.json` (`runner: php`, `requires_db: false`, `manual: false`) di commit yang sama; jalankan `php scripts/canopi-check --full` sebelum push.
- Tanpa emoji di file blade (pakai SVG / teks). Pesan Telegram boleh emoji (pola yang ada).
- Tanpa `confirm()` di tombol aksi penting (pola tekan-dua-kali yang ada).
- Commit: stage per-nama file, JANGAN `git add -A`. Akhiri pesan commit dengan baris atribusi sesi.
- Endpoint cron: `App\Services\CronAuth::valid()`, mode `&cek=1` membalas `OK` tanpa efek; jangan uji endpoint cron asli di production.
- Level: 1 Owner, 2 Admin, 3 Mandor/Supervisor, 4 Marketing, 5 Teknisi (tukang/kenek), 6 Driver, 7 Toko.
- Yang ikut penugasan: level **3, 5, 6** saja. Level 2/4/7 TIDAK berubah sama sekali.
- Jam pengingat (WIB): mandor 19:00 / 20:30 / 21:00, Owner 22:00; pekerja 21:30 / 05:00 / 05:45; batas jawab **06:00**; kode absen tetap 06:30.

## Review Focus

1. Tombol Hadir ditekan setelah 06:00 (atau untuk tanggal yang sudah lewat) → ditolak dengan pesan jelas, jawaban tidak berubah. (Task 3 test + Task 5)
2. Pesan bertombol diteruskan ke orang lain lalu ditekan → ditolak (chat id penekan ≠ pemilik). (Task 5 test)
3. Cron pengingat terpicu dua kali (retry/manual) → tidak ada pesan ganda. (Task 3 test `pengingat*`)
4. Rencana diubah setelah konfirmasi terkirim → hanya orang yang tugasnya berubah ditanya ulang; jawaban orang lain tetap. (Task 4 test `perluTanyaUlang`)
5. Orang yang besok izin/sakit disetujui atau jadwal libur dimasukkan ke rencana → ditolak saat simpan. (Task 4)

---

### Task 1: Kunci rahasia webhook Telegram

**Files:**
- Modify: `app/Http/Controllers/TelegramWebhookController.php`
- Create: `tests/telegram/test_webhook_secret.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Produces: `TelegramWebhookController::secretValid(?string $header, ?string $server): bool` (public static, murni).

- [ ] **Step 1: Write the failing test** — `tests/telegram/test_webhook_secret.php` (pola `$check` seperti `tests/absensi/test_gate_buka_absen.php`, require `tests/bootstrap.php`):
  - `secretValid('abcdefghijklmnop1234', 'abcdefghijklmnop1234')` === true
  - `secretValid('salah', 'abcdefghijklmnop1234')` === false
  - `secretValid(null, 'abcdefghijklmnop1234')` === false
  - `secretValid('', '')` === false (gagal tertutup)
  - `secretValid('pendek', 'pendek')` === false (server < 16 karakter = tolak semua)
- [ ] **Step 2:** `php tests/telegram/test_webhook_secret.php` → FAIL (method belum ada).
- [ ] **Step 3: Implement** — `secretValid` pakai `hash_equals`, min 16 karakter (samakan `CronAuth::MIN_PANJANG`). Di awal `handle()`: bila tidak valid terhadap header `X-Telegram-Bot-Api-Secret-Token` dan `getenv('TELEGRAM_WEBHOOK_SECRET')` → `response('Forbidden', 403)`.
- [ ] **Step 4:** test PASS; daftarkan di manifest; `php scripts/canopi-check --full` hijau.
- [ ] **Step 5: Langkah manual Elvan (tulis di pesan commit & laporan)** — urutan WAJIB agar sambung akun tak putus: (1) isi `TELEGRAM_WEBHOOK_SECRET` (hasil `openssl rand -hex 24`) di `.env` server; (2) daftarkan ulang webhook: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://app.kanopibsd.co.id/telegram/karyawan/webhook&secret_token=<SECRET>&allowed_updates=["message","callback_query"]`; (3) baru push kode. Uji: `/start` sambung akun masih jalan.
- [ ] **Step 6: Commit** `tests/telegram/test_webhook_secret.php app/Http/Controllers/TelegramWebhookController.php tests/guardrail/manifest.json` — "Webhook Telegram: tolak request tanpa secret token".

### Task 2: Skema + status absensi `tidak_hadir`

**Files:**
- Create: `docs/sql/2026-11-06-penugasan-harian.sql`
- Modify: `app/Models/Absensi.php` (`statusLabel`, `statusColor`)
- Modify: `app/Services/KerjaHariLiburService.php` (`statistikKehadiran`)
- Modify: `public/cron-alpha.php`
- Modify: `resources/views/absensi/rekap.blade.php`, `rekap-bulanan.blade.php`, `penggajian/slip.blade.php` hanya bila status ditampilkan lewat daftar tetap (cek `grep -n "dinas_luar"` di tiap file; tambah `tidak_hadir` di daftar yang sama)
- Test: `tests/absensi/test_status_tidak_hadir.php`

**Interfaces:**
- Produces tabel (dipakai semua task berikutnya):
  - `setting_penugasan` (baris tunggal id=1): `mandor_bertugas_id` BIGINT UNSIGNED NULL, `updated_at`.
  - `rencana_harian`: `tanggal` DATE PK, `disimpan_oleh` BIGINT NULL, `disimpan_at` DATETIME NULL, `pengingat_mandor` VARCHAR(10) NULL (`'1900'|'2030'|'2100'|'owner'` = terakhir terkirim), timestamps.
  - `penugasan_harian`: `id`, `tanggal` DATE, `user_id`, `project_tahap_id`, `porsi` VARCHAR(10) (`'penuh'|'setengah'`), `laporan` VARCHAR(10) NULL (`'selesai'|'lanjut'`), `catatan` TEXT NULL, `dilaporkan_at` DATETIME NULL, `dibuat_oleh`, timestamps; INDEX (`tanggal`,`user_id`).
  - `konfirmasi_hadir`: `id`, `tanggal` DATE, `user_id`, `jawaban` VARCHAR(12) NULL (`'hadir'|'tidak_hadir'`), `dijawab_at` DATETIME NULL, `pengingat` VARCHAR(10) NULL (`'2130'|'0500'|'0545'`), `dikirim_at` DATETIME NULL, timestamps; UNIQUE (`tanggal`,`user_id`).
  - `absensi.status` menerima `'tidak_hadir'`.
- Produces: `statistikKehadiran()` mengembalikan key tambahan `'tidak_hadir' => int`.

- [ ] **Step 1: SQL** — file berisi: bagian A `SHOW COLUMNS FROM absensi LIKE 'status';` dengan komentar "kirim hasilnya ke Claude dulu"; bagian B `CREATE TABLE IF NOT EXISTS` keempat tabel; `INSERT IGNORE INTO setting_penugasan (id) VALUES (1)`. **Berhenti, minta Elvan menjalankan bagian A dan mengirim hasilnya.** Bila ENUM → tambahkan bagian C `ALTER TABLE absensi MODIFY status ENUM(<daftar asli persis>, 'tidak_hadir') ...` (salin NULL/DEFAULT asli); bila VARCHAR → tak perlu ALTER. Elvan menjalankan B (+C), verifikasi dengan `SHOW TABLES LIKE '%penugasan%'`.
- [ ] **Step 2: failing test** `tests/absensi/test_status_tidak_hadir.php`:
  - `statistikKehadiran([['status'=>'hadir'],['status'=>'tidak_hadir'],['status'=>'alpha']])` → `hadir`=1, `alpha`=1, `izin`=0, `tidak_hadir`=1.
  - `(new Absensi(['status'=>'tidak_hadir']))->statusLabel()` mengandung `'Tidak Hadir'`; `statusColor()` !== `'#64748B'`.
  - Mirror kondisi cron-alpha: fungsi lokal `$lewatiAlpha(bool $sudahMasuk, bool $adaIzin, bool $sudahAlpha, bool $lewatiLibur, bool $tidakHadir)` → true bila `$tidakHadir` (baris `tidak_hadir` ada).
- [ ] **Step 3: run → FAIL.**
- [ ] **Step 4: implement** — label `'Tidak Hadir'`, warna `'#F97316'`; key `tidak_hadir` di statistik (TIDAK masuk `hadir` maupun `izin` → persen hadir otomatis turun karena penyebut hari kerja tetap). `cron-alpha.php`: tambah `$tidakHadir = Absensi::where(user, tanggal)->where('status','tidak_hadir')->exists();` dan ikut ke syarat `if`.
- [ ] **Step 5: test PASS, manifest, canopi-check, commit** (sertakan file SQL).

### Task 3: Aturan murni `PenugasanAturan`

**Files:**
- Create: `app/Services/PenugasanAturan.php`
- Test: `tests/penugasan/test_aturan.php`

**Interfaces (semua `public static`, tanpa DB, jam = string `'HH:MM'` WIB):**
- `LEVEL_IKUT = [3, 5, 6]`; `ikut(int $level): bool`
- `orangHari(string $porsi): float` — `'penuh'`→1.0, `'setengah'`→0.5, lainnya → `InvalidArgumentException`.
- `alasanTolakTugasOrang(array $porsiList): ?string` — total `orangHari` > 1.0 → `'Lebih dari 1 hari kerja'`; `'penuh'` bersama tugas lain → ditolak; kosong → null.
- `pengingatMandor(string $jam, bool $sudahDisimpan, ?string $terakhir): ?string` — kembalikan kode pengingat yang JATUH TEMPO & belum terkirim: `'1900'` (jam ≥19:00), `'2030'` (≥20:30), `'2100'` (≥21:00), `'owner'` (≥22:00); urutan maju saja (tak pernah mengulang/kembali); `$sudahDisimpan` → null; jam < 19:00 → null.
- `pengingatPekerja(string $jam, ?string $jawaban, ?string $terakhir): ?string` — jatuh tempo: `'2130'` bila 21:30 ≤ jam ≤ 23:59, `'0500'` bila 05:00 ≤ jam < 05:45, `'0545'` bila 05:45 ≤ jam < 06:00, selain itu null (tak ada pesan 00:00–04:59 maupun ≥ 06:00); `$jawaban !== null` → null; tidak pernah mengirim kode yang urutannya ≤ `$terakhir` (urutan `'2130'` < `'0500'` < `'0545'`).
- `bolehJawab(string $tanggalKerja, \DateTimeInterface $sekarang): bool` — true bila `$sekarang` < `$tanggalKerja 06:00:00`.
- `keputusanKode(bool $adaRencana, bool $adaDiRencana, ?string $jawaban): string` — `'kirim'` | `'catat_tidak_hadir'` | `'tanpa_jawaban'`: tanpa rencana → `'kirim'`; tak ada di rencana → `'kirim'`; `'hadir'` → `'kirim'`; `'tidak_hadir'` → `'catat_tidak_hadir'`; null → `'tanpa_jawaban'`.

- [ ] **Step 1: failing tests** (`tests/penugasan/test_aturan.php`) minimal:
  - `orangHari('setengah') === 0.5`; `alasanTolakTugasOrang(['setengah','setengah']) === null`; `(['penuh','setengah'])` !== null; `(['setengah','setengah','setengah'])` !== null.
  - `pengingatMandor('19:05', false, null) === '1900'`; `('19:05', false, '1900') === null` (anti ganda); `('21:10', false, '1900') === '2100'` (lompat ke yang jatuh tempo terakhir); `('22:00', false, '2100') === 'owner'`; `('22:30', false, 'owner') === null`; `('21:10', true, null) === null`; `('18:59', false, null) === null`.
  - `pengingatPekerja('21:35', null, null) === '2130'`; `('23:00', null, '2130') === null`; `('03:00', null, '2130') === null`; `('05:01', null, '2130') === '0500'`; `('05:50', null, '0500') === '0545'`; `('05:50', 'hadir', null) === null`; `('06:01', null, '0545') === null`.
  - `bolehJawab('2026-11-07', new DateTime('2026-11-07 05:59:59')) === true`; `('2026-11-07', '2026-11-07 06:00:00') === false`; `('2026-11-07', '2026-11-06 20:00') === true`.
  - `keputusanKode(false, false, null) === 'kirim'`; `(true, false, null) === 'kirim'`; `(true, true, 'hadir') === 'kirim'`; `(true, true, 'tidak_hadir') === 'catat_tidak_hadir'`; `(true, true, null) === 'tanpa_jawaban'`.
  - `ikut(2) === false`, `ikut(5) === true`, `ikut(3) === true`, `ikut(7) === false`.
- [ ] **Step 2: FAIL → Step 3: implement → Step 4: PASS, manifest, canopi-check → Step 5: commit.**

### Task 4: Mandor Bertugas + halaman Rencana Besok + kirim konfirmasi

**Files:**
- Create: `app/Http/Controllers/PenugasanController.php`
- Create: `resources/views/penugasan/rencana.blade.php`
- Modify: `app/Services/TelegramService.php` (method baru)
- Modify: `app/Services/PenugasanAturan.php` (`perluTanyaUlang`)
- Modify: `routes/web.php`; sidebar Owner + Mandor (cari partial sidebar yang memuat link `kode-hari-ini`, tambahkan link "Rencana Besok" di tempat yang sama, juga drawer HP)
- Test: tambah ke `tests/penugasan/test_aturan.php`

**Interfaces:**
- Consumes: Task 2 tabel; Task 3 `ikut`, `alasanTolakTugasOrang`.
- Produces:
  - `TelegramService::kirimTombol(?string $chatId, string $pesan, array $tombol): bool` — `$tombol` = `[[['text'=>..,'callback_data'=>..], ...]]` (inline_keyboard), lewat `kirimRequest` yang diperluas (field `reply_markup` JSON). Fallback tanpa Markdown sama seperti `kirim()`.
  - `TelegramService::jawabTombol(string $callbackId, string $teks): void` (`answerCallbackQuery`).
  - `PenugasanAturan::perluTanyaUlang(array $tugasLama, array $tugasBaru): bool` — tiap tugas `['project_tahap_id'=>int,'porsi'=>string]`; true bila himpunan berbeda (urutan tak berpengaruh).
  - `callback_data` = `"hadir:{konfirmasi_id}"` / `"tidak:{konfirmasi_id}"`.
  - Route (`auth`): `GET /penugasan/rencana` `penugasan.rencana`, `POST /penugasan/rencana` `penugasan.simpan`, `POST /penugasan/mandor-bertugas` `penugasan.mandor` (`level:1`).
- Akses rencana: Owner, atau user yang `id = setting_penugasan.mandor_bertugas_id`; selainnya 403.

- [ ] **Step 1: failing test** (singkatan: `t(1,'penuh')` = `['project_tahap_id'=>1,'porsi'=>'penuh']`) `perluTanyaUlang([t(1,'penuh')],[t(1,'penuh')]) === false`; `([t(1,'setengah'),t(2,'setengah')],[t(2,'setengah'),t(1,'setengah')]) === false`; `([t(1,'penuh')],[t(1,'setengah'),t(2,'setengah')]) === true`; `([],[t(1,'penuh')]) === true`.
- [ ] **Step 2: FAIL → implement `perluTanyaUlang` → PASS.**
- [ ] **Step 3: Controller `rencana()`** — tanggal kerja = besok (`today()->addDay()`). Tampilkan: (a) project aktif (`projects.status` in `persiapan`,`pengerjaan`) dengan `project_tahap` status `belum`/`sedang`, berlabel "sedang X, tinggal Y, Z"; (b) daftar user aktif `ikut(level)`; tiap user diberi tanda bila besok izin/sakit/cuti disetujui (`IzinAbsen` approved, tanggal besok) atau `LiburService::isLibur(user, besok)` → baris dinonaktifkan dengan teks "Izin"/"Libur — aktifkan Kerja Hari Libur besok pagi bila perlu"; (c) form per user: maks 2 baris tugas (project_tahap + porsi), terisi dari `penugasan_harian` tanggal kerja bila ada, kalau belum dari tanggal hari ini ("sama seperti hari ini"); (d) Owner melihat dropdown "Mandor Bertugas" (user level 3 aktif) + tombol simpan. Mandor Bertugas otomatis Hadir: barisnya tetap bisa diberi tugas, tanpa konfirmasi; ada centang "Saya tidak masuk besok" (→ simpan `konfirmasi_hadir` jawaban `tidak_hadir` + notif Owner).
- [ ] **Step 4: Controller `simpan()`** dalam `DB::transaction`: validasi tiap user (`ikut`, tidak izin/libur, `alasanTolakTugasOrang`); ganti baris `penugasan_harian` tanggal kerja per user; upsert `rencana_harian` (`disimpan_oleh`, `disimpan_at`). Setelah commit: untuk tiap user (bukan Mandor Bertugas) yang tugasnya baru atau `perluTanyaUlang` → upsert `konfirmasi_hadir` dengan `jawaban=NULL, pengingat=NULL, dikirim_at=now()` lalu `kirimTombol` "Besok {tgl} kamu di {project} - {tahap} ({porsi}). Bersedia hadir?" tombol `Hadir` / `Tidak Hadir`. User yang dihapus dari rencana → hapus `konfirmasi_hadir`-nya (kembali ke "tak ada di rencana" = kode normal). User tanpa `telegram_chat_id` → tetap dibuat konfirmasi, tampilkan peringatan di halaman ("belum sambung Telegram — konfirmasikan manual" ⇒ Mandor/Owner bisa menandai Hadir untuknya lewat tombol kecil di baris itu; simpan `jawaban='hadir'`).
- [ ] **Step 5: `mandorBertugas()`** (Owner): validasi user level 3 aktif, update `setting_penugasan`.
- [ ] **Step 6: Verifikasi** — `php -l` file baru; `php scripts/canopi-check --full` (kompilasi blade) hijau; route terdaftar: `php artisan route:list --path=penugasan`.
- [ ] **Step 7: commit.**

### Task 5: Webhook menerima jawaban Hadir / Tidak Hadir

**Files:**
- Modify: `app/Http/Controllers/TelegramWebhookController.php`
- Modify: `app/Services/PenugasanAturan.php` (`parseTombol`)
- Test: `tests/telegram/test_webhook_secret.php` atau `tests/penugasan/test_aturan.php` (tambahan)

**Interfaces:**
- Consumes: Task 1 cek secret; Task 3 `bolehJawab`; Task 4 `jawabTombol`, format `callback_data`.
- Produces: `PenugasanAturan::parseTombol(string $data): ?array` → `['jawaban'=>'hadir'|'tidak_hadir','id'=>int]` atau null.

- [ ] **Step 1: failing tests** `parseTombol('hadir:12') === ['jawaban'=>'hadir','id'=>12]`; `('tidak:7') === ['jawaban'=>'tidak_hadir','id'=>7]`; `('hadir:abc') === null`; `('lain:1') === null`; `('') === null`.
- [ ] **Step 2: FAIL → implement → PASS.**
- [ ] **Step 3: `handle()`** — bila ada `callback_query`: ambil `konfirmasi_hadir` by id; tolak (jawab tombol, tanpa perubahan) bila tak ada, bila `users.telegram_chat_id` pemilik ≠ `callback_query.from.id`, atau `!bolehJawab(tanggal, now())` ("Batas jawab jam 06:00 sudah lewat. Hubungi mandor."). Valid → update `jawaban`, `dijawab_at`; `jawabTombol` "Tercatat: Hadir"/"Tercatat: Tidak Hadir (libur tanpa gaji)". Jawaban boleh diganti berulang sampai 06:00. Bila jawaban akhir `tidak_hadir` (dan berbeda dari sebelumnya) → `kirim` ke Mandor Bertugas + Owner (`TELEGRAM_OWNER_CHAT_ID` via pola notif owner yang ada) "{nama} TIDAK HADIR besok ({tgl}) — {project/tahap}. Cari pengganti." Alur `/start` lama tidak berubah. Selalu balas `200 OK` ke Telegram.
- [ ] **Step 4:** canopi-check hijau; commit.

### Task 6: Cron pengingat

**Files:**
- Create: `public/cron-penugasan.php`
- Test: tak ada test baru (logika di Task 3); cek `php -l`.

**Interfaces:**
- Consumes: Task 3 `pengingatMandor`, `pengingatPekerja`; Task 2 tabel.
- Pola file = `public/cron-kode-absen.php` (bootstrap Laravel, `CronAuth`, `&cek=1`, log `echo`).

- [ ] **Step 1: implement** — sekali jalan melakukan dua hal untuk jam WIB sekarang:
  - Mandor: tanggal kerja = besok bila jam ≥ 12:00, else hari ini (jangan dipakai sebelum 12:00). Besok libur nasional (`LiburService` libur nasional) → lewati. `$kode = pengingatMandor(jam, rencana_harian ada & disimpan_at not null, rencana_harian.pengingat_mandor)`; bila ada: upsert `rencana_harian.pengingat_mandor = $kode` DULU (anti ganda), lalu kirim ke Mandor Bertugas: `'1900'` biasa + link `route('penugasan.rencana')`, `'2030'` tegas "BELUM diisi", `'2100'` mendesak "Batas 22:00, setelah itu diambil alih Owner"; `'owner'` → kirim ke Owner "Mandor belum mengisi rencana besok" + daftar nama level 3/5/6 aktif yang belum ada di rencana & tidak izin/libur + link.
  - Pekerja: tanggal kerja = besok bila jam ≥ 12:00, else hari ini. Untuk tiap `konfirmasi_hadir` tanggal itu `jawaban IS NULL`: `$kode = pengingatPekerja(jam, null, pengingat)`; bila ada → update `pengingat` DULU, lalu `kirimTombol` ulang (teks: `'2130'` "Belum dijawab", `'0500'` "Batas jawab jam 06:00", `'0545'` "15 menit lagi, tanpa jawaban tidak dapat kode absen").
- [ ] **Step 2: crontab** (VPS, UTC; pola baris yang ada memakai `$(cat /root/.canopi-cron-key)` dan log `/root/cron-logs/penugasan.log`): `0 12`, `30 13`, `0 14`, `30 14`, `0 15`, `0 22`, `45 22` setiap hari → `cron-penugasan.php?key=...`. Backup crontab dulu ke `/root/crontab.backup-2026-11-06`. Uji hanya dengan `&cek=1` → `OK`.
- [ ] **Step 3:** canopi-check hijau; commit.

### Task 7: Kode absen mengikuti konfirmasi

**Files:**
- Modify: `public/cron-kode-absen.php`
- Test: `keputusanKode` sudah diuji di Task 3.

**Interfaces:**
- Consumes: Task 3 `keputusanKode`, `ikut`; Task 2 status `tidak_hadir`.

- [ ] **Step 1: implement** — sebelum query `$karyawan`: `$adaRencana = rencana_harian(tanggal=today) disimpan_at not null`. Untuk tiap `konfirmasi_hadir` hari ini dengan `jawaban='tidak_hadir'` → `Absensi::firstOrCreate(['user_id','tanggal'], ['status'=>'tidak_hadir','gaji_hari_ini'=>0,'uang_makan_hari_ini'=>0,'potongan_telat'=>0])` (tidak menimpa baris yang sudah ada, mis. izin). Tambahkan `'tidak_hadir'` ke `$offHariIni`. Dalam loop, untuk user `ikut(level)`: `$k = keputusanKode($adaRencana, ada penugasan_harian hari ini, jawaban)`; `'tanpa_jawaban'` → log "Skip (tak menjawab konfirmasi)" + `continue` (sebelum `barisHariIniUntuk`, jadi kode tidak dibuat). Lainnya jalan seperti sekarang. Satu ringkasan ke Mandor Bertugas: daftar yang tak dapat kode karena tak menjawab.
- [ ] **Step 2: Keputusan Elvan 6 Nov — tombol manual "Buat & Kirim Kode" jadi OWNER SAJA.** `routes/web.php`: route `absensi.kirim-kode` dari `level:1,3` → `level:1` (route `kode-hari-ini` & `kerja-hari-libur` TIDAK diubah). Di `resources/views/absensi/kode-hari-ini.blade.php` sembunyikan tombol itu untuk non-Owner (`Auth::user()->level == 1`). Tambahkan ke `tests/keamanan/test_akses_izin.php` atau test akses yang memeriksa middleware route (ikuti pola yang ada) satu assert: `absensi.kirim-kode` hanya `level:1`. Ini jalan keluar resmi (selain Koreksi Owner) untuk orang yang tak menjawab.
- [ ] **Step 3:** canopi-check hijau; commit.

### Task 8: Laporan kerja sore

**Files:**
- Modify: `app/Http/Controllers/PenugasanController.php` (`laporan`)
- Modify: `resources/views/absensi/form-pulang.blade.php`
- Modify: `app/Http/Controllers/AbsensiController.php::formPulang` (kirim data penugasan hari ini ke view; `absenPulang` TIDAK diubah)
- Modify: `routes/web.php`
- Modify: `app/Services/PenugasanAturan.php` (`statusTahapDariLaporan`)
- Test: tambah ke `tests/penugasan/test_aturan.php`

**Interfaces:**
- Produces: route `POST /penugasan/laporan` `penugasan.laporan` (`auth`, hanya baris milik user login untuk hari ini).
- Produces: `PenugasanAturan::statusTahapDariLaporan(string $statusTahap, string $laporan): ?string` → `'selesai'` bila laporan `'selesai'` dan tahap belum selesai; `'sedang'` bila laporan `'lanjut'` dan tahap `'belum'`; selain itu null.

- [ ] **Step 1: failing test** `statusTahapDariLaporan('belum','lanjut') === 'sedang'`; `('sedang','selesai') === 'selesai'`; `('belum','selesai') === 'selesai'`; `('selesai','lanjut') === null`; `('sedang','lanjut') === null`.
- [ ] **Step 2: FAIL → implement → PASS.**
- [ ] **Step 3:** `formPulang` mengirim `$tugasHariIni` (penugasan_harian hari ini milik user + nama project/tahap). View: kotak "Laporan Kerja Hari Ini" di atas tombol pulang (tak memblokir): per baris tugas pilih **Selesai / Lanjut besok**, ganti tahap/porsi bila berbeda, catatan; tombol "Kirim Laporan" (POST terpisah, fetch, tampilkan pesan sukses/gagal). User tanpa tugas tidak melihat kotak ini.
- [ ] **Step 4:** `laporan()` — validasi `alasanTolakTugasOrang` atas porsi baru; update baris (`laporan`, `catatan`, `dilaporkan_at`, `project_tahap_id`, `porsi`); terapkan `statusTahapDariLaporan` ke `project_tahap` (`'sedang'` → isi `tanggal_mulai_aktual` = hari ini bila kosong; `'selesai'` → `tanggal_selesai_aktual` = hari ini). Tombol Mulai/Selesai tahap lama TIDAK disentuh.
- [ ] **Step 5:** canopi-check hijau; commit.

### Task 9: Verifikasi akhir & catatan

- [ ] `php scripts/canopi-check --full` hijau; semua test baru terdaftar di manifest.
- [ ] Push; cek deploy live dengan `curl` (route baru POST tanpa token → 419; `cron-penugasan.php?key=...&cek=1` → `OK`).
- [ ] Update CLAUDE.md "TITIK LANJUT" + checklist validasi HP untuk Elvan: (a) Owner pilih Mandor Bertugas; (b) mandor isi rencana → tukang dapat Telegram bertombol; (c) Tidak Hadir → mandor & Owner dapat notif; (d) tak menjawab → pagi tak dapat kode, mandor dapat ringkasan; (e) laporan sore tampil di halaman pulang; (f) admin/toko tak berubah.

## Sengaja tidak termasuk (dari spec, ditunda)

- Foto di laporan sore (spec: opsional) — tambah bila diminta.
- "Hitung Saran" PIC di halaman rencana — tetap ada di panel Mulai Tahap; pindahkan bila diminta.
- Rekap khusus Tidak Hadir per bulan — cukup lewat status di rekap/slip yang sudah ada.
- Membuka kembali tahap yang terlanjur "Selesai" karena laporan salah — untuk sekarang Owner koreksi lewat phpMyAdmin/permintaan ke Claude; tombol buka-kembali ditambah bila kejadian.
- Menugaskan orang yang besok jadwal libur pada malam sebelumnya — ditolak; pakai aktivasi Kerja Hari Libur pagi harinya (alur lama).
