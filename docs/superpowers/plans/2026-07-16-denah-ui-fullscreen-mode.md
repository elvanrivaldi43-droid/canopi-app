# DenahEditor — Mode Layar Penuh (Fullscreen Edit) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tombol "Perbesar Layar" membuat blok Denah menutupi seluruh layar (HP/PC) selama proses edit bentuk, ribbon jadi overlay di atas kanvas yang jauh lebih besar. Tombol "Selesai" yang SELALU kelihatan (pojok atas, fixed) buat keluar kapan pun — sekalian otomatis reset zoom ke awal, jadi tak akan pernah "terjebak" di zoom lagi.

**Architecture:** 1 class CSS baru `.de-fullscreen` di-toggle ke `.de-card` (elemen kontainer utama yang sudah ada) via `position:fixed` menutupi seluruh viewport — teknik murni CSS, tak mengubah struktur/urutan elemen di dalamnya (ribbon, kanvas, dst tetap sama, cuma "ditarik" jadi layar penuh). Tombol "Selesai" (`position:fixed`, selalu di pojok atas selama fullscreen aktif) manggil ulang fungsi `resetZoom` yang sudah ada di `_wireZoom()` (diekspos jadi `this._resetZoom` biar bisa dipanggil dari method baru).

**Tech Stack:** JS vanilla, CSS murni (pola sama seperti fitur-fitur sebelumnya di file ini).

## Global Constraints

- **Satu file kode yang berubah:** `public/js/denah-editor.js`.
- Semua `data-role` yang sudah ada TETAP, tak ada yang dipindah/dihapus di plan ini (beda dari iterasi sebelumnya) — plan ini murni ADITIF (2 elemen baru + 1 method baru).
- `_wireControls()`, `_wireRibbon()`, `bindSvg()`, `render()`, `DenahConv` TIDAK disentuh.
- `_wireZoom()` HANYA ditambah 1 baris (ekspos `resetZoom` ke `this._resetZoom`) — logic zoom/pan yang sudah ada tak berubah.
- Emoji dilarang — semua label teks biasa, tanpa simbol/ikon Unicode (konsisten dengan gaya tombol lain di file ini yang cuma pakai teks/`+`/`−`).
- Verifikasi UI dilakukan manual oleh Elvan di HP/PC production (sama seperti sebelumnya, tak bisa diuji headless).

---

### Task 1: Tombol "Perbesar Layar" + "Selesai" (mode layar penuh)

**Files:**
- Modify: `public/js/denah-editor.js` (`shellHTML()` CSS + body HTML, `_wireZoom()`, method baru `_wireFullscreen()`, constructor)

**Interfaces:**
- Consumes: `resetZoom` (fungsi lokal yang sudah ada di `_wireZoom()`, sekarang diekspos `this._resetZoom`).
- Produces: tidak ada API publik baru — murni toggle tampilan.

- [ ] **Step 1: Tambah CSS fullscreen**

Di `public/js/denah-editor.js`, method `static shellHTML()`, cari baris CSS `.de-quickbar`:

```css
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
```

Ganti jadi (tambah 2 baris CSS baru setelahnya):

```css
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.de-card.de-fullscreen{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;overflow-y:auto;border-radius:0;margin:0;box-shadow:none}
.de-fullscreen-exit{display:none;position:fixed;top:12px;right:12px;z-index:9001;padding:10px 18px;border-radius:22px;background:#1e293b;color:#fff;border:1px solid #334155;font-size:14px;font-weight:600;cursor:pointer;align-items:center;justify-content:center}
```

- [ ] **Step 2: Tambah tombol "Perbesar Layar" di quickbar + tombol "Selesai" (tersembunyi default)**

Cari:

```html
  <div class="de-quickbar">
    <span class="de-mini" data-role="btnUndo">Undo</span>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
```

Ganti jadi:

```html
  <div class="de-quickbar">
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnFullscreen">Perbesar Layar</span>
  </div>
  <span class="de-fullscreen-exit" data-role="btnFullscreenExit">Selesai</span>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
```

- [ ] **Step 3: Ekspos `resetZoom` jadi `this._resetZoom`**

Di method `_wireZoom()`, cari:

```js
    const resetZoom = () => {
      const c = canvasEl();
      if (c) { c.style.transition = 'transform .2s ease'; setTimeout(() => { if (c) c.style.transition = ''; }, 220); }
      this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
      applyTransform();
    };
    resetBtn.onclick = resetZoom;
```

Ganti jadi (tambah 1 baris):

```js
    const resetZoom = () => {
      const c = canvasEl();
      if (c) { c.style.transition = 'transform .2s ease'; setTimeout(() => { if (c) c.style.transition = ''; }, 220); }
      this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
      applyTransform();
    };
    this._resetZoom = resetZoom;
    resetBtn.onclick = resetZoom;
```

- [ ] **Step 4: Method baru `_wireFullscreen()`**

Cari method `_wireRibbon() { ... }` (isinya, dari iterasi sebelumnya, berakhir dengan `document.addEventListener('pointerdown', this._docPointerDownRibbon);` lalu `}`). Tambahkan method baru **setelah** `_wireRibbon()` selesai, **sebelum** `_wireZoom() {`:

```js
  _wireFullscreen() {
    const card = this._q('.de-card');
    const enterBtn = this._q('[data-role=btnFullscreen]');
    const exitBtn = this._q('[data-role=btnFullscreenExit]');
    enterBtn.onclick = () => {
      card.classList.add('de-fullscreen');
      exitBtn.style.display = 'flex';
    };
    exitBtn.onclick = () => {
      card.classList.remove('de-fullscreen');
      exitBtn.style.display = 'none';
      if (this._resetZoom) this._resetZoom();
    };
  }

```

- [ ] **Step 5: Panggil `_wireFullscreen()` di constructor**

Di `constructor(el, opts)`, cari:

```js
    this._wireControls();
    this._wireRibbon();
    this._wireZoom();
```

Ganti jadi:

```js
    this._wireControls();
    this._wireRibbon();
    this._wireZoom();
    this._wireFullscreen();
```

(`_wireFullscreen()` dipanggil SETELAH `_wireZoom()` karena butuh `this._resetZoom` yang baru diset di Step 3 — urutan panggilan tak masalah karena `_wireFullscreen()` cuma nyimpen event handler, `this._resetZoom` baru benar-benar dipakai nanti pas tombol "Selesai" diklik, jauh setelah constructor selesai.)

- [ ] **Step 6: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya (murni CSS/HTML/wiring baru, tak menyentuh `DenahConv`).

- [ ] **Step 7: Verifikasi manual (kalau ada display) — kalau tidak, catat sebagai deferred ke checklist production**

Kalau ada cara buka `tests/rangka/denah_editor_harness.html` di browser: ketuk "Perbesar Layar" → kanvas+ribbon menutupi seluruh layar, tombol "Selesai" muncul di pojok atas. Ketuk "Selesai" → balik ke tampilan biasa, zoom balik ke fit-semula. Kalau tak ada display, catat deferred.

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): mode layar penuh (tombol Perbesar Layar + Selesai, reset zoom otomatis saat keluar)"
```

---

### Task 2: Deploy & verifikasi Elvan di production

**Files:** (tidak ada file baru — deploy murni `git push`)

- [ ] **Step 1: Push ke production**

```bash
git push
```

- [ ] **Step 2: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata, di HP DAN PC)**

1. Ketuk "Perbesar Layar" → kanvas+ribbon menutupi seluruh layar (HP: layar penuh; PC: juga penuh browser).
2. Gambar/edit bentuk di mode layar-penuh — cek terasa lebih leluasa dibanding sebelumnya.
3. Zoom in beberapa kali → ketuk "Selesai" kapan pun (tak perlu keluar dari zoom dulu) → balik ke tampilan biasa DAN zoom otomatis balik ke fit-semula (tak pernah "terjebak" lagi).
4. Regresi: semua fungsi yang sudah ada (ribbon, Undo, Tambah Kotak, support manual, ortho-snap, drag) tetap normal di dalam mode layar-penuh maupun di luar.
5. Setelah "Selesai", lanjut isi bagian lain (atap, dll) di halaman RAB Opsi seperti biasa → tak ada yang aneh/rusak.

- [ ] **Step 3: Catat status drag-saat-zoom (masih "belum ikutin jari banget")**

Perbaikan `toCm()` sebelumnya SUDAH membantu tapi belum 100% presisi menurut laporan Elvan terakhir. Ini butuh info lebih spesifik sebelum diperbaiki lagi (supaya tidak tebak-tebakan) — tanyakan ke Elvan: apakah titik yang di-drag KONSISTEN meleset ke arah/jarak tertentu (bug hitungan), atau titiknya terasa "telat nyusul" jari (delay/lag, bukan salah posisi)? Jawaban ini menentukan jenis perbaikan berikutnya.
