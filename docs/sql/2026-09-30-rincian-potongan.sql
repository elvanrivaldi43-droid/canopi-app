-- =====================================================================
-- RINCIAN POTONGAN — SQL PRODUCTION (30 September 2026)
--
-- WAJIB dijalankan di phpMyAdmin production SEBELUM push kode fitur ini.
-- Kalau kode naik duluan: absen masuk, denda checkpoint, absen siang, dan
-- koreksi akan error "Unknown column rincian_potongan".
--
-- Cara: klik nama database di sidebar kiri -> tab SQL -> tempel SELURUH isi
-- file -> jalankan. Aman diulang (IF NOT EXISTS).
-- Hasil 2 SHOW COLUMNS di bawah HARUS masing-masing 1 baris. Kalau kosong,
-- JANGAN push.
-- =====================================================================

ALTER TABLE `absensi`   ADD COLUMN IF NOT EXISTS `rincian_potongan` TEXT NULL AFTER `potongan_telat`;
ALTER TABLE `slip_gaji` ADD COLUMN IF NOT EXISTS `rincian_potongan` TEXT NULL AFTER `potongan_telat`;

SHOW COLUMNS FROM `absensi`   LIKE 'rincian_potongan';
SHOW COLUMNS FROM `slip_gaji` LIKE 'rincian_potongan';
