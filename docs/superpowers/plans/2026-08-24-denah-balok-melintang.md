# Denah — Balok Melintang (Portal Frame + Bracing) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Elemen kelas 1 baru "Balok Melintang" (B1..Bn) di editor denah — dua ujung yang bisa dipilih tipe Tiang/Titik bebas (menutup portal frame + bracing miring + custom), pilih besi per balok, hitungan WF per batang 6m di legend.

**Architecture:** Entitas baru flat array `S.balok[] = {no, a, b, material}` + `S.balokSeq`, endpoint `{t: <tiangIdx>} | {p: {x,y}}`. Sepenuhnya independen dari fase pratinjau/terkunci Support. Semua di `public/js/denah-editor.js`.

**Tech Stack:** Vanilla JS classic script IIFE; test node `tests/rangka/*.mjs` pola read+eval.

## Global Constraints

- Classic script IIFE, DILARANG `export`/`import`; ekspos via `globalThis`.
- File test baru WAJIB terdaftar di `tests/guardrail/manifest.json` DI COMMIT YANG SAMA (runner `"node"`).
- Mutasi model selalu `this.pushUndo()` SEBELUM mutasi.
- Perilaku SEMUA fitur existing (frame, tiang, support pratinjau/terkunci, garis numerik) TIDAK boleh berubah — semua test lama harus tetap lolos.
- Komentar bahasa Indonesia gaya file. Jangan commit `public/hot`.
- `php scripts/canopi-check` hijau sebelum tiap commit.

## Model Data

```js
S.balok = [                                                  // absen/null = tak ada balok
  { no: 1, a: {t: 0}, b: {t: 2}, material: 'WF 100' },       // portal: 2 tiang
  { no: 2, a: {t: 0}, b: {p: {x, y}}, material: 'WF 100' },  // bracing: 1 tiang + 1 titik bebas
  { no: 3, a: {p: {x1,y1}}, b: {p: {x2,y2}}, material: 'WF 100' }, // custom
]
S.balokSeq = 4                    // nomor berikutnya (tak pernah turun; hapus TIDAK me-renumber)
S.matDefault.balok = 'WF 100'     // default besi balok (nebak: nama besi mengandung "wf")
```

Endpoint shape: `{t: <int>}` = referensi tiang di index tsb; `{p: {x, y}}` = titik bebas cm. Id member = `'B' + no`.

---

### Task 1: Model + fungsi murni + cabang `buildMembers` + test

**Files:**
- Modify: `public/js/denah-editor.js` — `DenahConv` (+4 fungsi murni), `buildMembers` (cabang balok).
- Create: `tests/rangka/test_balok_melintang.mjs`.
- Modify: `tests/guardrail/manifest.json`.

**Interfaces:**
- `DenahConv.resolveBalokEndpoint(S, end)` → `{x,y}` (tiang: dari `S.tiang[t]`; titik bebas: `p` disalin) atau `null` (referensi tiang hilang).
- `DenahConv.cascadeTiangRemoval(S, tiangIdx)` → patch `{tiang, balok, balokSeq}` — hapus tiang di idx tsb, referensi balok ke tiang itu **dikonversi jadi titik bebas** (freeze posisi terakhir tiang), ref ke tiang > idx **di-shift −1**. `balokSeq` tak berubah (nomor stabil). MURNI.
- `DenahConv.hitungBatangWF(mem, panjangBatang = 600)` → `{ 'WF 100': 3, 'WF 125': 1 }` — total panjang balok per material yang matches `/wf/i`, dibagi 600, `Math.ceil`. Material non-WF diabaikan.
- Cabang `buildMembers` untuk balok: member `{id:'B'+no, nama:'B'+no, jenis:'balok', panjang, material, geom:{a,b}}`. Ref-patah dilewati diam-diam.

- [ ] **Step 1: Test dulu (FAIL)** — `tests/rangka/test_balok_melintang.mjs`:

```js
// FILE: tests/rangka/test_balok_melintang.mjs
// Jalankan: node tests/rangka/test_balok_melintang.mjs
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../../public/js/denah-editor.js', import.meta.url), 'utf8');
(0, eval)(code);
const { DenahConv } = globalThis;

let fail = false;
const check = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name}` + (ok ? '' : ` (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`));
  if (!ok) fail = true;
};

const base = () => ({
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  kotak: 100, arah: 'h', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X', balok: 'WF 100' }, matOverride: {},
});

// ── resolveBalokEndpoint ──
{
  const S = base(); S.tiang = [{ x: 10, y: 20 }, { x: 100, y: 200 }];
  check('endpoint tiang', DenahConv.resolveBalokEndpoint(S, { t: 1 }), { x: 100, y: 200 });
  check('endpoint titik bebas (disalin, bukan referensi)', DenahConv.resolveBalokEndpoint(S, { p: { x: 5, y: 6 } }), { x: 5, y: 6 });
  check('endpoint tiang tak ada -> null', DenahConv.resolveBalokEndpoint(S, { t: 9 }), null);
}

// ── buildMembers cabang balok ──
{
  const S = base(); S.tiang = [{ x: 0, y: 0 }, { x: 300, y: 0 }];
  S.balok = [{ no: 1, a: { t: 0 }, b: { t: 1 }, material: 'WF 100' }];
  S.balokSeq = 2;
  const bl = DenahConv.buildMembers(S).filter(m => m.jenis === 'balok');
  check('balok member: id/panjang/material', [bl.length, bl[0].id, bl[0].panjang, bl[0].material], [1, 'B1', 300, 'WF 100']);
  // Ref patah -> dilewati diam-diam, sisanya tetap ada
  S.balok = [
    { no: 5, a: { t: 9 }, b: { t: 1 }, material: 'WF 100' },
    { no: 6, a: { t: 0 }, b: { p: { x: 100, y: 100 } }, material: 'WF 125' },
  ];
  const bl2 = DenahConv.buildMembers(S).filter(m => m.jenis === 'balok');
  check('ref patah dilewati; sisanya jalan', bl2.map(m => m.id), ['B6']);
  check('balok titik bebas: panjang benar', bl2[0].panjang, Math.round(Math.hypot(100, 100)));
}

// ── cascadeTiangRemoval ──
{
  const S = base();
  S.tiang = [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 200, y: 0 }];
  S.balok = [
    { no: 1, a: { t: 0 }, b: { t: 2 }, material: 'WF 100' },       // ke tiang yg dihapus: ujung A dibekukan
    { no: 2, a: { t: 1 }, b: { t: 2 }, material: 'WF 100' },       // ref ke t2 -> shift ke t1 (setelah t0 hilang, t1->t0, t2->t1)
    { no: 3, a: { p: { x: 5, y: 5 } }, b: { t: 1 }, material: 'WF 100' }, // titik bebas tak tersentuh; ref t1 -> shift t0
  ];
  S.balokSeq = 4;
  const p = DenahConv.cascadeTiangRemoval(S, 0);
  check('cascade: tiang tersisa', p.tiang, [{ x: 100, y: 0 }, { x: 200, y: 0 }]);
  check('cascade: B1 ujung A dibekukan ke posisi tiang lama, ujung B di-shift', p.balok[0], { no: 1, a: { p: { x: 0, y: 0 } }, b: { t: 1 }, material: 'WF 100' });
  check('cascade: B2 shift kedua ujung', p.balok[1], { no: 2, a: { t: 0 }, b: { t: 1 }, material: 'WF 100' });
  check('cascade: B3 titik bebas utuh, ref di-shift', p.balok[2], { no: 3, a: { p: { x: 5, y: 5 } }, b: { t: 0 }, material: 'WF 100' });
  check('cascade: balokSeq tak berubah (nomor stabil)', p.balokSeq, 4);
  check('cascade: S asal tak dimutasi', S.tiang.length, 3);
}
{
  // Balok yg KEDUA ujungnya ref ke tiang yg dihapus -> kedua ujungnya jadi titik bebas ke posisi lama.
  const S = base();
  S.tiang = [{ x: 0, y: 0 }, { x: 200, y: 0 }];
  S.balok = [{ no: 1, a: { t: 0 }, b: { t: 0 }, material: 'WF' }]; // sengaja aneh: A=B=t0
  S.balokSeq = 2;
  const p = DenahConv.cascadeTiangRemoval(S, 0);
  check('cascade: kedua ujung ref tiang hilang -> dua-duanya bebas',
    [p.balok[0].a, p.balok[0].b], [{ p: { x: 0, y: 0 } }, { p: { x: 0, y: 0 } }]);
}

// ── hitungBatangWF ──
{
  const mem = [
    { jenis: 'balok', panjang: 480, material: 'WF 100' },
    { jenis: 'balok', panjang: 720, material: 'WF 100' },   // total WF 100: 1200 -> 2 batang
    { jenis: 'balok', panjang: 550, material: 'WF 125' },   // WF 125: 550 -> 1 batang
    { jenis: 'balok', panjang: 300, material: 'Hollow 5x10' }, // non-WF diabaikan
    { jenis: 'frame', panjang: 900, material: 'WF 100' },      // non-balok diabaikan
  ];
  check('batang WF', DenahConv.hitungBatangWF(mem), { 'WF 100': 2, 'WF 125': 1 });
  check('batang standar boleh dikustom', DenahConv.hitungBatangWF([{ jenis: 'balok', panjang: 500, material: 'WF 100' }], 400), { 'WF 100': 2 });
  check('list balok kosong -> objek kosong', DenahConv.hitungBatangWF([]), {});
}

// ── kompat: model lama (tanpa S.balok) TIDAK berubah ──
{
  const S = base();
  const mem = DenahConv.buildMembers(S).map(m => m.jenis);
  check('kompat: fase pratinjau utuh tanpa balok', mem.includes('balok'), false);
}

process.exit(fail ? 1 : 0);
```

Jalankan → FAIL.

- [ ] **Step 2: Implementasi** — di `DenahConv` (setelah `moveManualReclip`):

```js
  // ---- Balok melintang (portal frame + bracing) ----
  // Endpoint balok: {t: tiangIdx} = referensi tiang (ikut geser); {p:{x,y}} = titik bebas.
  // resolveBalokEndpoint: kembalikan koordinat cm ATAU null kalau ref tiang tak valid
  // (dilewati diam-diam di buildMembers/render — data tetap ada, tinggal user perbaiki lewat panel).
  resolveBalokEndpoint(S, end) {
    if (end && end.t != null) {
      const t = (S.tiang || [])[end.t];
      return t ? { x: t.x, y: t.y } : null;
    }
    if (end && end.p) return { x: end.p.x, y: end.p.y };
    return null;
  },
  // Hapus tiang di idx + kaskade ke balok: ref ke tiang itu -> dibekukan jadi titik bebas
  // (posisi TERAKHIR tiang tsb), ref ke tiang > idx -> shift -1. balokSeq tak berubah.
  // MURNI: tidak memutasi S; caller Object.assign(S, patch).
  cascadeTiangRemoval(S, tiangIdx) {
    const tiangLama = (S.tiang || [])[tiangIdx];
    const tiang = (S.tiang || []).filter((_, i) => i !== tiangIdx);
    const remap = (end) => {
      if (!end || end.t == null) return end && end.p ? { p: { x: end.p.x, y: end.p.y } } : end;
      if (end.t === tiangIdx) return { p: { x: tiangLama.x, y: tiangLama.y } };
      return { t: end.t > tiangIdx ? end.t - 1 : end.t };
    };
    const balok = (S.balok || []).map(b => ({ no: b.no, a: remap(b.a), b: remap(b.b), material: b.material }));
    return { tiang, balok, balokSeq: S.balokSeq || (balok.reduce((m, b) => Math.max(m, b.no), 0) + 1) };
  },
  // Total batang standar per material WF (default batang 6m = 600cm). Non-WF diabaikan.
  hitungBatangWF(mem, panjangBatang = 600) {
    const tot = {};
    (mem || []).forEach(m => { if (m.jenis === 'balok' && /wf/i.test(m.material)) tot[m.material] = (tot[m.material] || 0) + m.panjang; });
    const out = {};
    Object.keys(tot).forEach(k => { out[k] = Math.ceil(tot[k] / panjangBatang); });
    return out;
  },
```

Di `buildMembers`, tambahkan cabang balok setelah loop tiang (member baru independen dari support/frame; ref patah dilewati diam-diam):

```js
    (S.balok || []).forEach(b => {
      const a = DenahConv.resolveBalokEndpoint(S, b.a), c = DenahConv.resolveBalokEndpoint(S, b.b);
      if (!a || !c) return;
      mem.push({ id: 'B' + b.no, nama: 'B' + b.no, jenis: 'balok', panjang: Math.round(dist(a, c)), material: b.material, geom: { a, b: c } });
    });
```

- [ ] **Step 3: Verifikasi + manifest + commit** — `node tests/rangka/test_balok_melintang.mjs` PASS, semua `tests/rangka/test_*.mjs` PASS, daftarkan test di manifest, `php scripts/canopi-check` PASS. Commit:

```bash
git add public/js/denah-editor.js tests/rangka/test_balok_melintang.mjs tests/guardrail/manifest.json
git commit -m "feat(denah): model balok melintang -- resolve endpoint + cascade hapus tiang + hitung batang WF"
```

---

### Task 2: Render layer balok + panel daftar + legend batang WF

**Files:**
- Modify: `public/js/denah-editor.js` — `constructor` (default balok), `defaultModel`, `render()` (layer + legend), `renderBalokPanel(mem)` BARU, `openMatMenu()` (label id 'B{n}'), `bindSvg()` (tap balok masuk mode ganti besi).

**Interfaces:**
- Layer balok di SVG: garis warna khas (`#a855f7` ungu), stroke-width 6, di atas support tapi di bawah frame; label `B{n} · {material} · {panjang}` di titik tengah ikut arah garis; endpoint yg ref tiang = titik solid warna sama, endpoint bebas = titik kosong (indikator visual).
- `renderBalokPanel(mem)`: dipanggil dari `render()`. Panel di tab **Tiang** BAWAH `tiangPanel` yang sudah ada. Header "Balok (n)" + toggle Lipat (`this.balokPanelOpen`); baris `B{n} · <deskripsi ujung> · {material} · {panjang}cm` + Fokus + Hapus + Ganti besi. Deskripsi ujung: `T1↔T3` / `T1↔bebas` / `bebas↔bebas`.
- Legend tambahan: baris "WF 100: 3 batang 6m" untuk tiap WF di `DenahConv.hitungBatangWF(mem)`.
- Tap balok saat mode 'besi' → `openMatMenu(e, 'B'+no)`.

- [ ] **Step 1: Default material balok di constructor** — di blok default (setelah `matDefault.tiang`):

```js
      if (!this.S.matDefault.balok) this.S.matDefault.balok = cari('wf') || this.besi[0].nama;
```

Di `defaultModel()`: tambah `balok: []`, `balokSeq: 1`, dan `matDefault.balok: ''`.

- [ ] **Step 2: Render layer + label** — di `render()` setelah loop tiang (SEBELUM `armed === 'addBox'` preview):

```js
    // Balok melintang (Portal/Bracing). Layer di atas support, di bawah frame — visual utama.
    // Ref tiang patah? `buildMembers` sudah menyaringnya (member tak dibuat); di sini pakai `mem` filter.
    const bSel = this.selBalok;
    mem.filter(m => m.jenis === 'balok').forEach(m => {
      const c = cmap[m.material];
      const no = +m.id.slice(1);
      const selected = bSel === no;
      const stroke = selected ? '#facc15' : (c || '#a855f7');
      const sw = selected ? 8 : 6;
      const ax = X(m.geom.a.x), ay = Y(m.geom.a.y), bx = X(m.geom.b.x), by = Y(m.geom.b.y);
      s += `<line x1="${ax}" y1="${ay}" x2="${bx}" y2="${by}" stroke="${stroke}" stroke-width="${sw}" stroke-linecap="round"><title>${m.material} • ${m.panjang}cm</title></line>`;
      s += `<line x1="${ax}" y1="${ay}" x2="${bx}" y2="${by}" stroke="transparent" stroke-width="18" data-id="${m.id}" class="hit" style="cursor:pointer"/>`;
      // Label di titik tengah ikut arah garis (pola sama frame — jangan pernah terbalik).
      const lx = (ax + bx) / 2, ly = (ay + by) / 2;
      let ang = Math.atan2(by - ay, bx - ax) * 180 / Math.PI;
      if (ang > 90 || ang <= -90) ang += 180;
      const fullText = `${m.nama} · ${m.material} · ${m.panjang}`;
      const shortText = m.nama;
      const label = DenahConv.supportLabelText(fullText, shortText, dist(m.geom.a, m.geom.b) * this.SC, selected);
      if (label) s += `<text x="${lx}" y="${ly - 10}" fill="#e2e8f0" font-size="12" font-weight="700" text-anchor="middle" dominant-baseline="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3" transform="rotate(${ang} ${lx} ${ly - 10})">${label}</text>`;
    });
    // Endpoint marker (visual saja): ref tiang = solid ungu, bebas = kosong.
    (S.balok || []).forEach(b => {
      const a = DenahConv.resolveBalokEndpoint(S, b.a), c = DenahConv.resolveBalokEndpoint(S, b.b);
      [['a', a, b.a], ['b', c, b.b]].forEach(([, pt, end]) => {
        if (!pt) return;
        const cx = X(pt.x), cy = Y(pt.y), isTiang = end && end.t != null;
        s += `<circle cx="${cx}" cy="${cy}" r="5" fill="${isTiang ? '#a855f7' : '#0f2740'}" stroke="#a855f7" stroke-width="2" style="pointer-events:none"/>`;
      });
    });
```

- [ ] **Step 3: Legend batang WF** — di render() setelah `this._q('[data-role=legend]').innerHTML = ...`:

```js
    // Info tambahan batang WF (dibeli per-batang 6m; sisa potongan biar user tulis manual di catatan RAB).
    const batang = DenahConv.hitungBatangWF(mem);
    const batangKeys = Object.keys(batang);
    if (batangKeys.length) {
      this._q('[data-role=legend]').innerHTML += ' <span style="margin-left:12px;color:#a855f7;font-weight:600">' +
        batangKeys.map(k => `${k}: ${batang[k]} batang 6m`).join(' · ') + '</span>';
    }
```

- [ ] **Step 4: Panel `renderBalokPanel(mem)`** — method baru di kelas (di bawah `renderSupportPanel`):

```js
  // Panel daftar Balok Melintang di tab Tiang. Muncul selalu di mode tiang (form tambah harus
  // terjangkau); baris ceklis tak diperlukan (balok pasti dipakai, tak ada konsep nonaktif).
  renderBalokPanel(mem) {
    const panel = this._q('[data-role=balokPanel]');
    if (!panel) return;
    if (this.mode !== 'tiang') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    panel.style.display = '';
    const list = this.S.balok || [];
    const desc = (end) => end && end.t != null ? 'T' + (end.t + 1) : 'bebas';
    const rows = !this.balokPanelOpen ? '' : list.map(b => {
      const a = DenahConv.resolveBalokEndpoint(this.S, b.a), c = DenahConv.resolveBalokEndpoint(this.S, b.b);
      const panj = (a && c) ? Math.round(Math.hypot(c.x - a.x, c.y - a.y)) + 'cm' : 'ref patah';
      const sel = this.selBalok === b.no;
      return `<div class="de-tiang-item" data-brow="${b.no}" style="${sel ? 'background:#fef9c3;border-radius:6px;padding-left:4px;' : ''}">
        <div class="de-tiang-head">
          <div><b style="font-size:12px">B${b.no}</b> <span style="color:#64748b;font-size:11px">${desc(b.a)}↔${desc(b.b)} · ${b.material} · ${panj}</span></div>
          <div class="de-tiang-actions">
            <span class="de-mini" data-role="bFokus" data-no="${b.no}">Fokus</span>
            <span class="de-mini" data-role="bBesi" data-no="${b.no}">Ganti besi</span>
            <span class="de-mini" data-role="bHapus" data-no="${b.no}">Hapus</span>
          </div>
        </div>
      </div>`;
    }).join('');
    panel.innerHTML =
      `<div class="de-tiang-head">
        <b style="font-size:12px">Balok Melintang (${list.length})</b>
        <span class="de-mini" data-role="bLipat">${this.balokPanelOpen ? 'Lipat' : 'Buka'}</span>
      </div>${rows}<div data-role="bMsg" style="font-size:11px;color:#dc2626;margin-top:4px"></div>`;
    this._q('[data-role=bLipat]').onclick = () => { this.balokPanelOpen = !this.balokPanelOpen; this.renderBalokPanel(mem); };
    panel.querySelectorAll('[data-role=bFokus]').forEach(btn => btn.onclick = () => { this.selBalok = +btn.dataset.no; this.balokPanelOpen = true; this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' }); this.render(); });
    panel.querySelectorAll('[data-role=bHapus]').forEach(btn => btn.onclick = () => {
      const no = +btn.dataset.no;
      this.pushUndo();
      this.S.balok = (this.S.balok || []).filter(x => x.no !== no);
      delete this.S.matOverride['B' + no];
      if (this.selBalok === no) this.selBalok = null;
      this.render();
    });
    panel.querySelectorAll('[data-role=bBesi]').forEach(btn => btn.onclick = (ev) => this.openMatMenu(ev, 'B' + btn.dataset.no));
  }
```

- [ ] **Step 5: Slot panel di shellHTML** — tambah `<div class="de-card de-tiang-panel" style="display:none;margin-top:10px;padding:10px" data-role="balokPanel"></div>` setelah `supportPanel`. State constructor tambah `this.selBalok = null;` dan `this.balokPanelOpen = false;`. `render()` panggil `this.renderBalokPanel(mem)` setelah `renderSupportPanel`.

- [ ] **Step 6: `openMatMenu` label id B** — di blok label, tambah cabang:

```js
      if (m.jenis === 'balok') code = m.nama;   // "B3"
```

- [ ] **Step 7: bindSvg — tap balok mode besi** — di cabang `else if (this.mode === 'besi')` sudah ada `if (t.dataset.id) this.openMatMenu(e, t.dataset.id);` — otomatis include id 'B{n}' karena render nulis `data-id="B{n}"`. Cek saja tak perlu ubah.

- [ ] **Step 8: Verifikasi + commit** — regresi + canopi-check. Commit:

```
feat(denah): render balok melintang + panel daftar + legend batang WF
```

---

### Task 3: Form "+ Balok" (tipe ujung dinamis) + ghost preview + cascade hapus tiang

**Files:**
- Modify: `public/js/denah-editor.js` — `renderBalokPanel` (tambah form), method preview baru `drawBalokPreview`/`clearBalokPreview`, wiring hapus tiang di `tiangMenuHapus` dan `tHapus` (pakai `cascadeTiangRemoval`), `undo/redo/setModel` (reset `selBalok`).

**Interfaces:**
- Form di panel Balok (di bawah rows):
  ```
  + Balok melintang
  Ujung 1: [Tipe: Tiang ▼]  [T1 ▼]        (kalau Titik bebas: [X cm][Y cm])
  Ujung 2: [Tipe: Tiang ▼]  [T2 ▼]
  Besi:    [WF 100 ▼]
  [Tambah] [Batal]
  ```
- Ghost preview live: garis dashed ungu di kanvas saat kedua ujung valid.
- Tambah: `pushUndo` → push entri `{no: balokSeq, a, b, material}` → `balokSeq++` → `selBalok = no` → render + hint.
- Cascade hapus tiang: `tiangMenuHapus` dan `tHapus` di panel Tiang panggil `Object.assign(this.S, DenahConv.cascadeTiangRemoval(this.S, i))` (bukan splice manual) → tampil hint kalau ada balok yg ujungnya dibekukan.

- [ ] **Step 1: Form di `renderBalokPanel`** — tambah setelah rows (hanya saat `balokPanelOpen`):

```js
    const nTiang = (this.S.tiang || []).length;
    const opsTiang = Array.from({ length: nTiang }, (_, i) => `<option value="${i}">T${i + 1}</option>`).join('');
    const opsBesi = this.besi.map(b => `<option>${b.nama}</option>`).join('');
    const endHtml = (label, prefix) => `
      <div style="border:1px solid #e2e8f0;border-radius:6px;padding:6px;margin-top:4px">
        <label style="font-size:11px;color:#334155">${label}
          <select data-role="${prefix}Tipe" style="margin-left:6px"><option value="t">Tiang</option><option value="p">Titik bebas</option></select>
        </label>
        <div data-role="${prefix}Tiang" style="margin-top:4px"><label style="font-size:11px">Tiang<select data-role="${prefix}T" ${nTiang ? '' : 'disabled'}>${opsTiang || '<option>—</option>'}</select></label></div>
        <div data-role="${prefix}Bebas" style="display:none;margin-top:4px" class="de-tiang-fields">
          <label style="font-size:11px">X (cm)<input type="text" inputmode="decimal" data-role="${prefix}X"></label>
          <label style="font-size:11px">Y (cm)<input type="text" inputmode="decimal" data-role="${prefix}Y"></label>
        </div>
      </div>`;
    const form = !this.balokPanelOpen ? '' :
      `<div class="de-tiang-item" style="border-bottom:0">
        <div class="de-tiang-head"><b style="font-size:12px">+ Balok melintang</b></div>
        ${endHtml('Ujung 1', 'b1')}
        ${endHtml('Ujung 2', 'b2')}
        <div style="margin-top:6px"><label style="font-size:11px">Besi<select data-role="bMat">${opsBesi}</select></label></div>
        <div class="de-tiang-actions" style="margin-top:6px">
          <span class="de-mini de-tiang-apply" data-role="bTambah">Tambah</span>
          <span class="de-mini" data-role="bBatal">Batal</span>
        </div>
      </div>`;
    // Sisipkan `form` sebelum `<div data-role="bMsg">` (jangan setelah — pesan error terkait Tambah)
```

Set default select besi ke `this.S.matDefault.balok`. Wiring tipe → toggle blok Tiang/Bebas visibility per prefix.

- [ ] **Step 2: Baca form + preview** — helper murni di kelas:

```js
  // Baca form ujung (prefix b1/b2) → {t:i} | {p:{x,y}} | null (belum lengkap).
  _readBalokEnd(prefix) {
    const tipe = this._q(`[data-role=${prefix}Tipe]`).value;
    if (tipe === 't') {
      const sel = this._q(`[data-role=${prefix}T]`);
      const t = +sel.value;
      return Number.isInteger(t) && (this.S.tiang || [])[t] ? { t } : null;
    }
    const x = DenahConv.parseCmValue(this._q(`[data-role=${prefix}X]`).value);
    const y = DenahConv.parseCmValue(this._q(`[data-role=${prefix}Y]`).value);
    return (x != null && y != null) ? { p: { x, y } } : null;
  }
```

Wiring input `oninput`/`onchange` semua field bTambah form → panggil `updatePreview()` yang:
- Baca `_readBalokEnd('b1')`/`b2` + resolve ke koordinat via `DenahConv.resolveBalokEndpoint(this.S, end)`.
- Kalau dua-duanya valid → `drawBalokPreview({a, b: c})`; lainnya → `drawBalokPreview(null)`.

- [ ] **Step 3: `drawBalokPreview` / `clearBalokPreview`** — pola sama `drawSupJalurPreview`:

```js
  drawBalokPreview(seg) {
    const svg = this._q('.de-canvas svg');
    if (!svg) return;
    const old = svg.querySelector('[data-balok-preview]');
    if (old) old.remove();
    if (!seg) return;
    const NS = 'http://www.w3.org/2000/svg';
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-balok-preview', '1');
    g.setAttribute('style', 'pointer-events:none');
    const ln = document.createElementNS(NS, 'line');
    [['x1', this.PAD + seg.a.x * this.SC], ['y1', this.PAD + seg.a.y * this.SC],
     ['x2', this.PAD + seg.b.x * this.SC], ['y2', this.PAD + seg.b.y * this.SC]].forEach(([k, v]) => ln.setAttribute(k, v));
    ln.setAttribute('stroke', '#a855f7'); ln.setAttribute('stroke-width', '4'); ln.setAttribute('stroke-dasharray', '8,5');
    g.appendChild(ln);
    svg.appendChild(g);
  }
  clearBalokPreview() { this.drawBalokPreview(null); }
```

- [ ] **Step 4: Wiring Tambah/Batal**:

```js
    const bTambah = this._q('[data-role=bTambah]');
    if (bTambah) bTambah.onclick = () => {
      const a = this._readBalokEnd('b1'), b = this._readBalokEnd('b2');
      const mat = this._q('[data-role=bMat]').value;
      if (!a || !b) { this._q('[data-role=bMsg]').textContent = 'Lengkapi kedua ujung (pilih tiang atau isi X/Y titik bebas).'; return; }
      const pa = DenahConv.resolveBalokEndpoint(this.S, a), pb = DenahConv.resolveBalokEndpoint(this.S, b);
      if (!pa || !pb || (pa.x === pb.x && pa.y === pb.y)) { this._q('[data-role=bMsg]').textContent = 'Dua ujung tak boleh sama.'; return; }
      this.pushUndo();
      this.S.balok = this.S.balok || [];
      const no = Math.max(this.S.balokSeq || 1, ...this.S.balok.map(x => x.no + 1), 1);
      this.S.balok.push({ no, a, b, material: mat });
      this.S.balokSeq = no + 1;
      this.selBalok = no;
      this.setHint(`Balok B${no} ditambah.`);
      this.render();
    };
    const bBatal = this._q('[data-role=bBatal]');
    if (bBatal) bBatal.onclick = () => { this.clearBalokPreview(); this._q('[data-role=bMsg]').textContent = ''; };
```

Preview otomatis hilang saat panel dilipat (`this.clearBalokPreview()` di handler `bLipat`) dan saat render (svg dibangun ulang).

- [ ] **Step 5: Cascade hapus tiang** — ganti dua lokasi yang saat ini `this.S.tiang.splice(i, 1)`:

Di `tiangMenuHapus.onclick`:
```js
    this._q('[data-role=tiangMenuHapus]').onclick = () => {
      if (this._tiangMenuIdx != null) {
        this.pushUndo();
        const i = this._tiangMenuIdx;
        const affected = (this.S.balok || []).filter(b => (b.a.t === i) || (b.b.t === i)).length;
        Object.assign(this.S, DenahConv.cascadeTiangRemoval(this.S, i));
        if (affected) this.setHint(`${affected} balok yg terhubung tiang T${i + 1} dibekukan ujungnya jadi titik bebas.`);
        this._closeTiangMenu(); this.render();
      }
    };
```

Sama di panel Tiang `tHapus`:
```js
      btn.onclick = () => {
        const i = +btn.dataset.i;
        this.pushUndo();
        const affected = (this.S.balok || []).filter(b => (b.a.t === i) || (b.b.t === i)).length;
        Object.assign(this.S, DenahConv.cascadeTiangRemoval(this.S, i));
        if (affected) this.setHint(`${affected} balok terhubung dibekukan.`);
        this.render();
      };
```

- [ ] **Step 6: Undo/Redo/setModel reset selBalok** — di `undo()`/`redo()` tambah `this.selBalok = null;` (setelah reset lainnya sebelum apply snapshot); di `setModel()` juga. `render()` validasi basi: kalau `selBalok != null && !(S.balok || []).some(b => b.no === selBalok)` → `this.selBalok = null` (satu titik validasi, pola sama `selSup`).

- [ ] **Step 7: Test kecil di file existing** — append 1-2 test ke `tests/rangka/test_balok_melintang.mjs` untuk memastikan cascade hasil UI konsisten (pakai `Object.assign`) — kalau semua logika sudah tertest di Task 1 boleh dilewati.

- [ ] **Step 8: Verifikasi + commit** — regresi + canopi-check. Commit:

```
feat(denah): form + Balok melintang (portal/bracing) + ghost preview + cascade hapus tiang
```

---

## Self-Review

- Kebutuhan yang disetujui Elvan: 3 kasus (portal/bracing/custom) via 1 form Ujung 1/2 dgn tipe dinamis; per-batang 6m di legend WF; nomor B1..Bn terpisah.
- Data lama (tanpa `S.balok`) tetap jalan — `buildMembers` skip loop kosong, `render` skip layer kosong.
- Ref tiang patah = data tetap ada di model, tak crash — user perbaiki lewat panel (hapus + tambah ulang) atau lewat cascade otomatis saat tiang dihapus.
- Konsistensi id `B{n}` di buildMembers vs render (`data-id`) vs panel vs matOverride vs openMatMenu — 1 sumber, cek di Task 2 Step 6.
- Fase pratinjau/terkunci support tak tersentuh sama sekali.
