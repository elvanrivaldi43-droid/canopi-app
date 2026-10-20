-- FILE: docs/sql/2026-10-20-luar-kota-status-ajuan.sql
-- Ajuan kerja di luar kantor (spec docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md)
-- Menambah status 'menunggu' & 'ditolak'. Nilai lama (aktif, selesai, dibatalkan),
-- NOT NULL, dan DEFAULT 'aktif' dipertahankan persis (dicek Elvan 20 Okt 2026:
-- enum('aktif','selesai','dibatalkan'), Null NO, Default aktif).
-- Aman diulang: menjalankan lagi hanya menulis definisi yang sama.
ALTER TABLE luar_kota
  MODIFY status ENUM('aktif','selesai','dibatalkan','menunggu','ditolak') NOT NULL DEFAULT 'aktif';

-- Cek sesudahnya: Type harus memuat 'menunggu' dan 'ditolak'
SHOW COLUMNS FROM luar_kota LIKE 'status';
