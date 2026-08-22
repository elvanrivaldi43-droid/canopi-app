# Denah Ribbon 3 Tab (Rangka/Support/Tiang) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rapikan ribbon DenahEditor jadi 3 tab (Rangka/Support/Tiang, tiap tab = 1 mode), pindahkan Snap grid & Ganti besi ke quickbar atas, besi default nempel ke domain masing-masing.

**Architecture:** Perubahan hampir seluruhnya relokasi markup dalam satu string template (`shellHTML()` di `public/js/denah-editor.js`). Handler sudah nyambung generik — mode via `.de-tool[data-mode]`, input/tombol via `[data-role=...]` — jadi memindah elemen tidak mengubah handler. Satu-satunya perubahan logika: generalisasi blok "tab Support → mode support" jadi peta `{rangka:'bentuk',support:'support',tiang:'tiang'}` di handler klik tab.

**Tech Stack:** Vanilla JS (1 file, no build step), SVG canvas. VPS tanpa browser/DOM.

## Global Constraints

- `public/js/denah-editor.js` tetap **1 file**, tidak dipecah.
- **Emoji DILARANG** di file ini (aturan CLAUDE.md).
- Handler terikat by `data-role`/`data-mode` (generik). Tiap `data-role` yang dipindah harus tetap **ada persis 1×** di markup baru; menghapus elemen HANYA untuk fitur yang memang sengaja dibuang (de-tool `data-mode="bentuk"` dan `data-mode="tiang"`).
- **Tidak ada tes DOM otomatis** (VPS tanpa browser). Verifikasi tiap task: `node --check public/js/denah-editor.js` + `node tests/rangka/test_support_spacing.mjs` + `node tests/rangka/test_support_pola.mjs` (dua-duanya harus tetap `=== SEMUA TES LULUS ===` — ini menjaga file bersama tidak rusak, walau tidak menyentuh perilaku DOM). Perilaku ribbon/mode diverifikasi **checklist manual Elvan** (browser/HP) di akhir, bukan test otomatis.
- Peta tab→mode (JANGAN dibalik): `rangka → 'bentuk'`, `support → 'support'`, `tiang → 'tiang'`.
- Di luar lingkup (JANGAN dikerjakan): pola tekan-tahan=menu untuk sudut/sisi/tiang, kerapian label kanvas, hapus mode "Ganti besi" sepenuhnya.

---

### Task 1: Pindahkan Snap grid & "Ganti besi" ke quickbar atas

**Files:**
- Modify: `public/js/denah-editor.js` (CSS `.de-quickbar` ~baris 322; markup quickbar ~baris 424-428; hapus Snap grid dari panel ukuran ~baris 371; hapus de-tool Ganti besi dari panel mode ~baris 412)

**Interfaces:**
- Consumes: handler existing `inGrid.onchange` (~baris 506) & handler generik `.de-tool` (~baris 472-483) — keduanya tidak diubah, cuma elemennya pindah.
- Produces: quickbar berisi Snap grid + Ganti besi; app tetap berfungsi penuh (Ukuran tab kehilangan Snap, Mode tab kehilangan Ganti besi).

- [ ] **Step 1: Beri `align-items:center` pada `.de-quickbar` (biar dropdown & tombol sejajar)**

Cari:
```css
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
```
Ganti jadi:
```css
.de-quickbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;align-items:center}
```

- [ ] **Step 2: Hapus Snap grid dari panel "ukuran"**

Cari (di panel `data-panel="ukuran"`):
```html
        <label>Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
```
Hapus baris itu (jangan sisakan baris kosong bermasalah — hapus seluruh baris).

- [ ] **Step 3: Hapus de-tool "Ganti besi" dari panel "mode"**

Cari (di panel `data-panel="mode"`):
```html
        <span class="de-tool" data-mode="besi">Ganti besi</span>
```
Hapus baris itu.

- [ ] **Step 4: Tambahkan Snap grid + Ganti besi ke quickbar**

Cari:
```html
  <div class="de-quickbar">
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnRedo">Redo</span>
    <span class="de-mini" data-role="btnFullscreen">Perbesar Layar</span>
  </div>
```
Ganti jadi:
```html
  <div class="de-quickbar">
    <label style="font-size:12px;display:flex;flex-direction:column;gap:3px">Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
    <span class="de-tool" data-mode="besi">Ganti besi</span>
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnRedo">Redo</span>
    <span class="de-mini" data-role="btnFullscreen">Perbesar Layar</span>
  </div>
```

- [ ] **Step 5: Verifikasi syntax + regresi**

Run: `node --check public/js/denah-editor.js && node tests/rangka/test_support_spacing.mjs && node tests/rangka/test_support_pola.mjs`
Expected: syntax bersih (tanpa output dari `node --check`), kedua test `=== SEMUA TES LULUS ===`.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): pindah Snap grid & Ganti besi ke quickbar atas"
```

---

### Task 2: Ribbon 3 tab (Rangka/Support/Tiang) + peta tab→mode

**Files:**
- Modify: `public/js/denah-editor.js` (ganti seluruh blok `<div class="de-ribbon">…</div>` ~baris 356-423; ganti blok tab→mode di `_wireRibbon` ~baris 600-609)

**Interfaces:**
- Consumes: hasil Task 1 (Snap grid & Ganti besi sudah di quickbar, tidak lagi di panel ribbon).
- Produces: tab bar 3 tab; tiap tab buka = aktif mode-nya; dropdown besi frame/support/tiang tersebar ke domain; tab Ukuran/Besi/Mode/Ukur Sisi hilang.

**Catatan penting:** Ini penggantian markup besar. `data-role` berikut WAJIB tetap ada persis 1× di hasil: `inL`, `inP`, `inT`, `btnReset`, `btnAddV`, `btnDelV`, `btnAddBox`, `sisiPanel`, `matFrame`, `matSupport`, `matTiang`, `inArah`, `inIdeal`, `btnSaran`, `saranHint`, `modeH`, `modeV`, `inKotakH`, `inKotakV`, `inKolomH`, `inKolomV`, `lblKotakH`, `lblKolomH`, `lblKotakV`, `lblKolomV`, `hintH`, `hintV`, `rowSupH`, `rowSupV`, `btnAddSupport`, `btnFullscreenExit`, `ribbonStrip`. Yang DIHAPUS: de-tool `data-mode="bentuk"` & `data-mode="tiang"`, tab `ukuran`/`besi`/`mode`/`sisi`, panel `ukuran`/`besi`/`mode`/`sisi`.

- [ ] **Step 1: Ganti seluruh blok ribbon**

Ganti seluruh isi dari `<div class="de-ribbon">` sampai penutup `</div>`-nya (blok yang berakhir TEPAT sebelum `<div class="de-quickbar">`) jadi:

```html
  <div class="de-ribbon">
  <div class="de-ribbon-tabs">
    <span class="de-ribbon-tab" data-tab="rangka">Rangka</span>
    <span class="de-ribbon-tab" data-tab="support">Support</span>
    <span class="de-ribbon-tab" data-tab="tiang">Tiang</span>
    <span class="de-fullscreen-exit" data-role="btnFullscreenExit">Selesai</span>
  </div>
  <div class="de-ribbon-strip" data-role="ribbonStrip">
    <div class="de-ribbon-panel" data-panel="rangka">
      <div class="de-row">
        <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
        <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
        <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
        <label>Besi frame<select data-role="matFrame"></select></label>
        <span class="de-mini" data-role="btnReset">Reset kotak dari Lebar×Panjang</span>
      </div>
      <div class="de-row" style="margin-top:8px">
        <span class="de-mini" data-role="btnAddV">+ Sudut</span>
        <span class="de-mini" data-role="btnDelV">− Sudut</span>
        <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
      </div>
      <div class="de-legend" data-role="sisiPanel" style="margin-top:8px"></div>
    </div>
    <div class="de-ribbon-panel" data-panel="support">
      <div class="de-row">
        <label>Arah support
          <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
        </label>
        <label>Ideal per kotak (cm)<input type="number" data-role="inIdeal" value="100" step="5" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
      <div class="de-row de-sup-axis" data-role="rowSupH" style="margin-top:10px">
        <span class="de-sup-axname">Horizontal</span>
        <label>Mode<select data-role="modeH"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakH">Kotak (cm)<input type="number" data-role="inKotakH" step="5" min="1"></label>
        <label data-role="lblKolomH" style="display:none">Jumlah kolom<input type="number" data-role="inKolomH" step="1" min="1" max="200"></label>
        <span class="de-sup-cm" data-role="hintH"></span>
      </div>
      <div class="de-row de-sup-axis" data-role="rowSupV" style="margin-top:8px">
        <span class="de-sup-axname">Vertikal</span>
        <label>Mode<select data-role="modeV"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakV">Kotak (cm)<input type="number" data-role="inKotakV" step="5" min="1"></label>
        <label data-role="lblKolomV" style="display:none">Jumlah kolom<input type="number" data-role="inKolomV" step="1" min="1" max="200"></label>
        <span class="de-sup-cm" data-role="hintV"></span>
      </div>
      <div class="de-row" style="margin-top:8px">
        <label>Besi support<select data-role="matSupport"></select></label>
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
    <div class="de-ribbon-panel" data-panel="tiang">
      <div class="de-row">
        <label>Besi tiang<select data-role="matTiang"></select></label>
      </div>
    </div>
  </div>
  </div>
```

- [ ] **Step 2: Generalisasi blok tab→mode**

Cari (di dalam `_wireRibbon`, handler `tabs.forEach(t => t.onclick = ...)`):
```js
      if (name === 'support' && this.mode !== 'support') {
        this._qa('.de-tool').forEach(el2 => el2.classList.remove('on'));
        this.mode = 'support';
        this.armed = null; this.addSupportPt = null; this.boxPreview = null;
        this.setHint();
        this.render();
      }
```
Ganti jadi:
```js
      // Tiap tab = 1 mode (Ribbon 3 Tab, 22 Ags). Buka tab -> aktifkan mode-nya.
      const tabMode = { rangka: 'bentuk', support: 'support', tiang: 'tiang' }[name];
      if (tabMode && this.mode !== tabMode) {
        this._qa('.de-tool').forEach(el2 => el2.classList.remove('on'));
        this.mode = tabMode;
        this.armed = null; this.addSupportPt = null; this.boxPreview = null;
        this.setHint();
        this.render();
        if (tabMode === 'tiang') requestAnimationFrame(() => {
          const panel = this._q('[data-role=tiangPanel]');
          if (panel) panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
      }
```

- [ ] **Step 3: Verifikasi syntax + regresi**

Run: `node --check public/js/denah-editor.js && node tests/rangka/test_support_spacing.mjs && node tests/rangka/test_support_pola.mjs`
Expected: syntax bersih, kedua test `=== SEMUA TES LULUS ===`.

- [ ] **Step 4: Verifikasi manual data-role (grep, biar tak ada yang hilang/dobel)**

Run:
```bash
for r in inL inP inT btnReset btnAddV btnDelV btnAddBox sisiPanel matFrame matSupport matTiang inArah btnAddSupport inGrid; do printf "%s: " "$r"; grep -o "data-role=\"$r\"" public/js/denah-editor.js | wc -l; done
```
Expected: tiap `data-role` bernilai **1**. (`inGrid` juga 1 — sudah di quickbar dari Task 1.)
Run juga: `grep -c 'data-mode="bentuk"\|data-mode="tiang"' public/js/denah-editor.js` → Expected: **0** (de-tool bentuk & tiang sudah dihapus; de-tool `data-mode="besi"` di quickbar tidak kena pola ini).

- [ ] **Step 5: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): ribbon 3 tab (Rangka/Support/Tiang), besi nempel ke domain, tab=mode"
```

---

## Verifikasi akhir (checklist manual Elvan — WAJIB, tak bisa diotomasi dari VPS)

- **A.** Tab tinggal 3: **Rangka, Support, Tiang**. Buka Rangka → sudut rangka langsung bisa digeser (mode Bentuk aktif).
- **B.** Tab Rangka lengkap & jalan: Lebar/Panjang/Tinggi tiang, +Sudut/−Sudut/+Tambah Kotak, Reset, dropdown **Besi frame**, daftar panjang sisi (F1, F2, …).
- **C.** Buka **Tiang** → bisa taruh/kelola tiang (mode tiang aktif, panel kelola tiang muncul), dropdown **Besi tiang** ada. Buka **Support** → mode support, dropdown **Besi support** ada + semua setelan support (spacing per-sumbu) normal.
- **D.** **Bar atas:** Snap grid (ganti nilai → snap saat geser ikut), **Ganti besi** (ketuk → masuk mode, tap 1 batang rangka/tiang → ganti besi batang itu, atau balik ke default), Undo/Redo/Perbesar Layar jalan.
- **E.** Tab "Ukuran", "Ukur Sisi", "Besi", "Mode" sudah tidak ada; tak ada kontrol lama yang hilang/dobel; besi **default** (dropdown) vs **per-batang** (Ganti besi) dua-duanya berfungsi.
