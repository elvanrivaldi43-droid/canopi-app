# Desain: DenahEditor — Support (pola drag/tahan + panel daftar + konsolidasi tab)

**Tanggal:** 21 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**File utama:** `public/js/denah-editor.js` (~1637 baris, 1 class `DenahEditor` + namespace `DenahConv`, satu classic-script IIFE — semua perubahan tetap di 1 file). Host: `resources/views/rab-opsi/index.blade.php` (cuma menampung widget, tidak diubah).

---

## 1. Ringkasan & pemicu

Support di DenahEditor sekarang punya interaksi lama yang rawan salah: **garis support manual tap sekali (tanpa geser) = LANGSUNG TERHAPUS** tanpa konfirmasi, dan support grid otomatis tap = langsung toggle on/off. Ini beda dengan Tiang yang baru diredesign (commit `a94aa52`) yang sudah pakai pola **tap cepat = no-op, geser = pindah, tahan ~0,5 detik = menu**.

Redesign ini menyeragamkan seluruh Support ke pola Tiang, menambah panel daftar Support (S1/S2/... + Fokus, meniru `renderTiangPanel`), membuat support grid bisa digeser, dan mengkonsolidasikan semua kontrol Support yang tadinya kesebar (tab "Support" + tombol di tab "Mode") ke dalam satu tab "Support".

**Pemicu utama (dikonfirmasi Elvan):** support kepencet gak sengaja lalu kehapus.

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Pola interaksi seragam | Ketiga sub-elemen Support (garis manual, titik ujung manual, grid otomatis) memakai pola **tap cepat tanpa gerak = no-op; geser = pindah; tahan diam ~450ms = buka menu** — persis pola Tiang (`denah-editor.js` timer long-press 450ms di mode tiang, ~baris 1300-1304). Menghapus perilaku lama "tap = hapus/toggle". |
| 2 | Menu titik ujung = menu garis | 1 garis support manual = `{a:{x,y}, b:{x,y}}` (sepasang titik tak terpisahkan; `S.supportsManual`, ~baris 1334). Tidak ada konsep "hapus 1 titik saja". Menu di titik ujung SAMA dengan menu di badan garis: **Hapus** (buang seluruh garis, `splice` 1 entri) + **Ganti Material**. |
| 3 | Grid support bisa digeser | Support grid (yang tadinya posisinya fix ikut grid) sekarang bisa digeser. **Dikunci searah:** support horizontal hanya naik-turun, support vertikal hanya kiri-kanan (tidak diagonal). |
| 4 | Grid digeser → "naik kelas" jadi manual | Posisi grid dihitung ulang tiap render dari geometri kotak (TIDAK ada koordinat tersimpan), dan ID-nya (`Sh_{li}_{s}`/`Sv_{li}_{s}`, ~baris 133/136) TIDAK stabil saat rangka berubah. Maka begitu 1 grid support digeser, ia dikonversi jadi entri `S.supportsManual` baru (dapat koordinat sungguhan tersimpan + ID stabil `Sm_i`), dan posisi grid asalnya ditandai `S.removed` biar tidak dobel. Ini reuse mekanisme yang sudah terbukti, bukan sistem penyimpanan-posisi baru yang rawan. |
| 5 | Panel daftar Support | Panel baru meniru `renderTiangPanel` (~baris 797-894): daftar semua support manual dengan label otomatis **S1, S2, ...** (urutan dari `S.supportsManual`), tiap baris punya tombol **Fokus** (scroll ke posisi + kedip highlight ~900ms, pola `tFokus` ~baris 855-865) dan **Hapus**. Grid support yang BELUM pernah digeser tidak masuk panel (belum jadi entri manual). |
| 6 | Panel TANPA input X/Y | Berbeda dari panel Tiang, panel Support tidak diberi input koordinat X/Y angka (belum diperlukan — geser di canvas sudah cukup untuk reposisi). Bisa ditambah nanti kalau kepakai. |
| 7 | Undo/Redo/autosave reuse | Tidak membuat mekanisme baru. Undo/Redo global (`btnUndo`/`btnRedo`, ~baris 343-345, `pushUndo()` ~654) dan autosave (`_changed()` → `onChange` → debounce 800ms `autoSave()` di `rab-opsi/index.blade.php`) otomatis berlaku untuk semua mutasi support asalkan tiap mutasi memanggil `pushUndo()` + `render()`. |
| 8 | Konsolidasi ke tab Support | Semua kontrol Support dikumpulkan ke tab "Support". Tombol mode "Support" dan "+ Support manual" DIHAPUS dari tab "Mode". |
| 9 | Buka tab Support = aktif mode edit | Membuka tab "Support" otomatis men-set `this.mode = 'support'` (canvas langsung siap edit support). Ini kopling baru: tab (yang tadinya murni toggle tampilan) sekarang juga menyetel mode. Hanya untuk tab Support dulu. |
| 10 | Menu grid vs menu manual | Menu tahan pada grid support (belum digeser): **Sertakan/Kecualikan** (toggle `S.removed`, pindah dari tap ke menu) + **Ganti Material**. Begitu grid sudah digeser jadi manual, menunya ikut jadi menu manual (Hapus + Ganti Material). |

---

## 3. Struktur data

**Tidak ada tabel/DB baru** — semua di model canvas `this.S` (client-side, di-autosave sebagai JSON ke `/rab-opsi/autosave`).

- `S.supportsManual: [{a:{x,y}, b:{x,y}}, ...]` — SUDAH ADA. Bertambah entri saat grid support digeser (Keputusan #4) atau via "+ Support manual".
- `S.removed: {id: true}` — SUDAH ADA. Dipakai untuk mengecualikan grid support (via menu, Keputusan #10) DAN untuk menandai posisi grid asal yang sudah "naik kelas" (Keputusan #4).
- `S.matOverride: {id: material}` — SUDAH ADA. Dipakai "Ganti Material" (tidak berubah).
- Tidak ada field posisi baru untuk grid support (sengaja — lihat Keputusan #4).

---

## 4. Alur kerja

**A. Tab Support (konsolidasi)**
Buka tab "Support" → `this.mode='support'` otomatis aktif → panel menampilkan: (1) setelan arah+kotak+"Pakai saran" (sudah ada, ~baris 309-318), (2) tombol "+ Support manual" (dipindah dari tab Mode), (3) daftar S1/S2/... (baru). Tombol "Support" & "+ Support manual" dihapus dari panel Mode (~baris 330, 335).

**B. Interaksi canvas (mode support)**
- **Garis support manual** — tap cepat: no-op. Geser: pindah seluruh garis (sudah ada, ~baris 1565-1568). Tahan 450ms: menu (Hapus / Ganti Material).
- **Titik ujung support manual** (`data-sm`+`data-end`) — tap cepat: no-op. Geser: pindah 1 titik presisi (sudah ada, ~baris 1384-1394). Tahan 450ms: menu (Hapus seluruh garis / Ganti Material).
- **Grid support** (`Sh_`/`Sv_`) — tap cepat: no-op (dulu langsung toggle). Geser (dikunci searah): konversi ke `supportsManual` + tandai `S.removed` posisi asal. Tahan 450ms: menu (Sertakan/Kecualikan / Ganti Material).

**C. Panel daftar (renderSupportPanel, baru)**
Dipanggil dari dalam `render()` (pola sama `renderTiangPanel` dipanggil di ~baris 1184), rebuild tiap render. Kalau `mode !== 'support'` → sembunyikan/kosongkan panel & return. Tiap baris manual support: label `S{i+1}`, tombol Fokus (`data-role=sFokus`), tombol Hapus (`data-role=sHapus`). Semua aksi panggil `pushUndo()` + mutasi `S.supportsManual` + `render()`.

**D. Long-press menu (reuse pola menu Tiang / matMenu)**
Reuse pola `openTiangMenu`/`_showTiangMenuAt`/`_closeTiangMenu` (~baris 998-1029) dan/atau `openMatMenu` (~baris 963-992, sudah generic per prefix id F/S/T) — viewport-clamped positioning yang sama. Tidak membuat komponen popup baru dari nol.

---

## 5. Batas scope (sengaja belum dikerjakan)

- **Frame** — tetap seperti sekarang (vertex bisa drag, sisi = input panjang angka). Redesign Frame ke pola drag/menu = task terpisah (backlog CLAUDE.md #2).
- **Konsolidasi tab Tiang/Bentuk** — hanya Support yang dikonsolidasi sekarang. Tiang tetap di tab Mode (transisi tidak konsisten yang disetujui Elvan; dirapikan menyusul). Tab "Mode" tidak dibubarkan.
- **Input X/Y angka di panel Support** — tidak sekarang (Keputusan #6).
- **Fokus/pelacakan untuk grid support yang belum digeser** — tidak masuk panel (belum jadi entri manual). Kalau nanti perlu grid support bisa dilacak/difokus tanpa digeser, itu diskusi terpisah.
- **Perubahan DB/backend** — tidak ada; murni client-side canvas + autosave JSON yang sudah ada.

---

## 6. Risiko & catatan teknis

- ID grid support tidak stabil (Keputusan #4) — inilah alasan konversi-ke-manual dipilih daripada menyimpan offset berbasis ID. Jangan menyimpan posisi/offset yang dikunci ke `Sh_`/`Sv_` id.
- Support/Frame/Tiang/box logic semua bercampur di 1 `bindSvg()` (pointerdown/move/up, ~baris 1207-1600), bercabang per `this.mode` & `drag.type`. Penambahan long-press timer untuk mode support harus meniru struktur timer mode tiang agar tidak bentrok.
- Emoji dilarang di file yang ter-deploy (aturan CLAUDE.md) — panel/menu pakai teks/SVG biasa. (Catatan: `denah-editor.js` sudah memuat beberapa emoji lama dari commit sebelumnya — itu di luar scope, jangan ditambah yang baru.)
- Tiap mutasi wajib `pushUndo()` + `render()` agar Undo/Redo/autosave konsisten (Keputusan #7).
