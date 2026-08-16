-- FILE: docs/sql/2026-08-16-swe-fase1-tahap-produksi.sql
-- Jalankan di phpMyAdmin production SEBELUM push ke main (deploy = FTP sync,
-- BUKAN artisan migrate — sesuai pola semua fitur sebelumnya di project ini).
-- Idempotent: aman dijalankan ulang / boleh skip error 1050 "table already exists".

CREATE TABLE IF NOT EXISTS `tahap_master` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `rab_jenis_kerja_id` BIGINT UNSIGNED NULL COMMENT 'tanpa FK sengaja - rab_jenis_kerja dibuat manual, tipe id tidak dipastikan',
  `tipe` ENUM('fab','inst') NULL,
  `urutan` INT NOT NULL DEFAULT 99,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `template_tahap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `jenis_project` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `template_tahap_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_tahap_id` BIGINT UNSIGNED NOT NULL,
  `tahap_master_id` BIGINT UNSIGNED NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `template_tahap_item_template_tahap_id_foreign` (`template_tahap_id`),
  KEY `template_tahap_item_tahap_master_id_foreign` (`tahap_master_id`),
  CONSTRAINT `template_tahap_item_template_tahap_id_foreign` FOREIGN KEY (`template_tahap_id`) REFERENCES `template_tahap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_tahap_item_tahap_master_id_foreign` FOREIGN KEY (`tahap_master_id`) REFERENCES `tahap_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tahap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `tahap_master_id` BIGINT UNSIGNED NULL,
  `nama_tahap` VARCHAR(255) NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `status` ENUM('belum','sedang','selesai') NOT NULL DEFAULT 'belum',
  `qty` DECIMAL(12,2) NULL,
  `satuan` VARCHAR(255) NULL,
  `tanggal_mulai_target` DATE NULL,
  `tanggal_selesai_target` DATE NULL,
  `tanggal_mulai_aktual` DATE NULL,
  `tanggal_selesai_aktual` DATE NULL,
  `jumlah_tukang_disarankan` INT NULL,
  `jumlah_kenek_disarankan` INT NULL,
  `catatan` TEXT NULL,
  `dibuat_oleh` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `project_tahap_project_id_foreign` (`project_id`),
  KEY `project_tahap_tahap_master_id_foreign` (`tahap_master_id`),
  KEY `project_tahap_dibuat_oleh_foreign` (`dibuat_oleh`),
  CONSTRAINT `project_tahap_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_tahap_master_id_foreign` FOREIGN KEY (`tahap_master_id`) REFERENCES `tahap_master` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_tahap_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tahap_pic` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_tahap_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `peran` ENUM('tukang','kenek') NOT NULL,
  `ditambahkan_oleh` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `project_tahap_pic_project_tahap_id_foreign` (`project_tahap_id`),
  KEY `project_tahap_pic_user_id_foreign` (`user_id`),
  KEY `project_tahap_pic_ditambahkan_oleh_foreign` (`ditambahkan_oleh`),
  CONSTRAINT `project_tahap_pic_project_tahap_id_foreign` FOREIGN KEY (`project_tahap_id`) REFERENCES `project_tahap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_pic_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_pic_ditambahkan_oleh_foreign` FOREIGN KEY (`ditambahkan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
