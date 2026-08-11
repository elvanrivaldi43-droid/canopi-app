# Desain: Jadwal Libur Per-Karyawan

**Tanggal:** 11 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Ketemu pas ngobrolin kenapa karyawan yang libur masih dapat kode absen. Investigasi lanjut nemuin masalah lebih serius: `cron-alpha.php` juga gak punya konsep jadwal libur, jadi karyawan yang punya hari libur tetap tapi gak ngajuin izin buat itu (dikonfirmasi Elvan: memang gak pernah ngajuin) bisa ke-tandain "Alpha" — yang motong gaji hari itu DAN ngancurin kelas KPI bulanan (`hitungKelasKpi`: 1 hari Alpha = kelas "none"). Selain itu, `GajiService::hitungHariKerja()` ngitung hari kerja sebulan SERAGAM buat semua karyawan (semua hari kecuali Minggu), gak mandang jadwal libur masing-masing — bikin persen kehadiran (dasar kelas KPI) bias buat siapapun yang liburnya bukan hari Minggu.

---

## 1. Ringkasan

Sistem baru buat nyimpen jadwal libur per-karyawan (1 hari tetap per minggu, beda-beda per orang, ada yang gak punya libur tetap sama sekali) plus mekanisme tukar/skip/tambah libur per-tanggal lewat alur ajuan-approval (mirip izin, tapi konsepnya beda — ini jadwal, bukan alasan gak masuk). Dipakai buat benerin 3 titik yang selama ini nganggep semua karyawan kerja tiap hari kecuali izin resmi: cron Alpha, cron kode absen, dan perhitungan persen kehadiran KPI.

---

## 2. Kenapa dibutuhkan

Dikonfirmasi langsung oleh Elvan (11 Agustus): setiap karyawan punya jadwal libur di hari yang beda-beda, ada juga yang gak punya libur tetap sama sekali, dan kadang jadwalnya ditukar/dipindah/di-skip (misal minggu ini full kerja, minggu depan libur 2 hari). Selama ini karyawan yang libur di hari jadwalnya **tidak pernah mengajukan izin** untuk itu — karena bagi mereka itu memang hari liburnya, bukan kejadian yang perlu "diizinkan".

Dampak nyata dari ketiadaan sistem ini (dibuktikan lewat baca kode, bukan tebakan):
- `cron-alpha.php` (jalan 13:00 & 20:00 WIB) cuma cek "sudah absen masuk ATAU ada izin approved" — kalau dua-duanya tidak, ditandai Alpha. Hari libur tetap karyawan otomatis kena Alpha tiap minggu.
- `GajiService.php`: karyawan harian, gaji harian diakumulasi dari kolom `gaji_hari_ini` per baris absensi — hari Alpha tidak menyumbang apa-apa, jadi kehilangan bayaran hari itu.
- `hitungKelasKpi()`: `if ($alpha > 0) return 'none';` — SATU hari Alpha saja langsung menjatuhkan kelas KPI sebulan ke kelas terjelek, walau hari-hari lain masuk normal.
- `hitungHariKerja()` seragam (semua hari kecuali Minggu) dipakai sebagai pembagi `persenHadir` — karyawan yang liburnya bukan hari Minggu punya pembagi yang terlalu besar, bias ke bawah walau tanpa Alpha sama sekali.
- `cron-kode-absen.php` tetap kirim kode absen ke karyawan yang lagi libur (pertanyaan awal yang memicu investigasi ini).

---

## 3. Keputusan yang dikunci

### 3.1 Model data

| # | Keputusan | Detail |
|---|---|---|
| 1 | Jadwal default | Kolom baru `users.hari_libur_default` — hari dalam seminggu (Senin..Minggu), nullable = karyawan itu tidak punya jadwal libur tetap. Diisi Owner lewat form edit karyawan yang sudah ada (tambah 1 field, bukan halaman baru). |
| 2 | Pengecualian per-tanggal | Tabel baru `jadwal_libur`: `user_id`, `tanggal`, `jenis` (`tambah` = libur di tanggal ini walau bukan default, `batal` = jadwal default dibatalkan buat tanggal ini), `status` (`pending`/`approved`/`rejected`, default `pending`), `alasan` (nullable), `diproses_oleh`, `diproses_at`, timestamps. |
| 3 | Kenapa 2 jenis, bukan 1 form "pindah dari-ke" | Kasus "minggu ini full kerja, minggu depan libur 2 hari" jadi 3 ajuan simpel (1 `batal` + 2 `tambah`) — lebih gampang dibangun & dipahami daripada 1 form yang harus nampung segala kombinasi tukar. |
| 4 | Sumber kebenaran tunggal | `LiburService::isLibur(User $user, Carbon $tanggal): bool` — cek dulu ada `jadwal_libur` **approved** buat tanggal itu (menang mutlak, apapun `jenis`-nya menentukan hasil true/false), kalau tidak ada baru fallback ke `hari_libur_default` (cocok hari dalam minggu → true). |
| 5 | Cegah dobel ajuan | Sebelum insert ajuan baru, cek ada ajuan `pending`/`approved` di tanggal yang sama buat user itu — kalau ada, tolak. Pola sama persis dengan pengecekan duplikat di `IzinAbsenController::store()`, bukan pola baru. |

### 3.2 Titik integrasi

| # | File | Perubahan |
|---|---|---|
| 1 | `public/cron-alpha.php` | Di kedua blok (jam 13:00 & jam 20:00): tambah `LiburService::isLibur($k, $tanggal)` — kalau true, skip, tidak ditandai Alpha sama sekali. |
| 2 | `public/cron-kode-absen.php` | Tambahkan pengecekan `isLibur` ke pengecualian yang sudah ada (yang sekarang cuma exclude izin/sakit/cuti/dinas_luar) — karyawan libur ikut di-skip dari generate+kirim kode. |
| 3 | `app/Services/GajiService.php` | `hitungHariKerja(int $bulan, int $tahun)` → `hitungHariKerja(User $user, int $bulan, int $tahun)`. Logic: loop tiap hari di bulan itu, hitung yang **bukan** `LiburService::isLibur($user, $hari)` — menggantikan logic lama yang cuma exclude hari Minggu seragam. Berlaku untuk SEMUA karyawan (harian maupun bulanan), karena `kelasKpi`/`bonusKpi` dihitung untuk keduanya. |

### 3.3 Halaman & alur baru

| # | Halaman | Untuk siapa | Mirip pola |
|---|---|---|---|
| 1 | Field "Hari Libur Default" di form edit karyawan (sudah ada) | Owner | — (tambahan field, bukan halaman baru) |
| 2 | `/jadwal-libur/create` — form ajuan (tanggal, jenis, alasan opsional) | Karyawan | `/izin/create` |
| 3 | `/jadwal-libur` — riwayat ajuan sendiri | Karyawan | `/izin/index` |
| 4 | `/jadwal-libur/approval` — list pending + riwayat, tombol Approve/Tolak | Owner/Mandor (level 1 & 3) | `/izin/approval` |
| 5 | Notifikasi Telegram: ajuan baru → Owner/Mandor; hasil approve/tolak → karyawan | — | `IzinAbsenController::kirimNotifPengajuan`/`kirimNotifHasilIzin`, reuse `TelegramService` |

Link menu baru ditambahkan di sidebar karyawan (ajukan) dan sidebar Owner/Mandor (approval), sama seperti menu izin sekarang.

### 3.4 Validasi

- Tanggal ajuan harus masa depan (`after:today`), sama seperti izin.
- Duplikat dicegah di level aplikasi (lihat 3.1 poin 5), bukan constraint unik di database — konsisten dengan pola `IzinAbsenController` yang sudah ada.

---

## 4. Testing

**Logic murni (standalone, tanpa database — pola sama seperti `tests/rangka/*.php` yang sudah ada di proyek ini):**

File baru `tests/jadwal-libur/test_libur_service.php`:
- `isLibur()`: default cocok hari → true. Ada `jadwal_libur` approved jenis `batal` di tanggal itu → false (menang lawan default). Ada approved jenis `tambah` di hari bukan-default → true. Karyawan tanpa default & tanpa pengecualian → selalu false.
- `hitungHariKerja()`: karyawan dengan default 1 hari/minggu → total hari sebulan dikurangi kemunculan hari itu. Karyawan tanpa default → semua hari kehitung sebagai hari kerja. Ada pengecualian tambah/batal di bulan itu → ikut mengoreksi angka. Kasus tepi: Februari, pergantian bulan.

**Butuh verifikasi manual di production (tidak bisa diuji headless bermakna, sama seperti fitur interaktif lain di proyek ini):**
- Form ajuan karyawan + approval Owner + notifikasi Telegram (kirim ajuan, approve, tolak)
- `cron-alpha.php` beneran skip karyawan yang libur (dites pas jam asli atau trigger manual)
- `cron-kode-absen.php` beneran skip kirim kode ke yang libur
- Slip gaji bulan berikutnya: `hari_kerja` yang tampil beda per-karyawan sesuai jadwal libur masing-masing

---

## 5. Yang TIDAK berubah

Alur izin/sakit/cuti/dinas_luar yang sudah ada (`IzinAbsen` model/tabel/controller), halaman absensi lain di luar `hitungHariKerja`, perhitungan Kasbon (`tanggal_bergabung`/`created_at` buat syarat masa kerja — tidak terkait jadwal libur).

---

## 6. Di luar cakupan / dicatat, bukan diperbaiki di sini

- **SP Karyawan (surat peringatan otomatis)** — kalau nanti fitur ini pakai hitungan Alpha, otomatis ikut kebantu begitu akar masalah Alpha ini dibenerin, tapi tidak dicek eksplisit di sini (status modul SP sendiri belum jelas, di luar cakupan — lihat catatan di `CLAUDE.md`).
- **Ditemukan sekilas, TIDAK diperbaiki di sini:** migrasi tabel `absensi` (`2026_06_02_112742_create_absensi_table.php`) definisikan `status` sebagai `enum('hadir','telat','setengah_hari','sakit','izin','diliburkan','alpha')` — tidak ada `'cuti'` atau `'dinas_luar'` di daftar itu, padahal `IzinAbsenController::updateAbsensiDariIzin()` menulis `status` dari `$izin->tipe` yang bisa berisi kedua nilai itu. Ini bug pre-existing yang tidak berhubungan dengan jadwal libur — dicatat di sini biar tidak hilang, perlu sesi terpisah buat verifikasi & perbaikan.
