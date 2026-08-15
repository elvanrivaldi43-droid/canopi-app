-- =====================================================================
-- AUDIT TANGGAL BERGABUNG — DAFTAR PERIKSA UNTUK BOS (15 Agustus 2026)
--
-- FILE INI HANYA MEMBACA. Tidak ada satu pun perintah yang mengubah data.
-- Tidak ada UPDATE, ALTER, DELETE, INSERT, DROP, atau CREATE di sini —
-- dan itu diperiksa otomatis oleh tests/keamanan/test_masa_kerja_kasbon.php.
--
-- KENAPA FILE INI ADA
-- Syarat pengajuan Kasbon adalah masa kerja minimal 1 tahun. Sistem sekarang
-- membaca tanggal mulai bekerja dengan urutan:
--
--     1. tanggal_bergabung   (paling akurat, diisi Owner)
--     2. tgl_masuk_kerja     (wajib di form karyawan)
--     3. created_at          (TANGGAL BARIS DIBUAT DI SISTEM, bukan tanggal
--                             orangnya mulai kerja — jaring pengaman terakhir)
--
-- Karyawan lama yang barisnya baru dibuat waktu sistem ini dipasang bisa
-- terlihat "baru" kalau dua kolom pertamanya kosong. Sebelum ada keputusan
-- perbaikan data, Bos perlu MELIHAT DULU daftarnya: siapa saja yang jatuh ke
-- created_at, dan apakah tanggalnya masuk akal.
--
-- CARA JALANKAN
--   1. Buka phpMyAdmin -> KLIK nama database dulu di sidebar kiri
--      (kalau langsung ke tab SQL: error "No database selected").
--   2. Tempel isi file ini, jalankan.
--   3. Baca hasilnya. Tidak ada yang berubah di database.
--
-- SETELAH INI
--   Perbaikan data (backfill) BELUM dibuat dan BELUM boleh dijalankan.
--   Perintah perbaikan baru akan disiapkan SETELAH Bos memeriksa daftar ini
--   dan memberi izin terpisah, per orang, bukan borongan.
--
-- CATATAN EKSTENSI BROWSER: pernah kejadian (13 Agustus) ekstensi pengecek ejaan
-- diam-diam mengubah garis bawah jadi spasi (`user_id` -> `user id`) waktu ditempel.
-- Kalau SQL ditolak dengan pola aneh seperti itu, pakai jendela Incognito.
-- =====================================================================


-- ─────────────────────────────────────────────────────────────────────
-- 1. DAFTAR LENGKAP: ketiga tanggal + tanggal mana yang sebenarnya dipakai
--
--    Kolom `sumber_dipakai`  = nama kolom yang menang menurut urutan di atas.
--    Kolom `tanggal_efektif` = tanggal yang benar-benar dipakai menghitung.
--    Kolom `masa_kerja_bulan`= hasil hitungannya hari ini.
--    Kolom `lolos_syarat_kasbon` = apakah dia >= 12 bulan.
--    Kolom `perlu_diperiksa`  = TANDA PERHATIAN, baca kolom ini duluan.
-- ─────────────────────────────────────────────────────────────────────
SELECT
    u.name                                        AS nama,
    u.jabatan                                     AS jabatan,
    u.level                                       AS level,
    u.status                                      AS status,
    u.tanggal_bergabung                           AS tanggal_bergabung,
    u.tgl_masuk_kerja                             AS tgl_masuk_kerja,
    DATE(u.created_at)                            AS created_at,

    CASE
        WHEN u.tanggal_bergabung IS NOT NULL AND u.tanggal_bergabung <> '0000-00-00'
            THEN 'tanggal_bergabung'
        WHEN u.tgl_masuk_kerja   IS NOT NULL AND u.tgl_masuk_kerja   <> '0000-00-00'
            THEN 'tgl_masuk_kerja'
        ELSE 'created_at'
    END                                           AS sumber_dipakai,

    COALESCE(
        NULLIF(u.tanggal_bergabung, '0000-00-00'),
        NULLIF(u.tgl_masuk_kerja,   '0000-00-00'),
        DATE(u.created_at)
    )                                             AS tanggal_efektif,

    TIMESTAMPDIFF(
        MONTH,
        COALESCE(
            NULLIF(u.tanggal_bergabung, '0000-00-00'),
            NULLIF(u.tgl_masuk_kerja,   '0000-00-00'),
            DATE(u.created_at)
        ),
        CURDATE()
    )                                             AS masa_kerja_bulan,

    CASE WHEN TIMESTAMPDIFF(
            MONTH,
            COALESCE(
                NULLIF(u.tanggal_bergabung, '0000-00-00'),
                NULLIF(u.tgl_masuk_kerja,   '0000-00-00'),
                DATE(u.created_at)
            ),
            CURDATE()
         ) >= 12 THEN 'YA' ELSE 'BELUM'
    END                                           AS lolos_syarat_kasbon,

    CASE
        WHEN u.tanggal_bergabung IS NULL OR u.tanggal_bergabung = '0000-00-00'
            THEN CASE
                WHEN u.tgl_masuk_kerja IS NULL OR u.tgl_masuk_kerja = '0000-00-00'
                    THEN 'PERIKSA — jatuh ke created_at (tanggal sistem, bukan tanggal kerja)'
                ELSE 'periksa ringan — tanggal_bergabung kosong, pakai tgl_masuk_kerja'
            END
        WHEN u.tgl_masuk_kerja IS NOT NULL
             AND u.tgl_masuk_kerja <> '0000-00-00'
             AND u.tgl_masuk_kerja <> u.tanggal_bergabung
            THEN 'periksa ringan — dua kolom tanggal berbeda isinya'
        ELSE 'aman'
    END                                           AS perlu_diperiksa

FROM users u
ORDER BY
    (u.tanggal_bergabung IS NULL OR u.tanggal_bergabung = '0000-00-00') DESC,
    u.status ASC,
    u.level  ASC,
    u.name   ASC;


-- ─────────────────────────────────────────────────────────────────────
-- 2. RINGKASAN: berapa orang di tiap sumber
--    Kalau angka baris `created_at` di sini 0, tidak ada yang perlu
--    diperbaiki sama sekali dan urusan ini selesai.
-- ─────────────────────────────────────────────────────────────────────
SELECT
    CASE
        WHEN u.tanggal_bergabung IS NOT NULL AND u.tanggal_bergabung <> '0000-00-00'
            THEN '1. tanggal_bergabung (akurat)'
        WHEN u.tgl_masuk_kerja   IS NOT NULL AND u.tgl_masuk_kerja   <> '0000-00-00'
            THEN '2. tgl_masuk_kerja (cadangan, biasanya benar)'
        ELSE '3. created_at (PERIKSA — tanggal sistem)'
    END                       AS sumber_dipakai,
    COUNT(*)                  AS jumlah_karyawan,
    SUM(CASE WHEN u.status = 'aktif' THEN 1 ELSE 0 END) AS jumlah_aktif
FROM users u
GROUP BY sumber_dipakai
ORDER BY sumber_dipakai ASC;


-- ─────────────────────────────────────────────────────────────────────
-- 3. YANG PALING PENTING DILIHAT: karyawan AKTIF yang keputusan
--    kasbonnya bergantung pada created_at. Ini daftar yang bisa
--    salah tolak / salah loloskan.
-- ─────────────────────────────────────────────────────────────────────
SELECT
    u.name                     AS nama,
    u.jabatan                  AS jabatan,
    u.status                   AS status,
    u.tanggal_bergabung        AS tanggal_bergabung,
    u.tgl_masuk_kerja          AS tgl_masuk_kerja,
    DATE(u.created_at)         AS created_at,
    TIMESTAMPDIFF(MONTH, DATE(u.created_at), CURDATE()) AS masa_kerja_bulan_versi_sistem
FROM users u
WHERE u.status = 'aktif'
  AND (u.tanggal_bergabung IS NULL OR u.tanggal_bergabung = '0000-00-00')
  AND (u.tgl_masuk_kerja   IS NULL OR u.tgl_masuk_kerja   = '0000-00-00')
ORDER BY u.created_at ASC;
