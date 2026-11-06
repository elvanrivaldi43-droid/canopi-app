# Survei Manual vs Aktual — Rancangan

Tanggal: 6 Nov 2026 · Status: **DRAFT, menunggu persetujuan Elvan** · Ukuran: proyek sedang
Revisi 2 (6 Nov): hasil kaji ulang — 4 celah diperbaiki, 5 bagian dipangkas.

## 1. Tujuan dan alasan

RAB otomatis (dan cutting list otomatis) terlalu kompleks untuk diselesaikan sekarang;
roadmap diubah atas keputusan Elvan: **SWE → Keuangan → RAB paling akhir**. Sementara itu
project harus bisa berjalan tanpa lead/RAB, dan datanya **tidak boleh kosong**.

Cara kerja sementara: surveyor tetap menghitung di KERTAS seperti sekarang. Admin mengetik
hasilnya ke web di dalam project sebagai **Perkiraan Survei**. Saat project berjalan, belanja
material nyata dan lama kerja nyata (tahap SWE) tercatat seperti biasa. Web membandingkan
**perkiraan vs aktual per barang dan per tahap**.

Tujuan utama: **data** — seberapa sering dan seberapa jauh perkiraan surveyor meleset.
Nantinya bahan kalibrasi RAB. Jangka panjang tetap: RAB otomatis + cutting list otomatis.

## 2. Yang dibangun

1. **Form Perkiraan Survei** di halaman project (diisi Admin level 2 / Owner):
   - Daftar barang: pilih dari **Master Material** (wajib) + jumlah. Satuan ikut master.
     Harga diambil dari `master_material.harga_pokok` **saat baris disimpan** dan dibekukan
     di baris itu. Form TIDAK menampilkan harga.
   - Per tahap kerja (pilih dari `tahap_master`): perkiraan **hari**, **jumlah tukang**,
     **jumlah kenek**.
   - Foto kertas survei (opsional, dianjurkan) lewat `R2Service` yang sudah ada.
2. **Kunci otomatis**: perkiraan tidak bisa diubah bila project status bukan `persiapan`
   ATAU ada `project_tahap` berstatus bukan `belum`. Dihitung saat edit (tidak disimpan,
   tidak ada hook di `mulaiTahap`/`updateStatus`). Owner tetap boleh mengedit setelah kunci;
   tiap edit Owner pasca-kunci dicatat satu baris log (siapa, kapan, alasan).
3. **Belanja material wajib pilih Master Material** pada project yang punya perkiraan
   (project tanpa perkiraan tidak berubah, input bebas tetap).
4. **Barang dari stok gudang** dicatat lewat form belanja yang sama dengan pilihan
   "Dari stok gudang": harga otomatis `harga_pokok` master. Dengan begitu project tidak
   tampak hemat palsu.
5. **Halaman/tab Perbandingan** per project (lihat bagian 3). Tampil sepanjang project
   berjalan berlabel **Sementara**; berlabel **Final** saat status `selesai`/`garansi`.
   Project `dibatalkan` tidak dihitung.
6. Project tanpa `rab_id`: blok "RAB vs Aktual" lama disembunyikan (bukan Rp 0 menyesatkan).

## 3. Cara hitung

**Material, per barang** (dicocokkan lewat `id_master_material`, beberapa pembelian dijumlah):
- selisih jumlah = aktual − perkiraan
- rupiah perkiraan = jumlah perkiraan × harga beku; rupiah aktual = jumlah `project_material.total`
- barang dibeli tapi tak ada di perkiraan = **di luar perkiraan**

**Waktu, per tahap** (dicocokkan lewat `tahap_master_id`):
- Perkiraan dalam satuan **setengah hari** (pilihan ½, 1, 1½, …), bukan pecahan bebas.
- **orang-hari**: perkiraan = hari × jumlah tukang (tukang-hari) dan hari × jumlah kenek
  (kenek-hari); **aktual = jumlah orang-hari dari Penugasan Harian** (proyek 1,
  `2026-11-06-penugasan-harian-design.md`): laporan sore per orang (Penuh = 1, Setengah = 0,5),
  dikelompokkan per peran. Tidak lagi dari tanggal tombol Mulai/Selesai — hari libur, tahap
  paralel, dan project kecil 1 hari otomatis benar.
- Tahap di perkiraan yang tak punya data kerja = **belum tercatat**, TIDAK dihitung hemat.
- rupiah = orang-hari × **tarif rata-rata per peran** (rata-rata `gaji_harian` karyawan aktif yang
  jabatannya terbaca tukang / kenek lewat `SkillKaryawanService::deteksiKategori` yang sudah ada), tarif SAMA untuk kedua sisi, dihitung saat halaman dibuka.
- tahap aktual yang tak ada di perkiraan = **di luar perkiraan**.

**Tiga angka ringkasan** — per sumber (Material, Waktu) dan total gabungan:
- **Hemat Maksimal** = jumlah hemat dari baris yang aktual < perkiraan saja.
- **Pengurang** = jumlah kelebihan dari baris yang aktual > perkiraan + semua "di luar perkiraan".
- **Hasil Akhir** = Hemat Maksimal − Pengurang. Positif = hemat bersih, negatif = **rugi bersih**.

Contoh (angka karangan): hollow 4x8 perkiraan 20 btg × Rp100rb, aktual 18 → hemat Rp200rb;
cat perkiraan 10 kg × Rp80rb, aktual 12 → lebih Rp160rb; dempul tak diperkirakan, beli Rp50rb.
Hemat Maksimal 200rb, Pengurang 210rb, Hasil Akhir −10rb (rugi 10rb).
Waktu: las perkiraan 1 tukang × 3 hari = 3 tukang-hari; aktual 3 tukang × 4 hari = 12 →
lebih 9 tukang-hari × tarif rata-rata tukang.

**Siapa lihat apa:** Owner melihat semua (jumlah + rupiah). Level 2/3 hanya jumlah barang,
hari, dan orang — tanpa rupiah/tarif (aturan modal/tarif yang berlaku).

## 4. Keputusan Elvan (terkunci, jangan didebat ulang)

1. Perbandingan **per barang**, bukan hanya total rupiah.
2. Waktu menyatu dengan tahap SWE; nama tahap perkiraan **disamakan** dengan `tahap_master`.
3. Perkiraan **dikunci** setelah project mulai.
4. Admin mengetik dari kertas; surveyor tidak menyentuh web; foto kertas sebagai bukti.
5. Tampil juga **rupiah**; waktu dirupiahkan lewat **upah harian**.
6. Tiga angka: hemat maksimal (bila tak ada yang melebihi), dikurangi kelebihan dan belanja lain.
7. Belanja material **wajib pilih master** (project ber-perkiraan).
8. Perkiraan memuat **jumlah tukang & kenek per tahap**, dibandingkan dengan yang nyata.
9. **Stok gudang boleh dipakai** project.

## 5. Keputusan Claude (cek)

- Harga material dibekukan saat baris perkiraan disimpan (itulah harga saat survei).
- Rupiah waktu pakai orang-hari × tarif rata-rata per peran yang sama di kedua sisi —
  mengukur meleset-nya perkiraan, bukan siapa yang ditugaskan; tak perlu membekukan tarif.
- Kunci dihitung, bukan disimpan; Owner bisa edit pasca-kunci dengan log.
- Pengembalian sisa barang ke gudang **tidak dicatat** di tahap ini.
  `ponytail:` hemat bisa tampak lebih kecil dari sebenarnya; tambah "retur ke gudang" bila
  ternyata sering terjadi.
- Satu orang di dua project pada hari yang sama: sudah terbagi benar lewat Penugasan Harian
  (maks 1 orang-hari per orang per tanggal).
- **Bergantung pada proyek 1 (Penugasan Harian)** — dibangun setelahnya.
- Nama tampilan "Hemat Maksimal / Pengurang / Hasil Akhir" boleh diganti Elvan.

## 6. Bukan bagian dari ini

- Tidak menghitung perkiraan sendiri, tidak menyentuh mesin RAB, penawaran, atau angka kalibrasi.
- Tidak mengubah alur SWE `mulaiTahap`/`selesaiTahap`.
- Tidak memigrasi project lama. Tidak ada kolom "sumber"/persiapan untuk RAB (RAB nanti
  menambah sendiri).

## 7. Batasan teknis

- Laravel 13 / PHP 8.3, shared hosting; `curl_init` bukan `Http::`; `getenv()` untuk env.
- Skema baru lewat **SQL idempotent manual** (phpMyAdmin) sebelum push kode yang bergantung.
- Test baru wajib didaftarkan di `tests/guardrail/manifest.json` di commit yang sama.
- Tanpa emoji di blade (pakai SVG); tanpa `confirm()` untuk aksi penting (pola tekan-dua-kali).
- Route yang menampilkan rupiah ke non-Owner tidak boleh ada; cek daftar test keamanan.
- Stage per-nama saat commit, jangan `git add -A`.

## 8. Diserahkan ke builder

Nama tabel/kolom, tata letak tab di `projects/show`, alur input cepat, format foto, urutan
pembagian task, cara membaca sumber libur yang sudah ada.
