# Desain: DenahEditor — Spacing Support Per-Sumbu (cm / jumlah kolom, saran per-sumbu)

**Tanggal:** 21 Agustus 2026
**Status:** Disetujui (brainstorming) — siap ke tahap rencana implementasi
**Pemilik:** Elvan (owner, non-teknis)
**File utama:** `public/js/denah-editor.js` (class `DenahEditor` + namespace `DenahConv`). Host: `resources/views/rab-opsi/index.blade.php` (tak diubah).

---

## 1. Ringkasan & pemicu

Spacing support grid sekarang cuma 1 angka (`S.kotak`) yang dipakai bareng untuk arah horizontal DAN vertikal, dan algoritmanya "melangkah per-cm dari tepi sampai mentok" — jadi sisi yang panjangnya bukan kelipatan pas angka itu akan menyisakan kolom terakhir yang lebih pendek.

**Pemicu (dikonfirmasi Elvan):** denah 600×450 dengan kotak 100 → sisi 600 rata (6×100), tapi sisi 450 jadi 4×100 + 1×50 (dipaksakan, tidak simetris). Elvan mau sisi 450 bisa jadi 5 kolom × 90 cm yang rata.

Redesign ini: (a) spacing horizontal & vertikal jadi **independen**, (b) tiap sumbu bisa dipilih mode **"cm per kotak"** (perilaku lama, boleh nyisa) atau **"jumlah kolom"** (dibagi rata pas, gak nyisa), (c) tombol "Pakai saran" dihitung **terpisah per sumbu**, (d) ada input **"ideal per kotak"** yang bisa Elvan atur sendiri (dipakai tombol saran), (e) kalau arah cuma 1, baris setelan sumbu yang tak dipakai **disembunyikan**.

---

## 2. Keputusan yang dikunci

| # | Keputusan | Detail |
|---|---|---|
| 1 | Spacing independen per sumbu | Horizontal (loop `Sh_`, melangkah sepanjang sumbu Y) dan vertikal (loop `Sv_`, melangkah sepanjang sumbu X) punya setelan sendiri-sendiri, tidak lagi berbagi 1 `S.kotak`. |
| 2 | Dua mode per sumbu | Tiap sumbu: dropdown mode **`'cm'`** (isi cm per kotak — perilaku lama, boleh menyisakan kolom pendek) atau **`'kolom'`** (isi jumlah kolom — dibagi rata persis, tanpa sisa). |
| 3 | Arti "jumlah kolom" | N kolom = N ruas/bay yang sama besar = **N−1 garis support internal** dengan jarak `span/N`. Contoh: sisi 450, 5 kolom → 4 garis di 90/180/270/360, tiap ruas 90 cm. Matematis pasti habis, tak ada sisa. |
| 4 | Ideal per kotak bisa diatur | Kolom input baru **"Ideal per kotak (cm)"** (default 100), **1 nilai dipakai kedua sumbu**. HANYA dipakai tombol "Pakai saran" untuk menentukan jumlah kolom terbaik — tidak langsung mengubah gambar. |
| 5 | "Pakai saran" per-sumbu, logika lama | Rumus `DenahConv.saranKotak` yang sudah ada dipertahankan (`N = max(1, round(span/ideal)); K = round(span/N)`), TAPI dijalankan 2× terpisah: horizontal pakai span-Y (Panjang bounding-box), vertikal pakai span-X (Lebar bounding-box). Hasilnya mengisi kedua baris setelan (mode `'cm'`). |
| 6 | Arah 1 → sembunyikan baris lain | `arah === 'h'` → hanya baris horizontal tampil; `arah === 'v'` → hanya baris vertikal; `arah === '2'` → dua-duanya. Baris yang tersembunyi tidak mempengaruhi gambar (loop-nya memang tak jalan untuk arah itu, konsisten `buildMembers` existing). |
| 7 | Backward-compatible, tanpa migrasi | Semua field baru **opsional**. Kalau field per-sumbu tidak ada di model tersimpan (denah lama), fallback ke `S.kotak` lama dengan algoritma langkah lama — denah lama tampil PERSIS seperti sebelumnya. Tidak ada backfill/migrasi data. |
| 8 | `S.kotak` lama dipertahankan | Kolom `S.kotak`/`S.autoKotak` yang lama TIDAK dihapus (jadi nilai fallback + kompat). Field baru menang kalau ada. |

---

## 3. Struktur data (`this.S`, client-side, di-autosave sebagai JSON)

Field baru, semua opsional (default via fallback ke `S.kotak`):

- `S.modeH` / `S.modeV` (enum `'cm'`|`'kolom'`, default `'cm'`) — mode tiap sumbu.
- `S.kotakH` / `S.kotakV` (number cm, opsional) — dipakai kalau mode `'cm'`. Fallback ke `S.kotak` kalau tak ada.
- `S.kolomH` / `S.kolomV` (int ≥ 1, opsional) — dipakai kalau mode `'kolom'`.
- `S.idealKotak` (number cm, default 100) — target untuk tombol saran.

Field lama dipertahankan: `S.kotak`, `S.autoKotak`, `S.arah`, `S.target`.

Konvensi arah (dari `buildMembers` existing, JANGAN dibalik):
- **Horizontal** = garis `Sh_`, dihasilkan loop `if (S.arah === 'h' || S.arah === '2')`, melangkah sepanjang **Y** (`bb.y0 → bb.y1`). Span = `bb.y1 - bb.y0`.
- **Vertikal** = garis `Sv_`, loop `if (S.arah === 'v' || S.arah === '2')`, melangkah sepanjang **X** (`bb.x0 → bb.x1`). Span = `bb.x1 - bb.x0`.

---

## 4. Alur kerja

**A. Mesin gambar — `DenahConv.buildMembers`**
Untuk tiap arah aktif, tentukan daftar posisi garis internal via fungsi pure baru `DenahConv.posisiSupport(lo, hi, mode, kotak, kolom, kotakFallback)`:
- Mode `'kolom'` (kolom ≥ 1): `K = (hi - lo) / kolom`; garis di `lo + K, lo + 2K, ..., lo + (kolom-1)K` (kolom−1 garis, semua ruas = K). **Tanpa sisa.**
- Mode `'cm'`: `K = kotak > 0 ? kotak : kotakFallback`; garis di `lo + K, lo + 2K, ...` selama `< hi - 1` (algoritma langkah lama — boleh menyisakan ruas terakhir).
- Kembalikan array koordinat garis (angka). Loop `Sh_`/`Sv_` di `buildMembers` memanggil fungsi ini alih-alih loop `for` inline, lalu untuk tiap posisi menjalankan `scanX`/`scanY` seperti sekarang (jadi bentuk poligon tak beraturan tetap ditangani benar — fungsi ini cuma memutuskan DI MANA garis, bukan panjang segmennya).

Guard tetap: `K > 0` wajib (kotak/kolom ≤ 0 atau NaN → fallback aman, jangan sampai loop tak berhenti / bagi 0).

**B. Tombol "Pakai saran" — `applySaran()`**
Hitung 2×: `kotakH = DenahConv.saranKotak(spanY, idealKotak)`, `kotakV = DenahConv.saranKotak(spanX, idealKotak)`. Set `modeH='cm'`/`modeV='cm'`, isi `kotakH`/`kotakV`, sinkronkan input, render. (spanY/spanX dari bounding-box verts saat itu.)

**C. UI tab Support**
- Baris atas: dropdown **Arah** (existing) + input **"Ideal per kotak (cm)"** (baru) + tombol **"Pakai saran"** (existing, logika baru) + hint.
- Baris **Horizontal** (tampil kalau `arah` = `'h'`/`'2'`): dropdown mode (`cm`/`kolom`) + input angka (label & isi ikut mode).
- Baris **Vertikal** (tampil kalau `arah` = `'v'`/`'2'`): sama.
- Ganti dropdown Arah → tampil/sembunyikan baris yang relevan (Keputusan #6) + render.
- Ganti mode/angka per baris → tulis ke field model terkait + render.

---

## 5. Batas scope (sengaja belum dikerjakan)

- **Spacing tak seragam DALAM satu sumbu** (mis. kolom 1=80, kolom 2=100, dst) — tidak; tiap sumbu tetap 1 spacing seragam (cm) atau 1 jumlah kolom rata.
- **Ideal per kotak beda per sumbu** — tidak; 1 nilai ideal dipakai dua sumbu (Keputusan #4).
- **Migrasi denah lama** — tidak ada; fallback otomatis (Keputusan #7).
- **Perubahan interaksi drag/menu support** — di luar scope ini (sudah selesai sesi sebelumnya); ini murni soal spacing grid.
- **Perubahan backend/DB** — tidak ada; murni client-side + autosave JSON existing.

---

## 6. Risiko & catatan teknis

- Mode `'kolom'` dengan `kolom = 1` → 0 garis internal (span jadi 1 ruas utuh) — valid, bukan error.
- `posisiSupport` HARUS pure (tanpa DOM) supaya bisa ditest otomatis di VPS tanpa browser (pola `tests/rangka/test_support_pola.mjs`) — ini justru bagian paling berisiko (matematika pembagian) dan paling layak ditest.
- Fallback denah lama wajib diverifikasi: model tanpa `modeH/kotakH/...` harus menghasilkan garis PERSIS sama dengan `S.kotak` lama (uji dengan fixture lama).
- Emoji dilarang di file ini (aturan CLAUDE.md).
- Tiap mutasi state lewat input → `render()` (autosave global ikut). Untuk perubahan spacing tak perlu `pushUndo()` per-keystroke (konsisten dengan `inKotak` existing yang juga tak push undo per input) — tapi ini bisa diputuskan di tahap plan; yang penting konsisten dengan pola field spacing yang sudah ada.
