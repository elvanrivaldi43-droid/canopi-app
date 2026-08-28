SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT NULL,
  `foto_siang_1` varchar(255) DEFAULT NULL,
  `foto_siang_2` varchar(255) DEFAULT NULL,
  `foto_siang_3` varchar(255) DEFAULT NULL,
  `lat_siang` decimal(10,7) DEFAULT NULL,
  `lng_siang` decimal(10,7) DEFAULT NULL,
  `gps_valid_siang` tinyint(1) DEFAULT 0,
  `jam_absen_siang` time DEFAULT NULL,
  `jam_lapor_progress` time DEFAULT NULL,
  `pertanyaan_progress` text DEFAULT NULL,
  `jawaban_progress` text DEFAULT NULL,
  `status_pekerjaan` varchar(50) DEFAULT NULL,
  `ada_kendala` tinyint(1) DEFAULT 0,
  `jenis_kendala` varchar(100) DEFAULT NULL,
  `deskripsi_kendala` text DEFAULT NULL,
  `kendala_kenapa` text DEFAULT NULL,
  `lat_kembali_kerja` decimal(10,7) DEFAULT NULL,
  `lng_kembali_kerja` decimal(10,7) DEFAULT NULL,
  `1ng_kembali_kerja` decimal(10,7) DEFAULT NULL,
  `gps_valid_kembali_kerja` tinyint(1) DEFAULT NULL,
  `lembur_jam` decimal(4,2) DEFAULT 0.00,
  `lembur_approved` tinyint(1) DEFAULT 0,
  `lembur_approved_oleh` bigint(20) DEFAULT NULL,
  `lat_masuk` decimal(10,7) DEFAULT NULL,
  `lng_masuk` decimal(10,7) DEFAULT NULL,
  `lat_pulang` decimal(10,7) DEFAULT NULL,
  `lng_pulang` decimal(10,7) DEFAULT NULL,
  `status` enum('hadir','telat','setengah_hari','alpha','sakit','izin','cuti','dinas_luar') DEFAULT 'alpha',
  `keterangan` text DEFAULT NULL,
  `foto_surat` varchar(255) DEFAULT NULL,
  `potongan_telat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `uang_makan_hari_ini` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gaji_hari_ini` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dikoreksi` tinyint(1) NOT NULL DEFAULT 0,
  `alasan_koreksi` varchar(255) DEFAULT NULL,
  `dikoreksi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `gps_valid_masuk` tinyint(1) DEFAULT 0,
  `gps_valid_pulang` tinyint(1) DEFAULT 0,
  `potongan_siang_dicatat` tinyint(1) DEFAULT 0,
  `potongan_progress_dicatat` tinyint(1) NOT NULL DEFAULT 0,
  `kerja_hari_libur` tinyint(1) NOT NULL DEFAULT 0,
  `upah_hari_libur` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `izin_absen` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `tipe` enum('sakit','izin','cuti','dinas_luar','setengah_hari') NOT NULL,
  `alasan` text NOT NULL,
  `foto_surat` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `diproses_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan_mandor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `diproses_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jadwal_libur` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `tanggal_baru` date DEFAULT NULL,
  `jenis` enum('tambah','batal','tukar') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `alasan` text DEFAULT NULL,
  `diproses_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `diproses_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `karyawan_tunjangan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tunjangan_master_id` bigint(20) UNSIGNED NOT NULL,
  `nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kasbon` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `tanggal` date NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `cicilan_per_bulan` decimal(12,2) NOT NULL,
  `jumlah_cicilan` tinyint(4) NOT NULL,
  `cicilan_ke` tinyint(4) DEFAULT 0,
  `sisa_kasbon` decimal(12,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `ditunda_sampai` date DEFAULT NULL,
  `approved_oleh` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `kategori` enum('kebutuhan_pribadi','kesehatan','pendidikan','renovasi_rumah','lainnya') DEFAULT 'kebutuhan_pribadi',
  `kategori_lainnya` varchar(255) DEFAULT NULL,
  `alasan_tolak` text DEFAULT NULL,
  `ttd_digital` text DEFAULT NULL,
  `ttd_tanggal` datetime DEFAULT NULL,
  `ditolak_oleh` bigint(20) DEFAULT NULL,
  `ditolak_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `kendaraan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL COMMENT 'Contoh: Suzuki Carry SS T120',
  `plat` varchar(20) NOT NULL,
  `jenis` varchar(50) DEFAULT NULL COMMENT 'Mobil/Motor/Pickup',
  `standar_km_per_liter` decimal(5,2) NOT NULL DEFAULT 9.00 COMMENT 'Standar konsumsi BBM km/liter',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kerja_hari_libur` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `diaktifkan_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `gaji_harian_snapshot` decimal(12,2) NOT NULL DEFAULT 0.00,
  `uang_makan_snapshot` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kode_absen` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `kode` varchar(6) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `komplain_karyawan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `sumber` enum('customer','internal','supervisor') NOT NULL DEFAULT 'customer',
  `keterangan` text NOT NULL,
  `bobot_potongan` decimal(5,2) DEFAULT 10.00 COMMENT 'Poin yang dikurangi dari KPI',
  `dicatat_oleh` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kpi_setting` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key_setting` varchar(100) NOT NULL,
  `value_setting` varchar(255) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `levels` (
  `id` tinyint(4) NOT NULL,
  `nama_level` varchar(255) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `redirect_to` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `libur_nasional` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(255) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `libur_nasional_piket` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `libur_nasional_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `log_bensin` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kendaraan_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `tujuan` varchar(255) NOT NULL,
  `km_awal` decimal(10,1) NOT NULL,
  `km_akhir` decimal(10,1) DEFAULT NULL COMMENT 'Diisi saat pulang',
  `liter` decimal(6,2) NOT NULL,
  `nominal` int(11) NOT NULL COMMENT 'Rp nominal BBM',
  `km_tempuh` decimal(10,1) DEFAULT NULL COMMENT 'Dihitung otomatis: km_akhir - km_awal',
  `konsumsi_aktual` decimal(6,2) DEFAULT NULL COMMENT 'Dihitung otomatis: km_tempuh / liter',
  `status` enum('berangkat','selesai') NOT NULL DEFAULT 'berangkat',
  `catatan` varchar(255) DEFAULT NULL,
  `notif_boros_terkirim` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `luar_kota` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Karyawan yang luar kota',
  `dibuat_oleh` bigint(20) UNSIGNED NOT NULL COMMENT 'Yang mengaktifkan',
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `lokasi` varchar(255) NOT NULL COMMENT 'Nama lokasi project',
  `keterangan` text DEFAULT NULL,
  `status` enum('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `master_material` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(30) DEFAULT NULL,
  `nama` varchar(150) NOT NULL,
  `kategori` enum('rangka_besi','kaca','atap','cat_finishing','aksesori','talang','konsumabel','jasa','lainnya') NOT NULL DEFAULT 'lainnya',
  `satuan` varchar(20) NOT NULL DEFAULT 'pcs',
  `harga_pokok` bigint(20) NOT NULL DEFAULT 0,
  `sumber` enum('pos','luar') NOT NULL DEFAULT 'luar',
  `keterangan` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `panjang_batang_cm` int(11) NOT NULL DEFAULT 600
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pembayaran_project` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_project` bigint(20) UNSIGNED NOT NULL,
  `jenis` enum('dp','termin','lunas') NOT NULL,
  `nominal` bigint(20) NOT NULL DEFAULT 0,
  `tanggal_bayar` date DEFAULT NULL,
  `metode` varchar(50) DEFAULT NULL COMMENT 'Transfer BCA, Cash, dll',
  `bukti_transfer` varchar(255) DEFAULT NULL COMMENT 'Path foto bukti',
  `keterangan` text DEFAULT NULL,
  `dikonfirmasi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `dikonfirmasi_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','dikonfirmasi') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pipeline_followups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pipeline_lead_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `metode` enum('whatsapp','telepon','email','kunjungan','lainnya') NOT NULL DEFAULT 'whatsapp',
  `catatan` text NOT NULL,
  `tgl_followup_berikutnya` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pipeline_leads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama_customer` varchar(255) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `alamat` text DEFAULT NULL,
  `lokasi_area` varchar(255) DEFAULT NULL,
  `lokasi_patokan` varchar(255) DEFAULT NULL,
  `lokasi_maps_link` varchar(500) DEFAULT NULL,
  `lokasi_sharelok` varchar(500) DEFAULT NULL,
  `lokasi_lat` decimal(10,7) DEFAULT NULL,
  `lokasi_lng` decimal(10,7) DEFAULT NULL,
  `lokasi_gps_at` timestamp NULL DEFAULT NULL,
  `lokasi_jarak_km` decimal(8,2) DEFAULT NULL,
  `lokasi_listrik` varchar(20) DEFAULT NULL,
  `lokasi_jarak_listrik_m` int(11) DEFAULT NULL,
  `lokasi_izin_nginap` varchar(10) DEFAULT NULL,
  `lokasi_akses` varchar(50) DEFAULT NULL,
  `lokasi_catatan` text DEFAULT NULL,
  `lokasi_oleh` bigint(20) DEFAULT NULL,
  `lokasi_updated_at` timestamp NULL DEFAULT NULL,
  `lokasi_foto` longtext DEFAULT NULL,
  `lokasi_video` longtext DEFAULT NULL,
  `produk` enum('kanopi','pagar','tralis','tenda_membrane','lainnya') NOT NULL DEFAULT 'kanopi',
  `atap_diminati` varchar(100) DEFAULT NULL,
  `sumber_lead` enum('instagram','whatsapp','referensi','google','spanduk','lainnya') NOT NULL DEFAULT 'whatsapp',
  `status` enum('lead','dihubungi','dijadwalkan','dikunjungi','ditawar','deal','tidak_jadi') NOT NULL DEFAULT 'lead',
  `estimasi_nilai` bigint(20) NOT NULL DEFAULT 0,
  `estimasi_min` bigint(20) DEFAULT NULL,
  `estimasi_max` bigint(20) DEFAULT NULL,
  `harga_final` bigint(20) DEFAULT NULL,
  `rab_snapshot` longtext DEFAULT NULL,
  `final_oleh` bigint(20) DEFAULT NULL,
  `final_at` timestamp NULL DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `tgl_kunjungan` datetime DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `input_oleh` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `penawaran_json` longtext DEFAULT NULL,
  `deal_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `poin_kinerja` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `bulan` tinyint(2) NOT NULL COMMENT '1-12',
  `tahun` smallint(4) NOT NULL,
  `poin_kehadiran` decimal(5,2) DEFAULT 0.00 COMMENT 'Maks 40 (teknisi/driver) atau sesuai bobot jabatan',
  `poin_tugas` decimal(5,2) DEFAULT 0.00 COMMENT 'Dari tugas_assignee',
  `poin_leads` decimal(5,2) DEFAULT 0.00 COMMENT 'Khusus admin & marketing',
  `poin_bbm` decimal(5,2) DEFAULT 0.00 COMMENT 'Khusus driver',
  `poin_komplain` decimal(5,2) DEFAULT 20.00 COMMENT 'Default penuh, dikurangi jika ada komplain',
  `total_poin` decimal(5,2) DEFAULT 0.00,
  `bintang` tinyint(1) DEFAULT 0 COMMENT '1-5',
  `is_alpha` tinyint(1) DEFAULT 0 COMMENT '1 = ada alpha bulan ini, gugur bonus',
  `bonus_nominal` bigint(20) DEFAULT 0,
  `is_bintang_jabatan` tinyint(1) DEFAULT 0 COMMENT '1 = terbaik di jabatannya bulan ini',
  `detail_kehadiran` text DEFAULT NULL COMMENT 'JSON: jumlah hadir, telat, alpha, izin',
  `detail_tugas` text DEFAULT NULL COMMENT 'JSON: total tugas, selesai, tidak selesai',
  `detail_bbm` text DEFAULT NULL COMMENT 'JSON: rata konsumsi, standar, selisih',
  `dihitung_pada` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `potongan_insidental` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `nominal_total` decimal(12,2) NOT NULL,
  `jumlah_cicilan` tinyint(4) NOT NULL,
  `cicilan_per_bulan` decimal(12,2) NOT NULL,
  `cicilan_ke` tinyint(4) DEFAULT 0,
  `sisa` decimal(12,2) NOT NULL,
  `status` enum('aktif','lunas') DEFAULT 'aktif',
  `input_oleh` bigint(20) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED DEFAULT NULL,
  `nama_project` varchar(200) DEFAULT NULL,
  `id_lead` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK ke pipeline_leads',
  `kode_project` varchar(20) DEFAULT NULL COMMENT 'Auto-generate: PRJ-2026-001',
  `nama_customer` varchar(150) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat_project` text DEFAULT NULL,
  `jenis_project` varchar(100) DEFAULT NULL COMMENT 'kanopi, pagar, tralis, dll',
  `deskripsi` text DEFAULT NULL,
  `nilai_kontrak` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Harga jual ke customer',
  `nilai_project` decimal(14,2) DEFAULT 0.00,
  `id_rate_kondisi` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK rate_kondisi, NULL = STD',
  `multiplier_upah` decimal(4,2) DEFAULT 1.00 COMMENT 'Snapshot multiplier saat project dibuat',
  `kondisi_approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Owner yang approve kondisi khusus',
  `kondisi_approved_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) DEFAULT 'menunggu_dp',
  `tgl_mulai_target` date DEFAULT NULL,
  `tgl_mulai_aktual` date DEFAULT NULL,
  `tgl_selesai_target` date DEFAULT NULL,
  `tgl_selesai_aktual` date DEFAULT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `project_material` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_project` bigint(20) UNSIGNED NOT NULL,
  `id_rab_item` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Link ke RAB item yang sesuai (nullable)',
  `id_master_material` bigint(20) UNSIGNED DEFAULT NULL,
  `nama_material` varchar(150) NOT NULL,
  `satuan` varchar(20) NOT NULL DEFAULT 'pcs',
  `qty_aktual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `harga_satuan` bigint(20) NOT NULL DEFAULT 0,
  `total` bigint(20) NOT NULL DEFAULT 0 COMMENT 'qty x harga_satuan',
  `tanggal_beli` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status_vs_rab` enum('normal','melebihi_rab','approved') DEFAULT 'normal' COMMENT 'normal=sesuai, melebihi_rab=pending approval, approved=sudah diapprove owner',
  `alasan_melebihi` text DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `project_tim` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_project` bigint(20) UNSIGNED NOT NULL,
  `id_user` bigint(20) UNSIGNED NOT NULL,
  `tgl_masuk` date NOT NULL,
  `tgl_keluar` date DEFAULT NULL,
  `jumlah_hari` int(11) DEFAULT NULL COMMENT 'Dihitung otomatis dari tgl_masuk - tgl_keluar',
  `jabatan_lapangan` enum('tukang','kenek') NOT NULL DEFAULT 'tukang',
  `rate_dasar` int(11) NOT NULL DEFAULT 170000 COMMENT 'Snapshot rate dasar saat assign',
  `multiplier` decimal(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Dari kondisi project',
  `rate_final` int(11) NOT NULL DEFAULT 170000 COMMENT 'rate_dasar x multiplier',
  `total_upah` bigint(20) DEFAULT NULL COMMENT 'rate_final x jumlah_hari, dihitung otomatis',
  `override_rate` int(11) DEFAULT NULL COMMENT 'Diisi kalau SPV override manual',
  `alasan_override` text DEFAULT NULL,
  `override_approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `override_approved_at` timestamp NULL DEFAULT NULL,
  `di_assign_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'SPV yang assign',
  `status` enum('pending_approval','disetujui','ditolak') DEFAULT 'pending_approval',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_addon` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(40) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` enum('talang','pembuangan','plafon','pencahayaan','struktur','dinding','finishing','lainnya') NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `formula_type` enum('per_unit','per_meter','per_m2','flat') NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `harga_pokok_satuan` decimal(12,2) DEFAULT NULL,
  `qty_default` decimal(8,2) DEFAULT 1.00,
  `deskripsi` varchar(200) DEFAULT NULL,
  `perlu_input_qty` tinyint(1) DEFAULT 1,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `level` varchar(10) NOT NULL DEFAULT 'total',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `durasi_fab` decimal(10,2) DEFAULT 0.00,
  `durasi_inst` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_approval` (
  `id` bigint(20) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `customer` varchar(255) DEFAULT NULL,
  `opsi_nama` varchar(255) DEFAULT NULL,
  `harga_normal` bigint(20) DEFAULT 0,
  `harga_nawar` bigint(20) DEFAULT 0,
  `diskon_persen` decimal(5,2) DEFAULT 0.00,
  `pokok` bigint(20) DEFAULT 0,
  `status` varchar(20) DEFAULT 'pending',
  `catatan_owner` varchar(500) DEFAULT NULL,
  `diminta_oleh` bigint(20) DEFAULT NULL,
  `diputus_oleh` bigint(20) DEFAULT NULL,
  `diputus_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `rab_approval_request` (
  `id` int(10) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED NOT NULL,
  `diminta_oleh` int(10) UNSIGNED NOT NULL,
  `harga_normal` decimal(14,2) NOT NULL,
  `harga_diminta` decimal(14,2) NOT NULL,
  `diskon_diminta_persen` decimal(5,2) NOT NULL,
  `alasan` varchar(300) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `diproses_oleh` int(10) UNSIGNED DEFAULT NULL,
  `catatan_owner` varchar(300) DEFAULT NULL,
  `notif_wa_terkirim` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_atap` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` enum('tembus_cahaya','tidak_tembus','kain_membrane') NOT NULL,
  `berat_kategori` enum('ringan','sedang','berat') DEFAULT 'ringan',
  `grade_adjustment` tinyint(4) DEFAULT 0,
  `harga_per_lembar` decimal(12,2) DEFAULT NULL,
  `harga_per_m2` decimal(12,2) DEFAULT NULL,
  `pemborosan_persen` decimal(5,2) DEFAULT 10.00,
  `upah_pasang_per_m2` decimal(12,2) DEFAULT NULL,
  `lebar_lembar_cm` decimal(6,2) DEFAULT 80.00,
  `keterangan_customer` varchar(200) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `consumable` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_evaluasi_notif` (
  `id` int(10) UNSIGNED NOT NULL,
  `komponen` varchar(100) NOT NULL,
  `jumlah_kasus` int(11) DEFAULT 0,
  `total_kerugian_estimasi` decimal(14,2) DEFAULT 0.00,
  `surveyor_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','ditinjau','ditunda','diabaikan') DEFAULT 'pending',
  `tunda_sampai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_header` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor_rab` varchar(30) DEFAULT NULL,
  `pipeline_lead_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `produk_kode` varchar(20) NOT NULL,
  `paket_konstruksi_id` int(10) UNSIGNED DEFAULT NULL,
  `atap_id` int(10) UNSIGNED DEFAULT NULL,
  `panjang` decimal(8,2) NOT NULL DEFAULT 0.00,
  `lebar` decimal(8,2) NOT NULL DEFAULT 0.00,
  `m2_total` decimal(10,2) DEFAULT 0.00,
  `bentangan_max` decimal(8,2) DEFAULT NULL,
  `zona_id` int(10) UNSIGNED DEFAULT NULL,
  `biaya_rangka` decimal(14,2) DEFAULT 0.00,
  `biaya_atap` decimal(14,2) DEFAULT 0.00,
  `biaya_jasa` decimal(14,2) DEFAULT 0.00,
  `biaya_addon` decimal(14,2) DEFAULT 0.00,
  `biaya_kondisi` decimal(14,2) DEFAULT 0.00,
  `biaya_pokok_total` decimal(14,2) DEFAULT 0.00,
  `buffer_persen` decimal(5,2) DEFAULT 20.00,
  `biaya_setelah_buffer` decimal(14,2) DEFAULT 0.00,
  `margin_persen` decimal(5,2) DEFAULT 25.00,
  `harga_sebelum_diskon` decimal(14,2) DEFAULT 0.00,
  `diskon_persen` decimal(5,2) DEFAULT 0.00,
  `diskon_nominal` decimal(14,2) DEFAULT 0.00,
  `harga_final` decimal(14,2) DEFAULT 0.00,
  `catatan_surveyor` text DEFAULT NULL,
  `catatan_internal` text DEFAULT NULL,
  `status` enum('draft','sent','negotiating','deal','batal','revised') DEFAULT 'draft',
  `tahap` enum('quick','detail') DEFAULT 'quick',
  `is_estimasi_kasar` tinyint(1) DEFAULT 0,
  `dibuat_oleh` int(10) UNSIGNED NOT NULL,
  `disetujui_oleh` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_item` (
  `id` int(10) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED NOT NULL,
  `tipe` enum('rangka','atap','jasa','addon','kondisi','manual') NOT NULL,
  `referensi_id` int(10) UNSIGNED DEFAULT NULL,
  `nama_item` varchar(150) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `qty` decimal(10,3) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `total` decimal(14,2) DEFAULT 0.00,
  `catatan` varchar(200) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_project` bigint(20) UNSIGNED NOT NULL,
  `id_master_material` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL kalau item bebas tidak ada di master',
  `nama_item` varchar(150) NOT NULL COMMENT 'Bisa dari master atau input bebas',
  `satuan` varchar(20) NOT NULL DEFAULT 'pcs',
  `kategori` varchar(50) DEFAULT NULL,
  `qty_rencana` decimal(10,2) NOT NULL DEFAULT 0.00,
  `harga_pokok` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Snapshot harga pokok saat RAB dibuat',
  `total_pokok` bigint(20) NOT NULL DEFAULT 0 COMMENT 'qty x harga_pokok',
  `margin_persen` decimal(5,2) DEFAULT 0.00 COMMENT 'Hanya owner/admin yang lihat',
  `harga_customer` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Yang ditampilkan ke customer',
  `total_customer` bigint(20) NOT NULL DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_jenis_kerja` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(120) NOT NULL,
  `produk` varchar(60) NOT NULL DEFAULT 'kanopi',
  `satuan` varchar(20) NOT NULL DEFAULT 'm2',
  `skill_default` varchar(100) NOT NULL DEFAULT 'umum',
  `produktivitas_per_hari` decimal(10,2) DEFAULT NULL,
  `produktivitas_inst` decimal(10,2) DEFAULT NULL,
  `jml_tukang` int(11) DEFAULT NULL,
  `jml_kenek` int(11) DEFAULT NULL,
  `jml_tukang_inst` int(11) DEFAULT NULL,
  `jml_kenek_inst` int(11) DEFAULT NULL,
  `urutan` int(11) NOT NULL DEFAULT 99,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_katalog` (
  `id` int(10) UNSIGNED NOT NULL,
  `produk_kode` varchar(20) NOT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` varchar(300) DEFAULT NULL,
  `foto_url` varchar(500) NOT NULL,
  `sumber_foto` enum('upload','pinterest','dokumentasi') DEFAULT 'dokumentasi',
  `atap_kode` varchar(30) DEFAULT NULL,
  `konstruksi_label` varchar(50) DEFAULT NULL,
  `addon_default` varchar(200) DEFAULT NULL,
  `kisaran_harga_min` bigint(20) DEFAULT 0,
  `kisaran_harga_max` bigint(20) DEFAULT 0,
  `tipe_lokasi` enum('rumah','ruko','kafe','gudang','mall','lainnya') DEFAULT 'rumah',
  `tag` varchar(200) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_kondisi_kerja` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `pengali_upah` decimal(5,2) DEFAULT NULL,
  `tambahan_per_hari` decimal(12,2) DEFAULT NULL,
  `urutan` int(11) NOT NULL DEFAULT 99,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `kena` varchar(10) DEFAULT 'fabinst'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_kondisi_lokasi` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` varchar(200) DEFAULT NULL,
  `tipe` enum('multiplier','flat_add','persen_add') DEFAULT 'persen_add',
  `nilai` decimal(8,2) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_margin_setting` (
  `id` int(10) UNSIGNED NOT NULL,
  `produk_kode` varchar(20) NOT NULL,
  `margin_min_persen` decimal(5,2) DEFAULT 15.00,
  `margin_standar_persen` decimal(5,2) DEFAULT 25.00,
  `margin_target_persen` decimal(5,2) DEFAULT 35.00,
  `diskon_max_persen` decimal(5,2) DEFAULT 15.00,
  `mode_aktif` enum('standar','target') DEFAULT 'standar',
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_paket_konstruksi` (
  `id` int(10) UNSIGNED NOT NULL,
  `zona_id` int(10) UNSIGNED NOT NULL,
  `produk_kode` varchar(20) DEFAULT 'KANOPI_STD',
  `nama_paket` enum('Hemat','Standar','Premium') NOT NULL,
  `label_display` varchar(100) DEFAULT NULL,
  `frame_material` varchar(50) NOT NULL,
  `frame_ukuran` varchar(20) NOT NULL,
  `frame_tebal` varchar(10) DEFAULT '1mm',
  `support_material` varchar(50) DEFAULT NULL,
  `support_ukuran` varchar(20) DEFAULT NULL,
  `metode` enum('solid','kremona','wf','sling') DEFAULT 'solid',
  `interval_kremona_cm` int(11) DEFAULT NULL,
  `harga_per_m2_rangka` decimal(12,2) NOT NULL,
  `harga_per_m2_jasa_pasang` decimal(12,2) NOT NULL DEFAULT 35000.00,
  `catatan_teknis` text DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_produk` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'kanopi',
  `is_estimasi_saja` tinyint(1) DEFAULT 0,
  `buffer_estimasi_persen` decimal(5,2) DEFAULT 20.00,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_selisih` (
  `id` int(10) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `surveyor_id` int(10) UNSIGNED DEFAULT NULL,
  `harga_deal` decimal(14,2) NOT NULL,
  `total_rab_detail` decimal(14,2) NOT NULL,
  `selisih` decimal(14,2) DEFAULT 0.00,
  `persen_selisih` decimal(6,2) DEFAULT NULL,
  `status_tindak` enum('otomatis','review_admin','hubungi_customer') DEFAULT 'otomatis',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_selisih_komponen` (
  `id` int(10) UNSIGNED NOT NULL,
  `selisih_id` int(10) UNSIGNED NOT NULL,
  `nama_komponen` varchar(100) NOT NULL,
  `selisih_nilai` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_setting_global` (
  `id` int(11) NOT NULL,
  `diskon_max` decimal(5,2) DEFAULT 15.00,
  `margin_default` decimal(5,2) DEFAULT 45.00,
  `lay_hemat` decimal(5,2) DEFAULT 5.00,
  `lay_kilat` decimal(5,2) DEFAULT 10.00,
  `tarif_km` int(11) DEFAULT 5000,
  `tarif_genset` int(11) DEFAULT 150000,
  `tarif_hotel` int(11) DEFAULT 0,
  `tarif_kontrakan` int(11) DEFAULT 0,
  `tarif_makan` int(11) DEFAULT 25000,
  `updated_at` timestamp NULL DEFAULT NULL,
  `consumable_rangka` decimal(12,2) DEFAULT 0.00,
  `consumable_atap` decimal(12,2) DEFAULT 0.00,
  `finishing_standar` decimal(12,2) DEFAULT 0.00,
  `powder_coating` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `rab_skill` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `upah_tukang_harian` decimal(12,2) DEFAULT NULL,
  `upah_kenek_harian` decimal(12,2) DEFAULT NULL,
  `urutan` int(11) NOT NULL DEFAULT 99,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_ttd` (
  `id` int(10) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED NOT NULL,
  `nama_penandatangan` varchar(100) NOT NULL,
  `ttd_data` longtext NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `lokasi_lat` decimal(10,7) DEFAULT NULL,
  `lokasi_lng` decimal(10,7) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_versi` (
  `id` int(10) UNSIGNED NOT NULL,
  `rab_id` int(10) UNSIGNED NOT NULL,
  `label` enum('Hemat','Standar','Premium') NOT NULL,
  `paket_konstruksi_id` int(10) UNSIGNED NOT NULL,
  `harga_final` decimal(14,2) NOT NULL,
  `margin_persen` decimal(5,2) NOT NULL,
  `detail_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_json`)),
  `dipilih` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rab_zona_bentangan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(50) NOT NULL,
  `bentangan_min` decimal(5,2) NOT NULL,
  `bentangan_max` decimal(5,2) NOT NULL,
  `deskripsi` varchar(200) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rapor_karyawan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `periode` enum('januari','juli') NOT NULL,
  `tahun` smallint(4) NOT NULL,
  `nilai_kpi` decimal(5,2) DEFAULT 0.00 COMMENT 'Rata-rata poin kinerja 6 bulan terakhir (bobot 50%)',
  `nilai_ujian` decimal(5,2) DEFAULT 0.00 COMMENT 'Nilai ujian online (bobot 30%)',
  `nilai_sp` decimal(5,2) DEFAULT 20.00 COMMENT 'Rekam SP (bobot 20%), default penuh jika bersih',
  `nilai_total` decimal(5,2) DEFAULT 0.00,
  `kelas_sebelumnya` enum('platinum','gold','silver','bronze','red_zone','') DEFAULT '',
  `kelas_baru` enum('platinum','gold','silver','bronze','red_zone') NOT NULL DEFAULT 'bronze',
  `kenaikan_gaji` bigint(20) DEFAULT 0 COMMENT 'Nominal kenaikan gaji permanen',
  `kelas_naik` tinyint(1) DEFAULT 0 COMMENT '1=naik, 0=tetap, -1=turun',
  `status` enum('pending','selesai') DEFAULT 'pending' COMMENT 'pending = ujian belum dikerjakan',
  `id_ujian_sesi` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Relasi ke ujian_sesi',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_kondisi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `multiplier` decimal(4,2) NOT NULL DEFAULT 1.00,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `registrasi_token` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expired_at` timestamp NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `riwayat_tabungan` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `tipe` enum('wajib','lebaran') NOT NULL,
  `tipe_transaksi` enum('masuk','keluar') NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `slip_gaji_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `slip_gaji` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `periode` enum('uang_makan','gaji_bulanan') NOT NULL,
  `bulan` tinyint(4) NOT NULL,
  `tahun` smallint(6) NOT NULL,
  `tanggal_generate` date NOT NULL,
  `tanggal_bayar` date DEFAULT NULL,
  `status` enum('draft','menunggu_konfirmasi','dibayar') DEFAULT 'draft',
  `hari_hadir` tinyint(4) DEFAULT 0,
  `hari_alpha` tinyint(4) DEFAULT 0,
  `hari_telat` tinyint(4) DEFAULT 0,
  `hari_izin` tinyint(4) DEFAULT 0,
  `gaji_pokok` decimal(12,2) DEFAULT 0.00,
  `total_uang_makan` decimal(12,2) DEFAULT 0.00,
  `total_tunjangan` decimal(12,2) DEFAULT 0.00,
  `bonus_kpi` decimal(12,2) DEFAULT 0.00,
  `kelas_kpi` enum('platinum','gold','silver','none') DEFAULT 'none',
  `bonus_lembur` decimal(12,2) DEFAULT 0.00,
  `jam_lembur` decimal(4,2) DEFAULT 0.00,
  `potongan_telat` decimal(12,2) DEFAULT 0.00,
  `potongan_kasbon` decimal(12,2) DEFAULT 0.00,
  `potongan_insidental` decimal(12,2) DEFAULT 0.00,
  `tabungan_wajib` decimal(12,2) DEFAULT 100000.00,
  `tabungan_lebaran` decimal(12,2) DEFAULT 0.00,
  `total_pendapatan` decimal(12,2) DEFAULT 0.00,
  `total_potongan` decimal(12,2) DEFAULT 0.00,
  `gaji_bersih` decimal(12,2) DEFAULT 0.00,
  `warning_batas_aman` tinyint(1) DEFAULT 0,
  `owner_konfirmasi` tinyint(1) DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hari_kerja_libur` int(11) NOT NULL DEFAULT 0,
  `upah_hari_libur` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `sp_karyawan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `level_sp` enum('sp1','sp2','sp3') NOT NULL,
  `alasan` text NOT NULL,
  `trigger_otomatis` tinyint(1) DEFAULT 0 COMMENT '1=diusulkan sistem, 0=manual owner',
  `status` enum('usulan','aktif','dicabut','pulih') DEFAULT 'usulan',
  `tanggal_sp` date NOT NULL,
  `tanggal_aktif` date DEFAULT NULL COMMENT 'Saat owner konfirmasi',
  `tanggal_pulih` date DEFAULT NULL COMMENT 'Saat SP turun otomatis',
  `dikonfirmasi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan_owner` text DEFAULT NULL,
  `bulan_bersih_berturut` tinyint(2) DEFAULT 0 COMMENT 'Hitung mundur 3 bulan untuk pulih',
  `reset_timer_pada` date DEFAULT NULL COMMENT 'Kapan timer terakhir direset karena pelanggaran baru',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tabungan_karyawan` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `tabungan_wajib_total` decimal(12,2) DEFAULT 0.00,
  `tabungan_lebaran_total` decimal(12,2) DEFAULT 0.00,
  `tabungan_lebaran_per_bulan` decimal(12,2) DEFAULT 0.00,
  `tabungan_lebaran_cair` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `tugas_assignee` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tugas_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('belum','dikerjakan','selesai','tidak_selesai') NOT NULL DEFAULT 'belum',
  `catatan_karyawan` text DEFAULT NULL,
  `waktu_mulai` timestamp NULL DEFAULT NULL,
  `waktu_selesai` timestamp NULL DEFAULT NULL,
  `notif_wa_terkirim` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tugas_harian` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai_target` time DEFAULT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `prioritas` enum('rendah','sedang','tinggi') NOT NULL DEFAULT 'sedang',
  `dibuat_oleh` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tunjangan_master` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama_tunjangan` varchar(255) NOT NULL,
  `tipe` enum('harian','bulanan','project') NOT NULL,
  `nominal_default` decimal(12,2) NOT NULL DEFAULT 0.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ujian_jawaban` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `soal_id` bigint(20) UNSIGNED NOT NULL,
  `urutan` tinyint(2) NOT NULL COMMENT 'Urutan soal 1-20',
  `jawaban_karyawan` enum('a','b','c','d') DEFAULT NULL COMMENT 'NULL = belum dijawab',
  `is_benar` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ujian_sesi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `periode` enum('januari','juli') NOT NULL,
  `tahun` smallint(4) NOT NULL,
  `mulai_pada` timestamp NULL DEFAULT NULL,
  `selesai_pada` timestamp NULL DEFAULT NULL,
  `batas_waktu` timestamp NULL DEFAULT NULL COMMENT 'mulai + 30 menit',
  `status` enum('belum','berlangsung','selesai','expired') DEFAULT 'belum',
  `nilai` decimal(5,2) DEFAULT 0.00 COMMENT '0-100',
  `jumlah_benar` tinyint(2) DEFAULT 0,
  `jumlah_soal` tinyint(2) DEFAULT 20,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ujian_soal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jabatan_level` tinyint(1) NOT NULL COMMENT '2=Admin, 3=Supervisor, 4=Marketing, 5=Teknisi, 6=Driver',
  `pertanyaan` text NOT NULL,
  `pilihan_a` varchar(500) NOT NULL,
  `pilihan_b` varchar(500) NOT NULL,
  `pilihan_c` varchar(500) NOT NULL,
  `pilihan_d` varchar(500) NOT NULL,
  `jawaban_benar` enum('a','b','c','d') NOT NULL,
  `is_aktif` tinyint(1) DEFAULT 1,
  `dibuat_oleh` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 5 COMMENT '1=Owner,2=Admin Ops,3=Supervisor,4=Marketing,5=Teknisi,6=Driver,7=Admin Toko',
  `jabatan` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nama_bank` varchar(255) DEFAULT NULL,
  `no_rekening` varchar(30) DEFAULT NULL,
  `atas_nama` varchar(255) DEFAULT NULL,
  `tempat_lahir` varchar(255) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `no_ktp` varchar(20) DEFAULT NULL,
  `no_kk` varchar(20) DEFAULT NULL,
  `darurat_nama` varchar(255) DEFAULT NULL,
  `darurat_no_hp` varchar(20) DEFAULT NULL,
  `darurat_hubungan` varchar(255) DEFAULT NULL,
  `ukuran_baju` varchar(255) DEFAULT NULL,
  `status_nikah` enum('belum_menikah','menikah','cerai') DEFAULT NULL,
  `jumlah_tanggungan` tinyint(4) NOT NULL DEFAULT 0,
  `golongan_darah` varchar(5) DEFAULT NULL,
  `no_bpjs_kesehatan` varchar(20) DEFAULT NULL,
  `no_bpjs_ketenagakerjaan` varchar(20) DEFAULT NULL,
  `status_registrasi` enum('menunggu','lengkap') NOT NULL DEFAULT 'lengkap',
  `tipe_gaji` enum('harian','bulanan','project') NOT NULL DEFAULT 'harian',
  `gaji_harian` decimal(12,2) NOT NULL DEFAULT 0.00,
  `uang_makan` decimal(10,2) NOT NULL DEFAULT 0.00,
  `uang_bonus` bigint(20) NOT NULL DEFAULT 0,
  `gaji_bulanan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `jam_masuk` time NOT NULL DEFAULT '07:30:00',
  `jam_pulang` time NOT NULL DEFAULT '17:00:00',
  `status` enum('aktif','nonaktif','sp1','sp2','sp3') NOT NULL DEFAULT 'aktif',
  `tgl_masuk_kerja` date DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nama_kontak_darurat` varchar(100) DEFAULT NULL,
  `no_kontak_darurat` varchar(20) DEFAULT NULL,
  `tanggal_bergabung` date DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `telegram_link_token` varchar(64) DEFAULT NULL,
  `hari_libur_default` tinyint(3) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `absensi_user_id_tanggal_unique` (`user_id`,`tanggal`),
  ADD KEY `absensi_dikoreksi_oleh_foreign` (`dikoreksi_oleh`);

ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

ALTER TABLE `izin_absen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `izin_absen_user_id_foreign` (`user_id`),
  ADD KEY `izin_absen_diproses_oleh_foreign` (`diproses_oleh`);

ALTER TABLE `jadwal_libur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `diproses_oleh` (`diproses_oleh`);

ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `karyawan_tunjangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_tunjangan_user_id_foreign` (`user_id`),
  ADD KEY `karyawan_tunjangan_tunjangan_master_id_foreign` (`tunjangan_master_id`);

ALTER TABLE `kasbon`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `kerja_hari_libur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kerja_hari_libur_user_tanggal_unique` (`user_id`,`tanggal`),
  ADD KEY `kerja_hari_libur_tanggal_index` (`tanggal`);

ALTER TABLE `kode_absen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_absen_tanggal_user_unique` (`tanggal`,`user_id`);

ALTER TABLE `komplain_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_tanggal` (`user_id`,`tanggal`),
  ADD KEY `fk_komplain_pencatat` (`dicatat_oleh`);

ALTER TABLE `kpi_setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_key` (`key_setting`);

ALTER TABLE `levels`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `libur_nasional`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

ALTER TABLE `libur_nasional_piket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `libur_nasional_id` (`libur_nasional_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `log_bensin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_bensin_kendaraan_id_foreign` (`kendaraan_id`),
  ADD KEY `log_bensin_driver_id_foreign` (`driver_id`),
  ADD KEY `log_bensin_tanggal_index` (`tanggal`);

ALTER TABLE `luar_kota`
  ADD PRIMARY KEY (`id`),
  ADD KEY `luar_kota_user_id_foreign` (`user_id`),
  ADD KEY `luar_kota_dibuat_oleh_foreign` (`dibuat_oleh`),
  ADD KEY `luar_kota_tanggal_index` (`tanggal_mulai`,`tanggal_selesai`);

ALTER TABLE `master_material`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

ALTER TABLE `pembayaran_project`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_project` (`id_project`);

ALTER TABLE `pipeline_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pipeline_followups_pipeline_lead_id_foreign` (`pipeline_lead_id`),
  ADD KEY `pipeline_followups_user_id_foreign` (`user_id`);

ALTER TABLE `pipeline_leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pipeline_leads_input_oleh_foreign` (`input_oleh`);

ALTER TABLE `poin_kinerja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kpi_bulan` (`user_id`,`bulan`,`tahun`),
  ADD KEY `idx_bulan_tahun` (`bulan`,`tahun`);

ALTER TABLE `potongan_insidental`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `project_material`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_project` (`id_project`);

ALTER TABLE `project_tim`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_project` (`id_project`);

ALTER TABLE `rab_addon`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD UNIQUE KEY `uq_addon_nama` (`nama`);

ALTER TABLE `rab_approval`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_approval_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rab_id` (`rab_id`);

ALTER TABLE `rab_atap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

ALTER TABLE `rab_evaluasi_notif`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_header`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_rab` (`nomor_rab`);

ALTER TABLE `rab_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rab_id` (`rab_id`);

ALTER TABLE `rab_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_project` (`id_project`);

ALTER TABLE `rab_jenis_kerja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jeniskerja_nama` (`nama`);

ALTER TABLE `rab_katalog`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_kondisi_kerja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kondisi_nama` (`nama`);

ALTER TABLE `rab_kondisi_lokasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

ALTER TABLE `rab_margin_setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `produk_kode` (`produk_kode`);

ALTER TABLE `rab_paket_konstruksi`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

ALTER TABLE `rab_selisih`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_selisih_komponen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `selisih_id` (`selisih_id`);

ALTER TABLE `rab_setting_global`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rab_skill`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_skill_nama` (`nama`);

ALTER TABLE `rab_ttd`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rab_id` (`rab_id`);

ALTER TABLE `rab_versi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rab_id` (`rab_id`);

ALTER TABLE `rab_zona_bentangan`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `rapor_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rapor` (`user_id`,`periode`,`tahun`);

ALTER TABLE `rate_kondisi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

ALTER TABLE `registrasi_token`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registrasi_token_token_unique` (`token`),
  ADD KEY `registrasi_token_user_id_foreign` (`user_id`);

ALTER TABLE `riwayat_tabungan`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

ALTER TABLE `slip_gaji`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slip_unik` (`user_id`,`periode`,`bulan`,`tahun`);

ALTER TABLE `sp_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sp_user` (`user_id`,`status`),
  ADD KEY `fk_sp_konfirmasi` (`dikonfirmasi_oleh`);

ALTER TABLE `tabungan_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE `tugas_assignee`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tugas_assignee_tugas_user_unique` (`tugas_id`,`user_id`),
  ADD KEY `tugas_assignee_user_id_foreign` (`user_id`),
  ADD KEY `tugas_assignee_status_index` (`status`);

ALTER TABLE `tugas_harian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tugas_harian_dibuat_oleh_foreign` (`dibuat_oleh`),
  ADD KEY `tugas_harian_tanggal_index` (`tanggal`);

ALTER TABLE `tunjangan_master`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ujian_jawaban`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jawaban` (`sesi_id`,`soal_id`),
  ADD KEY `fk_jawaban_soal` (`soal_id`);

ALTER TABLE `ujian_sesi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sesi` (`user_id`,`periode`,`tahun`);

ALTER TABLE `ujian_soal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soal_jabatan` (`jabatan_level`,`is_aktif`),
  ADD KEY `fk_soal_pembuat` (`dibuat_oleh`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);


ALTER TABLE `absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `izin_absen`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `jadwal_libur`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `karyawan_tunjangan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kasbon`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kendaraan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kerja_hari_libur`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kode_absen`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `komplain_karyawan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kpi_setting`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `libur_nasional`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `libur_nasional_piket`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `log_bensin`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `luar_kota`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `master_material`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pembayaran_project`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pipeline_followups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `pipeline_leads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `poin_kinerja`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `potongan_insidental`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `project_material`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `project_tim`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_addon`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_approval`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_approval_request`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_atap`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_evaluasi_notif`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_header`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_item`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_jenis_kerja`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_katalog`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_kondisi_kerja`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_kondisi_lokasi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_margin_setting`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_paket_konstruksi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_produk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_selisih`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_selisih_komponen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_skill`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_ttd`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_versi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rab_zona_bentangan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rapor_karyawan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rate_kondisi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `registrasi_token`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `riwayat_tabungan`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `slip_gaji`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sp_karyawan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tabungan_karyawan`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tugas_assignee`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tugas_harian`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tunjangan_master`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ujian_jawaban`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ujian_sesi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ujian_soal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_dikoreksi_oleh_foreign` FOREIGN KEY (`dikoreksi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `absensi_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `izin_absen`
  ADD CONSTRAINT `izin_absen_diproses_oleh_foreign` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `izin_absen_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `jadwal_libur`
  ADD CONSTRAINT `jadwal_libur_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_libur_ibfk_2` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `karyawan_tunjangan`
  ADD CONSTRAINT `karyawan_tunjangan_tunjangan_master_id_foreign` FOREIGN KEY (`tunjangan_master_id`) REFERENCES `tunjangan_master` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `karyawan_tunjangan_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `komplain_karyawan`
  ADD CONSTRAINT `fk_komplain_pencatat` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_komplain_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `libur_nasional`
  ADD CONSTRAINT `libur_nasional_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `libur_nasional_piket`
  ADD CONSTRAINT `libur_nasional_piket_ibfk_1` FOREIGN KEY (`libur_nasional_id`) REFERENCES `libur_nasional` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `libur_nasional_piket_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `log_bensin`
  ADD CONSTRAINT `log_bensin_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_bensin_kendaraan_id_foreign` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON DELETE CASCADE;

ALTER TABLE `luar_kota`
  ADD CONSTRAINT `luar_kota_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `luar_kota_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `pembayaran_project`
  ADD CONSTRAINT `pembayaran_project_ibfk_1` FOREIGN KEY (`id_project`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `pipeline_followups`
  ADD CONSTRAINT `pipeline_followups_pipeline_lead_id_foreign` FOREIGN KEY (`pipeline_lead_id`) REFERENCES `pipeline_leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pipeline_followups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `pipeline_leads`
  ADD CONSTRAINT `pipeline_leads_input_oleh_foreign` FOREIGN KEY (`input_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `poin_kinerja`
  ADD CONSTRAINT `fk_poin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_material`
  ADD CONSTRAINT `project_material_ibfk_1` FOREIGN KEY (`id_project`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_tim`
  ADD CONSTRAINT `project_tim_ibfk_1` FOREIGN KEY (`id_project`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_approval_request`
  ADD CONSTRAINT `rab_approval_request_ibfk_1` FOREIGN KEY (`rab_id`) REFERENCES `rab_header` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_item`
  ADD CONSTRAINT `rab_item_ibfk_1` FOREIGN KEY (`rab_id`) REFERENCES `rab_header` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_items`
  ADD CONSTRAINT `rab_items_ibfk_1` FOREIGN KEY (`id_project`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_selisih_komponen`
  ADD CONSTRAINT `rab_selisih_komponen_ibfk_1` FOREIGN KEY (`selisih_id`) REFERENCES `rab_selisih` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_ttd`
  ADD CONSTRAINT `rab_ttd_ibfk_1` FOREIGN KEY (`rab_id`) REFERENCES `rab_header` (`id`) ON DELETE CASCADE;

ALTER TABLE `rab_versi`
  ADD CONSTRAINT `rab_versi_ibfk_1` FOREIGN KEY (`rab_id`) REFERENCES `rab_header` (`id`) ON DELETE CASCADE;

ALTER TABLE `rapor_karyawan`
  ADD CONSTRAINT `fk_rapor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `registrasi_token`
  ADD CONSTRAINT `registrasi_token_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `sp_karyawan`
  ADD CONSTRAINT `fk_sp_konfirmasi` FOREIGN KEY (`dikonfirmasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `tugas_assignee`
  ADD CONSTRAINT `tugas_assignee_tugas_id_foreign` FOREIGN KEY (`tugas_id`) REFERENCES `tugas_harian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tugas_assignee_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `tugas_harian`
  ADD CONSTRAINT `tugas_harian_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `ujian_jawaban`
  ADD CONSTRAINT `fk_jawaban_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `ujian_sesi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jawaban_soal` FOREIGN KEY (`soal_id`) REFERENCES `ujian_soal` (`id`);

ALTER TABLE `ujian_sesi`
  ADD CONSTRAINT `fk_sesi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `ujian_soal`
  ADD CONSTRAINT `fk_soal_pembuat` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
