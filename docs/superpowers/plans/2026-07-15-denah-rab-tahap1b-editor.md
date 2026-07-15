# Denah Interaktif di RAB Opsi — Tahap 1B (DenahEditor + Integrasi UI) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah **tipe blok baru `denah`** di halaman RAB Opsi (`/rab-opsi`) — kartu blok berisi editor denah interaktif per blok yang memancarkan `members[]` + `luas_m2` ke jalur backend `tipe:'denah'` (sudah dibangun di Tahap 1A), dengan biaya tampil real-time & tersimpan di snapshot.

**Architecture:** Port editor dari prototype yang SUDAH disetujui (`tests/rangka/denah_prototype.html`) menjadi modul reusable `public/js/denah-editor.js`, dipecah dua lapis: (1) **`DenahConv`** — geometri MURNI (denah model → members + luas, scanline support), bisa dites di Node; (2) **`DenahEditor`** — kelas UI (SVG render + Pointer Events + tools), satu instance per kartu blok, state disimpan di instance (bukan global). Blok `denah` ditambah **berdampingan** dengan `kanopi`/`manual` (tombol `+ Blok Denah`) — jalur kotak lama TIDAK diubah (migrasi aman §8). Backend, persistensi (`rab_snapshot` JSON), dan render rincian besi (`rincian[].jumlah_batang/harga_pokok/subtotal_besi`) sudah kompatibel — tak perlu diubah.

**Tech Stack:** Laravel 13 / PHP 8.3, JS vanilla (tanpa framework/bundler — pola app: `<script>` inline + `@json`). Aset statis dari `public/`. Tes geometri = Node (`node tests/rangka/test_konverter.mjs`) memuat modul UMD yang sama. Tak ada Composer/DB untuk tes.

## Global Constraints

- **JANGAN ubah jalur `kanopi`/`manual`** di blade maupun `hitungSatuBlok`. `denah` murni aditif. (verifikasi: blok kanopi/manual lama tetap terbaca `bacaBlok` & terhitung.)
- **Emoji dilarang di blade** (korup di server Niagahoster) — pakai teks/SVG. (CLAUDE.md)
- **Kontrak payload blok denah** ke `/rab-blok/hitung` (sudah diterima backend 1A, `CuttingController.php:408-433`): `{ tipe:'denah', aktif, nama, luas_m2:float, members:[{nama,jenis,panjang,material}], harga:{<material>:harga_pokok}, besi_extra?, jenis_kerja_id?, kondisi_ids?, atap_jenis_id?/atap_luas?/atap_pasang?, addon_id?/addon_qty? }`.
- **Kontrak members** ke `RangkaDesignService::hitung`: tiap `{nama:string, jenis:'frame'|'support'|'tiang', panjang:float, material:string}`. `material = matOverride[id] ?? matDefault[jenis]`.
- **Model denah (state)** yang di-persist per blok (di `rab_snapshot` via `bacaBlok`): `{ verts:[{x,y}], grid, target, kotak, autoKotak, arah:'2'|'h'|'v', supportsManual:[{a,b}], removed:{}, tiang:[{x,y}], tinggi, matDefault:{frame,support,tiang}, matOverride:{} }`.
- **Sumber besi** untuk dropdown: variabel JS `BESI` (`index.blade.php:201`) & `hargaOf(nama)` (`:211`) yang sudah ada — jangan buat lookup baru.
- **Reuse prototype**: geometri & render diambil dari `tests/rangka/denah_prototype.html` (proven, lulus self-check). Perubahan HANYA: global `S` → `this` per instance, `BESI` dari app, dan hook recompute app.

---

### Task 1: Modul geometri murni `DenahConv` + tes Node

**Files:**
- Create: `public/js/denah-editor.js` (bagian `DenahConv` dulu; `DenahEditor` ditambah Task 2)
- Test: `tests/rangka/test_konverter.mjs` (create)

**Interfaces:**
- Produces (dipakai Task 2 & controller lewat members):
  - `DenahConv.buildMembers(model) -> [{id,nama,jenis,panjang,material,geom}]`
  - `DenahConv.luasM2(model) -> float` (shoelace/10000)
  - `DenahConv.saranKotak(lebar, target) -> int`
- Modul UMD: `if (typeof module!=='undefined') module.exports = {DenahConv}` **dan** `if (typeof window!=='undefined') window.DenahConv = DenahConv` — satu sumber, bisa di-`require` Node & `<script>` browser.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_konverter.mjs`:

```js
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { DenahConv } = require('../../public/js/denah-editor.js');

let fail = false;
const check = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name} (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`);
  if (!ok) fail = true;
};

// Kotak 400x400, support horizontal, kotak 100 -> 4 frame @400 + 3 support @400 (y=100,200,300)
const kotak = {
  verts: [{x:0,y:0},{x:400,y:0},{x:400,y:400},{x:0,y:400}],
  grid:20, target:100, kotak:100, autoKotak:true, arah:'h',
  supportsManual:[], removed:{}, tiang:[], tinggi:300,
  matDefault:{frame:'F',support:'S',tiang:'T'}, matOverride:{},
};
const m = DenahConv.buildMembers(kotak);
check('kotak: 4 frame', m.filter(x=>x.jenis==='frame').length, 4);
check('kotak: frame semua 400', m.filter(x=>x.jenis==='frame').every(x=>x.panjang===400), true);
check('kotak: 3 support', m.filter(x=>x.jenis==='support').length, 3);
check('kotak: support semua 400', m.filter(x=>x.jenis==='support').every(x=>x.panjang===400), true);
check('kotak: luas 16 m2', DenahConv.luasM2(kotak), 16);

// L-shape cekung: support tak bocor keluar poligon (segmen <= lebar 400)
const L = { ...kotak, verts:[{x:0,y:0},{x:400,y:0},{x:400,y:200},{x:200,y:200},{x:200,y:400},{x:0,y:400}] };
const seg = DenahConv.buildMembers(L).filter(x=>x.jenis==='support');
check('L: support tak melebihi 400', seg.every(s=>s.panjang<=400), true);

// saranKotak: lebar 700, target 100 -> 700/round(7)=100
check('saranKotak(700,100)=100', DenahConv.saranKotak(700,100), 100);
check('saranKotak(530,100)=106', DenahConv.saranKotak(530,100), 106); // round(5.3)=5 -> 106

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_konverter.mjs`
Expected: error "Cannot find module ... denah-editor.js" (file belum ada) → FAIL.

- [ ] **Step 3: Buat `public/js/denah-editor.js` (bagian DenahConv)**

Port fungsi geometri dari `tests/rangka/denah_prototype.html` (fungsi `dist`, `bbox`, `shoelace`, `scanX`, `scanY`, `buildMembers`) menjadi objek murni `DenahConv` yang menerima `model` sebagai argumen (bukan global `S`). Isi persis:

```js
(function (root) {
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
  const bbox = (v) => {
    const xs = v.map(p => p.x), ys = v.map(p => p.y);
    return { x0: Math.min(...xs), y0: Math.min(...ys), x1: Math.max(...xs), y1: Math.max(...ys) };
  };
  const shoelace = (v) => {
    let a = 0, n = v.length;
    for (let i = 0; i < n; i++) { const p = v[i], q = v[(i + 1) % n]; a += p.x * q.y - q.x * p.y; }
    return Math.abs(a) / 2;
  };
  const scanX = (v, Y) => { // perpotongan garis mendatar y=Y dgn poligon (even-odd), urut x
    const xs = [], n = v.length;
    for (let i = 0; i < n; i++) { const a = v[i], b = v[(i + 1) % n];
      if ((a.y <= Y && b.y > Y) || (b.y <= Y && a.y > Y)) xs.push(a.x + (Y - a.y) / (b.y - a.y) * (b.x - a.x)); }
    return xs.sort((p, q) => p - q);
  };
  const scanY = (v, X) => {
    const ys = [], n = v.length;
    for (let i = 0; i < n; i++) { const a = v[i], b = v[(i + 1) % n];
      if ((a.x <= X && b.x > X) || (b.x <= X && a.x > X)) ys.push(a.y + (X - a.x) / (b.x - a.x) * (b.y - a.y)); }
    return ys.sort((p, q) => p - q);
  };

  const DenahConv = {
    buildMembers(S) {
      const mem = [], V = S.verts, bb = bbox(V), K = S.kotak, rem = S.removed || {};
      // frame: tiap sisi poligon
      V.forEach((v, i) => {
        const w = V[(i + 1) % V.length], id = 'F' + i;
        const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.frame;
        mem.push({ id, nama: 'F' + (i + 1), jenis: 'frame', panjang: Math.round(dist(v, w) * 10) / 10, material: mat, geom: { a: v, b: w } });
      });
      const addSeg = (id, a, b) => {
        if (rem[id]) return;
        const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(a, b)), material: mat, geom: { a, b } });
      };
      if (S.arah === 'h' || S.arah === '2') { let li = 0;
        for (let Y = bb.y0 + K; Y < bb.y1 - 1; Y += K, li++) { const xs = scanX(V, Y);
          for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); } }
      if (S.arah === 'v' || S.arah === '2') { let li = 0;
        for (let X = bb.x0 + K; X < bb.x1 - 1; X += K, li++) { const ys = scanY(V, X);
          for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); } }
      (S.supportsManual || []).forEach((m, i) => {
        const id = 'Sm_' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(m.a, m.b)), material: mat, geom: { a: m.a, b: m.b } });
      });
      (S.tiang || []).forEach((t, i) => {
        const id = 'T' + i, mat = (S.matOverride && S.matOverride[id]) || S.matDefault.tiang;
        mem.push({ id, nama: 'T' + (i + 1), jenis: 'tiang', panjang: S.tinggi, material: mat, geom: { p: t } });
      });
      return mem;
    },
    luasM2(S) { return Math.round(shoelace(S.verts) / 10000 * 100) / 100; },
    saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n); },
    _dist: dist, _bbox: bbox,
  };

  if (typeof module !== 'undefined' && module.exports) module.exports = { DenahConv };
  if (typeof window !== 'undefined') window.DenahConv = DenahConv;
})(this);
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_konverter.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_konverter.mjs
git commit -m "feat(denah): modul geometri murni DenahConv (denah->members) + tes node"
```

---

### Task 2: Kelas UI `DenahEditor` (SVG + Pointer Events, per instance)

**Files:**
- Modify: `public/js/denah-editor.js` (tambah kelas `DenahEditor` memakai `DenahConv`)

**Interfaces:**
- Consumes: `DenahConv` (Task 1), daftar besi via opsi konstruktor.
- Produces (dipakai Task 3):
  - `new DenahEditor(mountEl, { besi:[{nama,harga}], onChange:fn, model?:obj })` — render editor ke `mountEl`.
  - `editor.getModel() -> model` (untuk persist)
  - `editor.getMembers() -> members[]`, `editor.getLuas() -> float`
  - `editor.setModel(model)` (rehidrasi)
  - `onChange()` dipanggil tiap edit (untuk trigger recompute+autosave app).

- [ ] **Step 1: Port kelas dari prototype**

Tambah ke `public/js/denah-editor.js` kelas `DenahEditor`. Ambil dari `tests/rangka/denah_prototype.html` fungsi: `render`, `bindSvg` (drag mulus rAF + snap saat lepas + titik support geser), `toCm`, `renderSides`, `setSideLength`/`typeSide`, `saranKotak`/`applySaran`, `syncLP`, `undo`/`pushUndo`, menu ganti besi. **Adaptasi wajib:**
- Global `S` → `this.S` (state instance). Semua fungsi jadi method / closure atas `this`.
- Global DOM (`document.getElementById('svg')`, `inL`, `inKotak`, panel) → elemen **di dalam `mountEl`** (query relatif: `this.el.querySelector(...)`). Tiap instance punya sub-DOM sendiri (kanvas, toolbar, panel sisi, legenda) yang dibuat kelas ini di `mountEl.innerHTML`.
- Daftar besi `BESI` konstanta prototype → `this.besi` dari opsi konstruktor (dropdown default frame/support/tiang + menu ganti besi).
- Hilangkan `fetch('/rangka-desain/hitung')` (biaya) — DIGANTI: tiap edit panggil `this.opts.onChange()` (app yang hitung lewat jalur RAB opsi).
- `pushUndo`/`undo` per instance (`this.undoStack`).
- Warna per besi (`colorMap`) & legenda tetap.
- Toolbar & panel sisi dibuat kelas (bukan HTML statis blade) agar mandiri per blok.

Struktur kelas (kerangka; badan method = port fungsi prototype dengan adaptasi di atas):

```js
class DenahEditor {
  constructor(el, opts) {
    this.el = el; this.opts = opts || {}; this.besi = opts.besi || [];
    this.S = opts.model || DenahEditor.defaultModel();
    this.undoStack = [];
    this.el.innerHTML = DenahEditor.shellHTML(); // toolbar + <div class="de-canvas"> + panel sisi + legenda
    this._wireControls();  // tool switch, +/- sudut, undo, arah, kotak, saran, material default
    this.render();
  }
  static defaultModel() {
    return { verts:[{x:0,y:0},{x:400,y:0},{x:400,y:300},{x:0,y:300}], grid:20, target:100,
      kotak:100, autoKotak:true, arah:'2', supportsManual:[], removed:{}, tiang:[],
      tinggi:300, matDefault:{frame:'', support:'', tiang:''}, matOverride:{} };
  }
  getModel(){ return JSON.parse(JSON.stringify(this.S)); }
  getMembers(){ return DenahConv.buildMembers(this.S); }
  getLuas(){ return DenahConv.luasM2(this.S); }
  setModel(m){ this.S = m; this.render(); }
  _changed(){ if (this.opts.onChange) this.opts.onChange(); }
  // render(), bindSvg(), toCm(), renderSides(), setSideLength(), pushUndo(), undo(), ... (port)
}
if (typeof window !== 'undefined') window.DenahEditor = DenahEditor;
```

Catatan: `render()` memanggil `DenahConv.buildMembers(this.S)` untuk menggambar; setiap mutasi state memanggil `this._changed()` (menggantikan `hitung()` prototype).

- [ ] **Step 2: Self-check UI ringkas (browser, opsional saat dev)**

Pertahankan `console.assert` self-check kecil di file (guard `if (window.__DENAH_SELFCHECK)`), meniru `selfCheck()` prototype: buat instance di div lepas, cek `getMembers()` kotak = 4 frame. Tak jalan di produksi (flag mati). Ini pengganti tes DOM (tak ada runner JS di repo).

- [ ] **Step 3: Verifikasi manual (browser lokal)**

Tambahkan `denah_prototype.html` sementara memuat `../../public/js/denah-editor.js` untuk sanity? TIDAK — cukup verifikasi via integrasi Task 3 di `/rab-opsi` (harness/preview). Di sini hanya pastikan file parse:
Run: `node -e "require('./public/js/denah-editor.js'); console.log('module load OK')"`
Expected: `module load OK` (DenahConv ekspor; kelas DenahEditor di-skip di node karena butuh window — pastikan guard `typeof window` melindungi kode yang menyentuh DOM di top-level).

- [ ] **Step 4: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): kelas DenahEditor (SVG editor per-blok) memakai DenahConv"
```

---

### Task 3: Integrasi ke blade RAB Opsi (tombol + tipe blok denah + serialize/rehidrasi)

**Files:**
- Modify: `resources/views/rab-opsi/index.blade.php` — 6 titik: muat skrip, tombol tambah, `tambahBlok` cabang denah, mount editor, `bacaBlok` denah, `isiBlok` denah.

**Interfaces:**
- Consumes: `DenahEditor`/`DenahConv` (Task 1–2), `BESI` (`:201`), `hargaOf` (`:211`), fungsi recompute app (lihat Step 6).
- Produces: blok `denah` yang: (a) tampil editor di kartu; (b) `bacaBlok` menghasilkan payload kontrak denah; (c) rehidrasi dari `rab_snapshot`.

- [ ] **Step 1: Muat modul + registry editor**

Setelah blok `<script>` besar dibuka (dekat `const BESI=@json($besi);` `index.blade.php:201`), tambah di `<head>`/sebelum script util:
```html
<script src="{{ asset('js/denah-editor.js') }}"></script>
```
Dan di JS app, siapkan peta instance editor per kartu:
```js
const DENAH = new WeakMap(); // card -> DenahEditor
```

- [ ] **Step 2: Tombol `+ Blok Denah`**

Di `index.blade.php:176-177` (dekat tombol Kanopi/Manual), tambah tombol ketiga:
```html
<button type="button" class="btn-sm" onclick="tambahBlok(null,'denah')">+ Blok Denah</button>
```

- [ ] **Step 3: Cabang `denah` di `tambahBlok`**

Di `tambahBlok(pane,tipe,data)` (`index.blade.php:388-477`), tambah template kartu untuk `tipe==='denah'`: header sama (nama/aktif/lipat/hapus, badge `DENAH`), body berisi:
- default besi per peran (3 select `.b-matFrame/.b-matSupport/.b-matTiang` dari `besiOpts()` — dipakai jadi `matDefault`),
- kontainer editor `<div class="b-denah"></div>`,
- (jika `LIHAT_HARGA`) blok upah `.b-jk`/`.b-kond` + atap/addon SAMA seperti kanopi (reuse markup — luas dari denah otomatis via backend).
Setelah kartu masuk DOM, mount editor:
```js
if (tipe === 'denah') {
  const mount = card.querySelector('.b-denah');
  const ed = new DenahEditor(mount, {
    besi: BESI.map(b => ({ nama: b.nama, harga: Number(b.harga_pokok) || 0 })),
    model: (data && data.denah) ? data.denah : null,
    onChange: () => { syncDenahDefault(card, ed); jadwalkanHitung(pane); }, // recompute + autosave
  });
  DENAH.set(card, ed);
  syncDenahDefault(card, ed); // set matDefault dari 3 select awal
}
```
Helper (tambahkan di JS app):
```js
function syncDenahDefault(card, ed){
  const g = s => card.querySelector(s)?.value || '';
  ed.S.matDefault = { frame:g('.b-matFrame'), support:g('.b-matSupport'), tiang:g('.b-matTiang') };
}
```
Select default besi `onchange` → `syncDenahDefault(card, DENAH.get(card)); DENAH.get(card).render(); jadwalkanHitung(pane);`.

- [ ] **Step 4: `bacaBlok` cabang denah**

Di `bacaBlok(card)` (`index.blade.php:558-596`), setelah menentukan `tipe`, tambah cabang untuk denah (sebelum return objek). Menghasilkan payload kontrak + menyertakan model untuk persist:
```js
if (tipe === 'denah') {
  const ed = DENAH.get(card);
  const members = ed ? ed.getMembers() : [];
  const harga = {};
  members.forEach(m => { harga[m.material] = hargaOf(m.material); });
  return {
    aktif: card.querySelector('.b-aktif').checked,
    tipe: 'denah',
    nama: card.querySelector('.b-nama').value || 'Blok',
    luas_m2: ed ? ed.getLuas() : 0,
    members: members.map(m => ({ nama: m.nama, jenis: m.jenis, panjang: m.panjang, material: m.material })),
    harga,
    denah: ed ? ed.getModel() : null,   // <- ikut ke rab_snapshot untuk rehidrasi
    besi_extra: bacaBesiExtra(card),     // reuse helper existing kalau ada; else []
    jenis_kerja_id: +(card.querySelector('.b-jk')?.value || 0),
    kondisi_ids: bacaKondisi(card),      // reuse pola kanopi
    atap_jenis_id: bacaAtapIds(card), atap_luas: bacaAtapLuas(card), atap_pasang: bacaAtapPasang(card),
    addon_id: bacaAddonIds(card), addon_qty: bacaAddonQty(card),
  };
}
```
Catatan: fungsi `bacaBesiExtra/bacaKondisi/bacaAtap*/bacaAddon*` = ekstrak dari kode kanopi `bacaBlok` yang sudah ada (`:585-594`) jika belum jadi helper; jika inline, salin ekspresi yang sama persis. (Tujuan: cabang denah pakai pipa upah/atap/addon identik kanopi.)

- [ ] **Step 5: `isiBlok` cabang denah (rehidrasi)**

Di `isiBlok(card,d)` (`index.blade.php:479-503`), untuk kartu denah: set 3 select default besi dari `d.denah.matDefault` (bila ada), lalu editor sudah dibangun dgn `model:d.denah` di `tambahBlok` Step 3 — jadi tak perlu aksi tambahan selain default besi & field upah/atap/addon (reuse pola kanopi). Tambahkan:
```js
if ((card.dataset.tipe) === 'denah' && d.denah) {
  if (d.denah.matDefault) {
    if (card.querySelector('.b-matFrame'))   card.querySelector('.b-matFrame').value   = d.denah.matDefault.frame || '';
    if (card.querySelector('.b-matSupport')) card.querySelector('.b-matSupport').value = d.denah.matDefault.support || '';
    if (card.querySelector('.b-matTiang'))   card.querySelector('.b-matTiang').value   = d.denah.matDefault.tiang || '';
  }
  // field upah/atap/addon di-rehidrasi lewat blok kanopi-shared di isiBlok (sudah ada)
}
```
(Editor mendapat model lewat argumen `data` di `tambahBlok`; `tambahOpsi`→`tambahBlok(pane,bd.tipe,bd)` `:349` sudah mengoper objek blok utuh sebagai `data`, termasuk `denah`.)

- [ ] **Step 6: Wiring recompute + autosave**

Cari fungsi yang dipanggil input blok lain saat berubah (recompute banding + autosave). Berdasarkan peta: `bandingkan()`/`renderBanding()` (`:610-711`) + `autoSave()` (`:949`). Definisikan (atau temukan) `jadwalkanHitung(pane)` = debounce yang memanggil recompute opsi + `autoSave()` — SAMA seperti yang dipakai input kanopi. Jika input kanopi memakai listener `oninput`/`onchange` yang memanggil suatu fungsi X, pakai X yang sama di `onChange` editor & di select default besi.
Verifikasi cara: `grep -n "oninput\|addEventListener('input'\|onchange" resources/views/rab-opsi/index.blade.php | head` → temukan handler recompute bersama, pakai itu. Jangan bikin jalur hitung baru.

- [ ] **Step 7: Verifikasi tak merusak kanopi/manual**

Run: `php -l` bukan untuk blade; cek JS parse via ekstraksi:
```bash
node -e "const fs=require('fs');const h=fs.readFileSync('resources/views/rab-opsi/index.blade.php','utf8');const m=[...h.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(x=>x[1]).join('\n');const c=m.replace(/@json\([^)]*\)/g,'null').replace(/\{\{[^}]*\}\}/g,'\"x\"');new Function('BESI','BESI_SEMUA','DenahEditor','DenahConv','CSRF','LIHAT_HARGA','LIHAT_MODAL','JK','KOND','ATAP','ADDON','LEAD',c);console.log('blade JS parse OK');"
```
Expected: `blade JS parse OK` (sintaks JS blade utuh — cabang denah tak merusak).

- [ ] **Step 8: Commit**

```bash
git add resources/views/rab-opsi/index.blade.php
git commit -m "feat(rab-opsi): tipe blok 'denah' + mount DenahEditor + serialize/rehidrasi denah"
```

---

### Task 4: Verifikasi end-to-end via harness (payload denah → hitung)

**Files:**
- Modify: `tests/rangka/preview_server.php` (tambah route uji `/rab-blok/hitung` minimal yang memanggil `hitungSatuBlok`-setara untuk 1 blok denah — TANPA DB, cukup jalur besi)

**Interfaces:**
- Consumes: `RangkaDesignService::hitung` (murni), payload denah dari editor.

- [ ] **Step 1: Endpoint uji di harness**

Karena `hitungSatuBlok` penuh butuh DB (upah/atap), verifikasi otomatis dibatasi ke **jalur besi denah** (yang tanpa DB). Tambah di `tests/rangka/preview_server.php` route:
```php
if ($uri === '/denah/hitung-besi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = json_decode(file_get_contents('php://input'), true) ?: [];
    $members = array_map(fn ($m) => (array) $m, (array) ($r['members'] ?? []));
    $harga   = (array) ($r['harga'] ?? []);
    header('Content-Type: application/json');
    echo json_encode(['data' => (new \App\Services\RangkaDesignService())->hitung($members, $harga, true)]);
    return true;
}
```

- [ ] **Step 2: Uji manual dari browser `/rab-opsi`-mock atau curl**

Jalankan harness, POST payload denah kotak 700×730 (members hasil `DenahConv.buildMembers`) → pastikan `per_material` masuk akal (5x10 frame beberapa batang + support). Contoh curl (members contoh):
```bash
php -S 0.0.0.0:8892 tests/rangka/preview_server.php >/dev/null 2>&1 &
curl -s -X POST http://127.0.0.1:8892/denah/hitung-besi -H 'Content-Type: application/json' \
  -d '{"members":[{"nama":"F1","jenis":"frame","panjang":700,"material":"Hollow 5x10"},{"nama":"F2","jenis":"frame","panjang":730,"material":"Hollow 5x10"}],"harga":{"Hollow 5x10":120000}}' | python3 -m json.tool
```
Expected: `per_material[0].jumlah_batang` = 3 (700+730=1430 → 3 batang), `total_biaya_besi` = 360000.

- [ ] **Step 3: Checklist verifikasi manual UI (PC + HP)** — *dikerjakan Elvan/di-deploy 1C, dicatat di sini*

1. `/rab-opsi` → `+ Blok Denah` → editor muncul di kartu.
2. Bentuk denah (seret sudut, panel ukur sisi, arah support, tiang, ganti besi) → rincian besi & pokok blok update.
3. Simpan (autosave) → reload halaman → blok denah + bentuknya kembali (rehidrasi dari `rab_snapshot`).
4. Blok kanopi/manual lama di opsi yang sama tetap normal.

- [ ] **Step 4: Commit**

```bash
git add tests/rangka/preview_server.php
git commit -m "test(denah): endpoint harness hitung-besi untuk verifikasi payload denah"
```

---

## Ringkasan cakupan 1B vs spec

| Spec | Task |
|---|---|
| §5.1 DenahEditor (SVG, tools, per blok) | 2, 3 |
| §5.2 konverter denah→members + scanline | 1 |
| §5.3 endpoint members per blok (sudah 1A) — disambung UI | 3 |
| §5.5 persistensi model denah JSON per blok (rab_snapshot) | 3 (bacaBlok/isiBlok) |
| §4 UX kartu blok = denah + material + rincian | 3 |
| §3 #11/#12/#13 besi per-bagian + warna/legenda + label | 2 (port prototype) |
| §8 migrasi aman (berdampingan, jalur lama utuh) | 3 (tipe aditif) |

**Yang SENGAJA di luar 1B (→ 1C):** jadikan denah default kartu blok & buang `/rangka-desain` + jalur kotak lama; validasi PA-DUTA end-to-end lewat UI nyata (butuh deploy + DB). **Di luar 1D:** kalibrasi ulang model support (target 9), retune consumable/finishing pakai luas ~40 m².

**Catatan verifikasi:** tes otomatis 1B menutupi geometri murni (Task 1, Node) & jalur besi (Task 4, harness). Upah/atap/consumable via DB + interaksi UI = verifikasi manual saat deploy 1C (tak ada DB/runner-JS di VPS ini).
