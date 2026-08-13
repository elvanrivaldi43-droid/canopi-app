# Desain: Libur Nasional / Libur Bersama

**Tanggal:** 13 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Dipicu pertanyaan Elvan soal Lebaran (libur ~2 minggu), Tahun Baru, 17 Agustus. Dicek ke kode: sistem sekarang cuma punya `LiburService` buat jadwal libur **per-karyawan** (hari libur tetap mingguan + ajuan tukar/skip/tambah individual, dibangun 11 Agustus). Nol konsep libur yang berlaku ke SEMUA karyawan sekaligus di tanggal yang sama. Tanpa ini, `cron-alpha.php` bakal nandain semua karyawan Alpha pas libur nasional (motong gaji + jatuhin kelas KPI — persis masalah yang mendorong dibikinnya fitur jadwal libur individual kemarin), `cron-kode-absen.php` tetap kirim kode absen, dan `GajiService::hitungHariKerja()` tetap hitung hari itu sebagai hari kerja normal.

---

## 1. Ringkasan

Kalender libur nasional (Lebaran, Tahun Baru, 17 Agustus, dll) yang berlaku ke SEMUA karyawan sekaligus, dikelola Owner lewat halaman kalender visual. Terintegrasi ke `LiburService` yang sudah ada — 4 titik konsumen existing (`cron-alpha.php`, `cron-kode-absen.php`, `GajiService`, `ProfilController`) otomatis ikut kecover tanpa disentuh. Ada mekanisme pengecualian per-karyawan per-tanggal ("piket") buat kasus driver/teknisi yang kadang tetap kerja pas libur nasional.

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Siapa input & gimana | Owner (level 1 saja) input manual lewat halaman kalender visual — klik tanggal mulai, klik tanggal selesai, isi nama liburnya. Bukan config file di kode, bukan diminta ke Claude tiap tahun. |
| 2 | Akses | Kelola (tambah/hapus/piket) khusus Owner. Karyawan & level lain bisa **lihat** kalender yang sama, read-only. |
| 3 | Piket/pengecualian | Ditambahkan TERPISAH, belakangan, per-tanggal — bukan bersamaan pas input libur nasionalnya. Owner pilih tanggal (yang sudah termasuk rentang libur nasional) + pilih karyawan yang tetap kerja hari itu. |
| 4 | Notifikasi | Broadcast Telegram ke semua karyawan connect Telegram pas libur nasional baru ditambahkan. Karyawan yang ditunjuk piket dapat notif personal terpisah. |
| 5 | Precedence vs jadwal pribadi | Libur nasional MENANG lawan jadwal libur individual karyawan (kalau bentrok tanggal) — KECUALI karyawan itu ada di daftar piket tanggal itu, baru jadwal individualnya yang berlaku seperti hari biasa. |

---

## 3. Model data

**Tabel baru `libur_nasional`:**
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `nama` | string | mis. "Lebaran 2026" |
| `tanggal_mulai` | date | |
| `tanggal_selesai` | date | |
| `dibuat_oleh` | bigint (FK users) | Owner yang input |
| timestamps | | |

**Tabel baru `libur_nasional_piket`:**
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `libur_nasional_id` | bigint (FK) | buat keperluan tampilan/list, bukan dipakai logic inti |
| `user_id` | bigint (FK users) | karyawan yang piket |
| `tanggal` | date | tanggal spesifik dia piket (bukan seluruh rentang) |
| timestamps | | |

Kenapa granularitas per-tanggal (bukan per-seluruh-rentang libur): sudah dikonfirmasi Elvan — piket biasanya cuma 1-2 hari dalam rentang libur panjang seperti Lebaran, bukan sepanjang rentangnya.

---

## 4. Integrasi ke `LiburService`

**Prinsip: TIDAK mengubah `cocokLiburPada()`/`hitungHariKerjaPada()` (logic murni, sudah teruji 25 assertion).** Libur nasional "menyamar" jadi override tambahan berbentuk sama persis dengan override individual yang sudah ada (`['tanggal'=>..., 'jenis'=>'tambah'|'batal']`), disisipkan di method wrapper database (`ambilOverride()` / method baru `ambilLiburNasional()`) — **ditaruh PALING DEPAN** di array overrides sebelum override pribadi karyawan, karena `cocokLiburPada()` return di override pertama yang cocok tanggalnya (jadi yang paling depan menang).

Logic method baru `ambilLiburNasional(User $user, Carbon $dari, Carbon $sampai): array`:
1. Ambil semua `libur_nasional` yang rentangnya overlap `$dari`..`$sampai`.
2. Buat tiap tanggal dalam rentang itu: kalau `$user` **tidak** ada di `libur_nasional_piket` buat tanggal itu → masukkan `['tanggal'=>..., 'jenis'=>'tambah']` ke hasil.
3. Kalau `$user` ADA di piket buat tanggal itu → skip (jangan generate override), biar jatuh ke override pribadi/`hari_libur_default` seperti hari biasa.

`isLibur()` dan `hitungHariKerja()` (wrapper) tinggal `array_merge($this->ambilLiburNasional(...), $this->ambilOverride(...))` sebelum lempar ke method pure — urutan merge ini yang bikin poin keputusan #5 (precedence) kejamin.

**4 titik konsumen existing (`cron-alpha.php:62`, `cron-kode-absen.php:48`, `GajiService.php:112`, `ProfilController.php:34`) TIDAK PERLU diubah** — mereka manggil `isLibur()`/`hitungHariKerja()` yang sama, cuma sekarang hasilnya lebih lengkap.

---

## 5. Halaman & routes baru

| Route | Method | Akses | Keterangan |
|---|---|---|---|
| `/libur-nasional` | GET | Semua level (auth) | Kalender bulanan (filter bulan/tahun, prev/next — pola sama `rekap-bulanan`). Tanggal libur nasional di-highlight + nama libur kelihatan. Owner lihat tombol kelola, level lain read-only. |
| `/libur-nasional` | POST | Owner saja | Tambah libur baru: nama + tanggal_mulai + tanggal_selesai (dipilih 2-klik di kalender). |
| `/libur-nasional/{id}` | DELETE | Owner saja | Hapus (jaga-jaga salah input). |
| `/libur-nasional/{id}/piket` | POST | Owner saja | Tambah pengecualian: user_id + tanggal (tanggal harus dalam rentang libur itu). |
| `/libur-nasional-piket/{id}` | DELETE | Owner saja | Batalkan piket. |

**Menu:** link "Libur Nasional" ditambah ke KEDUA sidebar (`sidebar-owner.blade.php` dan `sidebar-pipeline.blade.php`), konsisten dengan keputusan "Owner kelola, karyawan lihat".

---

## 6. Notifikasi Telegram

Reuse `TelegramService::kirim()` yang sudah ada, tidak ada jalur baru:
- **Broadcast** ke semua `User::whereNotNull('telegram_chat_id')` pas Owner simpan libur nasional baru — isi: nama libur + tanggal mulai-selesai. Loop sinkron (14 karyawan, bukan volume besar, gak perlu queue — konsisten sama pola notif lain di proyek ini yang juga sinkron).
- **Personal** ke karyawan yang baru ditunjuk piket — isi: tanggal piket.

---

## 7. Testing

**Logic murni (standalone, tanpa DB — pola sama `tests/jadwal-libur/test_libur_service.php`):**
File baru atau ditambahkan ke file yang sama, test kasus:
1. Tanggal dalam rentang libur nasional → `isLibur()` true, walau karyawan gak punya jadwal libur pribadi di situ.
2. Karyawan yang di-piket-in tanggal itu → `isLibur()` false (kerja normal), jadwal individual/`hari_libur_default` yang menentukan seperti biasa.
3. Libur nasional menang kalau bentrok sama override pribadi karyawan (mis. karyawan sudah ajuin "Tambah kerja"/batal libur di tanggal yang kebetulan jadi libur nasional — nasional yang menang, KECUALI dia di-piket-in).
4. `hitungHariKerja()` sebulan yang ada libur nasional di tengahnya → hari itu gak dihitung sebagai hari kerja buat karyawan yang bukan piket, TAPI dihitung normal buat yang piket.

**Butuh verifikasi manual di production (tidak bisa diuji headless bermakna):**
- Kalender visual: klik pilih rentang tanggal, simpan, tampil dengan benar di bulan itu.
- Owner tambah libur nasional → broadcast Telegram beneran nyampe ke karyawan yang connect.
- Owner tunjuk piket → notif personal nyampe ke karyawan itu.
- `cron-alpha.php` beneran skip karyawan pas libur nasional (kecuali yang piket).
- `cron-kode-absen.php` beneran skip kirim kode pas libur nasional (kecuali yang piket).
- Slip gaji bulan yang ada libur nasionalnya: `hari_kerja` berkurang sesuai, kecuali buat karyawan piket.

---

## 8. Yang TIDAK berubah

`cocokLiburPada()`/`hitungHariKerjaPada()` (logic pure, 0 perubahan), `jadwal_libur` (tabel & alur ajuan individual existing, tidak disentuh — libur nasional itu tabel terpisah), alur izin/sakit/cuti/dinas_luar, validasi silang izin↔jadwal-libur yang baru selesai 12 Agustus (di luar cakupan, gak berinteraksi langsung).

---

## 9. Di luar cakupan / dicatat, bukan diperbaiki di sini

- **Edit libur nasional yang sudah ada** (ubah tanggal/nama setelah disimpan) — v1 cuma tambah + hapus. Kalau salah input, hapus lalu tambah ulang. Ditambah nanti kalau ternyata sering kepakai.
- **Rekap/laporan piket** (siapa saja yang piket sepanjang tahun) — belum ada halaman ringkasan, cuma kelihatan per-tanggal di kalender. YAGNI sampai ada kebutuhan nyata.
- **Import kalender libur nasional resmi pemerintah otomatis** (API/scrape) — Owner input manual sesuai keputusan #1, bukan otomatis dari sumber luar.
