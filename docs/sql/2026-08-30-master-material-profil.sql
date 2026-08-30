-- FILE: docs/sql/2026-08-30-master-material-profil.sql
-- Jalankan di phpMyAdmin production SEBELUM push kode (pola project; error 1060
-- "Duplicate column" = sudah pernah jalan, aman dilewati).
-- Dimensi profil besi utk hitung tapak ruas support beririsan (spec 2026-08-30).
-- NULL = belum diisi -> sistem menebak dari nama; hollow "banci" (4x8 nyatanya
-- 3,5cm) HARUS diisi manual di sini.
ALTER TABLE `master_material`
  ADD COLUMN `lebar_profil_cm` DECIMAL(5,1) NULL AFTER `harga_pokok`,
  ADD COLUMN `tinggi_profil_cm` DECIMAL(5,1) NULL AFTER `lebar_profil_cm`;
