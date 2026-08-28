# Audit Migration Canopi — 15 Agustus 2026

## Scope dan pagar keselamatan

- Source: worktree `/root/projects/canopi-app/.worktrees/migration-audit`
- Commit: `219790a6cbadea75884a24d53516b5e24d0f3e89` (`origin/main`)
- Target eksekusi: Docker MariaDB 10.11 disposable, database `canopi_test`
- Tidak menghubungi database production.
- Tidak memakai dump atau data production.
- Tidak mengubah migration/source tracked.
- Shim hanya diterapkan ke database disposable lalu container dihancurkan.

## Bukti utama

- Migration repository: 26 file.
- Histori Git: hanya 26 path migration yang sama; tidak ada event penghapusan migration.
- Tabel yang dideklarasikan `Schema::create`: 21 (termasuk tabel framework).
- Migration Laravel tercatat 26/26 setelah shim test-only.
- Schema disposable akhir: 22 tabel (21 tabel deklaratif + tabel `migrations`).
- Tabel literal/model yang dipakai aplikasi: 55.
- Tabel yang dipakai aplikasi tetapi tidak dibuat migration: 43.
- Model Eloquent: 48.
- Tabel model yang tidak tersedia: 36.
- Setelah shim, scanner model/query masih menemukan minimal 30 kolom yang dipakai tetapi tidak tersedia pada tabel existing.
- Dokumentasi Markdown berisi sedikitnya 24 operasi schema manual unik, tetapi tidak mencakup seluruh 43 tabel yang hilang.

## Blocker migration berurutan yang direproduksi

### 1. `users.tanggal_bergabung`

Migration gagal:

`2026_08_11_000001_add_hari_libur_default_to_users_table`

Migration menambahkan `hari_libur_default` dengan `after('tanggal_bergabung')`, tetapi tidak ada migration yang membuat `tanggal_bergabung`.

Shim disposable:

- `users.tanggal_bergabung DATE NULL`

### 2. Kolom lama absensi

Migration gagal:

`2026_08_13_000003_add_lapor_progress_kembali_kerja_to_absensi_table`

Migration bergantung pada tiga kolom yang tidak dibuat migration:

- `absensi.jam_absen_siang`
- `absensi.deskripsi_kendala`
- `absensi.potongan_siang_dicatat`

Ketiganya ditambahkan sebagai shim disposable agar audit dapat diteruskan.

### 3. `pipeline_leads.lokasi_foto`

Migration gagal:

`2026_08_14_000001_add_lokasi_video_to_pipeline_leads_table`

Migration menambahkan `lokasi_video` setelah `lokasi_foto`, tetapi `lokasi_foto` tidak dibuat migration.

Shim disposable:

- `pipeline_leads.lokasi_foto TEXT NULL`

### Hasil setelah shim

Seluruh 26 migration tercatat selesai. Ini tidak berarti schema aplikasi lengkap: database hanya memiliki 22 tabel, sedangkan aplikasi memakai 55 tabel.

## 43 tabel aplikasi yang tidak dibuat migration

### Payroll, karyawan, operasional

- `kasbon`
- `kendaraan`
- `komplain_karyawan`
- `kpi_setting`
- `log_bensin`
- `luar_kota`
- `poin_kinerja`
- `potongan_insidental`
- `rapor_karyawan`
- `slip_gaji`
- `sp_karyawan`
- `tabungan_karyawan`
- `tugas_assignee`
- `tugas_harian`
- `ujian_jawaban`
- `ujian_sesi`
- `ujian_soal`

### Project

- `pembayaran_project`
- `project_material`
- `project_tim`
- `projects`

### RAB dan material

- `master_material`
- `rab_addon`
- `rab_approval`
- `rab_approval_request`
- `rab_atap`
- `rab_header`
- `rab_item`
- `rab_jenis_kerja`
- `rab_katalog`
- `rab_kondisi_kerja`
- `rab_kondisi_lokasi`
- `rab_margin_setting`
- `rab_paket_konstruksi`
- `rab_produk`
- `rab_selisih`
- `rab_selisih_komponen`
- `rab_setting_global`
- `rab_skill`
- `rab_ttd`
- `rab_versi`
- `rab_zona_bentangan`
- `rate_kondisi`

## Minimal 30 kolom tambahan yang dipakai tetapi tidak tersedia setelah shim

### `absensi`

- `ada_kendala`
- `foto_siang_1`
- `foto_siang_2`
- `foto_siang_3`
- `gps_valid_masuk`
- `gps_valid_pulang`
- `gps_valid_siang`
- `jenis_kendala`
- `lat_siang`
- `lembur_approved`
- `lembur_approved_oleh`
- `lembur_jam`
- `lng_siang`
- `status_pekerjaan`

Di luar daftar ini, tiga dependency shim absensi juga tidak punya migration.

### `izin_absen`

- `diproses_at`

### `pipeline_followups`

- `metode`
- `tgl_followup_berikutnya`

### `pipeline_leads`

- `created_by`
- `deal_json`
- `estimasi_max`
- `estimasi_min`
- `final_at`
- `final_oleh`
- `harga_final`
- `input_oleh`
- `penawaran_json`
- `rab_snapshot`
- `tgl_kunjungan`

Controller lokasi juga memakai field manual yang tidak tertangkap penuh oleh scanner chain karena payload dibangun dalam variabel:

- `lokasi_area`
- `lokasi_patokan`
- `lokasi_maps_link`
- `lokasi_sharelok`
- `lokasi_lat`
- `lokasi_lng`
- `lokasi_jarak_km`
- `lokasi_listrik`
- `lokasi_jarak_listrik_m`
- `lokasi_akses`
- `lokasi_catatan`
- `lokasi_foto`
- `lokasi_oleh`
- `lokasi_updated_at`
- `lokasi_gps_at`

`lokasi_video` memiliki migration baru, tetapi migration tersebut bergantung pada `lokasi_foto` manual.

### `users`

- `nama_kontak_darurat`
- `no_kontak_darurat`

Ada ketidakkonsistenan nama: migration lama membuat `darurat_nama` dan `darurat_no_hp`, sementara model/controller baru memakai `nama_kontak_darurat` dan `no_kontak_darurat`.

Kolom manual lain yang dipakai kode tetapi tidak punya migration antara lain:

- `tanggal_bergabung`
- `telegram_chat_id`
- `telegram_link_token`

## Risiko khusus

1. `migrate:fresh` gagal sebelum selesai tanpa shim.
2. Migration bisa terlihat `DONE` padahal fungsi tidak melakukan apa pun. Contoh: migration slip gaji memakai guard `Schema::hasTable('slip_gaji')`; karena tabelnya tidak ada, migration dilewati tetapi tetap dicatat selesai.
3. Schema model tidak cukup untuk merekonstruksi tipe, default, enum, unique index, foreign key, urutan kolom, dan kompatibilitas data production.
4. Menulis 43 migration berdasarkan tebakan kode berisiko membuat schema berbeda dari production.
5. Mengubah migration lama yang sudah tercatat di production juga berisiko dan tidak memperbaiki production yang sudah melewatinya.
6. Migration repository saat ini bukan sumber kebenaran schema production; source of truth nyata masih database production + SQL manual historis.

## Alternatif perbaikan

### A. Rekonstruksi seluruh histori menjadi puluhan migration kompatibilitas

Kelebihan:
- Replay migration normal dari nol.

Kekurangan:
- Butuh definisi persis seluruh 43 tabel dan banyak kolom/index/FK.
- Sangat besar dan rawan beda dari production.
- Migration kompatibilitas akan masuk jalur deploy production sehingga harus dijaga sangat ketat.

### B. Baseline schema DDL-only + migration forward-only

Langkah konsep:

1. Ambil struktur production secara read-only/DDL-only, tanpa rows/data customer.
2. Sanitasi dan validasi agar tidak ada data, credential, DEFINER, atau artefak hosting.
3. Simpan baseline Laravel untuk database kosong.
4. Database integration test memuat baseline lalu menjalankan hanya migration setelah baseline.
5. Tambahkan contract test tabel/kolom/index/FK penting.
6. Mulai saat itu setiap perubahan schema wajib punya migration; SQL phpMyAdmin hanya bagian deployment yang idempotent dan harus dicerminkan di migration.

Kelebihan:
- Paling akurat terhadap production sekarang.
- Tidak perlu menebak struktur 43 tabel.
- Baseline hanya dipakai database kosong; production yang sudah berjalan tidak dibangun ulang.

Kekurangan:
- Tidak menguji replay seluruh histori lama.
- Membutuhkan ekspor schema production read-only satu kali.

### C. Baseline test-only buatan tangan

Kelebihan:
- Cepat.

Kekurangan:
- Berisiko memberi tes hijau palsu karena schema test bisa berbeda dari production.
- Tidak direkomendasikan kecuali hanya spike sementara.

## Rekomendasi

Pilih **B: baseline schema DDL-only dari production + migration forward-only**.

Audit ini sudah membuktikan baseline khusus database kosong memang layak dipertimbangkan: histori tidak sekadar punya satu duplicate column, tetapi kehilangan 43 tabel dan banyak kolom. Rekonstruksi manual dari model tidak cukup aman.

Sebelum membuat patch repository, langkah berikutnya adalah audit schema production secara read-only. Ambil metadata tabel, kolom, index, dan foreign key tanpa mengambil satu pun row bisnis/customer. Setelah hasil diperiksa, baru susun plan implementasi baseline dan regression contract.

## Artefak lokal

- `inventory.json`
- `manual-sql-inventory.json`
- `model-schema.json`
- `query-column-audit.json`
- `REPORT.md`

Semua berada di `/root/projects/canopi-app/.hermes/migration-audit/` dan tidak tracked Git.

## Verifikasi metadata production read-only

Bos menjalankan tiga query `information_schema` read-only melalui phpMyAdmin dan mengirim hasil CSV. Tidak ada row bisnis/customer yang diambil.

### Struktur production

- 68 tabel.
- 873 kolom.
- Seluruh 55 tabel yang terdeteksi dipakai model/query aplikasi tersedia di production.
- 46 tabel production tidak dibuat oleh migration repository (lebih banyak dari batas bawah 43 tabel aplikasi karena production juga memiliki tabel lain seperti `rab_evaluasi_notif`, `rab_items`, dan `riwayat_tabungan`).
- Setelah lima shim test-only, schema hasil migration masih kekurangan kolom pada `absensi`, `izin_absen`, `pipeline_followups`, `pipeline_leads`, dan `users` dibanding production.
- Schema hasil migration juga membuat kolom yang tidak ada di production: `pipeline_followups.status_sebelum`, `pipeline_followups.status_sesudah`, `pipeline_leads.user_id`, `pipeline_leads.tanggal_jadwal`, dan `pipeline_leads.jam_jadwal`.

### Index production

- 162 row index metadata.
- 141 index berbeda pada 68 tabel.
- 26 unique index non-primary, termasuk constraint bisnis penting untuk absensi, kode absen, kerja hari libur, slip gaji, KPI, tugas, ujian, dan RAB.

### Foreign key production

- 41 foreign-key constraint pada 29 tabel.
- Seluruh foreign-key source column memiliki index yang sesuai.
- Tidak ada mismatch tipe kolom sumber vs kolom tujuan.
- Update rule: 41 `RESTRICT`.
- Delete rule: 33 `CASCADE`, 5 `SET NULL`, 3 `RESTRICT`.

### Anomali production yang ditemukan

- `absensi.1ng_kembali_kerja` diawali angka `1` dan berdampingan dengan kolom benar `absensi.lng_kembali_kerja`. Ini sangat mungkin typo historis/kolom yatim. Jangan dihapus sebelum audit isi dan usage terpisah.
- Collation bercampur: `utf8mb4_unicode_ci`, `utf8mb4_general_ci`, `utf8mb4_bin`, dan `latin1_swedish_ci`. Baseline awal harus meniru production apa adanya; normalisasi collation merupakan proyek terpisah karena dapat memengaruhi perbandingan/index/data.

Kesimpulan production memperkuat rekomendasi baseline DDL-only. Struktur tidak aman direkonstruksi dari 26 migration lama karena bukan hanya tidak lengkap, tetapi beberapa definisinya berbeda dari production aktual.

## Audit file DDL structure-only phpMyAdmin

Sumber diterima sebagai file lokal dan hanya dibaca sebagai teks; tidak pernah dieksekusi ke database mana pun.

- Format: plain text ASCII/SQL, 67.312 byte, 1.655 baris, tanpa byte NUL.
- 68 `CREATE TABLE`, 158 `ALTER TABLE`.
- 873 kolom.
- 141 index.
- 41 foreign key.
- Tidak ada `INSERT`, `REPLACE`, `LOAD DATA`, `UPDATE`, atau `DELETE`.
- Tidak ada `CREATE USER`, `GRANT`, `SET PASSWORD`, DSN, URI ber-credential, `DEFINER`, atau assignment secret/environment.
- Tidak ada `CREATE VIEW`, trigger, procedure, function, atau event.
- Tidak ada `CREATE DATABASE` atau `USE`; target database tidak di-hardcode di file.
- Tidak ada current `AUTO_INCREMENT=N`; hanya atribut kolom `AUTO_INCREMENT` yang memang bagian schema.
- Susunan tabel/kolom dan tipe/nullability cocok 100% dengan `COLUMNS.csv`.
- Nama/urutan 141 index cocok 100% dengan `STATISTICS.csv`.
- Nama, arah, kolom, dan update/delete rule 41 foreign key cocok 100% dengan metadata production.

File ini benar-benar **DDL-only** dan layak menjadi sumber baseline. Salinan identik disimpan lokal dengan mode `0600` sebagai `.hermes/migration-audit/production-schema-raw-ddl-only.sql`; belum masuk Git.

## Strategi baseline yang disarankan setelah persetujuan

Gunakan **DDL-only + runner test tipis dan deterministik**:

1. Jadikan DDL production yang sudah diaudit sebagai struktur awal database kosong. File baseline harus tetap murni DDL dan nol `INSERT`.
2. Simpan cutoff migration `2026_08_15_000004_add_user_unique_to_kode_absen_table` di manifest terpisah.
3. Setelah baseline dimuat ke database Docker disposable, runner menandai 26 nama migration lama sebagai applied di tabel `migrations`. Ini metadata test sintetis, bukan data bisnis, dan tidak ditulis ke file baseline.
4. Jalankan `php artisan migrate --force` agar hanya migration yang lebih baru dari cutoff yang diterapkan.
5. Jangan gunakan `schema:dump --prune`; migration lama tetap dipertahankan sebagai histori.
6. Tambahkan checksum/validator fail-closed: baseline wajib nol DML/data, nol credential, tepat 68 tabel/873 kolom/141 index/41 FK, dan hanya boleh dimuat ke host `127.0.0.1`, port `3307`, database `canopi_test`, environment `testing`.
7. Fixture bisnis harus palsu dan dimuat terpisah setelah schema selesai.
8. Verifikasi di Docker disposable: import baseline, tandai ledger migration test, `migrate:status`, PDO smoke, integration test nyata, lalu stop/remove.

Laravel native schema dump dipertimbangkan tetapi tidak dipilih sebagai bentuk baseline final karena implementasi MySQL Laravel menempelkan row tabel `migrations` ke file schema. Itu bertentangan dengan guardrail baseline nol `INSERT`. Runner tipis mempertahankan DDL murni tanpa membangun ulang schema secara custom.
