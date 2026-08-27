-- FILE: docs/sql/2026-08-27-penawaran-json-longtext.sql
-- Jalankan di phpMyAdmin production SEBELUM push ke main (deploy = FTP sync,
-- BUKAN artisan migrate — sesuai pola semua fitur sebelumnya di project ini).
--
-- KENAPA: penawaran cetak sekarang ikut membawa gambar denah (tampak atas).
-- Satu gambar ±3-8 KB; penawaran dengan beberapa opsi/blok denah bisa puluhan KB.
-- Kalau kolom `penawaran_json` bertipe TEXT (batas 64 KB), kelebihannya DIPOTONG
-- DIAM-DIAM oleh MySQL — JSON jadi rusak, halaman penawaran tampil "Penawaran
-- belum dibuat" tanpa pesan error. LONGTEXT menutup risiko itu.
-- (Bandingkan: `rab_snapshot` sudah LONGTEXT, dicek 27 Agustus 2026.)

-- 1) CEK DULU tipe kolom saat ini. Kalau hasilnya sudah `longtext`, TIDAK PERLU
--    menjalankan statement nomor 2 — lewati saja.
SHOW COLUMNS FROM `pipeline_leads` LIKE 'penawaran_json';

-- 2) Naikkan ke LONGTEXT. Aman diulang (hasil akhirnya sama kalau sudah longtext).
ALTER TABLE `pipeline_leads`
  MODIFY `penawaran_json` LONGTEXT NULL;
