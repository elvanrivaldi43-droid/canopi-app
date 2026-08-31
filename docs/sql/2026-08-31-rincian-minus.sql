-- FILE: docs/sql/2026-08-31-rincian-minus.sql
-- HANYA MEMBACA (SELECT). Tidak mengubah apa pun.
--
-- Temuan sejauh ini: yang benar-benar rugi cuma 2 karyawan HARIAN —
-- Sahrul hidayat (2 hari, -60.000) dan Bryan Arca Satryawan (1 hari, -20.000).
-- Yang janggal: gaji harian Sahrul Rp 120.000, tapi hari itu tercatat MINUS 30.000.
-- Kalau dia masuk lalu telat, seharusnya 120.000 dikurangi denda — bukan minus.
-- Berarti gaji hari itu TIDAK PERNAH TERISI, tapi dendanya tetap jalan.
-- Query di bawah membuktikan apa yang sebenarnya terjadi di hari-hari itu.

-- ═══════════════════════════════════════════════════════════════════
-- 1) TIGA HARI BERMASALAH ITU — apa adanya, semua kolom penentu.
--    Perhatikan `jam_masuk`: kalau KOSONG, berarti dia tak pernah absen masuk
--    tapi tetap didenda — itu bug penjaga denda.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, a.status,
       a.jam_masuk, a.jam_lapor_progress, a.jam_absen_siang, a.jam_pulang,
       a.gaji_hari_ini   AS 'Gaji hari itu',
       a.potongan_telat  AS 'Potongan',
       a.potongan_progress_dicatat AS 'Denda progress?',
       a.potongan_siang_dicatat    AS 'Denda siang?'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND u.tipe_gaji = 'harian' AND a.gaji_hari_ini < 0
ORDER BY u.name, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 2) Contoh 5 hari minus milik karyawan BULANAN — untuk memastikan
--    polanya sama (tak pernah absen masuk tapi didenda) atau beda.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', a.tanggal, a.status, a.jam_masuk,
       a.gaji_hari_ini AS 'Gaji hari itu', a.potongan_telat AS 'Potongan'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
  AND u.tipe_gaji = 'bulanan' AND a.gaji_hari_ini < 0
ORDER BY a.gaji_hari_ini ASC
LIMIT 5;

-- ═══════════════════════════════════════════════════════════════════
-- 3) STATUS SLIP AGUSTUS — Sahrul & Bryan sudah dibayar atau belum?
--    Ini menentukan cara memperbaikinya (hitung ulang vs transfer susulan).
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name AS 'Karyawan', u.tipe_gaji AS 'Tipe', s.status AS 'Status slip',
       s.gaji_pokok AS 'Gaji pokok', s.gaji_bersih AS 'Gaji bersih',
       s.tanggal_bayar AS 'Tanggal bayar'
FROM slip_gaji s JOIN users u ON u.id = s.user_id
WHERE s.bulan = 8 AND s.tahun = 2026 AND s.periode = 'gaji_bulanan'
ORDER BY FIELD(s.status,'dibayar','menunggu_konfirmasi','draft'), u.name;

-- ═══════════════════════════════════════════════════════════════════
-- 4) RIA NURAENI per tanggal — pertanyaan awal "kenapa gaji pokok 2,6 juta".
-- ═══════════════════════════════════════════════════════════════════
SELECT a.tanggal, a.status, a.jam_masuk,
       a.gaji_hari_ini AS 'Gaji hari itu', a.potongan_telat AS 'Potongan'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE u.name = 'RIA NURAENI' AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY a.tanggal;
