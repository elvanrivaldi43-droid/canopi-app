# Desain: Gabungan Kotak — bentuk campur (siku + lekukan) di DenahEditor

**Tanggal:** 16 Juli 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** Lanjutan #9 di `MEMORI_PROYEK.md` — freehand drag-sudut susah dipakai bikin bentuk "campur" (mayoritas siku 90° + kadang ada lekukan/sisi miring) kayak PA-DUTA.

---

## 1. Ringkasan

Tambah cara BARU buat bentuk dasar denah di dalam blok Denah yang sudah ada (`DenahEditor`, `public/js/denah-editor.js`) — **aditif**, bukan pengganti. Kotak pertama = kotak default yang sudah ada (input Lebar×Panjang). Tombol baru **"+ Tambah Kotak"** menempelkan kotak lain ke sisi lurus yang sudah ada, hasilnya otomatis:
- **Nambah** (kotak baru di LUAR bentuk lama) → jadi sayap/tonjolan (L, U, dst)
- **Lekukan** (kotak baru di DALAM bentuk lama, nempel ke tepi) → jadi bentuk "kotak besar dikurangi"

Arah (nambah/lekukan) **tidak pakai toggle** — otomatis kebaca dari posisi kotak baru pas digeser (di luar = nambah, nutup ke dalam = lekukan). Drag-sudut + ortho-snap yang sudah ada **tetap dipakai apa adanya** untuk sisi miring (jarang) dan rapi-rapi kecil — tidak diubah.

---

## 2. Kenapa dibutuhkan

- Freehand drag-sudut buat bentuk campur sering "ketarik jadi nggak beraturan" — laporan langsung dari Elvan: nambah sudut lalu ketik ukuran malah bikin sisi tetangga ikut geser.
- Kebutuhan lapangan: cepat, tanpa mikir derajat/koordinat.
- **Cocok dengan kasus nyata yang sudah divalidasi:** PA-DUTA (14 Juli) — luas asli ~40m² lahir dari bounding-box 51m² yang berlekuk. Pola "kotak besar dikurangi lekukan" ini persis kasus itu.

---

## 3. Keputusan yang dikunci

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Tempatnya | Di dalam blok Denah yang sudah ada (RAB Opsi), tombol baru di sebelah kontrol yang ada. Bukan halaman/menu baru. |
| 2 | Model data | **Tetap `S.verts`** (poligon tunggal) sebagai satu-satunya sumber kebenaran — sama seperti sekarang. "Tambah Kotak" adalah operasi **sekali-jalan** yang meng-update `S.verts` langsung (bukan menyimpan daftar kotak terpisah). Konsisten dengan pola "tambah sudut" yang sudah ada: mutasi state → `pushUndo()` sebelumnya → `render()`. |
| 3 | Ukuran kotak baru | **Ketik angka** (lebar × panjang, cm) — presisi, konsisten dengan input Lebar/Panjang yang sudah ada. |
| 4 | Posisi kotak baru | **Digeser (drag) 1 sumbu** sepanjang sisi yang dipilih, snap ke grid (sama seperti snap vertex sekarang). Ada input angka cm opsional buat yang mau presisi tanpa geser. |
| 5 | Arah nambah/lekukan | **Otomatis dari posisi** (di luar bentuk = nambah, menutup ke dalam bentuk = lekukan). Tidak ada toggle terpisah. |
| 6 | Sisi tempat nempel | Harus sisi **lurus penuh** (horizontal/vertikal), bukan sisi miring. Kalau sisi yang diketuk miring, "+ Tambah Kotak" tidak aktif di situ. |
| 7 | Preview | **Live** — kotak baru (outline) dan hasil gabungannya kelihatan sambil digeser, sebelum dikonfirmasi. |
| 8 | Sisi miring (diagonal) | **Tidak lewat fitur ini.** Tetap manual: drag 1-2 sudut pakai mode yang sudah ada (ortho-snap), dipakai sesekali untuk motong sudut — tidak berubah sama sekali. |
| 9 | Algoritma gabung | **Symmetric difference (XOR sisi)** antara poligon sekarang dan kotak baru — satu algoritma yang sama dipakai baik untuk nambah maupun lekukan (bedanya cuma posisi kotak relatif ke bentuk lama). Detail di §5. |
| 10 | Validasi | Kotak baru harus nempel ke **1 sisi lurus** milik bentuk lama (boleh sebagian dari panjang sisi itu — kotak kecil nempel di sebagian sisi besar itu wajar, contoh L-shape) dan **seluruhnya di luar** bentuk lama (nambah) **atau seluruhnya di dalam & nyentuh tepi** (lekukan) — bukan numpuk sebagian di luar-dalam sekaligus. Kasus ambigu (numpuk sebagian luar-dalam, lekukan ngambang di tengah tanpa nyentuh tepi) → **ditolak dengan peringatan**, tidak diproses diam-diam. |
| 11 | Undo | Pakai mekanisme undo yang sudah ada (`pushUndo`) — kalau hasil gabung salah, tinggal Undo. |

**Non-goals (sengaja tidak ditangani — ponytail, tambah kalau memang kepakai nyata):**
- Kotak nempel sebagian di 1 sisi (bukan penuh) atau numpuk kompleks multi-sisi
- Hasil gabung yang jadi >1 bentuk terpisah (tidak connected)
- Lekukan yang tidak nyentuh tepi bentuk sama sekali (bakal jadi lubang di tengah — poligon dengan lubang tidak didukung downstream: `buildMembers`, hitung luas, cutting semua asumsi 1 loop tertutup sederhana)
- Sisi miring lewat "Tambah Kotak" — tetap lewat drag manual

---

## 4. Alur pakai (UI)

1. Ketuk **"+ Tambah Kotak"**
2. Ketik ukuran kotak baru: lebar × panjang (cm)
3. Ketuk sisi lurus tempat kotak mau nempel (sama seperti cara ketuk sisi yang sudah ada untuk "tambah sudut")
4. Kotak baru muncul sebagai outline di kanvas, digeser (drag) sepanjang sisi itu — snap grid, live preview bentuk gabungan (nambah/lekukan otomatis kebaca dari posisi). Ada input cm kecil buat presisi kalau nggak puas hasil geser.
5. Lepas jari → posisi terkunci ke snap terdekat.
6. Ketuk **"Terapkan"** → validasi (§3.10) → kalau valid: `pushUndo()`, `S.verts` diganti hasil gabungan, `render()`. Kalau tidak valid: peringatan, kotak tetap di mode preview buat diperbaiki posisinya.

Sisa alur (assign besi per bagian, support, tiang, ukur-sisi buat rapi-rapi, hitung harga) **tidak berubah** — semua tetap jalan di atas `S.verts` yang sudah digabung.

---

## 5. Algoritma inti (ringkas)

Poligon sekarang (`S.verts`, rectilinear — sisi lurus, hasil drag/gabung sebelumnya) dan kotak baru (rectangle sederhana) di-XOR:
1. Pecah semua sisi (poligon lama + kotak baru) di titik potong koordinat x/y gabungan.
2. Segmen yang muncul di KEDUA bentuk (sisi dempetan) → dibuang.
3. Segmen yang cuma muncul di SATU bentuk → jadi bagian tepi hasil akhir.
4. Rangkai sisa segmen jadi 1 loop tertutup (urut sambung ujung-ke-ujung) → jadi `S.verts` baru.

Ini SATU fungsi yang sama untuk nambah maupun lekukan — bedanya cuma apakah kotak baru itu di luar bentuk lama (union → nambah) atau di dalam (subtract → lekukan). Validasi §3.10 dicek SEBELUM langkah ini jalan (memastikan hasil tetap 1 loop tertutup sederhana, bukan >1 bentuk atau ada lubang).

Diimplementasikan sebagai fungsi murni baru di `DenahConv` (`public/js/denah-editor.js`), sejalan pola yang sudah ada (`DenahConv.buildMembers`, `luasM2`, `_bbox` — semua fungsi geometri murni, testable tanpa DOM).

---

## 6. Testing

Tes standalone baru `tests/rangka/test_box_union.mjs` (pola sama seperti `test_konverter.mjs` yang sudah ada, `node` langsung tanpa framework):
- Kasus **nambah**: kotak 700×400 + kotak 300×250 nempel di 1 sisi → hasil bentuk L, sisi dempetan hilang, luas = jumlah 2 kotak (bukti tanpa tumpang tindih/celah)
- Kasus **lekukan**: kotak 700×730 dikurangi kotak 200×150 nempel di tepi → luas = luas besar − luas kecil, bentuk tetap 1 loop tertutup
- Kasus **ditolak**: kotak numpuk sebagian / lekukan tidak nyentuh tepi → fungsi mengembalikan error/null, bukan bentuk rusak

---

## 7. Yang TIDAK berubah

`app/Services/CuttingService.php`, `RangkaDesignService.php`, `CuttingController::hitungSatuBlok` (jalur `tipe:'denah'`), `buildPenawaran()`, jalur kanopi/manual, mode drag-sudut + ortho-snap + "Ukur sisi" yang sudah ada. Fitur ini murni menambah cara BARU menghasilkan `S.verts` di sisi client (`denah-editor.js`) — begitu `S.verts` terbentuk, semua downstream sama persis seperti sekarang.
