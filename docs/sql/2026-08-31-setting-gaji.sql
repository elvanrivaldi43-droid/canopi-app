-- FILE: docs/sql/2026-08-31-setting-gaji.sql
-- Jalankan di phpMyAdmin production SEBELUM push kode (pola project).
-- Aman diulang: error 1050 "Table already exists" / 1060 "Duplicate column" boleh dilewati.
--
-- KENAPA: dua kebijakan gaji yang selama ini DIPAKU DI KODE sekarang jadi saklar
-- yang bisa Bos nyalakan/matikan sendiri tanpa ubah kode:
--   1. Bonus KPI — ditunda (Elvan 31 Ags: "belum bisa nyalakan sekarang, masih banyak
--      yang belum selesai, mungkin Oktober atau Desember").
--   2. Tabungan wajib Rp 100.000 — karyawan belum diberi tahu, jadi belum boleh memotong.
--      Barisnya TETAP tampil di slip (tertulis Rp 0) supaya transparan, bukan disembunyikan.
--
-- Nominalnya sendiri tidak diubah (tetap 100.000 di kode) — yang disetel hanya
-- nyala/mati, sesuai keputusan Elvan.

CREATE TABLE IF NOT EXISTS `setting_gaji` (
  `id`              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `bonus_kpi_aktif`      TINYINT(1) NOT NULL DEFAULT 0,
  `tabungan_wajib_aktif` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baris tunggal. Default KEDUANYA MATI (0) sesuai keputusan 31 Ags.
INSERT IGNORE INTO `setting_gaji` (`id`, `bonus_kpi_aktif`, `tabungan_wajib_aktif`, `updated_at`)
VALUES (1, 0, 0, NOW());
