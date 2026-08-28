-- FILE: docs/sql/2026-08-28-cek-kalibrasi.sql
-- HANYA MEMBACA (SELECT). Tidak mengubah apa pun — aman dijalankan kapan saja.
--
-- Tujuan: menampilkan SEMUA angka yang dipakai mesin RAB untuk menghitung harga,
-- supaya bisa dibandingkan dengan biaya nyata di lapangan (= kalibrasi).
-- Jalankan di phpMyAdmin SATU PER SATU, lalu kirimkan hasilnya.

-- 1) Angka global: margin, consumable, finishing, tarif operasional
SELECT margin_default        AS 'Margin %',
       diskon_max            AS 'Diskon maks %',
       consumable_rangka     AS 'Consumable rangka /m2',
       consumable_atap       AS 'Consumable atap /m2',
       finishing_standar     AS 'Finishing standar /m2',
       powder_coating        AS 'Powder coating /m2',
       lay_hemat             AS 'Layanan hemat %',
       lay_kilat             AS 'Layanan kilat %',
       tarif_km              AS 'Tarif per km',
       tarif_genset          AS 'Tarif genset/hari',
       tarif_hotel           AS 'Tarif hotel/malam',
       tarif_kontrakan       AS 'Tarif kontrakan',
       tarif_makan           AS 'Tarif makan/orang/hari'
FROM rab_setting_global WHERE id = 1;

-- 2) Kecepatan kerja (m2/hari) + jumlah orang per tim.
--    INI YANG PALING SERING BIKIN HARGA MELESET kalau salah.
SELECT nama                    AS 'Jenis kerja',
       satuan                  AS 'Satuan',
       produktivitas_per_hari  AS 'Kecepatan FABRIKASI /hari',
       produktivitas_inst      AS 'Kecepatan INSTALASI /hari',
       jml_tukang              AS 'Tukang (fab)',
       jml_kenek               AS 'Kenek (fab)',
       jml_tukang_inst         AS 'Tukang (inst)',
       jml_kenek_inst          AS 'Kenek (inst)',
       skill_default           AS 'Skill dipakai'
FROM rab_jenis_kerja WHERE is_active = 1 ORDER BY urutan;

-- 3) Upah harian per skill (dikali jumlah orang di atas)
SELECT nama AS 'Skill', upah_tukang_harian AS 'Upah tukang/hari', upah_kenek_harian AS 'Upah kenek/hari'
FROM rab_skill ORDER BY nama;

-- 4) Atap: harga material, pemborosan, upah pasang, consumable per jenis
SELECT nama               AS 'Jenis atap',
       harga_per_m2       AS 'Harga material /m2',
       pemborosan_persen  AS 'Pemborosan %',
       upah_pasang_per_m2 AS 'Upah pasang /m2',
       consumable         AS 'Consumable /m2'
FROM rab_atap WHERE is_active = 1 ORDER BY nama;

-- 5) Harga besi yang paling sering dipakai + panjang batangnya
--    (panjang_batang_cm dipakai mesin cutting; kosong = dianggap 600cm)
SELECT nama AS 'Material', harga_pokok AS 'Harga per batang', panjang_batang_cm AS 'Panjang batang (cm)'
FROM master_material
WHERE aktif = 1 AND kategori = 'rangka_besi'
ORDER BY nama;

-- 6) Kondisi kerja (pengali upah: malam, beban berat, dll)
SELECT nama AS 'Kondisi', pengali_upah AS 'Pengali upah', tambahan_per_hari AS 'Tambahan /hari', kena AS 'Kena ke'
FROM rab_kondisi_kerja WHERE is_active = 1 ORDER BY urutan;
