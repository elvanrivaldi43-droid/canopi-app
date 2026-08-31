-- FILE: docs/sql/2026-08-31-cek-gaji-harian-minus.sql
-- HANYA MEMBACA (SELECT). Tidak mengubah apa pun.
--
-- KENAPA: hasil query sebelumnya menunjukkan status "telat" punya TOTAL GAJI MINUS
-- dan angka pecahan aneh (2.394.999,99). Query lama saya salah — pakai LIKE '%RIA%'
-- yang menangkap semua nama mengandung "ria" (Supriadi, Fitriani, dst), jadi datanya
-- tercampur banyak karyawan. Yang di bawah ini akurat DAN memeriksa seberapa luas
-- masalahnya, bukan cuma satu orang.

-- ═══════════════════════════════════════════════════════════════════
-- 1) SIAPA SAJA yang namanya mengandung "ria" — supaya tahu query lama
--    kemarin sebenarnya menggabung berapa orang.
-- ═══════════════════════════════════════════════════════════════════
SELECT id, name, level, status, gaji_harian, tipe_gaji
FROM users WHERE name LIKE '%ria%' ORDER BY name;

-- ═══════════════════════════════════════════════════════════════════
-- 2) PALING PENTING — berapa banyak baris absensi yang GAJI HARIANNYA MINUS,
--    di SELURUH karyawan bulan Agustus. Ini yang menentukan apakah masalahnya
--    satu orang atau sistemik.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, a.status,
       a.gaji_hari_ini   AS 'Gaji hari itu (MINUS?)',
       a.potongan_telat  AS 'Potongan telat',
       u.gaji_harian     AS 'Gaji harian seharusnya'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND a.gaji_hari_ini < 0
ORDER BY u.name, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 3) Baris yang potongannya MELEBIHI gaji harian orang itu — calon minus
--    berikutnya, walau hari ini belum minus.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, a.status,
       u.gaji_harian AS 'Gaji harian', a.potongan_telat AS 'Potongan', a.gaji_hari_ini AS 'Sisa'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND a.potongan_telat > u.gaji_harian
ORDER BY a.potongan_telat DESC;

-- ═══════════════════════════════════════════════════════════════════
-- 4) Status ALPHA tapi kena potongan telat — orang tidak masuk kok
--    dipotong karena telat?
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, a.status, a.jam_masuk,
       a.potongan_telat AS 'Potongan telat', a.gaji_hari_ini AS 'Gaji'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND a.status = 'alpha' AND a.potongan_telat > 0
ORDER BY u.name, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 5) RIA NURAENI saja — rincian per tanggal (query lama diperbaiki).
--    Ganti angka 8/2026 kalau mau lihat bulan lain.
-- ═══════════════════════════════════════════════════════════════════
SELECT a.tanggal, a.status, a.jam_masuk, a.jam_pulang,
       a.gaji_hari_ini AS 'Gaji hari itu', a.potongan_telat AS 'Potongan telat'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE u.name = 'RIA NURAENI'
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 6) Ada tanggal DOBEL? (dua baris absensi untuk orang & tanggal yang sama)
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, COUNT(*) AS 'Jumlah baris'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
GROUP BY a.user_id, a.tanggal HAVING COUNT(*) > 1;

-- ═══════════════════════════════════════════════════════════════════
-- 7) Tipe kolom uang — angka pecahan "2.394.999,99" menandakan kolomnya
--    FLOAT/DOUBLE (tidak akurat untuk uang), bukan DECIMAL.
-- ═══════════════════════════════════════════════════════════════════
SHOW COLUMNS FROM absensi LIKE '%gaji%';
SHOW COLUMNS FROM absensi LIKE '%potongan%';
