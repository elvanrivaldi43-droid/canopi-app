-- FILE: docs/sql/2026-08-31-cek-slip-ria.sql
-- HANYA MEMBACA (SELECT). Tidak mengubah apa pun.
--
-- Tujuan: cari tahu KENAPA gaji pokok RIA NURAENI di slip Agustus 2026 = Rp 2.600.000
-- padahal 25 hari hadir x Rp 100.000 = Rp 2.500.000 (selisih tepat 1 hari).
-- Catatan penting: di sistem ini "telat" SUDAH dihitung sebagai hadir, jadi
-- 25 hari itu sudah termasuk hari telat — bukan 25 + 1.
-- Dugaan: ada baris absensi yang punya `gaji_hari_ini` terisi tapi statusnya
-- BUKAN hari kerja (mis. izin/sakit). Query di bawah membuktikannya.

-- 1) RINCIAN PER TANGGAL — ini yang paling menjawab.
--    Lihat kolom `status` untuk baris yang `gaji_hari_ini`-nya > 0.
SELECT a.tanggal, a.status,
       a.gaji_hari_ini      AS 'Gaji hari itu',
       a.potongan_telat     AS 'Potongan telat',
       a.upah_hari_libur    AS 'Upah hari libur',
       a.uang_makan_hari_ini AS 'Uang makan'
FROM absensi a
JOIN users u ON u.id = a.user_id
WHERE u.name LIKE '%RIA%'
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY a.tanggal;

-- 2) REKAP PER STATUS — berapa baris & berapa rupiah per status.
--    Kalau ada baris ber-status izin/sakit/cuti dengan total > 0, itu biang selisihnya.
SELECT a.status,
       COUNT(*)                AS 'Jumlah hari',
       SUM(a.gaji_hari_ini)    AS 'Total gaji',
       SUM(a.potongan_telat)   AS 'Total potongan telat'
FROM absensi a
JOIN users u ON u.id = a.user_id
WHERE u.name LIKE '%RIA%'
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
GROUP BY a.status;

-- 3) ANGKA YANG MASUK SLIP — pembanding.
--    gaji_pokok slip = SUM(gaji_hari_ini) + SUM(potongan_telat)  [dikembalikan ke kotor]
SELECT SUM(a.gaji_hari_ini)                          AS 'Jumlah gaji harian (bersih)',
       SUM(a.potongan_telat)                         AS 'Jumlah potongan telat',
       SUM(a.gaji_hari_ini) + SUM(a.potongan_telat)  AS '= gaji pokok di slip'
FROM absensi a
JOIN users u ON u.id = a.user_id
WHERE u.name LIKE '%RIA%'
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026;

-- 4) ISI SLIP AGUSTUS-nya sendiri (buat mencocokkan).
SELECT s.status, s.hari_hadir, s.hari_telat, s.hari_izin, s.hari_alpha,
       s.gaji_pokok, s.total_tunjangan, s.bonus_kpi, s.kelas_kpi,
       s.potongan_telat, s.tabungan_wajib, s.gaji_bersih, s.tanggal_generate
FROM slip_gaji s
JOIN users u ON u.id = s.user_id
WHERE u.name LIKE '%RIA%' AND s.bulan = 8 AND s.tahun = 2026;
