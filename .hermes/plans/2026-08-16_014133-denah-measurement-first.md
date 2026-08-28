# DenahEditor Measurement-First Implementation Plan

> **For Hermes:** Implement task-by-task using one writer in one fresh worktree based on `origin/main`. Use Claude Code only after Bos explicitly approves model, effort, purpose, and hard-cap.

**Goal:** Mengubah DenahEditor dari editor sentuh sebagai jalur utama menjadi alur **isi ukuran → gambar mengikuti**, dengan prioritas pertama penempatan tiang numerik yang presisi di HP.

**Architecture:** State dan mesin lama tetap dipakai (`S.verts`, `S.tiang`, `S.supportsManual`, `DenahConv.buildMembers`, `RangkaDesignService`, `CuttingService`). Panel numerik baru hanya menjadi cara yang lebih mudah untuk mengubah state yang sama; SVG menjadi preview dan drag tetap tersedia sebagai koreksi/fallback. Tidak ada perubahan database, endpoint, rumus cutting, atau harga.

**Tech Stack:** JavaScript vanilla/IIFE, SVG, Pointer Events, Node `.mjs` tests, jsdom/harness browser existing, Laravel Blade integration existing.

---

## Keputusan yang sudah dikunci

1. Jalur utama: **ukuran dulu, gambar mengikuti**.
2. Posisi tiang: satu titik acuan + jarak horizontal/vertikal dalam cm.
3. Satuan model tetap cm.
4. Drag/tekan-tahan lama tidak langsung dibuang; tetap fallback sampai jalur angka terbukti.
5. DenahEditor tetap estimator gambar → daftar besi → harga, bukan mesin rekayasa struktur. Sistem tidak menentukan jumlah/titik tiang paling aman.
6. Model snapshot lama harus tetap bisa dibuka tanpa migrasi database.
7. Perubahan pertama fokus menyelesaikan kesulitan tiang. Bentuk dan support measurement-first dirilis bertahap setelah tahap tiang dikonfirmasi di HP.

## Non-goals

- Tidak mengubah `RangkaDesignService`, `CuttingService`, atau rumus harga.
- Tidak menambah tabel/kolom database.
- Tidak membuat perhitungan beban struktur.
- Tidak menghapus editor sentuh lama pada rilis pertama.
- Tidak mengerjakan Kelompok C support dua arah di task tiang.
- Tidak memperbaiki tombol `Lanjut → Finalisasi`/`+ Opsi` tanpa reproduksi terpisah.

## Safety dan branching

- Local `main` saat plan dibuat tertinggal 8 commit; jangan dipakai sebagai basis writer.
- Sebelum implementasi: `git fetch origin --prune`, buat branch/worktree baru dari `origin/main`.
- Satu writer/worktree; jangan ada dua Claude/tmux mengedit file yang sama.
- Stop jika worktree baru tidak bersih atau ada perubahan di luar manifest task.
- Tidak commit/push/deploy tanpa izin eksplisit Bos.
- Tidak memerlukan database integration test karena scope frontend/state lama; tetap jalankan regression PHP/Node yang relevan.

---

# Tahap 1 — Posisi Tiang Berbasis Angka (prioritas)

## Task 1: Kunci kontrak koordinat numerik dengan TDD

**Objective:** Membuat fungsi geometri murni untuk membaca/menulis posisi tiang relatif terhadap titik acuan tanpa DOM.

**Files:**
- Modify: `public/js/denah-editor.js` (`DenahConv`)
- Create: `tests/rangka/test_tiang_numerik.mjs`

**Kontrak:**

```js
DenahConv.denahOrigin(S)
// => { x: minX, y: minY }

DenahConv.tiangFromOffset(S, dx, dy)
// => { x: origin.x + dx, y: origin.y + dy }

DenahConv.tiangToOffset(S, point)
// => { dx: point.x - origin.x, dy: point.y - origin.y }
```

`origin` tahap pertama adalah kiri-depan/top-left bounding box denah. Nilai desimal diterima. Fungsi murni tidak melakukan clamp; clamp tetap di batas UI melalui `clampTiang()`.

**TDD steps:**
1. Tulis tes RED untuk persegi, bentuk L, koordinat negatif, desimal, dan round-trip point→offset→point.
2. Run: `node tests/rangka/test_tiang_numerik.mjs`; expected FAIL karena helper belum ada.
3. Implement minimal helper di `DenahConv`.
4. Run ulang; expected PASS.
5. Run existing geometry tests:
   - `node tests/rangka/test_konverter.mjs`
   - `node tests/rangka/test_align_snap.mjs`
   - `node tests/rangka/test_ortho_snap.mjs`
   - `node tests/rangka/test_box_union.mjs`
   - `node tests/rangka/test_box_reindex.mjs`
6. Review diff; tidak boleh ada perubahan render/gesture pada task ini.

**Acceptance:** Angka X/Y dapat dikonversi ke titik denah secara deterministik dan reversibel.

---

## Task 2: Panel daftar tiang numerik

**Objective:** Pengguna dapat menambah dan mengedit T1..Tn memakai angka tanpa menyentuh kanvas.

**Files:**
- Modify: `public/js/denah-editor.js` (`shellHTML`, binding UI, render panel)
- Extend: `tests/rangka/test_tiang_numerik.mjs`
- Create/extend: jsdom interaction test khusus panel tiang (ikuti pola harness/test tiang existing jika tersedia di `origin/main`)

**UI minimal:**

```text
Posisi Tiang (cm dari kiri/depan)
T1  X [____]  Y [____]  [Fokus] [Hapus]
T2  X [____]  Y [____]  [Fokus] [Hapus]

X [____]  Y [____]  [Preview] [Tambah Tiang]
```

**Behavior:**
- Input hanya menerima angka finite; koma dinormalisasi menjadi titik.
- Nilai kosong/NaN ditolak dengan pesan, tidak mengubah state.
- `Tambah Tiang` melakukan `pushUndo()` sekali lalu memasukkan hasil `clampTiang(tiangFromOffset(...))`.
- Edit X/Y tidak memutasi pada setiap ketikan. Commit saat blur/Enter atau tombol Terapkan agar Undo tidak berisi satu langkah per digit.
- Hapus dari daftar memakai konfirmasi ringan/pola menu yang sudah ada.
- Label T1..Tn sama persis dengan label SVG.
- Tombol Fokus memusatkan/highlight tiang, bukan mengubah posisi.
- Setiap perubahan memanggil `_changed()`/autosave melalui jalur state existing.

**TDD steps:**
1. Tulis RED untuk tambah valid, tolak invalid, edit, hapus, Undo/Redo, dan koma desimal.
2. Jalankan test jsdom fokus; expected FAIL.
3. Implement panel minimal tanpa merombak gesture lama.
4. Jalankan test fokus; expected PASS.
5. Jalankan 5 Node geometry tests dan test tiang lama.

**Acceptance:** Bos dapat menambah/mengedit tiang secara presisi tanpa tap kanvas.

---

## Task 3: Preview titik sebelum commit

**Objective:** Angka yang diketik menampilkan crosshair/ghost tiang sebelum benar-benar ditambahkan.

**Files:**
- Modify: `public/js/denah-editor.js`
- Extend: jsdom interaction test

**Behavior:**
- `Preview` atau input valid menampilkan ghost circle + garis silang dengan label posisi.
- Ghost tidak masuk `S.tiang`, tidak memengaruhi `buildMembers`, cutting, harga, autosave, atau Undo.
- `Tambah Tiang` baru melakukan mutasi.
- Batal/reset menghapus ghost.
- Ghost di-clamp hanya untuk tampilan dan memberikan pesan bila angka di luar area gambar.

**Tests:**
- Ghost tidak menambah member.
- Batal menghapus ghost.
- Commit menambah tepat satu tiang.
- Pinch/drag tidak membuat ghost berubah menjadi tiang tanpa tombol.

**Acceptance:** Pengguna melihat hasil posisi sebelum menyimpan.

---

## Task 4: Tombol posisi cepat

**Objective:** Mempercepat kasus mayoritas tanpa mengetik koordinat manual.

**Files:**
- Modify: `public/js/denah-editor.js`
- Extend: `tests/rangka/test_tiang_numerik.mjs`

**Preset minimum:**
- Sudut aktual S1..Sn (berdasarkan `S.verts`, bukan hanya sudut bounding box).
- Tengah sisi F1..Fn.
- Salin posisi dari tiang existing sebagai titik awal edit.

**YAGNI:** Jangan tambahkan pembagian otomatis banyak tiang atau keputusan teknik struktur pada tahap ini.

**Tests:**
- Preset sudut menghasilkan koordinat vertex tepat.
- Preset tengah sisi menghasilkan `(a+b)/2`.
- Bentuk L menggunakan vertex/sisi aktual.

**Acceptance:** Tiang di sudut/tengah sisi dapat dibuat tanpa drag dan tanpa hitung manual.

---

## Task 5: Koeksistensi dengan gesture lama dan regression test

**Objective:** Panel numerik tidak merusak drag, tekan-tahan, pinch, snap, mode lain, atau autosave.

**Files:**
- Modify only if test proves needed: `public/js/denah-editor.js`
- Extend/create: jsdom regression test
- Use: `tests/rangka/denah_editor_harness.html` bila tersedia/diaktifkan kembali secara lokal

**Required checks:**
1. Long-press tempat kosong masih membuka menu Tambah Tiang.
2. Drag tiang existing masih memindahkan tiang.
3. Long-press tiang existing masih membuka Ganti Besi/Hapus.
4. Pinch tidak menambah tiang.
5. Tiang numeric bisa diedit lagi lewat drag; panel ikut membaca posisi baru.
6. Undo/Redo lintas numeric dan drag benar.
7. Autosave→reload mempertahankan koordinat.
8. `buildMembers` tetap menghasilkan satu member per tiang dengan `panjang=S.tinggi`.
9. `Hitung Harga` tetap memakai daftar member yang sama.

**Acceptance:** Jalur angka dan jalur sentuh bekerja pada state yang sama tanpa divergensi.

---

# Tahap 2 — Bentuk Berbasis Ukuran (setelah Tahap 1 dikonfirmasi Bos)

## Task 6: Builder persegi numerik sebagai jalur default

**Objective:** Panjang/lebar menghasilkan `S.verts` tanpa drag.

**Files:**
- Modify: `public/js/denah-editor.js`
- Create/extend: `tests/rangka/test_bentuk_numerik.mjs`

**Contract:**

```js
DenahConv.rectFromSize(lebar, panjang)
// [{x:0,y:0},{x:lebar,y:0},{x:lebar,y:panjang},{x:0,y:panjang}]
```

- Validasi >0 dan finite.
- Preview dulu, Terapkan baru mutasi.
- Peringatkan bahwa menerapkan ulang ukuran dasar dapat mengubah lekukan/posisi relatif; jangan diam-diam membuang detail.

---

## Task 7: Daftar bagian tambahan/lekukan numerik

**Objective:** Gabungan Kotak dapat dibuat dari form, tanpa drag menentukan sisi/offset/depth.

**Reuse:** `DenahConv.combineBox`; jangan menulis algoritma geometri kedua.

**Input per bagian:**
- sisi acuan F1..Fn;
- jarak dari awal sisi (`offset`);
- lebar sepanjang sisi (`span`);
- kedalaman (`depth`);
- jenis: tambah keluar atau lekukan masuk.

**Tests:**
- Bentuk L tambah.
- Lekukan masuk.
- Tolak span melewati sisi.
- Preview sama dengan hasil Terapkan.
- Undo/Redo dan snapshot reload.

---

## Task 8: Template bentuk sebagai shortcut

**Objective:** Sediakan template Persegi, L, dan U yang hanya mengisi form ukuran; tidak membuat mesin terpisah.

**YAGNI:** Tidak membuat katalog bentuk kompleks pada rilis pertama.

**Acceptance:** Template menghasilkan state yang sama seperti builder numerik manual.

---

# Tahap 3 — Support Berbasis Ukuran (setelah bentuk dikonfirmasi)

## Task 9: Integrasikan rencana Kelompok C secara terkontrol

**Reference:**
- `docs/superpowers/specs/2026-07-17-denah-ui-kelompok-c-design.md`
- `docs/superpowers/plans/2026-07-17-denah-ui-kelompok-c-implementation.md`

**Objective:** Jarak horizontal dan vertikal bisa berbeda, masing-masing diatur melalui angka.

- Jangan menyalin plan lama mentah-mentah; re-review terhadap `origin/main` terbaru.
- Migrasi snapshot lama `kotak` menjadi nilai awal kedua arah secara backward-compatible.
- Support manual mendapat daftar endpoint angka seperti panel tiang, tetapi task terpisah.

---

# Verifikasi rilis

## Automated focused tests

Run seluruh tes geometri:

```bash
for f in tests/rangka/*.mjs; do node "$f"; done
```

Run tes PHP rangka yang tracked dan relevan (kecuali preview server):

```bash
php tests/rangka/test_hitung.php
php tests/rangka/test_seed.php
php tests/rangka/test_stok.php
php tests/rangka/test_stok_material.php
php tests/rangka/test_denah_blok.php
php tests/rangka/test_paduta.php
```

Run deterministic project guard:

```bash
scripts/canopi-check
```

Expected: semua PASS, tidak ada tracked file di luar manifest tahap.

## Manual HP acceptance — Tahap 1

Pada RAB/lead tes:

1. Tambah T1 melalui X/Y tanpa menyentuh kanvas.
2. Preview tepat; Batal tidak menambah.
3. Edit T1 melalui angka; posisi SVG ikut.
4. Drag T1; angka panel ikut.
5. Tambah lewat preset sudut dan tengah sisi.
6. Long-press/drag lama tetap jalan.
7. Pinch tidak menambah tiang.
8. Undo/Redo benar.
9. Autosave→reload benar.
10. Hitung Harga tetap menghasilkan rincian tiang/material yang sama.

Jika satu butir gagal, berhenti dan kirim video reproduksi; jangan menambal beberapa gejala sekaligus.

## Release gate

- Review diff oleh Hermes.
- Untuk perubahan UX besar ini, maksimal satu review read-only independen setelah tes fokus hijau, hanya jika Bos mengizinkan usage Claude.
- Bos mengonfirmasi Tahap 1 di HP sebelum Tahap 2 dimulai.
- Commit/push/deploy masing-masing memerlukan izin eksplisit.

---

# Files likely to change

**Tahap 1 minimum:**
- `public/js/denah-editor.js`
- `tests/rangka/test_tiang_numerik.mjs`
- satu test jsdom interaksi tiang (nama final mengikuti pola existing di `origin/main`)
- dokumentasi status setelah verifikasi

**Tahap 2/3 kemudian:**
- `public/js/denah-editor.js`
- `tests/rangka/test_bentuk_numerik.mjs`
- test converter/support existing
- mungkin `resources/views/rab-opsi/index.blade.php` hanya jika mount/container perlu, bukan untuk logika geometri

**Tidak boleh berubah pada Tahap 1:**
- database/migrations
- `app/Services/CuttingService.php`
- `app/Services/RangkaDesignService.php`
- `app/Http/Controllers/CuttingController.php`
- formula harga/margin

# Risiko dan mitigasi

1. **State numeric vs drag berbeda:** satu state source-of-truth; panel selalu membaca `S`, bukan menyimpan salinan permanen.
2. **Undo terlalu banyak saat mengetik:** commit pada blur/Enter/Terapkan, bukan per keypress.
3. **Koordinat membingungkan pada bentuk L:** tampilkan titik acuan visual dan preset vertex aktual.
4. **Snapshot lama rusak:** tidak mengubah schema `S.tiang`; helper hanya cara input baru.
5. **Panel membuat layar HP penuh:** gunakan panel ringkas/collapsible dan daftar satu baris per tiang.
6. **Pengguna menganggap sistem menentukan keamanan struktur:** label jelas bahwa posisi ditentukan surveyor; sistem hanya menghitung material.
7. **Scope membesar:** rilis Tahap 1 sendiri; Tahap 2 dan 3 menunggu konfirmasi nyata.

# Open question sebelum implementasi Tahap 2

Untuk bentuk yang sangat miring/asimetris, apakah jalur numerik lanjut memakai koordinat X/Y, rantai sisi+diagonal, atau tetap drag sebagai fallback? Ini tidak menghalangi Tahap 1 posisi tiang.
