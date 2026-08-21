-- FILE: docs/sql/2026-08-21-swe-fase2-skill-rekomendasi-pic.sql
-- Jalankan di phpMyAdmin production SEBELUM push ke main (deploy = FTP sync,
-- BUKAN artisan migrate — sesuai pola semua fitur sebelumnya di project ini).
-- Jalankan 2 statement ini SATU-SATU (bukan sekaligus), biar kalau salah satu
-- sudah pernah jalan (error 1060 "Duplicate column" / 1050 "Table already
-- exists"), tinggal skip baris itu dan lanjut ke baris berikutnya.

ALTER TABLE `rab_skill`
  ADD COLUMN `default_role` ENUM('tukang','kenek','tukang_kenek','manual') NOT NULL DEFAULT 'manual' AFTER `nama`;

CREATE TABLE IF NOT EXISTS `user_skill` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `rab_skill_id` BIGINT UNSIGNED NOT NULL COMMENT 'tanpa FK sengaja - rab_skill dibuat manual, tipe id tidak dipastikan',
  `sumber` ENUM('default_role','manual') NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `user_skill_user_id_foreign` (`user_id`),
  CONSTRAINT `user_skill_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
