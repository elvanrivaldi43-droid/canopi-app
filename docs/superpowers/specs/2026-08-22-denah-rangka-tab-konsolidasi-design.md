# Desain: DenahEditor — Ribbon 3 Tab (Rangka / Support / Tiang), 1 Tab = 1 Mode

**Tanggal:** 2026-08-22
**File utama:** `public/js/denah-editor.js` (1 file, tidak dipecah)

## Masalah

Kontrol di ribbon tercerai-berai dan tidak konsisten:

- Kontrol **rangka** tersebar di 3 tab: **Ukuran** (Lebar/Panjang/Tinggi/Snap/Reset),
  **Mode** (Bentuk, +Sudut, −Sudut, +Tambah Kotak), **Ukur Sisi** (panjang tiap sisi).
- Tab **Mode** adalah "tab serbaguna" yang ngambang: isinya Bentuk + Ganti besi + Tiang
  yang tidak satu domain.
- Tab **Besi** cuma 3 dropdown besi default yang terpisah dari domain-nya masing-masing.

Pola "1 tab = 1 mode" sudah dipakai Support (buka tab = aktif mode support, 21 Agustus).
Sisanya belum ikut pola itu.

## Tujuan

Rapikan ribbon jadi **3 tab, tiap tab = 1 mode** (buka tab → otomatis masuk mode-nya):
**Rangka (bentuk) · Support · Tiang**. Besi default nempel ke domain-nya. Tab "Ukuran",
"Ukur Sisi", "Besi", "Mode" dibubarkan.

## Keputusan (disepakati Elvan)

| # | Keputusan | Alasan |
|---|---|---|
| 1 | Ribbon jadi 3 tab: **Rangka · Support · Tiang** | Tiap tab satu pekerjaan jelas |
| 2 | Buka tab = otomatis aktif mode-nya (Rangka→bentuk, Support→support, Tiang→tiang) | Pola konsisten (sudah dipakai Support) |
| 3 | Kontrol rangka (L/P/Tinggi, +/−Sudut, +Kotak, Reset, panjang sisi) semua ke tab Rangka | Hilangkan loncat antar tab |
| 4 | Besi **default** nempel ke domain: "Besi frame"→Rangka, "Besi support"→Support, "Besi tiang"→Tiang | Besi default = sifat tiap benda, bukan setelan bersama |
| 5 | Tab "Besi" & "Mode" dibubarkan | Isinya sudah pindah / jadi mode-tab |
| 6 | **Snap grid** & **Ganti besi** pindah ke quickbar atas | Snap = alat global lintas mode; Ganti besi = mode lintas-batang, tak punya domain tunggal |
| 7 | "Ganti besi" (override per-batang) dipertahankan SEMENTARA | Masih satu-satunya cara override besi 1 batang rangka/tiang; akan pensiun saat backlog "tekan-tahan=menu Frame/Tiang" jalan (Support sudah punya di menu tahan) |

## Target akhir

**Tab bar: 5 → 3 tab** — `Rangka | Support | Tiang`

**Tab "Rangka"** (buka → `mode='bentuk'`):
1. **Ukuran:** `Lebar` · `Panjang` · `Tinggi tiang` · `[Reset kotak dari L×P]`
2. **Bentuk:** `[+ Sudut]` · `[− Sudut]` · `[+ Tambah Kotak]`
3. **Besi frame:** dropdown `Besi frame`
4. **Panjang tiap sisi:** daftar `F1 [__] F2 [__] …` (pindahan dari Ukur Sisi)

**Tab "Support"** (buka → `mode='support'`, spt sekarang): setelan support existing + tambah dropdown `Besi support`.

**Tab "Tiang"** (buka → `mode='tiang'`): dropdown `Besi tiang` (panel kelola tiang tetap muncul di bawah kartu spt sekarang saat mode tiang aktif).

**Quickbar atas:** `Snap grid [20▾]` · `Ganti besi` · `Undo` · `Redo` · `Perbesar Layar`

## Detail teknis

Sebagian besar = **relokasi markup**; handler nyambung via `data-role`/`data-mode`,
jadi memindah elemen TIDAK mengubah handler. Perubahan logika minimal & terisolasi.

### Markup ribbon

- Tab bar: sisakan 3 span — `data-tab="rangka"` (label "Rangka"), `data-tab="support"`,
  `data-tab="tiang"` (baru). Hapus `data-tab="ukuran"`, `data-tab="besi"`, `data-tab="mode"`,
  `data-tab="sisi"`. `.de-fullscreen-exit` ("Selesai") tetap child terakhir.
- Panel: `data-panel="rangka"` (isi gabungan), `data-panel="support"` (tambah dropdown besi),
  `data-panel="tiang"` (baru, dropdown besi). Hapus panel `ukuran`, `besi`, `mode`, `sisi`.

### Relokasi elemen (data-role/data-mode TETAP)

- `inL`, `inP`, `inT`, `btnReset` → panel `rangka`
- `btnAddV` (+Sudut), `btnDelV` (−Sudut), `btnAddBox` (+Tambah Kotak) → panel `rangka`
- `sisiPanel` (daftar panjang sisi) → panel `rangka`
- `matFrame` (dropdown) → panel `rangka`
- `matSupport` (dropdown) → panel `support`
- `matTiang` (dropdown) → panel `tiang`
- `inGrid` (Snap grid) → `.de-quickbar`
- Tool `data-mode="besi"` ("Ganti besi") → `.de-quickbar` (jadi tombol mode di quickbar)

### Elemen yang DIHAPUS dari markup

- Tool `data-mode="bentuk"` ("Bentuk") — mode bentuk kini lewat tab Rangka.
- Tool `data-mode="tiang"` ("Tiang") sebagai de-tool — mode tiang kini lewat tab Tiang.
- Panel-panel tab lama (`ukuran`/`besi`/`mode`/`sisi`) beserta wadah `.de-tools`-nya.

### Perubahan logika

1. **Tab → mode (generalisasi blok Support yang ada, `_wireRibbon` ~baris 600-609):**
   ganti special-case `if (name === 'support' ...)` jadi peta
   `{ rangka:'bentuk', support:'support', tiang:'tiang' }`. Saat tab dibuka & mode belum
   sesuai: set mode, bersihkan `armed`/`addSupportPt`/`boxPreview`, `setHint()`, `render()`.
2. **Ganti besi di quickbar:** tombol `data-mode="besi"` di-wire seperti de-tool lama —
   set `mode='besi'`, `setHint()`, `render()`; beri indikasi aktif (mis. class `on`) biar
   pengguna tahu sedang di mode Ganti besi. Handler pemilihan besi per-batang
   (`openMatMenu`) TIDAK berubah.
3. **Default mode init** tetap `bentuk` (~baris 270). Saat load, ribbon tertutup, mode bentuk.

### Yang TIDAK berubah

- `openMatMenu`, `matOverride`, `matDefault`, `saranKotak`, `posisiSupport`, `buildMembers`,
  render kanvas, panel kelola tiang/support, semua tes.
- Snap grid handler `inGrid.onchange`.

## Di luar lingkup (sengaja)

- Pola drag=pindah / tekan-tahan=menu untuk sudut & sisi rangka, dan untuk tiang — backlog
  terpisah (resume point #2). Ini penataan ribbon, bukan ubah interaksi kanvas.
- Kerapian label angka di kanvas (F1·600, dll).
- Menghapus mode "Ganti besi" sepenuhnya — ditunda sampai menu-tahan Frame/Tiang jadi.

## Verifikasi

- `node --check public/js/denah-editor.js` (VPS tidak punya browser/DOM).
- Guardrail penuh + tes `node tests/rangka/*.mjs` tetap hijau.
- Checklist manual Elvan (browser/HP):
  - **A.** Tab tinggal 3: Rangka, Support, Tiang. Buka Rangka → sudut langsung bisa digeser (mode Bentuk).
  - **B.** Tab Rangka lengkap: Lebar/Panjang/Tinggi, +Sudut/−Sudut/+Kotak, Reset, Besi frame, daftar panjang sisi — semua jalan.
  - **C.** Buka tab Tiang → bisa taruh/kelola tiang (mode tiang aktif), dropdown "Besi tiang" ada. Buka Support → mode support, dropdown "Besi support" ada.
  - **D.** Bar atas: Snap grid (ganti nilai → snap ikut), Ganti besi (ketuk → masuk mode, tap batang rangka/tiang → ganti besi 1 batang), Undo/Redo/Perbesar Layar jalan.
  - **E.** Tidak ada kontrol lama yang hilang/dobel; ganti besi default vs per-batang dua-duanya berfungsi.
