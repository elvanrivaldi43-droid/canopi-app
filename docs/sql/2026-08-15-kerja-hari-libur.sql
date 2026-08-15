-- =====================================================================
-- KERJA HARI LIBUR — SQL PRODUCTION (15 Agustus 2026)
--
-- WAJIB dijalankan di phpMyAdmin production SEBELUM push kode fitur ini.
-- Auto-deploy (GitHub Actions) cuma FTP sync + clear cache, TIDAK menjalankan
-- migration. Kalau kode naik duluan: halaman absensi, rekap, profil, slip gaji,
-- dan cron alpha akan error "unknown table/column".
--
-- Cara jalankan:
--   1. Buka phpMyAdmin -> KLIK nama database dulu di sidebar kiri
--      (kalau langsung ke tab SQL: error "No database selected").
--   2. Tempel SELURUH isi file ini, jalankan sekali.
--   3. Aman diulang (idempotent) — semua ALTER pakai IF NOT EXISTS.
--   4. LIHAT HASIL bagian 4 (SHOW INDEX kode_absen). Kalau hasilnya kosong,
--      JANGAN push dulu — ikuti instruksi di bagian itu.
--
-- CATATAN EKSTENSI BROWSER: pernah kejadian (13 Agustus) ekstensi pengecek ejaan
-- diam-diam mengubah garis bawah jadi spasi (`user_id` -> `user id`) waktu ditempel.
-- Kalau SQL ditolak dengan pola aneh seperti itu, pakai jendela Incognito.
-- =====================================================================


-- ─────────────────────────────────────────────────────────────────────
-- 1. Tabel otorisasi "masuk kerja di hari libur"
--    1 baris = 1 karyawan = 1 tanggal. Baris ini MEMBATALKAN libur di tanggal
--    tersebut: hari itu jadi hari kerja biasa (jatah libur terpakai, tanpa hari
--    pengganti). Isinya audit siapa yang mengaktifkan + snapshot tarif upahnya.
--    Snapshot dipakai supaya perubahan gaji belakangan tidak mengubah histori.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `kerja_hari_libur` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`              BIGINT UNSIGNED NOT NULL,
  `tanggal`              DATE NOT NULL,
  `diaktifkan_oleh`      BIGINT UNSIGNED NULL,
  `gaji_harian_snapshot` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `uang_makan_snapshot`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`           TIMESTAMP NULL DEFAULT NULL,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kerja_hari_libur_user_tanggal_unique` (`user_id`, `tanggal`),
  KEY `kerja_hari_libur_tanggal_index` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- 2. Penanda + nominal audit di tabel absensi
--    kerja_hari_libur = hari itu sebenarnya jadwal libur, tapi karyawan masuk atas
--    otorisasi Owner/Mandor (dan tanggalnya jadi hari kerja biasa). upah_hari_libur = nominal KOTOR dari snapshot;
--    potongan tetap lewat kolom potongan_telat yang sudah ada.
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `absensi`
  ADD COLUMN IF NOT EXISTS `kerja_hari_libur` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `absensi`
  ADD COLUMN IF NOT EXISTS `upah_hari_libur` DECIMAL(12,2) NOT NULL DEFAULT 0.00;


-- ─────────────────────────────────────────────────────────────────────
-- 3. Rincian kerja hari libur di slip gaji
--    hari_kerja_libur cuma menghitung status yang benar-benar bekerja
--    (hadir/telat/setengah hari) — diisi oleh GajiService, bukan manual.
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `slip_gaji`
  ADD COLUMN IF NOT EXISTS `hari_kerja_libur` INT NOT NULL DEFAULT 0;

ALTER TABLE `slip_gaji`
  ADD COLUMN IF NOT EXISTS `upah_hari_libur` DECIMAL(12,2) NOT NULL DEFAULT 0.00;


-- ─────────────────────────────────────────────────────────────────────
-- 4. kode_absen — VERIFIKASI INDEX: LANGKAH WAJIB SEBELUM PUSH.
--
-- Jalankan perintah di bawah dan LIHAT HASILNYA. Ini bukan langkah opsional:
-- kalau index-nya ternyata tidak ada, kode fitur ini TIDAK BOLEH di-push dulu.
--
-- Kenapa: `user_id` + UNIQUE (tanggal, user_id) dipasang manual 5 Agustus 2026
-- (fitur kode absen per-karyawan) dan TIDAK PERNAH masuk migrasi, jadi tidak ada
-- mekanisme otomatis apa pun yang memastikan dia masih terpasang. Padahal
-- KodeAbsen::barisHariIniUntuk() (createOrFirst) dan cron kode absen pagi
-- bergantung penuh pada unique itu untuk keatomikannya. Tanpa index tersebut,
-- createOrFirst diam-diam turun jadi "cek lalu insert" biasa: dua request barengan
-- (cron retry + Owner menekan "Aktifkan Masuk Hari Ini") bisa menghasilkan DUA kode
-- valid untuk satu karyawan di satu hari, dan kode bisa terkirim ulang.
--
-- Perintah ini hanya MEMBACA, tidak mengubah apa pun:

SHOW INDEX FROM `kode_absen` WHERE Key_name = 'kode_absen_tanggal_user_unique';

-- Hasil yang benar: 2 baris (satu per kolom: `tanggal` dan `user_id`), Non_unique = 0.
--   * Ada hasilnya  -> lanjut, tidak ada yang perlu dijalankan untuk kode_absen.
--   * KOSONG        -> STOP, jangan push dulu. Jalankan perintah perbaikan di bawah
--                      (hapus tanda -- di depannya), lalu ulangi SHOW INDEX di atas
--                      sampai keluar hasilnya.
--
--   ALTER TABLE `kode_absen` ADD UNIQUE INDEX `kode_absen_tanggal_user_unique` (`tanggal`, `user_id`);
--
-- Sengaja dibiarkan terkomentar, jangan dijalankan buta-buta: di MySQL < 8.0.29
-- `ADD INDEX IF NOT EXISTS` tidak didukung, dan menambah unique index yang sudah ada
-- akan error. Kalau ALTER-nya gagal karena ada data kembar, bersihkan dulu duplikat
-- (tanggal, user_id) di kode_absen sebelum mencoba lagi.
--
-- Catatan instalasi BARU (bukan production): migrasi
-- 2026_08_15_000004_add_user_unique_to_kode_absen_table.php memasang kolom+index yang
-- sama lewat `php artisan migrate`, dan melewati dirinya sendiri kalau sudah ada.
-- ─────────────────────────────────────────────────────────────────────


-- ─────────────────────────────────────────────────────────────────────
-- Verifikasi setelah dijalankan (opsional, tidak mengubah data):
--   SHOW COLUMNS FROM `absensi`   LIKE '%hari_libur%';   -- harus 2 baris
--   SHOW COLUMNS FROM `slip_gaji` LIKE '%libur%';        -- harus 2 baris
--   SELECT COUNT(*) FROM `kerja_hari_libur`;             -- harus 0 (tabel baru)
-- ─────────────────────────────────────────────────────────────────────
