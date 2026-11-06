-- Jalankan di phpMyAdmin production SEBELUM push kode (pola project).
-- Aman diulang: CREATE TABLE IF NOT EXISTS + INSERT IGNORE; tidak ada UPDATE/DELETE.
--
-- KENAPA: fitur Penugasan Harian (rencana kerja besok per karyawan, konfirmasi
-- hadir malam sebelumnya, laporan hasil kerja) butuh 4 tabel baru, plus status
-- absensi baru 'tidak_hadir' (karyawan menjawab tidak hadir di konfirmasi).

-- ── BAGIAN A: cek tipe kolom absensi.status (SUDAH dijalankan) ──
-- Kirim hasilnya ke Claude dulu.
-- SHOW COLUMNS FROM absensi LIKE 'status';

-- ── BAGIAN B: tabel baru ──
CREATE TABLE IF NOT EXISTS `setting_penugasan` (
  `id`                 TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `mandor_bertugas_id` BIGINT UNSIGNED NULL,
  `updated_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `setting_penugasan` (`id`) VALUES (1);

CREATE TABLE IF NOT EXISTS `rencana_harian` (
  `tanggal`          DATE NOT NULL,
  `disimpan_oleh`    BIGINT UNSIGNED NULL,
  `disimpan_at`      DATETIME NULL,
  `pengingat_mandor` VARCHAR(10) NULL COMMENT '1900|2030|2100|owner = terakhir terkirim',
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `penugasan_harian` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal`         DATE NOT NULL,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `project_tahap_id` BIGINT UNSIGNED NOT NULL,
  `porsi`           VARCHAR(10) NOT NULL COMMENT 'penuh|setengah',
  `laporan`         VARCHAR(10) NULL COMMENT 'selesai|lanjut',
  `catatan`         TEXT NULL,
  `dilaporkan_at`   DATETIME NULL,
  `dibuat_oleh`     BIGINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_penugasan_tgl_user` (`tanggal`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `konfirmasi_hadir` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal`    DATE NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `jawaban`    VARCHAR(12) NULL COMMENT 'hadir|tidak_hadir',
  `dijawab_at` DATETIME NULL,
  `pengingat`  VARCHAR(10) NULL COMMENT '2130|0500|0545',
  `dikirim_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_konfirmasi_tgl_user` (`tanggal`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifikasi: SHOW TABLES LIKE '%penugasan%';

-- ── BAGIAN C: tambah 'tidak_hadir' ke ENUM absensi.status ──
-- Definisi asli (dikonfirmasi Elvan): enum('hadir','telat','setengah_hari','alpha',
-- 'sakit','izin','cuti','dinas_luar') NULL DEFAULT 'alpha'. Urutan lama dipertahankan,
-- 'tidak_hadir' ditaruh paling akhir. Aman diulang (hasil definisi selalu sama).
-- Catatan: 'diliburkan' (dipakai label di model) TIDAK ada di ENUM DB — celah lama,
-- sengaja tidak ditambahkan di sini.
ALTER TABLE `absensi` MODIFY `status` ENUM('hadir','telat','setengah_hari','alpha','sakit','izin','cuti','dinas_luar','tidak_hadir') NULL DEFAULT 'alpha';

-- Verifikasi:
SHOW COLUMNS FROM absensi LIKE 'status';
