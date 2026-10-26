-- docs/sql/2026-10-26-absensi-pulang-cepat.sql
-- Izin pulang cepat (spec 2026-10-26). Aman diulang (IF NOT EXISTS). Jalankan SEBELUM push.
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_status VARCHAR(10) NULL;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_alasan VARCHAR(255) NULL;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_oleh BIGINT UNSIGNED NULL;
