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

-- ── BAGIAN C: menunggu daftar ENUM lengkap dari Elvan ──
-- absensi.status adalah ENUM (NULL=YES, DEFAULT 'alpha'). Perlu:
--   ALTER TABLE absensi MODIFY status ENUM(<daftar asli persis>, 'tidak_hadir') NULL DEFAULT 'alpha';
-- JANGAN dijalankan sebelum daftar diisi.
