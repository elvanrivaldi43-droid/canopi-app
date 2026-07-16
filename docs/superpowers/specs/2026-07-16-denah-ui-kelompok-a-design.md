# Desain: DenahEditor Kelompok A — ribbon layout, zoom, ukuran visual, ortho-snap support

**Tanggal:** 16 Juli 2026
**Status:** Disetujui (brainstorming, termasuk mockup visual) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**Konteks:** #10 di `MEMORI_PROYEK.md` — 6 permintaan update UI DenahEditor, dipecah 3 kelompok (A/B/C). Ini Kelompok A: bagian paling cepat/aman (layout+zoom+ukuran+ortho-support), tidak menyentuh keputusan yang masih ambigu (drag-pindah-besi di Kelompok B, saran-kotak-2-arah di Kelompok C).

---

## 1. Ringkasan

Empat perbaikan UI di `DenahEditor` (`public/js/denah-editor.js`), semua **aditif/tampilan** — tidak mengubah `S.verts`, model data, atau jalur hitung harga (`CuttingController`/`RangkaDesignService`):

1. **Layout ribbon** — kontrol dikelompokkan jadi tab (ala MS Word), kanvas selalu besar & tetap terlihat, tidak perlu scroll bolak-balik.
2. **Zoom & pan** — pinch 2-jari + tombol Reset eksplisit.
3. **Ukuran visual** — titik sudut dikecilin, garis frame & label dibesarin.
4. **Ortho-snap support manual** — drag titik ujung support ikut nge-snap lurus, sama seperti drag sudut poligon yang sudah ada.

Alasan digabung 1 spec: keempatnya murni presentasi/interaksi kanvas di layar HP, tidak saling bergantung ke keputusan yang masih terbuka di Kelompok B/C.

---

## 2. Kenapa dibutuhkan

Laporan langsung Elvan (16 Juli): di HP, kanvas denah kejepit di antara kontrol atas (Lebar/Panjang/Arah/Besi/toolbar) dan panel "Ukur Sisi" di paling bawah — tiap mau ubah angka sambil lihat hasilnya, harus gantian scroll naik-turun. Kanvas juga kurang leluasa/kecil, jadi kotak-kotak kecil (mis. support 30×50cm) susah dilihat jelas. Titik sudut yang besar (r=10) juga menutupi garis+label ukuran di sekitarnya.

---

## 3. Keputusan yang dikunci

### 3.1 Layout ribbon

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Struktur | 5 tab ribbon horizontal di ATAS kanvas: **Ukuran** (Lebar/Panjang/Tinggi/Grid) · **Support** (Arah+Kotak support) · **Besi** (3 dropdown: Frame/Support/Tiang) · **Mode** (Bentuk/Ganti besi/Support/Tiang/+Sudut/−Sudut/+Kotak/Undo/+Support manual) · **Ukur Sisi** (F1..Fn). |
| 2 | Perilaku buka/tutup | Ketuk tab → strip panel keluar tepat di bawah baris tab (tinggi terbatas, ~≤130px, isi discroll internal kalau kepanjangan), MENGGESER kanvas turun sedikit (tidak overlay menutupi kanvas). Ketuk tab yang sama lagi → strip tertutup, kanvas balik naik. Ketuk tab lain → isi strip berganti (tab lama tertutup otomatis), cuma 1 strip terbuka dalam satu waktu. |
| 3 | Kanvas | Selalu terlihat penuh di bawah baris tab (+ strip kalau lagi terbuka) — tidak pernah discroll keluar layar dalam pemakaian normal. Ambil porsi tinggi layar sebisa mungkin (bukan tinggi tetap kecil seperti sekarang). |
| 4 | Legenda + Luas denah | Tetap di bawah kanvas seperti sekarang (info pasif, jarang dipakai, tidak perlu masuk ribbon). |
| 5 | Ukuran tap | Semua tombol ribbon (tab + tombol di dalam strip Mode: Bentuk/+Sudut/−Sudut/dst) pakai target sentuh minimal ±40×40px dengan jarak antar tombol yang cukup — bukan cuma tombol zoom. Mengikuti pola CSS yang sudah ada (`.de-tool`, `.de-mini`) tapi padding/gap diperbesar. |
| 6 | Default saat buka blok | Tidak ada strip yang otomatis terbuka (semua tab tertutup) — kanvas dapat porsi maksimal saat pertama kali blok Denah dibuka. |

### 3.2 Zoom & pan

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Mekanisme | Pinch 2-jari (zoom) + geser 2-jari (pan), lewat Pointer Events di wrapper `<div>` kanvas — CSS `transform: scale()/translate()`, TIDAK mengubah `viewBox`/`SC`/koordinat cm↔px yang sudah dipakai semua handler drag. |
| 2 | Konflik dengan drag 1-jari | Kalau drag 1-jari (sudut/besi/support) lagi jalan terus jari ke-2 nempel di kanvas, drag itu dibatalkan otomatis dan pindah ke mode pinch-pan. |
| 3 | Reset otomatis | Tiap ganti blok Denah atau reload halaman → zoom balik ke fit-semua (tidak disimpan/diingat). |
| 4 | Reset manual — 2 cara | (a) Double-tap area kosong kanvas → animasi balik ke fit-semua. (b) Tombol **Reset** eksplisit di pojok kanvas (bukan +/−) — ukuran besar (≥44×44px), ditempatkan berjauhan dari tombol/handle lain di kanvas biar tidak salah pencet. |
| 5 | Tombol +/− | **Tidak dipakai** — dianggap berlebihan karena pinch sudah menangani zoom in/out manual. |
| 6 | Batas zoom | 1x (fit awal, minimum) sampai 4x (maksimum). |

### 3.3 Ukuran visual

| # | Elemen | Sebelum | Sesudah |
|---|---|---|---|
| 1 | Titik sudut (`vh`, lingkaran) — kelihatan | r=10 | r=5 |
| 2 | Titik sudut — area sentuh (hit-area transparan, elemen terpisah, TIDAK berubah) | r=24 | r=24 (tetap) |
| 3 | Garis frame (`stroke-width`) | 4 | 5 |
| 4 | Label sisi frame (F1/F2 dst, `font-size`) | 10 | 13 |

### 3.4 Ortho-snap support manual

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Target snap | Saat drag titik A atau B support manual (`drag.type==='sup'`), bandingkan ke X/Y titik **satunya milik support yang sama** (bukan sudut poligon lain) — biar garis support jadi lurus vertikal/horizontal. |
| 2 | Threshold | Sama seperti ortho-snap sudut poligon yang sudah ada: `(S.grid || 20) * 0.8`. |
| 3 | Lokasi kode | Ditambahkan di blok `pointermove` (`else if (drag.type === 'sup')`, `denah-editor.js` sekitar baris 643-648) — pola sama dengan ortho-snap `vert` (baris 626-632) tapi bandingnya ke pasangan endpoint sendiri, bukan `pv`/`nx` tetangga poligon. |

---

## 4. Alur pakai (UI)

1. Buka blok Denah → kanvas langsung tampil besar, semua tab ribbon tertutup.
2. Mau ubah Lebar/Panjang → ketuk tab **Ukuran** → strip keluar di bawah tab, isi angka → ketuk tab **Ukuran** lagi (atau tab lain) untuk tutup → kanvas balik penuh.
3. Mau ubah panjang sisi presisi → ketuk tab **Ukur Sisi** → ketik F1..Fn → tutup tab.
4. Di kanvas: pinch buat zoom in ke bagian kecil (mis. kotak support 30×50cm) → lihat jelas → double-tap kosong ATAU ketuk tombol Reset buat balik fit-semua.
5. Bikin support manual (2 klik titik A→B) → drag titik ujung → otomatis lurus kalau hampir sejajar sama titik satunya (ortho-snap).

Sisa alur (assign besi per bagian, hitung harga, dsb) **tidak berubah**.

---

## 5. Testing

- **Ortho-snap support manual** — logic murni matematika, testable headless. Ditambahkan ke test yang sudah ada (pola `tests/rangka/test_konverter.mjs`, node langsung tanpa framework): drag titik B mendekati X titik A → terkunci ke X titik A; sebaliknya untuk Y; kalau di luar threshold → tidak snap (nilai asli dipakai).
- **Layout ribbon, zoom/pan, ukuran visual** — murni interaksi & tampilan browser (Pointer Events, CSS transform, sentuh layar), tidak bisa diuji headless bermakna. Divalidasi manual oleh Elvan di HP production, pola sama seperti fitur editor sebelumnya (mis. Gabungan Kotak #9).

---

## 6. Yang TIDAK berubah

`app/Services/CuttingService.php`, `RangkaDesignService.php`, `CuttingController::hitungSatuBlok` (jalur `tipe:'denah'`), `buildPenawaran()`, jalur kanopi/manual, model data `S.verts`/`S.supportsManual`/dst, algoritma `DenahConv` (termasuk `combineBox` dari #9), drag-sudut + ortho-snap poligon yang sudah ada (cuma dipakai ulang untuk support). Kelompok B (drag-pindah-besi + snap-tengah) dan Kelompok C (saran-kotak-2-arah) sengaja di luar cakupan — masih butuh brainstorming terpisah.
