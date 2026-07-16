# Gabungan Kotak (Tambah/Lekukan) — DenahEditor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah cara baru bikin bentuk denah campur (siku + lekukan) di `DenahEditor` — tombol **"+ Tambah Kotak"**: ketuk sisi lurus, geser kotak (ukuran diketik) buat nempel keluar (nambah) atau ke dalam (lekukan), tanpa toggle — arah otomatis dari posisi drag.

**Architecture:** Satu fungsi geometri murni baru `DenahConv.combineBox(verts, sisiIdx, offset, span, depth)` — menyisipkan "detour" 3-sisi ke satu sisi lurus polygon; tanda `depth` (bukan logic terpisah) yang menentukan hasilnya jadi tonjolan keluar (nambah) atau notch ke dalam (lekukan) — **satu algoritma yang sama** untuk keduanya, sesuai spec §5. Divalidasi dengan cek polygon-sederhana (tak ada sisi yang saling potong) sebelum diterima. Di atasnya, `DenahEditor` dapat state baru `this.boxPreview` (armed `'addBox'`) yang menggambar preview kotak sebagai overlay SVG yang bisa digeser (pointer events, pola sama seperti drag-vertex yang sudah ada), lalu tombol "Terapkan" memanggil `combineBox` dan mengganti `this.S.verts`.

**Tech Stack:** JS vanilla, tanpa framework/bundler (pola file: IIFE classic-script, `globalThis.DenahConv`/`globalThis.DenahEditor`). Tes geometri murni via Node (`node tests/rangka/test_box_union.mjs`), pola sama seperti `test_konverter.mjs` (baca file + `eval` global, karena file bukan ESM).

## Global Constraints

- **Satu file saja yang berubah:** `public/js/denah-editor.js`. Blade (`resources/views/rab-opsi/index.blade.php`) **TIDAK disentuh** — shell HTML editor (`shellHTML()`), render, dan bind event semua sudah di-generate dari JS ini, dan blade sudah memuatnya dengan cache-bust otomatis (`?v={{ filemtime(...) }}`, `index.blade.php:196`) — tidak perlu langkah cache-bust manual.
- **Aditif murni** — mode/armed state yang sudah ada (`bentuk`/`besi`/`support`/`tiang`, armed `addV`/`delV`/`addSupport`) **tidak berubah perilakunya**. Fitur baru = armed state baru `'addBox'`, dipicu tombol baru, tidak mengubah jalur lain.
- **`S.verts` tetap satu-satunya sumber kebenaran** (spec §3.2). "Tambah Kotak" adalah operasi sekali-jalan yang mengganti `this.S.verts` lewat `pushUndo()` dulu — sama seperti pola `addV`/`delV` yang sudah ada. Tidak ada model kotak-list yang di-persist terpisah.
- **Emoji dilarang di kode yang tampil ke user** (korup di server Niagahoster, CLAUDE.md) — semua label teks/simbol biasa (`+`, `−`), bukan emoji.
- **Tak ada DOM test runner di repo** — verifikasi UI interaktif (drag) dilakukan manual lewat harness lokal yang sudah ada (`tests/rangka/preview_server.php`, pola sama seperti validasi Tahap 1B), bukan otomatis.
- **Kontrak `DenahConv.buildMembers`/`luasM2`/dst (dipakai controller & test lain) tidak berubah** — `combineBox` murni fungsi baru, tidak mengubah fungsi yang sudah ada.

---

### Task 1: `DenahConv.combineBox` — fungsi geometri murni + tes Node

**Files:**
- Modify: `public/js/denah-editor.js` (tambah helper `segInt`/`isSimplePolygon` + method `combineBox` di objek `DenahConv`)
- Create: `tests/rangka/test_box_union.mjs`

**Interfaces:**
- Produces (dipakai Task 2):
  - `DenahConv.combineBox(verts, sisiIdx, offset, span, depth) -> newVerts[] | null`
    - `verts`: array `{x,y}` polygon sekarang
    - `sisiIdx`: indeks sisi (dari `verts[sisiIdx]` ke `verts[(sisiIdx+1)%n]`) tempat kotak nempel
    - `offset`: jarak dari `verts[sisiIdx]` ke ujung dekat kotak, searah sisi (cm)
    - `span`: panjang kotak sepanjang sisi itu (cm); harus `offset + span <= panjang sisi`
    - `depth`: kedalaman kotak tegak lurus sisi (cm). Tanda menentukan arah — **positif dan negatif dua-duanya valid**, keduanya dipakai oleh Task 2 (UI yang menentukan tanda dari posisi drag, bukan fungsi ini). `depth === 0` invalid.
    - Return `null` kalau: `offset`/`span` di luar jangkauan sisi, `depth === 0`, atau hasil poligon jadi tidak sederhana (ada sisi yang saling potong — proteksi dari kotak yang ketembus/numpuk ke bagian lain bentuk).

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_box_union.mjs`:

```js
// Muat modul classic-script (globalThis.DenahConv) via read+eval — sama pola test_konverter.mjs.
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../../public/js/denah-editor.js', import.meta.url), 'utf8');
(0, eval)(code);
const { DenahConv } = globalThis;

let fail = false;
const check = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name} (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`);
  if (!ok) fail = true;
};

// Kotak dasar 700x400: (0,0)-(700,0)-(700,400)-(0,400)
const kotak = [{ x: 0, y: 0 }, { x: 700, y: 0 }, { x: 700, y: 400 }, { x: 0, y: 400 }];

// --- Kasus NAMBAH: tempel di sisi kanan (idx 1), menjorok KELUAR (depth negatif -> +x) ---
const tambah = DenahConv.combineBox(kotak, 1, 75, 250, -150);
check('nambah: 8 titik', tambah && tambah.length, 8);
check('nambah: luas jadi 31.75 m2 (28m2 + tab 1.5x2.5m)', tambah && DenahConv.luasM2({ verts: tambah }), 31.75);
check('nambah: titik menonjol (850,75) ada', tambah && tambah.some(p => p.x === 850 && p.y === 75), true);

// --- Kasus LEKUKAN: tempel di sisi bawah (idx 2), menjorok ke DALAM (depth positif -> -y) ---
const lekukan = DenahConv.combineBox(kotak, 2, 200, 300, 100);
check('lekukan: 8 titik', lekukan && lekukan.length, 8);
check('lekukan: luas jadi 25 m2 (28m2 - notch 2x1m)', lekukan && DenahConv.luasM2({ verts: lekukan }), 25);
check('lekukan: titik notch (500,300) ada', lekukan && lekukan.some(p => p.x === 500 && p.y === 300), true);

// --- Kasus DITOLAK: offset+span melebihi panjang sisi (700) ---
const invalidRange = DenahConv.combineBox(kotak, 0, 600, 200, 100);
check('ditolak: offset+span > panjang sisi -> null', invalidRange, null);

// --- Kasus DITOLAK: lekukan kelewat dalam, nembus sisi seberang (self-intersect) ---
const invalidDeep = DenahConv.combineBox(kotak, 2, 200, 300, 500);
check('ditolak: lekukan nembus sisi seberang -> null', invalidDeep, null);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_box_union.mjs`
Expected: error `DenahConv.combineBox is not a function` (fungsi belum ada) → proses exit non-zero.

- [ ] **Step 3: Tambah `combineBox` + helper ke `DenahConv`**

Di `public/js/denah-editor.js`, tambah 2 fungsi helper di closure IIFE (sejajar `dist`/`bbox`/`shoelace`/`scanX`/`scanY`, **sebelum** `const DenahConv = {`):

```js
// Deteksi 2 segmen saling potong (dipakai validasi combineBox) — sengaja skip kasus kolinear/nyentuh
// ujung persis (jarang terjadi dari hasil combineBox, dan self-intersect nyata selalu ke-tangkap
// oleh pasangan sisi lain di sekitarnya).
const segInt = (p1, p2, p3, p4) => {
  const d = (a, b, c) => (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
  const d1 = d(p3, p4, p1), d2 = d(p3, p4, p2), d3 = d(p1, p2, p3), d4 = d(p1, p2, p4);
  return ((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0));
};
// Poligon sederhana = tak ada 2 sisi tak-bertetangga yang saling potong.
const isSimplePolygon = (v) => {
  const n = v.length;
  for (let i = 0; i < n; i++) {
    const a1 = v[i], a2 = v[(i + 1) % n];
    for (let j = i + 1; j < n; j++) {
      if ((j + 1) % n === i || (i + 1) % n === j) continue; // lewati sisi bertetangga (berbagi titik)
      if (segInt(a1, a2, v[j], v[(j + 1) % n])) return false;
    }
  }
  return true;
};
```

Lalu tambah method `combineBox` di objek `DenahConv` (setelah `saranKotak(...)`, sebelum `_dist: dist, _bbox: bbox,`):

```js
  // Tempel kotak ke 1 sisi lurus (sisiIdx): sisipkan "detour" 4 titik pengganti segmen yang
  // ketutup. Tanda `depth` menentukan arah — SATU fungsi yang sama menghasilkan tonjolan
  // keluar (nambah) atau notch ke dalam (lekukan), tergantung tanda itu. UI (DenahEditor) yang
  // memutuskan tandanya dari posisi drag — fungsi ini tak tahu & tak perlu tahu mana "luar"/"dalam".
  combineBox(verts, sisiIdx, offset, span, depth) {
    const n = verts.length;
    const a = verts[sisiIdx], b = verts[(sisiIdx + 1) % n];
    const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey);
    if (!(len > 1e-6) || !(span > 0) || offset < -1e-6 || offset + span > len + 1e-6 || !depth) return null;
    const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
    const p1 = { x: a.x + ux * offset, y: a.y + uy * offset };
    const p2 = { x: a.x + ux * (offset + span), y: a.y + uy * (offset + span) };
    const p4 = { x: p1.x + nx * depth, y: p1.y + ny * depth };
    const p3 = { x: p2.x + nx * depth, y: p2.y + ny * depth };
    const seq = [];
    if (offset > 1e-6) seq.push(p1);
    seq.push(p4, p3);
    if (offset + span < len - 1e-6) seq.push(p2);
    const out = [...verts.slice(0, sisiIdx + 1), ...seq, ...verts.slice(sisiIdx + 1)];
    return isSimplePolygon(out) ? out : null;
  },
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_box_union.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Jalankan tes lama — pastikan tak ada regresi**

Run: `node tests/rangka/test_konverter.mjs`
Expected: `SEMUA LULUS` (tak berubah — `combineBox` murni fungsi tambahan, tak menyentuh `buildMembers`/`luasM2` yang sudah ada).

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_box_union.mjs
git commit -m "feat(denah): DenahConv.combineBox — tempel kotak (nambah/lekukan) 1 sisi + tes node"
```

---

### Task 2: UI "+ Tambah Kotak" — preview drag + Terapkan/Batal di `DenahEditor`

**Files:**
- Modify: `public/js/denah-editor.js` (shell HTML, constructor, `_wireControls`, `render`, `bindSvg`, method baru `renderBoxPanel`/`computeBoxPreviewVerts`/`applyBoxPreview`)

**Interfaces:**
- Consumes: `DenahConv.combineBox` (Task 1).
- Produces: tombol `+ Tambah Kotak` yang aktif di mode `bentuk`; state `this.boxPreview` selama proses; tak mengekspos API publik baru (tetap lewat `getModel()`/`getMembers()` yang sudah ada, karena hasil akhir masuk ke `this.S.verts`).

- [ ] **Step 1: Tambah state awal di constructor**

Di `constructor(el, opts)`, cari baris:
```js
    this.armed = null;      // 'addV' | 'delV' | 'addSupport'
    this.addSupportPt = null;
```
Ganti jadi:
```js
    this.armed = null;      // 'addV' | 'delV' | 'addSupport' | 'addBox'
    this.addSupportPt = null;
    this.boxPreview = null; // { sisiIdx, offset, span, depthMag, depthSign } selama armed === 'addBox'
```

- [ ] **Step 2: Reset `boxPreview` di semua titik ganti mode/armed lain**

Di `_wireControls()`, cari blok `.de-tool` mode-switch:
```js
    this._qa('.de-tool').forEach(elx => elx.onclick = () => {
      this._qa('.de-tool').forEach(t => t.classList.remove('on'));
      elx.classList.add('on');
      this.mode = elx.dataset.mode;
      this.armed = null; this.addSupportPt = null;
      this.setHint();
      this.render();
    });
```
Ganti baris `this.armed = null; this.addSupportPt = null;` jadi:
```js
      this.armed = null; this.addSupportPt = null; this.boxPreview = null;
```
Lalu cari 2 baris berikutnya (btnAddV, btnDelV) dan tambahkan reset yang sama:
```js
    this._q('[data-role=btnAddV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'addV'; this.boxPreview = null; this.setHint('Klik sisi frame untuk sisipkan sudut baru.'); this.renderBoxPanel(); };
    this._q('[data-role=btnDelV]').onclick = () => { if (this.mode !== 'bentuk') return; this.armed = 'delV'; this.boxPreview = null; this.setHint('Klik sudut untuk menghapus (min 3 sudut).'); this.renderBoxPanel(); };
```
(Perubahan vs kode lama: tambah `this.boxPreview = null;` dan `this.renderBoxPanel();` di kedua baris — method `renderBoxPanel` dibuat Step 5, aman dipanggil karena hanya menyembunyikan panel kalau `armed !== 'addBox'`.)

- [ ] **Step 3: Tombol "+ Tambah Kotak" di shell HTML**

Di `static shellHTML()`, cari baris toolbar:
```html
    <span class="de-mini" data-role="btnAddV">+ Sudut</span>
    <span class="de-mini" data-role="btnDelV">− Sudut</span>
    <span class="de-mini" data-role="btnUndo">Undo</span>
```
Tambah 1 baris setelahnya:
```html
    <span class="de-mini" data-role="btnAddV">+ Sudut</span>
    <span class="de-mini" data-role="btnDelV">− Sudut</span>
    <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
    <span class="de-mini" data-role="btnUndo">Undo</span>
```
Lalu cari baris hint (`<div class="de-hint" data-role="hint">...`) dan tambah container panel SEBELUM baris itu:
```html
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
  <div class="de-hint" data-role="hint">Mode Bentuk: seret bulatan sudut untuk mengubah bentuk. Ketuk angka cm di sisi untuk ketik panjang pasti.</div>
```

- [ ] **Step 4: Wiring tombol "+ Tambah Kotak"**

Di `_wireControls()`, setelah baris `btnAddSupport` yang sudah ada, tambah:
```js
    this._q('[data-role=btnAddBox]').onclick = () => {
      if (this.mode !== 'bentuk') return;
      this.armed = 'addBox';
      this.boxPreview = { sisiIdx: null, offset: 0, span: 100, depthMag: 100, depthSign: 1 };
      this.setHint('Ketuk sisi lurus tempat kotak mau nempel.');
      this.renderBoxPanel();
    };
```

- [ ] **Step 5: Method `renderBoxPanel`, `computeBoxPreviewVerts`, `applyBoxPreview`**

Tambah 3 method baru di kelas `DenahEditor` (taruh setelah `openMatMenu(...)`, sebelum `// ---- Render SVG ----`):

```js
  // Panel input span/menjorok + Terapkan/Batal — cuma tampil selagi armed === 'addBox'.
  renderBoxPanel() {
    const panel = this._q('[data-role=boxPanel]');
    if (this.armed !== 'addBox') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    const bp = this.boxPreview;
    panel.style.display = 'flex';
    panel.innerHTML =
      '<label style="font-size:12px;display:flex;flex-direction:column;gap:3px">Panjang di sisi ini (cm)' +
      '<input type="number" data-role="inBoxSpan" value="' + bp.span + '" min="1" step="10"></label>' +
      '<label style="font-size:12px;display:flex;flex-direction:column;gap:3px">Menjorok (cm)' +
      '<input type="number" data-role="inBoxDepth" value="' + bp.depthMag + '" min="1" step="10"></label>' +
      (bp.sisiIdx != null ? '<span class="de-mini" data-role="btnBoxApply">Terapkan</span>' : '') +
      '<span class="de-mini" data-role="btnBoxCancel">Batal</span>';
    this._q('[data-role=inBoxSpan]').oninput = e => { bp.span = Math.max(1, +e.target.value) || bp.span; this.render(); };
    this._q('[data-role=inBoxDepth]').oninput = e => { bp.depthMag = Math.max(1, +e.target.value) || bp.depthMag; this.render(); };
    this._q('[data-role=btnBoxCancel]').onclick = () => { this.armed = null; this.boxPreview = null; this.setHint(); this.render(); };
    const apply = this._q('[data-role=btnBoxApply]');
    if (apply) apply.onclick = () => this.applyBoxPreview();
  }

  // Titik kotak-preview sekarang (cm), dari sisi+offset+span+depth yang sedang diedit/digeser.
  computeBoxPreviewVerts() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey) || 1;
    const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const p1 = { x: a.x + ux * off, y: a.y + uy * off };
    const p2 = { x: a.x + ux * (off + bp.span), y: a.y + uy * (off + bp.span) };
    const d = bp.depthMag * bp.depthSign;
    const p4 = { x: p1.x + nx * d, y: p1.y + ny * d };
    const p3 = { x: p2.x + nx * d, y: p2.y + ny * d };
    return { p1, p2, p3, p4 };
  }

  // Terapkan: panggil combineBox murni (Task 1); kalau valid ganti S.verts, kalau tidak kasih hint & tetap di preview.
  applyBoxPreview() {
    const bp = this.boxPreview;
    const result = DenahConv.combineBox(this.S.verts, bp.sisiIdx, bp.offset, bp.span, bp.depthMag * bp.depthSign);
    if (!result) { this.setHint('Kotak tidak valid di posisi ini — geser lagi atau kecilkan ukurannya.'); return; }
    this.pushUndo();
    this.S.verts = result;
    this.armed = null; this.boxPreview = null;
    this.setHint();
    this.syncLP();
    this.render();
  }
```

- [ ] **Step 6: Ketuk sisi buat mulai preview (bindSvg pointerdown)**

Di `bindSvg(el)`, dalam blok `if (this.mode === 'bentuk') { ... }`, tambah SEBAGAI BARIS PALING ATAS dalam blok itu (sebelum cek `armed === 'delV'`):
```js
      if (this.mode === 'bentuk') {
        if (this.armed === 'addBox' && this.boxPreview.sisiIdx == null && t.dataset.id && t.dataset.id.startsWith('F')) {
          const i = +t.dataset.id.slice(1);
          const a = this.S.verts[i], b = this.S.verts[(i + 1) % this.S.verts.length];
          const len = dist(a, b);
          const bp = this.boxPreview;
          bp.sisiIdx = i;
          bp.span = Math.min(bp.span, Math.max(1, Math.round(len - 1)));
          bp.offset = Math.max(0, (len - bp.span) / 2);
          this.setHint('Geser kotak buat pas-in posisi & arah (luar = nambah, dalam = lekukan), lalu ketuk Terapkan.');
          this.renderBoxPanel();
          this.render();
          return;
        }
        if (t.dataset.boxprev && this.boxPreview && this.boxPreview.sisiIdx != null) {
          drag = { type: 'box' };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (this.armed === 'delV' && t.dataset.vert != null) { if (this.S.verts.length > 3) { this.pushUndo(); this.S.verts.splice(+t.dataset.vert, 1); } this.armed = null; this.setHint(); this.render(); return; }
```
(Baris `if (this.armed === 'delV' ...)` dan seterusnya di bawahnya TETAP seperti semula, tidak diubah — cukup sisipkan 2 blok baru sebelum baris itu.)

- [ ] **Step 7: Update preview saat digeser (pointermove)**

Di `bindSvg(el)`, dalam `el.addEventListener('pointermove', ...)`, cari blok `raf = requestAnimationFrame(() => { ... if (drag.type === 'vert') { ... } else if (drag.type === 'sup') { ... } });` dan tambah 1 `else if` baru setelah blok `sup`:
```js
        } else if (drag.type === 'box') {
          const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
          const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
          const ex = b.x - a.x, ey = b.y - a.y, len = Math.hypot(ex, ey) || 1;
          const ux = ex / len, uy = ey / len, nx = -uy, ny = ux;
          const vx = cm.x - a.x, vy = cm.y - a.y;
          const along = vx * ux + vy * uy, side = vx * nx + vy * ny;
          bp.offset = Math.max(0, Math.min(along - bp.span / 2, len - bp.span));
          bp.depthSign = side >= 0 ? 1 : -1;
          const pv = this.computeBoxPreviewVerts();
          const poly = el.querySelector('[data-boxprev]');
          if (poly) poly.setAttribute('points', [pv.p1, pv.p4, pv.p3, pv.p2].map(p => `${X(p.x)},${Y(p.y)}`).join(' '));
        }
```

- [ ] **Step 8: Snap saat lepas (pointerup `end()`)**

Di `bindSvg(el)`, cari:
```js
    const end = () => { if (!drag) return;
      if (drag.type === 'vert') { const vi = drag.vi; this.S.verts[vi] = { x: this.snap(this.S.verts[vi].x), y: this.snap(this.S.verts[vi].y) }; }
      else if (drag.type === 'sup') { const p = this.S.supportsManual[drag.i][drag.end]; this.S.supportsManual[drag.i][drag.end] = { x: this.snap(p.x), y: this.snap(p.y) }; }
      drag = null; if (raf) { cancelAnimationFrame(raf); raf = 0; } this.render(); };
```
Tambah 1 `else if` sebelum baris `drag = null;`:
```js
      else if (drag.type === 'box') { this.boxPreview.offset = this.snap(this.boxPreview.offset); }
```

- [ ] **Step 9: Gambar overlay preview di `render()` + panggil `renderBoxPanel()`**

Di `render()`, cari baris penutup SVG:
```js
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="10" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
    s += '</svg>';
```
Sisipkan sebelum `s += '</svg>';`:
```js
    if (this.armed === 'addBox' && this.boxPreview.sisiIdx != null) {
      const pv = this.computeBoxPreviewVerts();
      const pts = [pv.p1, pv.p4, pv.p3, pv.p2].map(p => `${X(p.x)},${Y(p.y)}`).join(' ');
      s += `<polygon points="${pts}" fill="rgba(56,189,248,0.35)" stroke="#38bdf8" stroke-width="2" data-boxprev="1" style="cursor:grab"/>`;
    }
```
Lalu cari baris akhir `render()`:
```js
    this.renderSides(mem);
    this._changed();
  }
```
Ganti jadi:
```js
    this.renderSides(mem);
    this.renderBoxPanel();
    this._changed();
  }
```

- [ ] **Step 10: Self-check ringkas — pastikan tombol baru ke-wire**

Di blok `if (globalThis.__DENAH_SELFCHECK) { try { ... } }`, cari baris:
```js
    const mem = ed.getMembers();
    const fr = mem.filter(m => m.jenis === 'frame');
```
Tambah 1 baris SEBELUM baris itu:
```js
    console.assert(!!ed._q('[data-role=btnAddBox]'), 'DenahEditor selfcheck: tombol + Tambah Kotak ada');
```

- [ ] **Step 11: Verifikasi file tetap valid (parse check)**

Run: `node -e "require('./public/js/denah-editor.js'); console.log('parse OK')" 2>&1 || node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: tak ada `SyntaxError` (file classic-script, `require` akan gagal karena tak ada `module.exports` — itu wajar; yang dicek di sini murni SINTAKS lewat `node --check`, harus `syntax OK`).

- [ ] **Step 12: Jalankan ulang kedua tes Node — pastikan tak ada regresi**

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs`
Expected: `SEMUA LULUS` untuk keduanya (Task 2 murni menambah UI di kelas `DenahEditor` — tidak menyentuh `DenahConv.buildMembers`/`luasM2`/`combineBox` yang sudah dites Task 1).

- [ ] **Step 13: Verifikasi manual di browser (harness lokal)**

Jalankan harness preview yang sudah ada (dipakai sejak validasi prototype 14 Juli):
```bash
php -S 0.0.0.0:8892 tests/rangka/preview_server.php >/dev/null 2>&1 &
```
Buka `http://187.77.143.121:8892/denah` (atau route yang sama dipakai validasi Tahap 1B). Checklist:
1. Mode "Bentuk" → tombol **"+ Tambah Kotak"** muncul di toolbar.
2. Ketuk tombol → panel span/menjorok + "Batal" muncul, hint berubah jadi "Ketuk sisi lurus...".
3. Ketuk salah satu sisi frame → kotak biru transparan (preview) muncul menempel di sisi itu, tombol "Terapkan" muncul.
4. Geser kotak (drag) sepanjang sisi → posisi ikut jari, dan begitu digeser ke sisi lain garis, preview terlihat berubah dari "nonjol keluar" jadi "notch ke dalam" (atau sebaliknya).
5. Ubah angka "Panjang di sisi ini"/"Menjorok" → ukuran preview berubah live.
6. Ketuk "Terapkan" pas preview di luar bentuk → bentuk polygon bertambah (tonjolan), Undo balikin ke semula.
7. Ketuk "Terapkan" pas preview di dalam bentuk → bentuk polygon jadi ada lekukan, Undo balikin ke semula.
8. Coba geser kotak sampai ukurannya bikin invalid (menjorok kelewat dalam) → hint "Kotak tidak valid..." muncul, TIDAK nge-crash, bentuk tak berubah.
9. Mode Besi/Support/Tiang, dan tombol "+ Sudut"/"− Sudut"/"+ Support manual" yang lama → masih jalan seperti sebelumnya (tak ada regresi).

- [ ] **Step 14: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): UI Tambah Kotak — preview drag nambah/lekukan + Terapkan/Batal"
```

---

### Task 3: Deploy & verifikasi Elvan di production

**Files:** (tidak ada file baru — deploy murni `git push`)

- [ ] **Step 1: Push ke production**

```bash
git push
```
Expected: GitHub Actions `deploy.yml` jalan, FTP ke Niagahoster, selesai ±1-2 menit (alur yang sudah baku, lihat CLAUDE.md § Deploy Workflow).

- [ ] **Step 2: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata)**

1. Buka blok Denah yang sudah ada (atau buat baru), ketuk **"+ Tambah Kotak"**.
2. Coba bikin bentuk L sederhana: kotak dasar 500×700, tambah kotak 150×250 nempel di 1 sisi, geser ke posisi yang pas, Terapkan → cek bentuk & luas masuk akal.
3. Coba bikin lekukan: pas kotak tambahan digeser ke DALAM bentuk → Terapkan → cek notch muncul & luas berkurang sesuai.
4. Simpan (autosave) → reload halaman → bentuk hasil gabungan kotak tetap ada (rehidrasi `S.verts` lewat `rab_snapshot`, tidak ada perubahan kontrak model jadi ini otomatis jalan).
5. Klik "Hitung Harga" → rincian besi ikut bentuk baru, tidak error.
6. Blok kanopi/manual & denah lain yang sudah ada di opsi yang sama → tetap normal (regresi check).

- [ ] **Step 3: Update `MEMORI_PROYEK.md`**

Tandai item **#9** ("Freehand susah bikin bentuk PA-DUTA") jadi selesai, catat ringkas: fitur Gabungan Kotak (tambah/lekukan) dipakai buat gantiin freehand utk bentuk campur, sisi miring tetap manual drag. Commit dokumentasi terpisah dari kode (pola yang sudah dipakai sesi-sesi sebelumnya).

---

## Ringkasan cakupan spec vs task

| Spec (`2026-07-16-gabungan-kotak-design.md`) | Task |
|---|---|
| §3.2 Model data tetap `S.verts`, operasi sekali-jalan | 1 (`combineBox` return verts baru, bukan model kotak-list) |
| §3.3/3.4 Ukuran diketik, posisi digeser | 2 (panel span/depth diketik, offset+arah lewat drag) |
| §3.5 Arah otomatis dari posisi (tanpa toggle) | 1 (tanda `depth`) + 2 (`depthSign` dari sisi drag) |
| §3.6 Sisi tempel harus lurus | **Koreksi (ditemukan final review):** setiap sisi poligon punya id `F{i}` termasuk sisi yang sudah dimiringkan manual — `combineBox` sebenarnya tetap benar secara geometri untuk sisi miring (kotak tegak lurus ke sisi itu apa adanya), jadi §3.6 bukan dipaksakan oleh kode, ini kapabilitas ekstra yang tak sengaja lebih luas dari spec, bukan bug |
| §3.7 Preview live | 2 Step 7, 9 (+ fix final review: update preview saat ketik span/depth TANPA `render()` penuh — full render menghancurkan `<input>` yang sedang diketik lewat `renderBoxPanel()`'s `innerHTML`, bikin fokus hilang tiap huruf) |
| §3.8 Sisi miring tetap manual | Tidak disentuh sama sekali (non-goal, tak ada task) — TAPI lihat catatan §3.6: kalau user tempel kotak ke sisi yang KEBETULAN sudah dimiringkan manual sebelumnya, itu tetap jalan benar, bukan dilarang aktif |
| §3.9 Algoritma XOR/detour satu fungsi | 1 |
| §3.10 Validasi & penolakan kasus ambigu | 1 (`isSimplePolygon`) + 2 Step 5 (`applyBoxPreview` hint kalau `null`) |
| §3.11 Undo | 2 (`pushUndo()` di `applyBoxPreview` sebelum ganti verts) |
| §6 Testing | 1 (Node), 2 Step 13 (manual browser) |
| §7 Yang tidak berubah | Global Constraints (tak sentuh blade/backend/mode lain) |

**Di luar plan ini (tak masuk scope):** sisi miring lewat fitur ini (tetap manual, spec §3.8), kotak nempel sebagian tumpang-tindih kompleks, hasil >1 bentuk terpisah (spec §3 non-goals) — semua ini SENGAJA ditolak oleh `isSimplePolygon`/validasi range, bukan lubang yang belum ditangani.
