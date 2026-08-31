-- FILE: docs/sql/2026-08-31-cek-dampak-minus.sql
-- HANYA MEMBACA (SELECT). Tidak mengubah apa pun.
--
-- Query sebelumnya menemukan 101 baris absensi bergaji MINUS di Agustus 2026.
-- Yang menentukan bahaya atau tidak: TIPE GAJI karyawannya.
--   - tipe BULANAN  -> gaji pokok diambil dari `users.gaji_bulanan`, angka harian
--                      TIDAK dipakai. Minus di situ jelek tapi tidak merugikan.
--   - tipe HARIAN   -> gaji pokok = JUMLAH `gaji_hari_ini` sebulan. Satu hari minus
--                      langsung MENGGERUS gaji hari-hari lain. INI YANG BAHAYA.
--
-- Jalankan nomor 1 dulu — satu tabel itu sudah menjawab pertanyaan utamanya.

-- ═══════════════════════════════════════════════════════════════════
-- 1) JAWABAN UTAMA — siapa saja yang punya baris minus, tipe gajinya apa,
--    dan berapa rupiah yang tergerus. Kolom "BAHAYA?" yang dibaca duluan.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name                         AS 'Karyawan',
       u.tipe_gaji                    AS 'Tipe gaji',
       CASE WHEN u.tipe_gaji = 'harian' THEN 'YA - gaji berkurang'
            ELSE 'tidak (gaji dari bulanan)' END AS 'BAHAYA?',
       COUNT(*)                       AS 'Hari minus',
       SUM(a.gaji_hari_ini)           AS 'Total minus (Rp)',
       u.gaji_harian                  AS 'Gaji harian'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026 AND a.gaji_hari_ini < 0
GROUP BY u.id, u.name, u.tipe_gaji, u.gaji_harian
ORDER BY u.tipe_gaji, SUM(a.gaji_hari_ini);

-- ═══════════════════════════════════════════════════════════════════
-- 2) KHUSUS karyawan HARIAN yang kena — berapa gaji pokok mereka SEKARANG
--    vs SEHARUSNYA (kalau hari minus dianggap 0, bukan negatif).
--    Selisihnya = uang yang hilang dari karyawan itu.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name                                            AS 'Karyawan',
       SUM(a.gaji_hari_ini)                              AS 'Terhitung sekarang',
       SUM(GREATEST(a.gaji_hari_ini, 0))                 AS 'Seharusnya (minus jadi 0)',
       SUM(GREATEST(a.gaji_hari_ini, 0)) - SUM(a.gaji_hari_ini) AS 'HILANG (Rp)'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND u.tipe_gaji = 'harian'
GROUP BY u.id, u.name
HAVING SUM(GREATEST(a.gaji_hari_ini, 0)) <> SUM(a.gaji_hari_ini)
ORDER BY 4 DESC;

-- ═══════════════════════════════════════════════════════════════════
-- 3) RIA NURAENI per tanggal — jawaban asli pertanyaan "kenapa 2,6 juta".
-- ═══════════════════════════════════════════════════════════════════
SELECT a.tanggal, a.status, a.jam_masuk,
       a.gaji_hari_ini AS 'Gaji hari itu', a.potongan_telat AS 'Potongan'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE u.name = 'RIA NURAENI' AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 4) Slip Agustus yang SUDAH DIBAYAR — mana saja yang perlu ditinjau ulang
--    kalau ternyata karyawan harian ikut kena.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', u.tipe_gaji AS 'Tipe', s.status AS 'Status slip',
       s.gaji_pokok AS 'Gaji pokok', s.gaji_bersih AS 'Gaji bersih', s.tanggal_bayar AS 'Dibayar'
FROM slip_gaji s JOIN users u ON u.id = s.user_id
WHERE s.bulan = 8 AND s.tahun = 2026 AND s.periode = 'gaji_bulanan'
ORDER BY s.status DESC, u.name;
