# Desain: DenahEditor Kelompok B — drag-pindah-besi + snap-tengah

**Tanggal:** 17 Juli 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** #10 di `MEMORI_PROYEK.md` — 6 permintaan update UI DenahEditor, dipecah 3 kelompok (A/B/C). Kelompok A (zoom+ukuran+ortho-support+ribbon+fullscreen) sudah selesai & dikonfirmasi Elvan di production. Ini Kelompok B: drag-pindah-besi (poin 3) + snap-tengah (poin 4) dari 6 permintaan asli.

---

## 1. Ringkasan

Tiga elemen di `DenahEditor` (`public/js/denah-editor.js`) yang sekarang cuma bisa "diedit sebagian" (tap toggle, atau drag per-titik) ditambah kemampuan **digeser sebagai satu kesatuan (translate)**, plus **1 mesin snap generik** yang berlaku ke ketiganya:

1. **Tiang** — dari tap-toggle (taruh/hapus) jadi bisa juga di-drag pindah posisi.
2. **Support manual** — dari drag-per-ujung (sudah ada) ditambah drag-garis-utuh (A+B geser bareng).
3. **Kotak support** (hasil "+ Tambah Kotak" / Gabungan Kotak, #9) — dari drag-per-sudut (sudah ada) ditambah drag-kotak-utuh (semua vertex kotak geser bareng).
4. **Snap-tengah** — waktu drag salah satu dari 3 elemen di atas, posisi bisa "nempel" (soft-snap, bisa dilewatin) ke titik acuan: titik tiang lain, titik tengah/ujung support lain, atau titik tengah sisi frame saat ini (otomatis ikut kalau sisi berubah panjang karena lekukan/resize). Ditemani garis bantu putus-putus yang muncul selagi nempel.

---

## 2. Kenapa dibutuhkan

Dua kasus nyata dari Elvan (16 Juli):

- **Re-center setelah resize.** Kotak support default 100×100cm yang nempel di satu sisi frame jadi tidak center kalau sisi itu memanjang (mis. jadi 160cm akibat lekukan/resize). Perlu cara gampang geser kotak itu balik ke tengah sisi yang baru, bukan hitung manual lalu drag sudut satu-satu.
- **Sejajar antar tiang.** Tiang 1 dipasang 100cm dari sisi depan. Waktu pasang tiang 2, enak kalau ada "titik magnet" yang bantu nemuin posisi sejajar (jarak sama dari sisi yang sama) dengan tiang 1 — tapi ini cuma bantuan, bukan aturan wajib; boleh juga taruh tidak sejajar.

Kedua kasus ini secara teknis sama: "selagi drag, tawarkan nempel ke suatu titik acuan yang relevan." Karena itu didesain sebagai **1 mesin snap**, bukan 2 fitur terpisah.

---

## 3. Keputusan yang dikunci

### 3.1 Data model — 1 tambahan state

| # | Keputusan | Detail |
|---|---|---|
| 1 | State baru | `S.combinedBoxes`: array `{verts: [i0,i1,i2,i3]}`. `verts` = index vertex di `S.verts` yang membentuk 1 kotak dari Gabungan Kotak. **Revisi implementasi:** `sideA`/`sideB` terpisah ternyata tak perlu — titik tengah sisi governing sudah otomatis ikut sebagai kandidat align-snap lewat `collectAlignCandidates` (yang mengecualikan hanya sisi yang sepenuhnya di dalam kotak), jadi cukup `verts` saja. |
| 2 | Diisi kapan | Otomatis di dalam `combineBox()`, waktu "+ Tambah Kotak" dipakai — tidak ada input tambahan dari user. |
| 3 | Invalidasi | Kalau salah satu vertex di suatu entry `combinedBoxes` dihapus lewat mode "−Sudut", entry itu dibuang (kotak dianggap sudah bukan satu kesatuan lagi, drag-kotak-utuh untuk area itu tidak berlaku, tapi vertex-nya tetap bisa di-drag satu-satu seperti sudut biasa). |
| 4 | Yang TIDAK berubah | `S.verts`, `S.tiang`, `S.supportsManual` tetap format sama persis. `combinedBoxes` murni bantu UI drag, tidak dikirim ke `CuttingController`/mesin hitung harga, tidak memengaruhi `DenahConv` konversi member. |

### 3.2 Mekanik drag per elemen

| Elemen | Sekarang | Jadi (Kelompok B) |
|---|---|---|
| Tiang | Tap: taruh (kosong) / hapus (kena tiang) | Pointerdown di tiang, tunggu gerakan. Tidak gerak sampai lepas → tetap hapus (perilaku lama, tidak berubah). Gerak → drag pindah posisi, grid-snap + cek snap-align (§3.3). |
| Support manual | Drag titik A/B independen (tidak berubah) | Tambah: pointerdown di **badan garis** → drag translate, A dan B geser bareng (jarak & arah sama, garis tetap sejajar dirinya sendiri) + cek snap-align. |
| Kotak support | Drag 1 vertex sudut (tidak berubah) | Tambah: pointerdown di **badan kotak** (area dalam kotak yang tercatat di `combinedBoxes`) → semua vertex terkait geser bareng dengan delta sama + cek snap-align & snap-tengah-sisi. |

Drag-badan masuk ke mode yang sedang aktif (mode Support / mode Bentuk) — tidak ada tab/mode ribbon baru.

### 3.3 Mesin snap generik

| # | Keputusan | Detail |
|---|---|---|
| 1 | Fungsi | 1 fungsi murni (mis. `DenahConv.findAlignSnap(point, candidates, threshold)`, tanpa akses DOM, testable headless) — dipanggil tiap `pointermove` selagi drag tiang/support/kotak. |
| 2 | Kandidat titik acuan | Titik semua tiang lain (kecuali yang sedang digeser) + titik tengah & kedua ujung semua support manual lain + titik tengah tiap sisi frame saat ini (`S.verts`, dihitung ulang tiap panggilan — otomatis ikut kalau sisi berubah panjang). |
| 3 | Aturan cocok | Cek X dan Y independen terhadap tiap kandidat (bisa nyantol salah satu atau dua-duanya). Threshold sama seperti ortho-snap yang sudah ada: `(S.grid || 20) * 0.8`. |
| 4 | Sifat | Soft-snap — posisi "nempel" ke kandidat yang cocok, tapi user tetap bisa terus menggeser lepas dari situ (tidak dikunci permanen, tidak ada mode "matikan snap"). |
| 5 | Indikator visual | Selagi ada kandidat yang cocok: 1 garis bantu putus-putus tipis dari elemen yang digeser ke titik acuan yang match. Hilang otomatis begitu drag selesai atau posisi keluar dari threshold. |
| 6 | Tidak ada yang cocok | Posisi ikut jari + grid-snap biasa (perilaku lama, tidak berubah). |
| 7 | Bug class dari Kelompok A | Posisi hasil align-snap yang aktif SAAT `pointerup` dipakai apa adanya — TIDAK ditimpa ulang oleh grid-snap polos di `pointerup` (pola bug "lurus pas drag, bengkok pas lepas jari" yang sudah diperbaiki di cabang `sup`/`vert` Kelompok A; cabang baru ini harus ikuti pola perbaikan yang sama). |

---

## 4. Alur pakai (UI)

1. Mode Tiang: tekan-tahan lalu geser tiang yang sudah ada → tiang pindah, muncul garis bantu putus-putus kalau sejajar sama tiang lain, lepas jari → posisi terkunci di situ (atau di tempat terakhir kalau tidak ada yang cocok).
2. Mode Support: tekan badan garis A-B (bukan titik ujung) → seret → garis pindah utuh, sejajar dirinya sendiri, snap-align aktif sama seperti tiang.
3. Kotak support hasil "+ Tambah Kotak": tekan badan kotak → seret → semua sudut kotak ikut pindah bareng, bisa nempel ke titik tengah sisi frame yang sekarang (berguna kalau sisi barusan diperpanjang lewat lekukan/resize).
4. Kalau user pilih geser terus lewat titik snap, ya jalan terus — snap tidak memaksa.

Sisa alur (assign besi, hitung harga, dsb.) **tidak berubah**.

---

## 5. Testing

- **`findAlignSnap` (mesin snap) & translate-kotak/garis** — logika murni matematika, ditambahkan ke `tests/rangka/test_konverter.mjs` (pola sama dengan ortho-snap Kelompok A): titik dekat X titik lain → match X; dekat Y → match Y; di luar threshold → tidak snap; translate kotak menggeser semua vertex dengan delta yang sama persis; entry `combinedBoxes` dibuang saat salah satu vertex-nya dihapus.
- **Drag-badan (pointerdown di garis/kotak), rendering garis bantu putus-putus** — murni interaksi & tampilan browser, tidak bisa diuji headless bermakna. Divalidasi manual oleh Elvan di HP production (pola sama seperti fitur editor sebelumnya).

---

## 6. Yang TIDAK berubah

`app/Services/CuttingService.php`, `RangkaDesignService.php`, `CuttingController::hitungSatuBlok` (jalur `tipe:'denah'`), `buildPenawaran()`, jalur kanopi/manual, format data `S.verts`/`S.tiang`/`S.supportsManual`, algoritma `DenahConv` (termasuk `combineBox` dari #9 — hanya ditambah pencatatan `combinedBoxes`, tidak diubah logikanya), ortho-snap sudut poligon & support (Kelompok A, tidak disentuh). Kelompok C (saran-kotak-2-arah) sengaja di luar cakupan — masih butuh brainstorming terpisah.
