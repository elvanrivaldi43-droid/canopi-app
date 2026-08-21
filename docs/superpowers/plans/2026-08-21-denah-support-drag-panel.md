# DenahEditor — Support (pola drag/tahan + panel + konsolidasi tab) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support di DenahEditor (garis manual, titik ujung manual, grid otomatis) pakai pola seragam "tap cepat = no-op, geser = pindah, tahan ~450ms = menu" (persis pola Tiang), grid support jadi bisa digeser (dikunci searah) lalu naik-kelas jadi entri manual, dapat panel daftar S1/S2 dengan tombol Fokus/Hapus, dan semua kontrol Support dikonsolidasi ke 1 tab.

**Architecture:** Satu file `public/js/denah-editor.js` (classic-script IIFE, class `DenahEditor` + namespace `DenahConv`) — semua perubahan tetap di file ini, tidak ada file baru selain test. Reuse pola yang sudah terbukti dari redesign Tiang (commit `a94aa52`): long-press timer 450ms, popup menu viewport-clamped, panel daftar meniru `renderTiangPanel`.

**Tech Stack:** JavaScript vanilla (SVG string templating + Pointer Events), Node.js untuk test standalone (`tests/rangka/*.mjs`, pola `readFileSync`+`eval` karena classic-script tanpa module export).

## Global Constraints

- `public/js/denah-editor.js` HARUS tetap 1 file (tidak dipecah) — pola sudah dikunci di plan-plan sebelumnya.
- Setiap mutasi state (`this.S.*`) WAJIB dipasangkan `this.pushUndo()` SEBELUM mutasi, dan `this.render()` SETELAHNYA — supaya Undo/Redo/autosave global tetap konsisten (tidak ada mekanisme baru).
- `pushUndo()` untuk gestur drag baru (support garis/titik-ujung/grid) dipanggil PERSIS saat gerakan nyata pertama terdeteksi di `pointermove` (bukan di `pointerdown`) — pola yang sudah dikunci dari redesign Tiang, supaya gestur yang berakhir tanpa mutasi (tap sekejap, buka-menu-lalu-Batal) tidak mengotori undo stack.
- Emoji DILARANG di file ini (aturan CLAUDE.md, produksi pernah korup karenanya) — semua label/tombol teks biasa.
- VPS pengembangan ini TIDAK PUNYA browser/DOM (jsdom tidak terpasang) — logika DOM/event-wiring (long-press timer, drag SVG, popup menu, panel render) TIDAK BISA ditest otomatis di sini. Yang bisa ditest otomatis HANYA fungsi murni tanpa DOM di `DenahConv` (pola sama `tests/rangka/test_tiang_numerik.mjs`, `Number.isFinite`/aritmatika murni). Sisanya WAJIB diverifikasi manual oleh Bos langsung di browser/HP setelah deploy — tuliskan checklist manual di Task 7, jangan klaim "sudah teruji" untuk bagian interaktif.
- Verifikasi tiap task: `node --check public/js/denah-editor.js` (syntax) + jalankan test `.mjs` terkait dengan `node`.

---

### Task 1: `DenahConv.lockSupportAxis` + `DenahConv.numberSupportsManual` (logika murni, testable tanpa DOM)

**Files:**
- Modify: `public/js/denah-editor.js` (tambah 2 fungsi ke object literal `DenahConv`, setelah `parseCmValue` di baris ~110-116, sebelum `buildMembers` baris 117)
- Test: `tests/rangka/test_support_pola.mjs`

**Interfaces:**
- Consumes: tidak ada dependency lain.
- Produces:
  - `DenahConv.lockSupportAxis(id, startA, startB, dx, dy): {a:{x,y}, b:{x,y}}` — dipakai Task 3 (drag grid support).
  - `DenahConv.numberSupportsManual(mem): {[id]: number}` — dipakai Task 3/4/5 (label S{n} konsisten di kanvas, menu, dan panel).

- [ ] **Step 1: Tulis test (gagal dulu, fungsi belum ada)**

```js
// FILE: tests/rangka/test_support_pola.mjs
// Jalankan: node tests/rangka/test_support_pola.mjs
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

// ── lockSupportAxis ──────────────────────────────────────
// Support horizontal (Sh_, a.y===b.y) -> cuma boleh naik-turun (Y berubah, X tetap).
const shA = { x: 50, y: 100 }, shB = { x: 250, y: 100 };
check('Sh_ digeser dx=30,dy=-15 -> X TIDAK berubah, Y berubah -15 di kedua ujung',
  DenahConv.lockSupportAxis('Sh_2_4', shA, shB, 30, -15),
  { a: { x: 50, y: 85 }, b: { x: 250, y: 85 } });

// Support vertikal (Sv_, a.x===b.x) -> cuma boleh kiri-kanan (X berubah, Y tetap).
const svA = { x: 150, y: 20 }, svB = { x: 150, y: 220 };
check('Sv_ digeser dx=40,dy=25 -> Y TIDAK berubah, X berubah +40 di kedua ujung',
  DenahConv.lockSupportAxis('Sv_1_2', svA, svB, 40, 25),
  { a: { x: 190, y: 20 }, b: { x: 190, y: 220 } });

// dx/dy = 0 -> tidak berubah sama sekali (identitas).
check('dx=0,dy=0 -> posisi identik',
  DenahConv.lockSupportAxis('Sh_0_0', shA, shB, 0, 0),
  { a: shA, b: shB });

// ── numberSupportsManual ──────────────────────────────────
// mem gabungan: 2 grid support duluan, lalu 2 manual -> manual dapat nomor SENDIRI (1,2),
// TIDAK ikut kehitung grid-nya (bukan 3,4).
const mem1 = [
  { id: 'Sh_0_0', jenis: 'support' },
  { id: 'Sv_0_0', jenis: 'support' },
  { id: 'Sm_0', jenis: 'support' },
  { id: 'Sm_1', jenis: 'support' },
];
check('2 grid + 2 manual -> manual bernomor 1,2 (bukan 3,4)',
  DenahConv.numberSupportsManual(mem1), { Sm_0: 1, Sm_1: 2 });

// Grid support TIDAK masuk hasil sama sekali (bukan cuma dilewati nomornya, tapi memang tak ada key-nya).
check('grid support tidak punya entri di hasil',
  Object.prototype.hasOwnProperty.call(DenahConv.numberSupportsManual(mem1), 'Sh_0_0'), false);

// Frame/tiang ikut campur di mem (kasus nyata dari buildMembers) -> tetap diabaikan, tidak bikin nomor meleset.
const mem2 = [
  { id: 'F0', jenis: 'frame' },
  { id: 'Sm_0', jenis: 'support' },
  { id: 'T0', jenis: 'tiang' },
  { id: 'Sm_1', jenis: 'support' },
];
check('frame/tiang tercampur -> tidak pengaruhi nomor manual',
  DenahConv.numberSupportsManual(mem2), { Sm_0: 1, Sm_1: 2 });

// Tidak ada manual sama sekali -> object kosong, bukan error.
check('tidak ada support manual -> object kosong',
  DenahConv.numberSupportsManual([{ id: 'Sh_0_0', jenis: 'support' }]), {});

if (fail) { console.log('\n=== ADA YANG GAGAL ==='); process.exit(1); }
console.log('\n=== SEMUA TES LULUS ===');
```

- [ ] **Step 2: Jalankan test, pastikan gagal (fungsi belum ada)**

Run: `node tests/rangka/test_support_pola.mjs`
Expected: error `DenahConv.lockSupportAxis is not a function` (atau sejenis, karena fungsi belum ditulis).

- [ ] **Step 3: Tambahkan 2 fungsi ke `DenahConv`**

Di `public/js/denah-editor.js`, cari baris persis ini (akhir `parseCmValue`, sebelum `buildMembers`):

```js
    const n = Number(s.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
  },
  buildMembers(S) {
```

Ganti jadi (sisipkan 2 fungsi baru DI ANTARA `parseCmValue` dan `buildMembers`):

```js
    const n = Number(s.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
  },
  // Kunci arah geser support grid (Redesign Support, 21 Agustus): support horizontal (id "Sh_...",
  // a.y===b.y) cuma boleh naik-turun; support vertikal (id "Sv_...", a.x===b.x) cuma boleh
  // kiri-kanan. Alasan: hasil geser HARUS tetap horizontal/vertikal (masuk akal secara struktur
  // rangka), tidak boleh miring diagonal.
  lockSupportAxis(id, startA, startB, dx, dy) {
    const horizontal = id.startsWith('Sh_');
    const ddx = horizontal ? 0 : dx;
    const ddy = horizontal ? dy : 0;
    return {
      a: { x: startA.x + ddx, y: startA.y + ddy },
      b: { x: startB.x + ddx, y: startB.y + ddy },
    };
  },
  // Nomor S{n} KHUSUS support manual (id "Sm_..."), independen dari support grid otomatis.
  // SATU sumber angka dipakai render() (label kanvas), openMatMenu() (label menu Ganti Material),
  // dan renderSupportPanel() (panel daftar) — jangan hitung ulang beda-beda di tiap tempat, itu
  // akar masalah lama: S{n} kanvas dulu gabungan grid+manual, jadi beda dari nomor panel.
  numberSupportsManual(mem) {
    let n = 0;
    const map = {};
    mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_')).forEach(m => { n++; map[m.id] = n; });
    return map;
  },
  buildMembers(S) {
```

- [ ] **Step 4: Jalankan test lagi, pastikan lulus**

Run: `node tests/rangka/test_support_pola.mjs`
Expected: `=== SEMUA TES LULUS ===`, semua baris `PASS`.

- [ ] **Step 5: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_pola.mjs
git commit -m "feat(denah): DenahConv.lockSupportAxis + numberSupportsManual (logika murni)"
```

---

### Task 2: Menu tekan-tahan Support (HTML/CSS + buka/tutup, pola persis tiangmenu)

**Files:**
- Modify: `public/js/denah-editor.js` — CSS (~baris 278-279), HTML shell (~baris 363-368), method baru setelah `_closeTiangMenu()` (~baris 1029), wiring tombol di `_wireControls()` (~baris 447), `_docPointerDown` (~baris 449-456)

**Interfaces:**
- Consumes: `this.S.removed`, `this.openMatMenu()` (sudah ada), `this.pushUndo()`/`this.render()`.
- Produces: `openSupportMenu(evt, id)`, `_closeSupportMenu()` dipakai Task 3. `this._supportMenuId` dipakai tombol menu.

- [ ] **Step 1: Tambah CSS (setelah `.de-tiangmenu.show`, baris 279)**

Cari:
```css
.de-tiangmenu.show{display:flex}
```
Tambahkan PERSIS setelah baris itu:
```css
.de-supportmenu{position:fixed;z-index:9999;display:none;flex-direction:column;gap:4px;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:6px}
.de-supportmenu.show{display:flex}
```

- [ ] **Step 2: Tambah HTML menu (setelah blok `de-tiangmenu`, sebelum penutup template literal)**

Cari:
```html
<div class="de-tiangmenu" data-role="tiangMenu">
  <span class="de-mini" data-role="tiangMenuTambah">Tambah Tiang</span>
  <span class="de-mini" data-role="tiangMenuGanti">Ganti Besi</span>
  <span class="de-mini" data-role="tiangMenuHapus">Hapus</span>
  <span class="de-mini" data-role="tiangMenuCancel">Batal</span>
</div>`;
```
Ganti jadi (sisipkan blok baru SEBELUM baris penutup `` `; ``):
```html
<div class="de-tiangmenu" data-role="tiangMenu">
  <span class="de-mini" data-role="tiangMenuTambah">Tambah Tiang</span>
  <span class="de-mini" data-role="tiangMenuGanti">Ganti Besi</span>
  <span class="de-mini" data-role="tiangMenuHapus">Hapus</span>
  <span class="de-mini" data-role="tiangMenuCancel">Batal</span>
</div>
<div class="de-supportmenu" data-role="supportMenu">
  <span class="de-mini" data-role="supportMenuSertakan">Sertakan</span>
  <span class="de-mini" data-role="supportMenuKecualikan">Kecualikan</span>
  <span class="de-mini" data-role="supportMenuGanti">Ganti Material</span>
  <span class="de-mini" data-role="supportMenuHapus">Hapus</span>
  <span class="de-mini" data-role="supportMenuCancel">Batal</span>
</div>`;
```

- [ ] **Step 3: Tambah method `openSupportMenu`/`_showSupportMenuAt`/`_closeSupportMenu`**

Cari method `_closeTiangMenu()` (persis setelah `_showTiangMenuAt`):
```js
  _closeTiangMenu() {
    this._q('[data-role=tiangMenu]').classList.remove('show');
    this._tiangMenuIdx = null;
    this._tiangAddPt = null;
  }
```
Tambahkan PERSIS setelah method itu (sebelum komentar `// Panel input span/menjorok...`):
```js

  // Menu tekan-tahan Support — SATU popup dipakai 2 konteks: grid otomatis (Sertakan/Kecualikan
  // toggle + Ganti Material) dan manual/titik-ujungnya (Hapus + Ganti Material) — saling
  // eksklusif, tombol tak relevan disembunyikan. Pola identik openTiangMenu di atas.
  openSupportMenu(evt, id) {
    this._supportMenuId = id;
    const isGrid = id.startsWith('Sh_') || id.startsWith('Sv_');
    const excluded = !!this.S.removed[id];
    this._q('[data-role=supportMenuSertakan]').style.display = (isGrid && excluded) ? '' : 'none';
    this._q('[data-role=supportMenuKecualikan]').style.display = (isGrid && !excluded) ? '' : 'none';
    this._q('[data-role=supportMenuHapus]').style.display = isGrid ? 'none' : '';
    this._showSupportMenuAt(evt);
  }
  // Posisi clamp-ke-viewport sama persis openMatMenu()/_showTiangMenuAt() di atas.
  _showSupportMenuAt(evt) {
    const menu = this._q('[data-role=supportMenu]');
    menu.style.left = '0px'; menu.style.top = '0px'; menu.classList.add('show');
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    let left = evt.clientX + 6, top = evt.clientY + 6;
    if (left + mw > window.innerWidth) left = Math.max(6, evt.clientX - mw - 6);
    if (top + mh > window.innerHeight) top = Math.max(6, evt.clientY - mh - 6);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }
  _closeSupportMenu() {
    this._q('[data-role=supportMenu]').classList.remove('show');
    this._supportMenuId = null;
  }
```

- [ ] **Step 4: Wire tombol menu di `_wireControls()`**

Cari (akhir blok tombol menu tiang):
```js
    this._q('[data-role=tiangMenuCancel]').onclick = () => this._closeTiangMenu();
```
Tambahkan PERSIS setelah baris itu:
```js

    // Tombol menu Support — Hapus HANYA berlaku untuk manual (Sm_), grid tidak punya "Hapus"
    // (cuma Sertakan/Kecualikan). Ganti Material reuse openMatMenu() yang sudah generic per-prefix.
    this._q('[data-role=supportMenuSertakan]').onclick = () => {
      if (this._supportMenuId) { this.pushUndo(); this.S.removed[this._supportMenuId] = false; this._closeSupportMenu(); this.render(); }
    };
    this._q('[data-role=supportMenuKecualikan]').onclick = () => {
      if (this._supportMenuId) { this.pushUndo(); this.S.removed[this._supportMenuId] = true; this._closeSupportMenu(); this.render(); }
    };
    this._q('[data-role=supportMenuGanti]').onclick = e => {
      if (this._supportMenuId) { const id = this._supportMenuId; this._closeSupportMenu(); this.openMatMenu(e, id); }
    };
    this._q('[data-role=supportMenuHapus]').onclick = () => {
      if (this._supportMenuId && this._supportMenuId.startsWith('Sm_')) {
        this.pushUndo();
        const i = +this._supportMenuId.slice(3);
        this.S.supportsManual.splice(i, 1);
        this._closeSupportMenu();
        this.render();
      }
    };
    this._q('[data-role=supportMenuCancel]').onclick = () => this._closeSupportMenu();
```

- [ ] **Step 5: Tambahkan supportMenu ke `_docPointerDown` (tutup otomatis kalau tap di luar)**

Cari:
```js
    this._docPointerDown = (e) => {
      const menu = this._q('[data-role=matMenu]');
      const tmenu = this._q('[data-role=tiangMenu]');
      const canvas = this._q('.de-canvas');
      if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !(canvas && canvas.contains(e.target))) menu.style.display = 'none';
      if (tmenu && tmenu.classList.contains('show') && !tmenu.contains(e.target) && !(canvas && canvas.contains(e.target))) this._closeTiangMenu();
    };
```
Ganti jadi:
```js
    this._docPointerDown = (e) => {
      const menu = this._q('[data-role=matMenu]');
      const tmenu = this._q('[data-role=tiangMenu]');
      const smenu = this._q('[data-role=supportMenu]');
      const canvas = this._q('.de-canvas');
      if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !(canvas && canvas.contains(e.target))) menu.style.display = 'none';
      if (tmenu && tmenu.classList.contains('show') && !tmenu.contains(e.target) && !(canvas && canvas.contains(e.target))) this._closeTiangMenu();
      if (smenu && smenu.classList.contains('show') && !smenu.contains(e.target) && !(canvas && canvas.contains(e.target))) this._closeSupportMenu();
    };
```

- [ ] **Step 6: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): menu tekan-tahan Support (Sertakan/Kecualikan/Ganti Material/Hapus)"
```

---

### Task 3: Redesign interaksi Support di `bindSvg()` — garis manual, titik ujung, grid (drag+konversi)

**Files:**
- Modify: `public/js/denah-editor.js` — blok `pointerdown` mode `support` (~baris 1325-1355), blok `pointermove` (~baris 1384-1394 tipe `sup`, ~baris 1437-1458 tipe `supline`, tambah tipe baru `supgrid`), blok `end()`/pointerup (~baris 1514-1526 tipe `sup`, ~baris 1546-1571 tipe `supline`, tambah tipe baru `supgrid`), dan render() label line (~baris 1125-1134, gabung sekalian ke Task 4 di sini biar `id="sg_..."` konsisten dengan drag)

**Interfaces:**
- Consumes: `DenahConv.lockSupportAxis` (Task 1), `this.openSupportMenu` (Task 2).
- Produces: perilaku interaksi baru — tidak ada fungsi baru yang dikonsumsi task lain, tapi Task 4/5 BERGANTUNG pada `id="sg_${id}"` di elemen `<line>` visible grid support yang ditambahkan di sini (lihat Step 1).

- [ ] **Step 1: Beri id stabil ke SEMUA garis support visible (grid dapat `sg_{id}`, manual tetap `sm{i}`)**

Di `render()`, cari:
```js
    s += '<g id="supLayer">';
    mem.filter(m => m.jenis === 'support').forEach((m, i) => { const c = cmap[m.material]; const manual = m.id.startsWith('Sm_');
      const mx = (m.geom.a.x + m.geom.b.x) / 2, my = (m.geom.a.y + m.geom.b.y) / 2;
      // garis tampak (tanpa event) + garis transparan lebar (target ketuk) + label S{n}·panjang
      s += `<line ${manual ? `id="sm${m.id.slice(3)}"` : ''} x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="${c}" stroke-width="${manual ? 3 : 2}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="transparent" stroke-width="14" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      s += `<text ${manual ? `id="smlbl${m.id.slice(3)}"` : ''} x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">S${i + 1} · ${m.panjang}</text>`; });
```
Ganti jadi:
```js
    s += '<g id="supLayer">';
    const supNum = DenahConv.numberSupportsManual(mem);
    mem.filter(m => m.jenis === 'support').forEach(m => { const c = cmap[m.material]; const manual = m.id.startsWith('Sm_');
      const mx = (m.geom.a.x + m.geom.b.x) / 2, my = (m.geom.a.y + m.geom.b.y) / 2;
      // garis tampak (tanpa event) + garis transparan lebar (target ketuk) + label. id garis tampak
      // SELALU ada (bukan cuma manual seperti dulu) -- grid support butuh id stabil-per-render
      // "sg_{id}" biar drag-preview (Task 3) bisa update atribut x1/y1/x2/y2-nya langsung tanpa
      // render ulang, sama pola dgn manual (sm{i}).
      const lineId = manual ? 'sm' + m.id.slice(3) : 'sg_' + m.id;
      s += `<line id="${lineId}" x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="${c}" stroke-width="${manual ? 3 : 2}"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${X(m.geom.a.x)}" y1="${Y(m.geom.a.y)}" x2="${X(m.geom.b.x)}" y2="${Y(m.geom.b.y)}" stroke="transparent" stroke-width="14" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      // Label S{n} KHUSUS manual (nomor independen dari grid, lihat DenahConv.numberSupportsManual).
      // Grid support tidak diberi nomor lagi (id-nya tak stabil lintas render, lihat Task 1).
      const label = manual ? `S${supNum[m.id]} · ${m.panjang}` : `${m.panjang}`;
      s += `<text ${manual ? `id="smlbl${m.id.slice(3)}"` : ''} x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">${label}</text>`; });
```

- [ ] **Step 2: Redesign `pointerdown` mode `support`**

Cari blok persis ini:
```js
      } else if (this.mode === 'support') {
        if (t.dataset.sm != null) {
          this.pushUndo();
          const i = +t.dataset.sm, end = t.dataset.end;
          drag = { type: 'sup', i, end, hit: t, h: el.querySelector('#smh' + i + end), line: el.querySelector('#sm' + i) };
          el.setPointerCapture(e.pointerId); e.preventDefault(); return;
        }
        if (this.armed === 'addSupport') {
          if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
          else { this.pushUndo(); this.S.supportsManual.push({ a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) } }); this.addSupportPt = null; this.armed = null; this.setHint(); this.render(); }
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('Sm_')) {
          const i = +t.dataset.id.slice(3);
          const m = this.S.supportsManual[i];
          this.pushUndo();
          // Tunggu ada gerakan dulu sebelum diputuskan drag-pindah-garis-utuh atau tap-hapus
          // (perilaku lama) — sama pola dgn tiang di Task 3.
          drag = { type: 'supline', i, startPt: cm, moved: false,
            startA: { ...m.a }, startB: { ...m.b },
            line: el.querySelector('#sm' + i), hit: t, lbl: el.querySelector('#smlbl' + i),
            ha: el.querySelector('#smh' + i + 'a'), hb: el.querySelector('#smh' + i + 'b'),
            hita: el.querySelector('[data-sm="' + i + '"][data-end="a"]'),
            hitb: el.querySelector('[data-sm="' + i + '"][data-end="b"]') };
          el.setPointerCapture(e.pointerId); e.preventDefault(); return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          this.pushUndo(); const id = t.dataset.id;
          this.S.removed[id] = !this.S.removed[id];
          this.render();
        }
      } else if (this.mode === 'besi') {
```
Ganti SELURUH blok itu jadi:
```js
      } else if (this.mode === 'support') {
        // Redesign Support (21 Agustus): SEMUA sub-elemen pakai pola Tiang -- tap cepat tanpa
        // gerak = no-op, geser = pindah, tahan diam 450ms = menu. pushUndo() TIDAK di sini
        // (banyak gestur berakhir tanpa mutasi) -- dipindah ke pointermove persis saat gerakan
        // nyata pertama terdeteksi, sama pola persis Tiang.
        if (t.dataset.sm != null) {
          // Titik ujung support manual: menu-nya SAMA dengan badan garis (Hapus = hapus seluruh
          // garis) -- struktur data {a,b} tak terpisahkan, tak ada konsep "hapus 1 titik saja"
          // (lihat spec Keputusan #2).
          const i = +t.dataset.sm, end = t.dataset.end;
          const myDrag = { type: 'sup', i, end, startPt: cm, moved: false,
            hit: t, h: el.querySelector('#smh' + i + end), line: el.querySelector('#sm' + i) };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, 'Sm_' + i);
            drag = null;
          }, 450);
          return;
        }
        if (this.armed === 'addSupport') {
          if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
          else { this.pushUndo(); this.S.supportsManual.push({ a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) } }); this.addSupportPt = null; this.armed = null; this.setHint(); this.render(); }
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('Sm_')) {
          const i = +t.dataset.id.slice(3);
          const m = this.S.supportsManual[i];
          const myDrag = { type: 'supline', i, startPt: cm, moved: false,
            startA: { ...m.a }, startB: { ...m.b },
            line: el.querySelector('#sm' + i), hit: t, lbl: el.querySelector('#smlbl' + i),
            ha: el.querySelector('#smh' + i + 'a'), hb: el.querySelector('#smh' + i + 'b'),
            hita: el.querySelector('[data-sm="' + i + '"][data-end="a"]'),
            hitb: el.querySelector('[data-sm="' + i + '"][data-end="b"]') };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, 'Sm_' + i);
            drag = null;
          }, 450);
          return;
        }
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          // Support grid otomatis: sekarang BISA digeser (dikunci searah, lihat pointermove tipe
          // "supgrid" & DenahConv.lockSupportAxis) -- begitu dilepas dgn gerakan nyata, "naik
          // kelas" jadi entri supportsManual (lihat end()). Tahan diam 450ms tanpa gerak = menu
          // Sertakan/Kecualikan + Ganti Material.
          const id = t.dataset.id;
          const mem = DenahConv.buildMembers(this.S);
          const m = mem.find(x => x.id === id);
          if (!m) return;
          const myDrag = { type: 'supgrid', id, startPt: cm, moved: false,
            startA: { ...m.geom.a }, startB: { ...m.geom.b }, hit: t };
          drag = myDrag;
          el.setPointerCapture(e.pointerId); e.preventDefault();
          myDrag.longPressTimer = setTimeout(() => {
            if (drag !== myDrag || myDrag.moved) return;
            this.openSupportMenu(e, id);
            drag = null;
          }, 450);
        }
      } else if (this.mode === 'besi') {
```

- [ ] **Step 3: Tambah gerak untuk titik ujung (`type: 'sup'`) — pushUndo pindah ke pointermove**

Cari (di dalam `pointermove`):
```js
        } else if (drag.type === 'sup') {
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 1.2;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          this.S.supportsManual[drag.i][drag.end] = snapped;
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px2);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py2);
          drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2);
          drag.hit.setAttribute('cx', px2); drag.hit.setAttribute('cy', py2);
        } else if (drag.type === 'box') {
```
Ganti jadi:
```js
        } else if (drag.type === 'sup') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 1.2;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          this.S.supportsManual[drag.i][drag.end] = snapped;
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px2);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py2);
          drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2);
          drag.hit.setAttribute('cx', px2); drag.hit.setAttribute('cy', py2);
        } else if (drag.type === 'box') {
```

- [ ] **Step 4: `supline` — hilangkan `pushUndo()` di pointerdown (sudah dihapus di Step 2), pastikan pointermove-nya push undo saat gerak pertama**

Cari (di dalam `pointermove`, tipe `supline`):
```js
        } else if (drag.type === 'supline') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
```
Ganti jadi:
```js
        } else if (drag.type === 'supline') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
```

- [ ] **Step 5: Tambah cabang `pointermove` baru untuk `type: 'supgrid'`**

Cari (akhir blok `pointermove`, tepat sebelum `}` penutup `if (drag.type === 'boxgroup')` yang diikuti `}` penutup arrow function pointermove):

```js
          if (drag.poly) drag.poly.setAttribute('points', drag.vertIdx.map(vi => `${X(this.S.verts[vi].x)},${Y(this.S.verts[vi].y)}`).join(' '));
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```
Ganti jadi (tambah 1 blok `else if` baru SEBELUM `}` penutup terakhir):
```js
          if (drag.poly) drag.poly.setAttribute('points', drag.vertIdx.map(vi => `${X(this.S.verts[vi].x)},${Y(this.S.verts[vi].y)}`).join(' '));
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'supgrid') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) {
            drag.moved = true;
            if (drag.longPressTimer) { clearTimeout(drag.longPressTimer); drag.longPressTimer = null; }
            this.pushUndo();
          }
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const locked = DenahConv.lockSupportAxis(drag.id, drag.startA, drag.startB, dx, dy);
          drag.curA = locked.a; drag.curB = locked.b;
          const ax = X(locked.a.x), ay = Y(locked.a.y), bx = X(locked.b.x), by = Y(locked.b.y);
          const line = el.querySelector('#sg_' + drag.id);
          if (line) { line.setAttribute('x1', ax); line.setAttribute('y1', ay); line.setAttribute('x2', bx); line.setAttribute('y2', by); }
          if (drag.hit) { drag.hit.setAttribute('x1', ax); drag.hit.setAttribute('y1', ay); drag.hit.setAttribute('x2', bx); drag.hit.setAttribute('y2', by); }
        }
      }
    });
```

- [ ] **Step 6: `end()` — `sup` (titik ujung) tidak lagi selalu snap (cuma kalau `moved`)**

Cari:
```js
      else if (drag.type === 'sup') {
        // Snap grid biasa BISA menggeser lagi titik yang barusan pas ortho-snap-kan ke anchor
        // (anchor sering tak persis kelipatan grid — datang dari resize/"Ukur Sisi" presisi yang
        // sengaja tak di-snap). Kalau sumbu itu SUDAH persis sama anchor (ortho-snap aktif pas
        // drag), pertahankan persis — jangan di-snap-grid lagi, biar tak jadi bengkok pas dilepas.
        const otherEnd = drag.end === 'a' ? 'b' : 'a';
        const anchor = this.S.supportsManual[drag.i][otherEnd];
        const p = this.S.supportsManual[drag.i][drag.end];
        this.S.supportsManual[drag.i][drag.end] = {
          x: p.x === anchor.x ? p.x : this.snap(p.x),
          y: p.y === anchor.y ? p.y : this.snap(p.y),
        };
      }
```
Ganti jadi:
```js
      else if (drag.type === 'sup') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved) {
          // Snap grid biasa BISA menggeser lagi titik yang barusan pas ortho-snap-kan ke anchor
          // (anchor sering tak persis kelipatan grid — datang dari resize/"Ukur Sisi" presisi yang
          // sengaja tak di-snap). Kalau sumbu itu SUDAH persis sama anchor (ortho-snap aktif pas
          // drag), pertahankan persis — jangan di-snap-grid lagi, biar tak jadi bengkok pas dilepas.
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const p = this.S.supportsManual[drag.i][drag.end];
          this.S.supportsManual[drag.i][drag.end] = {
            x: p.x === anchor.x ? p.x : this.snap(p.x),
            y: p.y === anchor.y ? p.y : this.snap(p.y),
          };
        }
        // !moved dan menu belum sempat kebuka (dilepas cepat < 450ms) -> tak ada aksi, titik
        // tetap di tempat semula. Sama pola persis Tiang -- tak lagi ambigu drag vs menu.
      }
```

- [ ] **Step 7: `end()` — `supline` hapus perilaku "tap = hapus", ganti jadi no-op**

Cari:
```js
      else if (drag.type === 'supline') {
        if (!drag.moved) {
          this.S.supportsManual.splice(drag.i, 1);
        } else {
```
Ganti jadi:
```js
      else if (drag.type === 'supline') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved) {
```
(Baris-baris SETELAH `if (drag.moved) {` sampai akhir blok `else if (drag.type === 'supline')` yang lama TETAP SAMA PERSIS — hanya bagian `if (!drag.moved) { splice }` yang dibuang, ganti `else {` jadi `if (drag.moved) {`.)

- [ ] **Step 8: `end()` — tambah cabang baru `supgrid` (konversi jadi manual)**

Cari:
```js
      else if (drag.type === 'boxgroup') {
        if (drag.moved) {
```
Cari juga akhir blok `boxgroup` (baris `this._hideAlignGuides(); }` sebelum `drag = null; this.render(); };`):
```js
          drag.vertIdx.forEach(vi => { const p = this.S.verts[vi]; this.S.verts[vi] = { x: p.x + shiftX, y: p.y + shiftY }; });
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```
Ganti jadi (tambah 1 blok `else if` baru SEBELUM baris `drag = null; this.render(); };`):
```js
          drag.vertIdx.forEach(vi => { const p = this.S.verts[vi]; this.S.verts[vi] = { x: p.x + shiftX, y: p.y + shiftY }; });
        }
        this._hideAlignGuides();
      }
      else if (drag.type === 'supgrid') {
        if (drag.longPressTimer) clearTimeout(drag.longPressTimer);
        if (drag.moved && drag.curA && drag.curB) {
          // "Naik kelas": ID grid tak stabil lintas render (lihat DenahConv.lockSupportAxis
          // komentar + spec Keputusan #4) -- JANGAN simpan offset nempel ke ID itu. Konversi jadi
          // entri supportsManual sungguhan (ID stabil Sm_i), lalu kecualikan posisi grid asal
          // biar tak dobel tergambar.
          const a = { x: this.snap(drag.curA.x), y: this.snap(drag.curA.y) };
          const b = { x: this.snap(drag.curB.x), y: this.snap(drag.curB.y) };
          const newIdx = this.S.supportsManual.length;
          this.S.supportsManual.push({ a, b });
          if (this.S.matOverride[drag.id] != null) {
            this.S.matOverride['Sm_' + newIdx] = this.S.matOverride[drag.id];
            delete this.S.matOverride[drag.id];
          }
          this.S.removed[drag.id] = true;
        }
        // !moved dan menu belum sempat kebuka (dilepas cepat < 450ms) -> tak ada aksi sama sekali.
      }
      drag = null; this.render(); };
```

- [ ] **Step 9: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 10: Jalankan ulang test Task 1 + test lama (pastikan tak ada regresi logic murni)**

Run: `node tests/rangka/test_support_pola.mjs && node tests/rangka/test_tiang_numerik.mjs`
Expected: `=== SEMUA TES LULUS ===` untuk keduanya (drag/menu interaktif TIDAK tercakup test ini — lihat Global Constraints; ini cuma jaga logika murni tak rusak).

- [ ] **Step 11: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): redesign interaksi Support -- tap=no-op, geser=pindah, tahan=menu; grid bisa digeser+naik-kelas jadi manual"
```

---

### Task 4: Label "S{n}" di menu Ganti Material ikut nomor manual-only

**Files:**
- Modify: `public/js/denah-editor.js` — method `openMatMenu()` (~baris 963-992)

**Interfaces:**
- Consumes: `DenahConv.numberSupportsManual` (Task 1).

- [ ] **Step 1: Ganti perhitungan label support di `openMatMenu()`**

Cari:
```js
    const mem = this.getMembers();
    const jenisNama = { frame: 'Frame', support: 'Support', tiang: 'Tiang' };
    const m = mem.find(x => x.id === id);
    let label = id;
    if (m) {
      // frame/tiang: m.nama sudah "F3"/"T2". support: nomor dihitung ulang sesuai urutan render
      // (nama mentah cuma "S" generik, bukan bernomor).
      const code = m.jenis === 'support' ? 'S' + (mem.filter(x => x.jenis === 'support').findIndex(x => x.id === id) + 1) : m.nama;
      label = `${jenisNama[m.jenis]} ${code} · ${m.panjang}cm`;
    }
```
Ganti jadi:
```js
    const mem = this.getMembers();
    const jenisNama = { frame: 'Frame', support: 'Support', tiang: 'Tiang' };
    const m = mem.find(x => x.id === id);
    let label = id;
    if (m) {
      // frame/tiang: m.nama sudah "F3"/"T2". support MANUAL: nomor dari DenahConv.numberSupportsManual
      // (SATU sumber sama dgn label kanvas & panel, lihat Task 1/3). Support GRID: tak dinomori
      // lagi (ID-nya tak stabil lintas render), cukup ditandai "grid" biar user tahu ini bukan
      // support yang bisa di-Fokus dari panel.
      let code;
      if (m.jenis === 'support') {
        code = id.startsWith('Sm_') ? 'S' + DenahConv.numberSupportsManual(mem)[id] : 'grid';
      } else {
        code = m.nama;
      }
      label = `${jenisNama[m.jenis]} ${code} · ${m.panjang}cm`;
    }
```

- [ ] **Step 2: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 3: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "fix(denah): label Ganti Material pakai nomor S{n} manual-only, bukan gabungan grid"
```

---

### Task 5: Panel daftar Support (S1/S2/... + Fokus/Hapus)

**Files:**
- Modify: `public/js/denah-editor.js` — HTML shell (tambah container, setelah `tiangPanel` ~baris 350), method baru `renderSupportPanel()` (setelah `renderTiangPanel`, ~baris 894), wire ke `render()` (~baris 1184)

**Interfaces:**
- Consumes: `DenahConv.numberSupportsManual` (Task 1), `mem` (parameter yang sudah dilempar ke `renderTiangPanel` di `render()`, dipakai ulang).

- [ ] **Step 1: Tambah container panel di HTML shell**

Cari:
```html
  <div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="tiangPanel"></div>
```
Tambahkan PERSIS setelah baris itu:
```html
  <div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="supportPanel"></div>
```

- [ ] **Step 2: Tambah method `renderSupportPanel()`**

Cari akhir method `renderTiangPanel()`:
```js
    this._q('[data-role=tTambah]').onclick = () => {
      const dx = DenahConv.parseCmValue(xAdd.value), dy = DenahConv.parseCmValue(yAdd.value);
      if (dx == null || dy == null) {
        showMsg('Isi X/Y dengan angka (koma/titik boleh) sebelum menambah tiang.');
        return;
      }
      showMsg('');
      this.clearTiangPreview();
      this.pushUndo();
      this.S.tiang.push(this.clampTiang(DenahConv.tiangFromOffset(this.S, dx, dy)));
      this.render();
    };
  }
```
Tambahkan method baru PERSIS setelah `}` penutup `renderTiangPanel()` (sebelum komentar `// Preview Task 3...`):
```js

  // Panel daftar Support manual (Redesign Support, 21 Agustus) -- meniru renderTiangPanel di atas,
  // TANPA input X/Y (geser di canvas sudah cukup, lihat spec Keputusan #6). Support grid otomatis
  // SENGAJA tak masuk sini -- belum jadi entri manual, tak addressable/tak stabil (Keputusan #5).
  renderSupportPanel(mem) {
    const panel = this._q('[data-role=supportPanel]');
    if (!panel) return;
    panel.style.display = this.mode === 'support' ? '' : 'none';
    if (this.mode !== 'support') { panel.innerHTML = ''; return; }
    const supNum = DenahConv.numberSupportsManual(mem);
    const manualMem = mem.filter(m => m.jenis === 'support' && m.id.startsWith('Sm_'));
    const rows = manualMem.map(m => {
      const i = +m.id.slice(3);
      return `<div class="de-tiang-item" data-srow="${i}">
        <div class="de-tiang-head">
          <b style="font-size:12px">S${supNum[m.id]}</b>
          <div class="de-tiang-actions"><span class="de-mini" data-role="sFokus" data-i="${i}">Fokus</span><span class="de-mini" data-role="sHapus" data-i="${i}">Hapus</span></div>
        </div>
      </div>`;
    }).join('');
    panel.innerHTML =
      '<b style="font-size:12px;color:#334155">Daftar Support Manual</b>' +
      (rows || '<div style="font-size:12px;color:#94a3b8;margin-top:4px">Belum ada support manual. Geser support grid atau pakai "+ Support manual" untuk menambah.</div>');

    panel.querySelectorAll('[data-role=sFokus]').forEach(btn => {
      btn.onclick = () => {
        const i = +btn.dataset.i;
        const line = this.el.querySelector('#sm' + i);
        if (!line) return;
        this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
        const prevStroke = line.getAttribute('stroke'), prevW = line.getAttribute('stroke-width');
        line.setAttribute('stroke', '#facc15'); line.setAttribute('stroke-width', '6');
        setTimeout(() => { if (line.isConnected) { line.setAttribute('stroke', prevStroke); line.setAttribute('stroke-width', prevW); } }, 900);
      };
    });
    panel.querySelectorAll('[data-role=sHapus]').forEach(btn => {
      btn.onclick = () => {
        const i = +btn.dataset.i;
        this.pushUndo();
        this.S.supportsManual.splice(i, 1);
        this.render();
      };
    });
  }
```

- [ ] **Step 3: Panggil dari `render()`**

Cari:
```js
    this.renderSides(mem);
    this.renderBoxPanel();
    this.renderTiangPanel(mem);
    this._changed();
```
Ganti jadi:
```js
    this.renderSides(mem);
    this.renderBoxPanel();
    this.renderTiangPanel(mem);
    this.renderSupportPanel(mem);
    this._changed();
```

- [ ] **Step 4: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 5: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): panel daftar Support manual (S1/S2 + Fokus/Hapus)"
```

---

### Task 6: Konsolidasi kontrol Support ke 1 tab

**Files:**
- Modify: `public/js/denah-editor.js` — HTML shell (blok panel `support` ~baris 309-318, blok panel `mode` ~baris 326-337), `_wireRibbon()` (~baris 472-480)

**Interfaces:**
- Consumes: tidak ada dependency baru — pakai ulang `this.mode`, `this._qa('.de-tool')`, `this.render()`.

- [ ] **Step 1: Pindahkan "+ Support manual" ke tab Support, hapus tombol mode "Support" dari tab Mode**

Cari:
```html
    <div class="de-ribbon-panel" data-panel="support">
      <div class="de-row">
        <label>Arah support
          <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
        </label>
        <label>Kotak support (cm)<input type="number" data-role="inKotak" value="100" step="5" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
    </div>
```
Ganti jadi:
```html
    <div class="de-ribbon-panel" data-panel="support">
      <div class="de-row">
        <label>Arah support
          <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
        </label>
        <label>Kotak support (cm)<input type="number" data-role="inKotak" value="100" step="5" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
      <div class="de-row" style="margin-top:8px">
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
```

Lalu cari:
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
```
Ganti jadi (hapus tool "Support" DAN baris `btnAddSupport` lama -- sudah pindah ke atas):
```html
    <div class="de-ribbon-panel" data-panel="mode">
      <div class="de-tools">
        <span class="de-tool on" data-mode="bentuk">Bentuk</span>
        <span class="de-tool" data-mode="besi">Ganti besi</span>
        <span class="de-tool" data-mode="tiang">Tiang</span>
        <span class="de-mini" data-role="btnAddV">+ Sudut</span>
        <span class="de-mini" data-role="btnDelV">− Sudut</span>
        <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
      </div>
    </div>
```

- [ ] **Step 2: Tab Support otomatis menyalakan mode `support`**

Cari (`_wireRibbon()`):
```js
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) { closeRibbon(); return; }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
    });
```
Ganti jadi:
```js
    tabs.forEach(t => t.onclick = () => {
      const name = t.dataset.tab;
      if (openTab === name) { closeRibbon(); return; }
      if (openTab) { panels[openTab].classList.remove('on'); tabs.forEach(x => { if (x.dataset.tab === openTab) x.classList.remove('on'); }); }
      panels[name].classList.add('on');
      t.classList.add('on');
      strip.classList.add('open');
      openTab = name;
      // Tab Support = satu-satunya jalan masuk mode edit support sekarang (tombol mode "Support"
      // dihapus dari tab Mode, spec Keputusan #8-9, 21 Agustus). Tab LAIN tidak menyetel mode
      // (tetap decoupled seperti sebelumnya) -- cuma Support yang baru dikopel begini.
      if (name === 'support' && this.mode !== 'support') {
        this._qa('.de-tool').forEach(el2 => el2.classList.remove('on'));
        this.mode = 'support';
        this.armed = null; this.addSupportPt = null; this.boxPreview = null;
        this.setHint();
        this.render();
      }
    });
```

- [ ] **Step 3: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 4: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): konsolidasi kontrol Support ke 1 tab, buka tab = aktifkan mode support"
```

---

### Task 7: Regresi akhir + checklist verifikasi manual

**Files:** Tidak ada file baru — task verifikasi + dokumentasi checklist.

**Interfaces:**
- Consumes: seluruh Task 1-6.

- [ ] **Step 1: Jalankan semua test standalone yang tersentuh**

Run: `node tests/rangka/test_support_pola.mjs && node tests/rangka/test_tiang_numerik.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_konverter.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `=== SEMUA TES LULUS ===` (atau setara) untuk semua, tidak ada `FAIL`.

- [ ] **Step 2: Jalankan test PHP terkait rangka (pastikan `buildMembers`/id support tak mempengaruhi engine biaya PHP-nya)**

Run: `php tests/rangka/test_denah_blok.php && php tests/rangka/test_hitung.php && php tests/rangka/test_paduta.php && php tests/rangka/test_seed.php && php tests/rangka/test_stok.php && php tests/rangka/test_stok_material.php`
Expected: semua PASS, tidak ada FAIL (perubahan sesi ini murni JS front-end, tidak menyentuh PHP -- ini regresi jaga-jaga).

- [ ] **Step 3: Syntax check final**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit code 0).

- [ ] **Step 4: Update manifest guardrail (`tests/guardrail/manifest.json`) — daftarkan test baru**

`tests/guardrail/test_manifest.php` MEWAJIBKAN setiap file `test_*.php`/`*.mjs` di `tests/` terdaftar di manifest, kalau tidak CI guardrail gagal (persis insiden `preview_server.php` sebelumnya di sesi ini). Tambahkan entri untuk `tests/rangka/test_support_pola.mjs` ke array `tests` di `tests/guardrail/manifest.json`, format sama seperti entri `.mjs` lain yang sudah ada (`runner: "node"`, `requires_db: false`, `manual: false`).

Run setelah edit: `php tests/guardrail/test_manifest.php`
Expected: `PASS: manifest valid — N tes otomatis, M helper manual/excluded.` (N bertambah 1 dari sebelumnya, tidak ada FAIL).

- [ ] **Step 5: Checklist verifikasi manual (WAJIB dijalankan Bos langsung di browser/HP — TIDAK bisa diotomasi dari VPS ini, lihat Global Constraints)**

Tulis checklist ini ke pesan akhir buat Bos, bahasa awam dulu:

1. Buka project apapun di RAB Multi-Opsi, masuk halaman Denah.
2. **Tab Support**: klik tab "Support" — pastikan panel setelan (arah/kotak/saran) + tombol "+ Support manual" + daftar (kosong dulu kalau belum ada support manual) semuanya nongol di 1 tempat, dan mode kanvas otomatis siap edit support (coba tap garis support, harus responsif).
3. **Support grid — tap cepat**: tap sekali garis support grid (yang otomatis dari kotak rangka) TANPA geser — pastikan TIDAK toggle on/off lagi (dulu langsung toggle).
4. **Support grid — geser**: geser garis support grid horizontal — pastikan cuma naik-turun (tidak bisa miring/ke samping). Geser yang vertikal — pastikan cuma kiri-kanan. Lepas — pastikan garis itu sekarang muncul di daftar panel dengan nomor S1 (atau nomor berikutnya).
5. **Support grid — tahan**: tahan diam (jangan gerak) di garis support grid ~0,5 detik — pastikan muncul menu Sertakan/Kecualikan + Ganti Material (bukan langsung toggle).
6. **Support manual — tap cepat**: tap sekali garis support manual TANPA geser — pastikan TIDAK hilang lagi (dulu langsung hilang).
7. **Support manual — geser**: geser garis support manual — pastikan tetap pindah normal seperti biasa.
8. **Support manual — tahan**: tahan diam di garis support manual ~0,5 detik — pastikan muncul menu Hapus + Ganti Material. Coba juga tahan di titik ujungnya — menu yang sama harus muncul.
9. **Panel — Fokus**: di panel daftar Support, klik "Fokus" pada salah satu baris — pastikan halaman scroll ke canvas dan garis itu berkedip kuning sebentar.
10. **Panel — Hapus**: klik "Hapus" di panel — pastikan garis itu hilang dan nomor S sisanya tidak bolong (mis. kalau hapus S2 dari [S1,S2,S3], sisanya jadi [S1,S2]).
11. **Undo/Redo**: setelah beberapa aksi di atas, coba tombol Undo lalu Redo (di quickbar atas) — pastikan semua perubahan support (geser/hapus/toggle) bisa dibatalkan & diulang.
12. **Frame tidak berubah**: pastikan interaksi Frame (klik sisi buka input panjang, drag sudut) masih persis seperti sebelumnya — TIDAK ikut berubah sesi ini.
13. **Tiang tidak berubah**: pastikan mode Tiang (tab Mode) masih ada dan berfungsi seperti sebelumnya.

**Jangan tandai sesi ini "selesai" ke Bos sebelum checklist manual di atas benar-benar dicoba dan lolos.**
