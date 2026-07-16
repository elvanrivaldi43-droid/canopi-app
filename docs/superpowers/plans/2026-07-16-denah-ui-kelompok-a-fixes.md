# DenahEditor Kelompok A — Perbaikan dari Tes Nyata Elvan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 4 masalah nyata yang dilaporkan Elvan setelah tes Kelompok A di HP production: (1) ribbon "dorong ke bawah" mengubur kanvas → jadi panel melayang (overlay) yang lebih kecil, (2) tombol Undo dipindah keluar ribbon (selalu kelihatan), (3) drag titik/besi saat zoom-in tidak ikut jari, (4) double-tap-reset tidak jalan + ortho-snap support kurang toleran.

**Architecture:** Semua di **satu file** `public/js/denah-editor.js` (sama seperti Kelompok A sebelumnya). Ribbon strip pindah dari layout normal (push-down) ke `position:absolute` (overlay di atas kanvas, bukan mendorongnya) — dibungkus kontainer relatif baru `.de-ribbon`. Undo dipindah ke `<div class="de-quickbar">` baru di luar ribbon, `data-role="btnUndo"` TIDAK berubah jadi `_wireControls()` tidak perlu disentuh. `toCm()` diganti dari `getScreenCTM()` (bergantung browser mengurai CSS transform leluhur dgn benar — diduga tak konsisten di device Elvan) ke pengukuran langsung `getBoundingClientRect()` pada svg (mengukur ukuran/posisi svg APA ADANYA di layar, sudah termasuk semua transform apa pun sumbernya — lebih robust, tak bergantung asumsi browser). Threshold double-tap & ortho-snap support diperlonggar (angka saja, tak ada perubahan struktur).

**Tech Stack:** JS vanilla (pola sama, classic-script IIFE). Tes regresi 3 file Node yang sudah ada. UI/interaksi tetap diverifikasi manual oleh Elvan di HP production (sama seperti sebelumnya, tak bisa diuji headless).

## Global Constraints

- **Satu file kode yang berubah:** `public/js/denah-editor.js`. Blade tidak disentuh (cache-bust otomatis).
- **Semua `data-role` yang sudah ada TETAP** — termasuk `btnUndo` (cuma pindah lokasi HTML, `_wireControls()` tak berubah).
- **`_wireControls()` TIDAK diubah sama sekali** di task manapun di plan ini.
- **`S.verts`/model data, `DenahConv`, `bindSvg()` (isi logic drag vertex/support/box) TIDAK disentuh** — perbaikan ini murni layout/CSS + 1 method (`toCm`) yang mengubah CARA HITUNG koordinat, bukan logic drag itu sendiri.
- **Emoji dilarang.**
- Verifikasi UI dilakukan manual oleh Elvan di HP production setelah deploy (sama seperti sebelumnya).

---

### Task 1: Ribbon jadi panel melayang (overlay) + Undo selalu kelihatan di luar ribbon

**Files:**
- Modify: `public/js/denah-editor.js` (`shellHTML()` CSS + body HTML)

**Interfaces:** Tidak ada — murni CSS positioning + relokasi 1 elemen HTML (`btnUndo`), semua `data-role`/wiring lain tak berubah.

- [ ] **Step 1: Ganti CSS ribbon jadi overlay + tambah `.de-quickbar`**

Di `public/js/denah-editor.js`, method `static shellHTML()`, cari blok CSS ribbon ini:

```css
.de-ribbon-tabs{display:flex;border:1px solid #334155;border-radius:8px 8px 0 0;overflow:hidden;background:#1e293b}
.de-ribbon-tab{flex:1;text-align:center;padding:11px 4px;min-height:40px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;color:#cbd5e1;cursor:pointer;user-select:none;border-right:1px solid #334155}
.de-ribbon-tab:last-child{border-right:none}
.de-ribbon-tab.on{background:#0f2740;color:#38bdf8;font-weight:600}
.de-ribbon-strip{border:1px solid transparent;border-top:none;border-radius:0 0 8px 8px;background:#f8fafc;padding:0;margin-bottom:10px;max-height:0;overflow:hidden;transition:max-height .15s ease}
.de-ribbon-strip.open{border-color:#334155;padding:10px 12px;max-height:220px;overflow-y:auto}
.de-ribbon-panel{display:none}
.de-ribbon-panel.on{display:block}
```

Ganti jadi (tabs+strip dibungkus `.de-ribbon` posisi relatif, strip jadi `position:absolute` — melayang DI ATAS kanvas, bukan mendorongnya; tinggi maksimal pakai `vh` biar tak pernah kelewat besar di layar HP manapun; tambah `.de-quickbar` buat Undo):

```css
.de-ribbon{position:relative;margin-bottom:10px}
.de-ribbon-tabs{display:flex;border:1px solid #334155;border-radius:8px;overflow:hidden;background:#1e293b}
.de-ribbon-tab{flex:1;text-align:center;padding:11px 4px;min-height:40px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;color:#cbd5e1;cursor:pointer;user-select:none;border-right:1px solid #334155}
.de-ribbon-tab:last-child{border-right:none}
.de-ribbon-tab.on{background:#0f2740;color:#38bdf8;font-weight:600}
.de-ribbon-strip{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:20;border:1px solid transparent;border-radius:8px;background:#f8fafc;padding:0;max-height:0;overflow:hidden;transition:max-height .15s ease;box-shadow:0 6px 18px rgba(0,0,0,.28)}
.de-ribbon-strip.open{border-color:#334155;padding:10px 12px;max-height:45vh;overflow-y:auto}
.de-ribbon-panel{display:none}
.de-ribbon-panel.on{display:block}
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
```

- [ ] **Step 2: Bungkus tabs+strip dalam `.de-ribbon`, pindah Undo ke `.de-quickbar`**

Di method yang sama, cari:

```html
<div class="de-card">
  <div class="de-ribbon-tabs">
    <span class="de-ribbon-tab" data-tab="ukuran">Ukuran</span>
    <span class="de-ribbon-tab" data-tab="support">Support</span>
    <span class="de-ribbon-tab" data-tab="besi">Besi</span>
    <span class="de-ribbon-tab" data-tab="mode">Mode</span>
    <span class="de-ribbon-tab" data-tab="sisi">Ukur Sisi</span>
  </div>
  <div class="de-ribbon-strip" data-role="ribbonStrip">
```

Ganti jadi (tambah pembuka `<div class="de-ribbon">`):

```html
<div class="de-card">
  <div class="de-ribbon">
  <div class="de-ribbon-tabs">
    <span class="de-ribbon-tab" data-tab="ukuran">Ukuran</span>
    <span class="de-ribbon-tab" data-tab="support">Support</span>
    <span class="de-ribbon-tab" data-tab="besi">Besi</span>
    <span class="de-ribbon-tab" data-tab="mode">Mode</span>
    <span class="de-ribbon-tab" data-tab="sisi">Ukur Sisi</span>
  </div>
  <div class="de-ribbon-strip" data-role="ribbonStrip">
```

Lalu cari penutup panel "mode" dan panel "sisi" + baris `boxPanel` setelahnya:

```html
    <div class="de-ribbon-panel" data-panel="mode">
      <div class="de-tools">
        <span class="de-tool on" data-mode="bentuk">Bentuk</span>
        <span class="de-tool" data-mode="besi">Ganti besi</span>
        <span class="de-tool" data-mode="support">Support</span>
        <span class="de-tool" data-mode="tiang">Tiang</span>
        <span class="de-mini" data-role="btnAddV">+ Sudut</span>
        <span class="de-mini" data-role="btnDelV">− Sudut</span>
        <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
        <span class="de-mini" data-role="btnUndo">Undo</span>
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="sisi">
      <div class="de-legend" data-role="sisiPanel"></div>
    </div>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
```

Ganti jadi (hapus `btnUndo` dari panel "mode"; tutup `.de-ribbon`; tambah `.de-quickbar` berisi Undo, SETELAH `.de-ribbon` ditutup, SEBELUM `boxPanel`):

```html
    <div class="de-ribbon-panel" data-panel="mode">
      <div class="de-tools">
        <span class="de-tool on" data-mode="bentuk">Bentuk</span>
        <span class="de-tool" data-mode="besi">Ganti besi</span>
        <span class="de-tool" data-mode="support">Support</span>
        <span class="de-tool" data-mode="tiang">Tiang</span>
        <span class="de-mini" data-role="btnAddV">+ Sudut</span>
        <span class="de-mini" data-role="btnDelV">− Sudut</span>
        <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="sisi">
      <div class="de-legend" data-role="sisiPanel"></div>
    </div>
  </div>
  </div>
  <div class="de-quickbar">
    <span class="de-mini" data-role="btnUndo">Undo</span>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
```

(Perhatikan: sekarang ada 2 `</div>` berurutan sebelum `.de-quickbar` — satu penutup `.de-ribbon-strip` [sudah ada sebelumnya], satu lagi penutup `.de-ribbon` yang baru dibuka di Step 2 bagian pertama.)

- [ ] **Step 3: Tutup panel ribbon otomatis kalau ketuk di luar (termasuk ketuk kanvas)**

Di `_wireRibbon()`, cari:

```js
  _wireRibbon() {
    const tabs = this._qa('.de-ribbon-tab');
    const strip = this._q('[data-role=ribbonStrip]');
    const panels = {};
    this._qa('.de-ribbon-panel').forEach(p => { panels[p.dataset.panel] = p; });
    let openTab = null;
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) {
        strip.classList.remove('open');
        panels[name].classList.remove('on');
        t.classList.remove('on');
        openTab = null;
        return;
      }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
    });
  }
```

Ganti jadi (tambah fungsi `closeRibbon` dipakai ulang + listener dokumen buat nutup pas ketuk di luar `.de-ribbon`, termasuk kanvas):

```js
  _wireRibbon() {
    const tabs = this._qa('.de-ribbon-tab');
    const strip = this._q('[data-role=ribbonStrip]');
    const panels = {};
    this._qa('.de-ribbon-panel').forEach(p => { panels[p.dataset.panel] = p; });
    let openTab = null;
    const closeRibbon = () => {
      if (!openTab) return;
      strip.classList.remove('open');
      panels[openTab].classList.remove('on');
      tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); });
      openTab = null;
    };
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) { closeRibbon(); return; }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
    });
    this._docPointerDownRibbon = (e) => {
      const ribbon = this._q('.de-ribbon');
      if (openTab && ribbon && !ribbon.contains(e.target)) closeRibbon();
    };
    document.addEventListener('pointerdown', this._docPointerDownRibbon);
  }
```

- [ ] **Step 4: Lepas listener dokumen baru saat instance dibuang**

Di method `destroy()`, cari:

```js
  destroy() {
    if (this._docPointerDown) document.removeEventListener('pointerdown', this._docPointerDown);
  }
```

Ganti jadi:

```js
  destroy() {
    if (this._docPointerDown) document.removeEventListener('pointerdown', this._docPointerDown);
    if (this._docPointerDownRibbon) document.removeEventListener('pointerdown', this._docPointerDownRibbon);
  }
```

- [ ] **Step 5: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya (murni CSS/HTML restrukturisasi + 1 listener dokumen baru, tak menyentuh `DenahConv`).

- [ ] **Step 6: Verifikasi manual di harness (kalau tersedia) — kalau tidak, catat sebagai deferred**

Kalau `tests/rangka/denah_editor_harness.html` masih ada (dibuat Kelompok A Task 2), jalankan `php -S 0.0.0.0:8892 -t /root/projects/canopi-app` lalu buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`, cek: ketuk tab manapun → panel muncul MELAYANG di atas kanvas (kanvas TIDAK ikut turun), ketuk kanvas atau area luar ribbon → panel otomatis tutup, tombol Undo kelihatan TANPA perlu buka tab Mode. Kalau tak ada display/tak bisa diuji (headless), catat sebagai deferred ke checklist production (Task 4).

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "fix(denah): ribbon jadi panel melayang (overlay, bukan dorong ke bawah) + Undo selalu kelihatan di luar ribbon"
```

---

### Task 2: `toCm()` — perbaiki drag titik/besi saat zoom-in tidak ikut jari

**Files:**
- Modify: `public/js/denah-editor.js` (`toCm()`)

**Interfaces:**
- Consumes: dipakai `bindSvg()` (tak berubah) buat semua drag (vertex/support/box) dan tap-untuk-tempatkan (tiang, support manual).
- Produces: signature `toCm(evt, el)` TETAP SAMA (`{x, y}` dalam cm) — cuma cara hitungnya yang berubah, semua pemanggil tak perlu diubah.

- [ ] **Step 1: Ganti isi `toCm()`**

Di `public/js/denah-editor.js`, cari:

```js
  // screen→cm
  toCm(evt, el) {
    const pt = el.createSVGPoint(); pt.x = evt.clientX; pt.y = evt.clientY;
    const m = el.getScreenCTM().inverse();
    const p = pt.matrixTransform(m);
    return { x: (p.x - this.PAD) / this.SC, y: (p.y - this.PAD) / this.SC };
  }
```

Ganti jadi (ukur langsung kotak svg APA ADANYA di layar via `getBoundingClientRect()` — sudah otomatis termasuk SEMUA sumber skala: zoom pinch kita, `max-width:100%` browser, dll — tak bergantung `getScreenCTM()` mengurai CSS transform leluhur dengan benar, yang diduga tak konsisten di sebagian browser HP):

```js
  // screen→cm. Ukur kotak svg langsung di layar (getBoundingClientRect, sudah termasuk
  // SEMUA skala yang berlaku: pinch-zoom kita + max-width:100% browser), bukan getScreenCTM
  // (bergantung browser mengurai CSS transform leluhur dgn benar — tak konsisten di sebagian
  // browser HP, bikin drag meleset dari jari saat pinch-zoom aktif).
  toCm(evt, el) {
    const rect = el.getBoundingClientRect();
    const svgW = el.width.baseVal.value, svgH = el.height.baseVal.value;
    const scaleX = rect.width / svgW, scaleY = rect.height / svgH;
    const localX = (evt.clientX - rect.left) / scaleX;
    const localY = (evt.clientY - rect.top) / scaleY;
    return { x: (localX - this.PAD) / this.SC, y: (localY - this.PAD) / this.SC };
  }
```

- [ ] **Step 2: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` (perubahan murni cara hitung koordinat layar→cm, tak menyentuh `DenahConv`/model).

- [ ] **Step 3: Catatan verifikasi manual (WAJIB di production, Task 4)**

Ini perubahan paling kritis di plan ini dan TIDAK BISA diverifikasi otomatis (Pointer Events + rendering CSS transform butuh device sentuh nyata). Checklist production Task 4 WAJIB mencakup: pinch-zoom in, lalu drag titik sudut/besi — harus ikut jari persis, tanpa meleset, di BERBAGAI level zoom (1.5x, 2x, 4x).

- [ ] **Step 4: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "fix(denah): toCm() pakai getBoundingClientRect (bukan getScreenCTM) — perbaiki drag meleset saat zoom-in"
```

---

### Task 3: Perlonggar double-tap-reset + ortho-snap support manual

**Files:**
- Modify: `public/js/denah-editor.js` (`_wireZoom()`, `bindSvg()`)

**Interfaces:** Tidak ada — murni ubah 3 angka konstanta, logic/alur tak berubah.

- [ ] **Step 1: Perlonggar threshold double-tap-reset**

Di `_wireZoom()`, cari:

```js
        if (isEmpty && now - lastTapTime < 350 && Math.hypot(e.clientX - lastTapX, e.clientY - lastTapY) < 24) {
```

Ganti jadi (jendela waktu 350ms→450ms, toleransi jarak 24px→40px — mentolerir variasi jari nyata di HP):

```js
        if (isEmpty && now - lastTapTime < 450 && Math.hypot(e.clientX - lastTapX, e.clientY - lastTapY) < 40) {
```

- [ ] **Step 2: Perlonggar threshold ortho-snap support manual**

Di `bindSvg()`, cari blok `drag.type === 'sup'` (dari perbaikan Kelompok A Task 1/4 sebelumnya):

```js
        } else if (drag.type === 'sup') {
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 0.8;
```

Ganti jadi (threshold grid×0.8 → grid×1.2, LEBIH LONGGAR — tak mengubah threshold ortho-snap SUDUT poligon yang lama/terpisah, cuma yang buat support manual):

```js
        } else if (drag.type === 'sup') {
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 1.2;
```

- [ ] **Step 3: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS`. **PERHATIAN:** `test_ortho_snap.mjs` menguji `DenahConv._orthoSnapToPoint` langsung dengan `TH=16` di-hardcode di dalam file tes itu sendiri (bukan baca `this.S.grid`) — tes ini TETAP LULUS tanpa perubahan karena perubahan Step 2 di atas cuma mengubah NILAI `TH` yang dikirim dari `bindSvg()` ke fungsi murni itu, bukan fungsi murninya sendiri. Tak perlu ubah file tes.

- [ ] **Step 4: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "fix(denah): perlonggar threshold double-tap-reset dan ortho-snap support manual"
```

---

### Task 4: Deploy & verifikasi Elvan di production

**Files:** (tidak ada file baru — deploy murni `git push`)

- [ ] **Step 1: Push ke production**

```bash
git push
```

- [ ] **Step 2: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata, di HP)**

1. Ketuk tab ribbon manapun → panel muncul MELAYANG di atas kanvas (kanvas tidak ke-dorong turun, tetap kelihatan penuh).
2. Ketuk kanvas atau area luar ribbon saat panel terbuka → panel otomatis tutup.
3. Tombol Undo kelihatan TANPA buka tab Mode dulu, dan bisa dipencet berkali-kali (bukan cuma 2-3x).
4. **Paling kritis:** pinch-zoom in, lalu drag titik sudut/besi di beberapa level zoom → harus ikut jari persis.
5. Double-tap area kosong kanvas (saat zoom-in) → balik ke fit-semula. Tombol Reset juga tetap jalan.
6. Bikin support manual (masuk mode Support dari tab Mode, ketuk "+ Support manual") → sekarang lebih gampang dijangkau (panel melayang, kanvas tak ketutup). Drag salah satu ujung mendekati ujung satunya → ortho-snap lebih gampang kena (threshold diperlonggar).
7. "+ Tambah Kotak" → sekarang kanvas tetap kelihatan penuh setelah ketuk tombol (panel Mode melayang, tak ketutup), coba ketuk sisi lurus di kanvas → preview kotak + panel span/menjorok/Terapkan muncul.
8. Regresi: Ukuran/Support/Besi/Ukur Sisi tab, Hitung Harga, autosave→reload → tetap normal.
9. (Opsional, di luar scope kalau ternyata bukan gara-gara ribbon) Cek tombol "+ Opsi" dan "Lanjut/Finalisasi" di halaman RAB Opsi — laporkan kalau masih susah dipencet setelah perbaikan ini, biar bisa ditelusuri terpisah.

- [ ] **Step 3: Update `MEMORI_PROYEK.md`**

Catat: Kelompok A sempat 1 iterasi perbaikan setelah tes nyata Elvan di production (ribbon push-down → overlay, Undo di luar ribbon, toCm() lebih robust, threshold diperlonggar). Kalau semua checklist di atas lolos, tandai #10 Kelompok A benar-benar selesai, lanjut ke Kelompok B.

---

## Ringkasan cakupan feedback vs task

| Feedback Elvan | Task |
|---|---|
| Ribbon terlalu besar, kanvas tak kelihatan saat tab dibuka | 1 |
| "+ Tambah Kotak" tak berfungsi (tak ada input/Terapkan kelihatan) | 1 (akar: kanvas ketutup ribbon push-down) |
| Undo harus di luar ribbon | 1 |
| Undo cuma bisa 2-3x | 1 (dugaan akar: sama, kesulitan reach tombol berulang) |
| Kanvas "tenggelam" saat buka/tutup/pindah tab, mau melayang & tak terlalu besar | 1 |
| Support manual sulit masuk mode | 1 (akar: tombol kebur ribbon push-down) |
| Zoom out susah, tersesat saat zoom in | 1 (Reset lebih gampang dijangkau setelah kanvas tak ketutup) + 3 (double-tap diperlonggar) |
| Drag titik/besi saat zoom-in tak ikut jari | 2 |
| Double-tap kosong tak balik fit | 3 |
| Ortho-snap support susah dipakai | 3 |
| Ukuran titik/garis/label (poin 7) | Sudah benar, tak ada task |
| Tombol "+Opsi"/"Lanjut Finalisasi" susah dipencet | Di luar scope (halaman, bukan komponen Denah) — dicatat di checklist Task 4 buat konfirmasi ulang |
