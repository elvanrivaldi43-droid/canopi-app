-- FILE: docs/sql/2026-08-31-hapus-alpha-bryan-rizko.sql
-- Permintaan Elvan 31 Ags: hapus baris ALPHA bulan Agustus utk user 17 (Bryan)
-- & 18 (Rizko), termasuk potongannya.
--
-- URUTAN WAJIB: jalankan 1 -> periksa -> 2 -> periksa -> baru 3 (penghapusan).
-- Jangan lompat ke nomor 3 sebelum hasil 1 & 2 dilihat.
--
-- ⚠ PERHATIKAN SEBELUM MENGHAPUS: baris alpha yang kolom `jam_masuk`-nya TERISI
--   berarti orangnya BENAR-BENAR ABSEN MASUK hari itu (dicap alpha oleh cron krn
--   lupa absen pulang — kasus Bryan 6/8, masuk 07:09). Kalau baris itu DIHAPUS,
--   jejak kehadirannya ikut hilang dan hari itu jadi TANPA CATATAN = tetap tidak
--   dibayar. Kalau maunya hari itu DIBAYAR, jangan dihapus — pakai nomor 4
--   (ubah status) untuk baris ber-jam_masuk, dan nomor 3 hanya untuk baris yang
--   jam_masuk-nya kosong. Menghapus semua juga sah kalau memang itu maunya —
--   efek baiknya sama: alpha hilang -> bonus KPI mereka tidak hangus lagi.

-- ═══════════════════════════════════════════════════════════════════
-- 1) PASTIKAN id 17 & 18 memang Bryan & Rizko (jangan sampai salah orang).
-- ═══════════════════════════════════════════════════════════════════
SELECT id, name, tipe_gaji, gaji_harian FROM users WHERE id IN (17, 18);

-- ═══════════════════════════════════════════════════════════════════
-- 2) LIHAT DULU baris yang akan terhapus — periksa kolom jam_masuk!
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name, a.id AS absensi_id, a.tanggal, a.status, a.jam_masuk, a.jam_pulang,
       a.gaji_hari_ini, a.potongan_telat, a.keterangan
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE a.user_id IN (17, 18)
  AND a.status = 'alpha'
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY u.name, a.tanggal;

-- ═══════════════════════════════════════════════════════════════════
-- 3) HAPUS baris alpha itu (potongan_telat di baris tsb ikut hilang
--    karena satu baris dengan datanya). Aman diulang: kalau sudah
--    terhapus, hasilnya "0 rows affected".
-- ═══════════════════════════════════════════════════════════════════
DELETE FROM absensi
WHERE user_id IN (17, 18)
  AND status = 'alpha'
  AND MONTH(tanggal) = 8 AND YEAR(tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 4) ALTERNATIF utk baris ber-jam_masuk (JALANKAN SEBELUM nomor 3 kalau
--    hari itu mau DIBAYAR, bukan dihapus): ubah jadi setengah hari sesuai
--    kebijakan lupa-absen-pulang. Gaji = 50% gaji harian, potongan
--    dinolkan. Baris yang jam_masuk-nya kosong TIDAK disentuh nomor ini.
-- ═══════════════════════════════════════════════════════════════════
-- UPDATE absensi a JOIN users u ON u.id = a.user_id
-- SET a.status = 'setengah_hari',
--     a.gaji_hari_ini = u.gaji_harian * 0.5,
--     a.potongan_telat = 0,
--     a.keterangan = CONCAT(IFNULL(a.keterangan,''), ' | koreksi manual 31/8: lupa absen pulang -> setengah hari')
-- WHERE a.user_id IN (17, 18)
--   AND a.status = 'alpha'
--   AND a.jam_masuk IS NOT NULL
--   AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026;

-- ═══════════════════════════════════════════════════════════════════
-- 5) SETELAH nomor 3 (dan/atau 4): cek sisa potongan mereka bulan ini —
--    kalau masih ada potongan di hari-hari LAIN yang juga mau dihapus,
--    kabari, jangan hapus sendiri tanpa lihat dulu.
-- ═══════════════════════════════════════════════════════════════════
SELECT u.name, a.tanggal, a.status, a.potongan_telat
FROM absensi a JOIN users u ON u.id = a.user_id
WHERE a.user_id IN (17, 18)
  AND a.potongan_telat > 0
  AND MONTH(a.tanggal) = 8 AND YEAR(a.tanggal) = 2026
ORDER BY u.name, a.tanggal;
