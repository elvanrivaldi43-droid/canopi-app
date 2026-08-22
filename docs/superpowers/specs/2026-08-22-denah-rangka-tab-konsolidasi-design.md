# Desain: DenahEditor — Konsolidasi Kontrol Rangka ke 1 Tab

**Tanggal:** 2026-08-22
**File utama:** `public/js/denah-editor.js` (1 file, tidak dipecah)

## Masalah

Kontrol yang berkaitan dengan **rangka (frame)** tercerai-berai di 3 tab ribbon
berbeda, jadi untuk mengatur satu rangka pengguna harus loncat-loncat antar tab:

- Tab **Ukuran** → Lebar, Panjang, Tinggi tiang, Snap grid, "Reset kotak dari L×P"
- Tab **Mode** → Bentuk, +Sudut, −Sudut, +Tambah Kotak (plus mode Tiang & Ganti besi yang BUKAN rangka)
- Tab **Ukur Sisi** → panjang tiap sisi (F1, F2, ...)

Ini pola yang sama seperti Support dulu (berserakan lalu disatukan ke 1 tab,
21 Agustus). Sekarang giliran rangka.

## Tujuan

Satukan semua kontrol rangka ke **satu tab "Rangka"**. Support & Besi dibiarkan.
Bukan perombakan seluruh ribbon — hanya konsolidasi rangka.

## Keputusan (sudah disepakati Elvan)

| # | Keputusan | Alasan |
|---|---|---|
| 1 | Semua kontrol bentuk + ukuran rangka masuk 1 tab "Rangka" | Hilangkan loncat-loncat antar tab |
| 2 | Buka tab Rangka = otomatis aktif mode Bentuk | Pola sama Support (tab = jalan masuk mode) |
| 3 | Tinggi tiang ikut ke tab Rangka (sebaris Lebar/Panjang) | Cara pikir "ukuran" menyatukan L/P/T |
| 4 | Snap grid pindah ke quickbar atas (dekat Undo/Redo/Perbesar Layar) | Snap global lintas mode, bukan khusus rangka; selalu terlihat tanpa buka tab |
| 5 | Tab "Ukuran" & "Ukur Sisi" dihapus | Isinya sudah pindah ke Rangka |
| 6 | Tab "Mode" tetap ada, tinggal Ganti besi + Tiang | Bentuk pindah jadi implisit di tab Rangka; mode non-rangka tetap |

## Target akhir

**Tab bar: 5 → 4 tab** — `Rangka | Support | Besi | Mode`

**Tab "Rangka"** (buka tab → set `mode='bentuk'`), 3 baris:
1. **Ukuran:** `Lebar` · `Panjang` · `Tinggi tiang` · `[Reset kotak dari L×P]`
2. **Bentuk:** `[+ Sudut]` · `[− Sudut]` · `[+ Tambah Kotak]`
3. **Panjang tiap sisi:** daftar `F1 [__] F2 [__] …` (pindahan dari Ukur Sisi)

**Quickbar atas:** `Snap grid [20▾]` · `Undo` · `Redo` · `Perbesar Layar`

**Tab "Mode":** `Ganti besi` · `Tiang`

**Tab "Support" & "Besi":** tidak berubah.

## Detail teknis

Perubahan ini **murni relokasi markup + 1 tambahan logika**. Semua tombol/input
sudah nyambung ke handler lewat atribut `data-role`, jadi memindahkan elemennya di
markup TIDAK mengubah handler apa pun. Elemen yang dipindah (data-role tetap):

- Dari panel `ukuran` → panel `rangka`: `inL`, `inP`, `inT`, `btnReset`
- Dari panel `mode` → panel `rangka`: `btnAddV` (+Sudut), `btnDelV` (−Sudut), `btnAddBox` (+Tambah Kotak)
- Dari panel `sisi` → panel `rangka`: `sisiPanel` (daftar panjang sisi)
- Dari panel `ukuran` → quickbar: `inGrid` (Snap grid)

Elemen yang TETAP di tempatnya:
- Tab `mode`: tool `data-mode="besi"` (Ganti besi) + `data-mode="tiang"` (Tiang).
  Tool `data-mode="bentuk"` DIHAPUS dari markup (mode bentuk kini lewat tab Rangka).
- Tab `support`, tab `besi`: utuh.

**Satu tambahan logika** — di handler klik tab (`_wireRibbon`, sekitar baris 592-610):
saat tab yang dibuka = `rangka` dan mode belum `bentuk`, set `mode='bentuk'`, bersihkan
highlight `.de-tool`, reset `armed`/`addSupportPt`/`boxPreview`, `setHint()`, `render()`
— PERSIS pola blok `if (name === 'support' ...)` yang sudah ada, tinggal ditambah cabang
`rangka`.

**Markup tab:** ganti `data-tab="ukuran"` jadi `data-tab="rangka"` (label "Rangka"),
hapus `data-tab="sisi"`. Panel `data-panel="ukuran"` jadi `data-panel="rangka"`,
hapus panel `data-panel="sisi"`.

**Snap grid di quickbar:** pindahkan `<label>Snap grid<select data-role="inGrid">…</select></label>`
ke dalam `.de-quickbar`. Handler `inGrid` (`onchange`) tidak berubah.

**Default mode saat init** tetap `bentuk` (baris ~270) — tidak berubah.

## Di luar lingkup (sengaja)

- Pola drag=pindah / tekan-tahan=menu untuk sudut & sisi rangka — backlog terpisah
  (item resume point #2). Ini murni penataan tab, bukan ubah interaksi.
- Kerapian label angka di kanvas (F1·600, angka cm support, label tiang) — tidak disentuh.
- Rename tab "Mode" atau pecah jadi tab per-mode — tidak dilakukan (jaga scope).

## Verifikasi

- `node --check public/js/denah-editor.js` (VPS tidak punya browser/DOM).
- Tes regresi rangka/support yang ada tetap hijau (`node tests/rangka/*.mjs`, guardrail penuh).
- Checklist manual Elvan di browser/HP:
  - **A.** Tab "Rangka" ada; buka → sudut rangka langsung bisa digeser (mode Bentuk aktif).
  - **B.** Di tab Rangka lengkap: Lebar/Panjang/Tinggi tiang, +Sudut/−Sudut/+Kotak, Reset, daftar panjang sisi — semua jalan seperti dulu.
  - **C.** Snap grid ada di bar atas, ganti nilainya → geser sudut membulat sesuai nilai baru.
  - **D.** Tab "Mode" tinggal Ganti besi + Tiang, dua-duanya masih jalan. Tab Support & Besi normal.
  - **E.** Tab "Ukuran" & "Ukur Sisi" sudah tidak ada, tidak ada kontrol yang hilang/dobel.
