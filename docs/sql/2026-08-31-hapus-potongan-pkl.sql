-- FILE: docs/sql/2026-08-31-hapus-potongan-pkl.sql
-- Permintaan Elvan 31 Ags: hapus potongan Agustus utk id 11, 12, 13, 14, 16
-- (anak PKL — belum disosialisasikan aturan absen siang/checkpoint).
--
-- Kelompok ini yang kemarin slipnya MINUS besar (denda checkpoint menumpuk di
-- gaji harian 0, s/d -696rb). Selain menolkan potongan, query ini juga
-- MENORMALKAN gaji harian yang terlanjur minus kembali ke arah nol.
--
-- URUTAN: 1 (pastikan nama!) -> 2 (lihat dulu) -> 3 & 4 (eksekusi) -> 5 (cek)
-- -> lalu "Hitung Ulang" slip mereka di halaman Penggajian.

-- ═══════════════════════════════════════════════════════════════════
-- 1) PASTIKAN id 11-14 & 16 memang anak PKL yang dimaksud (baca NAMANYA
--    sebelum lanjut — jangan sampai potongan orang lain ikut terhapus).
-- ═══════════════════════════════════════════════════════════════════
SELECT id, name, jabatan, level, tipe_gaji, gaji_harian
FROM users WHERE id IN (11, 12, 13, 14, 16) ORDER BY id;

-- ═══════════════════════════════════════════════════════════════════
-- 2) LIHAT DULU: baris yang akan berubah (berpotongan ATAU bergaji minus).
-- ═══════════════════════════════════════════════════════════════════
SELECT u.id, u.name, a.tanggal, a.status,
       a.gaji_hari_ini  AS 'Gaji sekarang',
       a.potongan_telat AS 'Potongan (dihapus)',
       CASE WHEN a.status IN ('hadir','telat','setengah_hari')
            THEN GREATEST(a.gaji_hari_ini + a.potongan_telat, 0)
            ELSE GREATEST(a.gaji_hari_ini, 0) END AS 'Gaji sesudah'
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE a.user_id IN (11, 12, 13, 14, 16)
  AND (a.potongan_telat > 0 OR a.gaji_hari_ini < 0)
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY u.id, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 3) Hari KERJA: kembalikan gaji (mentok bawah 0 — jangan sisakan minus)
--    + nolkan potongan. Aman diulang.
-- ═══════════════════════════════════════════════════════════════════
UPDATE absensi
SET gaji_hari_ini  = GREATEST(gaji_hari_ini + potongan_telat, 0),
    potongan_telat = 0,
    keterangan     = CONCAT(IFNULL(keterangan,''), ' | potongan dihapus 31/8 (PKL, belum sosialisasi absen siang)')
WHERE user_id IN (11, 12, 13, 14, 16)
  AND (potongan_telat > 0 OR gaji_hari_ini < 0)
  AND status IN ('hadir', 'telat', 'setengah_hari')
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 4) Hari BUKAN kerja (alpha/izin/dst): nolkan potongan + normalkan gaji
--    minus ke 0. Gaji TIDAK ditambah balik di sini (hari non-kerja memang 0).
-- ═══════════════════════════════════════════════════════════════════
UPDATE absensi
SET gaji_hari_ini  = GREATEST(gaji_hari_ini, 0),
    potongan_telat = 0,
    keterangan     = CONCAT(IFNULL(keterangan,''), ' | potongan dihapus 31/8 (PKL, belum sosialisasi absen siang)')
WHERE user_id IN (11, 12, 13, 14, 16)
  AND (potongan_telat > 0 OR gaji_hari_ini < 0)
  AND status NOT IN ('hadir', 'telat', 'setengah_hari')
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 5) CEK HASIL: dua-duanya harus 0.
-- ═══════════════════════════════════════════════════════════════════
SELECT SUM(potongan_telat > 0)  AS 'Sisa berpotongan (harus 0)',
       SUM(gaji_hari_ini < 0)   AS 'Sisa gaji minus (harus 0)'
FROM absensi
WHERE user_id IN (11, 12, 13, 14, 16)
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;
