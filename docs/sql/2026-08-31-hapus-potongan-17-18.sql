-- FILE: docs/sql/2026-08-31-hapus-potongan-17-18.sql
-- Permintaan Elvan 31 Ags: hapus SEMUA potongan Agustus utk user 17 (Bryan) &
-- 18 (Rizco) — mereka belum disosialisasikan aturan absen siang/checkpoint.
--
-- CATATAN YANG SUDAH DISADARI: kolom potongan_telat adalah WADAH CAMPURAN
-- (denda siang + denda progress + denda telat-masuk asli). Tidak bisa dipisah
-- dari data, jadi penghapusan ini ikut menghapus denda telat asli bulan ini.
--
-- KENAPA DUA ARAH: saat denda dicatat, gaji_hari_ini SUDAH dikurangi. Kalau
-- cuma menolkan potongan, uangnya tidak kembali (gaji pokok slip = jumlah
-- gaji_hari_ini). Maka: gaji dikembalikan (gaji += potongan) DAN potongan = 0.
--
-- URUTAN: 1 (lihat dulu) -> periksa -> 2 & 3 (eksekusi) -> 4 (cek hasil)
-- -> lalu tekan "Hitung Ulang" di slip Bryan & Rizco (draft) di halaman
-- Penggajian — tanpa itu slip masih angka lama.

-- ═══════════════════════════════════════════════════════════════════
-- 1) LIHAT DULU: baris yang akan berubah + angka sebelum/sesudah.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name, a.tanggal, a.status,
       a.gaji_hari_ini                      AS 'Gaji sekarang',
       a.potongan_telat                     AS 'Potongan (dihapus)',
       CASE WHEN a.status IN ('hadir','telat','setengah_hari')
            THEN a.gaji_hari_ini + a.potongan_telat
            ELSE a.gaji_hari_ini END        AS 'Gaji sesudah'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE a.user_id IN (17, 18) AND a.potongan_telat > 0
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY u.name, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 2) Hari KERJA (hadir/telat/setengah hari): kembalikan gaji + nolkan potongan.
--    Aman diulang: setelah jalan sekali, potongan sudah 0 -> tak ada baris kena lagi.
-- ═══════════════════════════════════════════════════════════════════
UPDATE absensi
SET gaji_hari_ini  = gaji_hari_ini + potongan_telat,
    potongan_telat = 0,
    keterangan     = CONCAT(IFNULL(keterangan,''), ' | potongan dihapus 31/8 (belum sosialisasi absen siang)')
WHERE user_id IN (17, 18)
  AND potongan_telat > 0
  AND status IN ('hadir', 'telat', 'setengah_hari')
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 3) Hari BUKAN kerja (izin/sakit/dst yang entah kenapa kena denda):
--    nolkan potongannya SAJA — gajinya memang 0, jangan ditambah.
-- ═══════════════════════════════════════════════════════════════════
UPDATE absensi
SET potongan_telat = 0,
    keterangan     = CONCAT(IFNULL(keterangan,''), ' | potongan dihapus 31/8 (belum sosialisasi absen siang)')
WHERE user_id IN (17, 18)
  AND potongan_telat > 0
  AND status NOT IN ('hadir', 'telat', 'setengah_hari')
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 4) CEK HASIL: harus nol baris.
-- ═══════════════════════════════════════════════════════════════════
SELECT COUNT(*) AS 'Sisa baris berpotongan (harus 0)'
FROM absensi
WHERE user_id IN (17, 18) AND potongan_telat > 0
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;
