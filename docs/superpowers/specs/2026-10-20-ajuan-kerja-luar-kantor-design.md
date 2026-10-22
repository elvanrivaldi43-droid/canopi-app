# Ajuan Kerja di Luar Kantor (Admin Operasional & Toko Besi) — Design

Tanggal: 20 Okt 2026 · Status: disetujui Elvan di chat, menunggu review spec tertulis

## Latar

Per 20 Okt 2026 (commit `16d129a`) lapor progress siang & Kembali Kerja wajib di
titik lokasi (200 m) HANYA untuk Admin Operasional (level 2) dan Admin Toko Besi
(level 7). Level lain bebas, lokasi cukup dicatat. Absen masuk/pulang tetap wajib
di lokasi untuk semua.

Admin & Toko Besi kadang harus keluar di tengah hari (supplier, bank). Sekarang
satu-satunya jalan adalah Owner/Admin/Supervisor membuat "Luar Kota" manual di
`/luar-kota` — halaman yang bahkan tak punya link menu.

## Tujuan & kriteria berhasil

- Admin Operasional & Toko Besi bisa **mengajukan sendiri** kerja di luar kantor
  untuk **hari ini**, **setelah absen masuk**.
- Setelah **disetujui Owner atau Admin Operasional** (bukan diri sendiri), lapor
  siang, Kembali Kerja, dan absen pulang hari itu bebas lokasi (GPS tetap dicatat).
- Ada jejak siapa yang menyetujui/menolak.
- Besoknya otomatis normal tanpa tindakan apa pun.

## Keputusan Elvan (jangan didebat ulang)

| Hal | Keputusan |
|---|---|
| Siapa boleh mengajukan | Level 2 & 7 saja |
| Kapan | Hari ini saja, setelah absen masuk |
| Penyetuju | ~~Owner (1) atau Admin Operasional (2)~~ → revisi 21 Okt: ajuan Admin Ops → **Owner saja**; ajuan Marketing (4, ditambahkan) & Toko Besi → Owner atau Admin Ops |
| Yang dibebaskan | Lapor siang, Kembali Kerja, absen pulang (= perilaku Luar Kota yang ada) |

## Pendekatan: pakai ulang tabel `luar_kota`

Ajuan = baris `luar_kota` dengan `tanggal_mulai = tanggal_selesai = hari ini` dan
status baru **`menunggu`**. Disetujui → `aktif`; ditolak → **`ditolak`**.

Semua pengecualian GPS sudah lewat `LuarKota::sedangLuarKota()` yang hanya
membaca status `aktif` (`scopeAktifPadaTanggal`), jadi baris `menunggu`/`ditolak`
otomatis TIDAK membebaskan apa pun, dan setelah disetujui SEMUA titik absen ikut
bebas tanpa mengubah `AbsensiController`.

Ditolak dari alternatif: tabel ajuan baru (banyak kode, sambung ulang semua cek
GPS); izin tipe `dinas_luar` (justru menutup absen masuk & mengubah gaji).

### Pemakaian kolom yang ada (tanpa kolom baru)

| Kolom | Isi untuk ajuan |
|---|---|
| `user_id` | karyawan pengaju |
| `lokasi` | tujuan/keperluan yang diketik pengaju (wajib, maks 255) |
| `dibuat_oleh` | saat diajukan = pengaju; saat disetujui/ditolak = **penyetuju** (jejak) |
| `keterangan` | kosong saat diajukan; diisi alasan saat ditolak |
| `status` | `menunggu` → `aktif` / `ditolak` |

`dibuat_oleh == user_id` berarti "belum diproses / diajukan sendiri".

### Prasyarat database

Tipe kolom `luar_kota.status` tidak tercatat di repo. Elvan menjalankan
`SHOW COLUMNS FROM luar_kota LIKE 'status';`:
- `varchar(n)` → tak perlu SQL (nilai lama `dibatalkan` sudah 10 huruf, `menunggu`/`ditolak` lebih pendek).
- `enum(...)` → jalankan `docs/sql/2026-10-20-luar-kota-status-ajuan.sql`
  (`ALTER TABLE ... MODIFY status ENUM('aktif','selesai','dibatalkan','menunggu','ditolak') NOT NULL DEFAULT 'aktif'`
  — menambah nilai, aman diulang). Isi persis ENUM disalin dari hasil SHOW supaya
  tak ada nilai lama yang hilang.

Fitur TIDAK di-push sebelum prasyarat ini beres.

## Alur

### 1. Mengajukan (karyawan level 2/7)

- Halaman Absensi (`absensi/index.blade.php`): tombol **"Ajukan Kerja di Luar
  Kantor"** tampil bila: level 2/7, sudah absen masuk hari ini, belum absen
  pulang, dan tak ada baris `luar_kota` hari ini berstatus `aktif`/`menunggu`.
  Bila ada `menunggu` → tampil teks "Ajuan kerja di luar kantor menunggu
  persetujuan". Bila ada `ditolak` hari ini → tampil alasannya (boleh ajukan lagi).
- Form satu kolom: **Tujuan/keperluan** (wajib).
- `POST /absensi/ajukan-luar-kantor` (grup `auth`, cek level di server, bukan
  middleware — route absensi karyawan sengaja tak dikunci level).
- Server menolak bila: bukan level 2/7; belum absen masuk; sudah absen pulang;
  sudah ada `aktif`/`menunggu` hari ini.
- Notif Telegram ke semua user level 1 & 2 aktif ber-`telegram_chat_id`, kecuali
  pengaju: nama, tujuan, link `app.kanopibsd.co.id/luar-kota`.

### 2. Menyetujui / menolak (level 1 & 2)

- `luar-kota/index.blade.php`: bagian **"Ajuan Menunggu"** di atas, berisi baris
  `menunggu`. Tombol Setujui / Tolak (Tolak wajib alasan) hanya bila baris tsb
  bukan milik penonton dan penonton level 1/2.
- Ajuan `menunggu` dengan tanggal < hari ini tampil **"Kedaluwarsa"**, tanpa tombol.
- `POST /luar-kota/{id}/setujui`, `POST /luar-kota/{id}/tolak` — middleware
  `level:1,2`. Server menolak bila: status ≠ `menunggu`; tanggal ≠ hari ini;
  `user_id == Auth::id()` (tak boleh menyetujui diri sendiri).
- Setujui → `status=aktif`, `dibuat_oleh=penyetuju`; notif Telegram ke pengaju.
- Tolak → `status=ditolak`, `dibuat_oleh=penolak`, `keterangan=alasan`; notif.
- `labelStatus()`/`warnaStatus()` tambah `menunggu` & `ditolak`.

### 3. Besoknya

Tak ada cron/kode. `aktifPadaTanggal` hanya cocok di tanggal itu;
`autoUpdateStatus()` yang ada mengubah `aktif` lewat tanggal → `selesai`.

## Logika murni yang dites (tanpa DB)

Diletakkan sebagai `public static` di `LuarKota`:

- `bolehAjukan(int $level, bool $sudahMasuk, bool $sudahPulang, bool $adaAktifAtauMenunggu): ?string`
  → null kalau boleh, atau pesan alasan.
- `alasanTolakProses(string $status, string $tanggal, int $pemilikId, int $aktorId, string $hariIni): ?string`
  → null kalau boleh disetujui/ditolak.

## Tes

- `tests/absensi/test_ajuan_luar_kantor.php`: tabel kasus `bolehAjukan` &
  `alasanTolakProses` (level 4 ditolak, belum masuk ditolak, setujui diri sendiri
  ditolak, tanggal kemarin ditolak, status aktif ditolak, kasus normal lolos).
- `tests/keamanan/test_akses_izin.php` (atau file route baru): `luar-kota.setujui`
  & `luar-kota.tolak` ber-`level:1,2`; `absensi.ajukan-luar-kantor` TIDAK dikunci level.
- Regresi: `scopeAktifPadaTanggal` tetap hanya `status='aktif'` (cek string query
  builder `toSql()` memuat `status` = `aktif`).
- `php scripts/canopi-check --full` hijau sebelum push.

## Di luar cakupan (sengaja)

Ajuan multi-hari (pakai menu Luar Kota lama) · foto bukti (GPS sudah tercatat) ·
halaman riwayat khusus (sudah ada filter "Semua" di Luar Kota) · link menu Luar
Kota di sidebar (link ada di notif Telegram; tambah bila diminta).

## Risiko

- `luar-kota.index` dapat diakses level 3 (grup `level:1,2,3`). Supervisor akan
  MELIHAT ajuan tapi tak punya tombol & route setujui/tolak menolaknya. Diterima.
- Form Luar Kota manual (`create`) tak menyertakan level 7 — tidak diubah, di luar cakupan.
