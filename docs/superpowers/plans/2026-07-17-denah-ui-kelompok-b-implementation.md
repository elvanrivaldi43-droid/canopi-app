# DenahEditor Kelompok B — drag-pindah-besi + snap-tengah — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tiga elemen di `DenahEditor` (tiang, support manual, kotak support dari Gabungan Kotak) bisa digeser sebagai satu kesatuan (translate), dengan 1 mesin snap generik yang menawarkan "nempel" (soft-snap) ke titik tiang/support lain atau titik tengah sisi frame — sesuai `docs/superpowers/specs/2026-07-17-denah-ui-kelompok-b-design.md`.

**Architecture:** Semua perubahan tetap di **satu file** `public/js/denah-editor.js` (classic-script IIFE, pola yang sudah ada, tidak berubah). Mesin snap (`DenahConv.findAlignSnap` + `DenahConv.collectAlignCandidates`) jadi fungsi geometri murni baru di `DenahConv` (testable via Node), dipakai bareng oleh 3 drag baru. Drag translate tiang/support-garis/kotak ditambahkan sebagai 3 cabang `drag.type` baru (`'tiang'`, `'supline'`, `'boxgroup'`) di `bindSvg()`, mengikuti pola attribute-patch-tanpa-render-ulang yang sudah dipakai cabang `vert`/`sup`/`box` (bukan `this.render()` tiap `pointermove` — akan bikin drag terasa tersendat, persis masalah yang sudah diperbaiki di Iterasi 4 Kelompok A). Kotak support butuh 1 state baru `S.combinedBoxes` (dicatat di `applyBoxPreview()`, di-reindex lewat 2 fungsi murni baru saat `+Sudut`/`−Sudut` mengubah `S.verts`).

**Tech Stack:** JS vanilla, tanpa framework/bundler. Tes geometri murni via Node (pola sama `test_ortho_snap.mjs`). Verifikasi UI/drag manual lewat harness lokal yang SUDAH ADA (`tests/rangka/denah_editor_harness.html`, gitignored, dibuat Kelompok A) — dites di browser sebelum deploy ke production.

## Global Constraints

- **Satu file kode yang berubah:** `public/js/denah-editor.js`. Blade (`resources/views/rab-opsi/index.blade.php`) **TIDAK disentuh** — cache-bust otomatis sudah ada.
- **Semua `data-role`/`id` elemen yang sudah ada TETAP** — task-task di bawah cuma MENAMBAH `id` baru ke elemen yang belum punya (`tc${i}`/`tl${i}` tiang, `smlbl${i}` label support manual, `vhit${i}` hit-area sudut), tidak mengganti/menghapus yang sudah ada.
- **`S.verts`/`S.tiang`/`S.supportsManual` format TETAP** — `S.combinedBoxes` murni metadata UI tambahan (array baru), tidak dikirim ke `CuttingController`/`RangkaDesignService`/`DenahConv.buildMembers`, tidak memengaruhi hitung harga.
- **Backward-compat data lama wajib:** semua pembacaan `S.combinedBoxes` HARUS pakai fallback `(S.combinedBoxes || [])` — model denah lama tersimpan di DB (dibuat sebelum Kelompok B) tidak punya field ini.
- **Drag baru TIDAK pakai `this.render()` per-`pointermove`** — ikuti pola attribute-patch yang sudah ada (`vert`/`sup`/`box`), supaya tidak mengulang masalah drag tersendat yang sudah diperbaiki Kelompok A Iterasi 4.
- **Bug class "lurus pas drag, bengkok pas lepas jari" WAJIB dicegah** di SEMUA 3 cabang drag baru — pola: kalau sumbu sudah persis sama titik acuan align-snap yang aktif saat `pointerup`, JANGAN snap-grid lagi di situ (persis pola fix `vert`/`sup` Kelompok A Iterasi 7 & 9).
- **Emoji dilarang** di kode yang tampil ke user (CLAUDE.md).
- **Verifikasi UI dilakukan manual** lewat harness lokal sebelum deploy — bukan otomatis.

---

### Task 1: Mesin snap generik murni — `DenahConv.findAlignSnap` + `collectAlignCandidates`

**Files:**
- Modify: `public/js/denah-editor.js`
- Create: `tests/rangka/test_align_snap.mjs`

**Interfaces:**
- Produces (dipakai Task 3, 4, 6):
  - `DenahConv.findAlignSnap(p, candidates, TH) -> {x, y, guides}` — `guides` = array berisi `{axis:'x'|'y', ref:{x,y}}` (0-2 entri), kandidat TERDEKAT per sumbu yang jaraknya `< TH` dari `p`. Kalau tak ada yang cocok di sumbu itu, `guides` tak punya entri untuk sumbu itu dan nilai `x`/`y` di hasil sama seperti `p.x`/`p.y`.
  - `DenahConv.collectAlignCandidates(S, exclude) -> [{x,y}, ...]` — `exclude` salah satu dari `{kind:'tiang', i}` / `{kind:'sup', i}` / `{kind:'box', vertIdx:[...]}` (atau `null`), dipakai supaya elemen yang sedang digeser sendiri tak jadi kandidat magnet ke dirinya sendiri.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_align_snap.mjs`:

```js
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

const TH = 16;

// --- findAlignSnap ---
// X dekat kandidat A (beda 5), Y jauh dari semua -> snap X ke A, Y tetap
check('snap X ke kandidat terdekat',
  DenahConv.findAlignSnap({ x: 105, y: 500 }, [{ x: 100, y: 300 }], TH),
  { x: 100, y: 500, guides: [{ axis: 'x', ref: { x: 100, y: 300 } }] });

// Dua kandidat, keduanya dalam threshold X -> pilih yang PALING DEKAT (bukan yang pertama di array)
check('pilih kandidat X terdekat, bukan urutan pertama',
  DenahConv.findAlignSnap({ x: 108, y: 500 }, [{ x: 95, y: 0 }, { x: 100, y: 0 }], TH),
  { x: 100, y: 500, guides: [{ axis: 'x', ref: { x: 100, y: 0 } }] });

// Tak ada kandidat dalam threshold -> tak berubah, guides kosong
check('tak snap kalau semua jauh',
  DenahConv.findAlignSnap({ x: 250, y: 250 }, [{ x: 100, y: 300 }], TH),
  { x: 250, y: 250, guides: [] });

// Pas di threshold (selisih == TH) -> tak snap (batas eksklusif, sama pola _orthoSnapToPoint)
check('tak snap tepat di threshold',
  DenahConv.findAlignSnap({ x: 116, y: 500 }, [{ x: 100, y: 300 }], TH),
  { x: 116, y: 500, guides: [] });

// X dan Y dua-duanya cocok (bisa beda kandidat) -> dua guide sekaligus
check('snap X dan Y dari kandidat berbeda',
  DenahConv.findAlignSnap({ x: 104, y: 306 }, [{ x: 100, y: 999 }, { x: 999, y: 300 }], TH),
  { x: 100, y: 300, guides: [{ axis: 'x', ref: { x: 100, y: 999 } }, { axis: 'y', ref: { x: 999, y: 300 } }] });

// --- collectAlignCandidates ---
const S1 = {
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  tiang: [{ x: 100, y: 100 }, { x: 300, y: 100 }],
  supportsManual: [{ a: { x: 50, y: 50 }, b: { x: 50, y: 150 } }],
};
const cands1 = DenahConv.collectAlignCandidates(S1, null);
check('candidates: 2 tiang + 3 titik support (a,b,tengah) + 4 titik tengah sisi frame = 9',
  cands1.length, 9);

// exclude tiang index 0 -> tiang itu hilang dari daftar, sisanya utuh (8)
check('exclude tiang sendiri',
  DenahConv.collectAlignCandidates(S1, { kind: 'tiang', i: 0 }).length, 8);

// exclude support index 0 -> 3 titik support itu (a,b,tengah) hilang (6)
check('exclude support sendiri',
  DenahConv.collectAlignCandidates(S1, { kind: 'sup', i: 0 }).length, 6);

// exclude box vertIdx [1,2] (sisi 1->2 sepenuhnya milik kotak) -> midpoint sisi 1->2 hilang,
// tapi sisi 0->1 dan 2->3 (cuma 1 ujung milik kotak) TETAP jadi kandidat (governing side)
const S2 = { verts: S1.verts, tiang: [], supportsManual: [] };
check('exclude box: sisi internal hilang, sisi governing tetap',
  DenahConv.collectAlignCandidates(S2, { kind: 'box', vertIdx: [1, 2] }).length, 3);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_align_snap.mjs`
Expected: error `DenahConv.findAlignSnap is not a function`.

- [ ] **Step 3: Tambah kedua fungsi murni**

Di `public/js/denah-editor.js`, cari (helper `orthoSnapToPoint` yang sudah ada, ditambahkan Kelompok A):

```js
const orthoSnapToPoint = (p, anchor, TH) => {
  let { x, y } = p;
  if (Math.abs(x - anchor.x) < TH) x = anchor.x;
  if (Math.abs(y - anchor.y) < TH) y = anchor.y;
  return { x, y };
};

const DenahConv = {
```

Ganti jadi (tambah 2 fungsi baru sebelum `const DenahConv`):

```js
const orthoSnapToPoint = (p, anchor, TH) => {
  let { x, y } = p;
  if (Math.abs(x - anchor.x) < TH) x = anchor.x;
  if (Math.abs(y - anchor.y) < TH) y = anchor.y;
  return { x, y };
};
// Mesin snap generik Kelompok B: cari kandidat titik acuan TERDEKAT per-sumbu (independen X/Y)
// dalam threshold TH. Dipakai drag-pindah tiang/support-garis/kotak (bindSvg) — beda dari
// orthoSnapToPoint (Kelompok A, ortho-snap 1 anchor tetap) krn di sini kandidatnya banyak &
// dipilih yang paling dekat, bukan cuma 1 anchor tetap.
const findAlignSnap = (p, candidates, TH) => {
  let x = p.x, y = p.y, guideX = null, guideY = null, bestDx = TH, bestDy = TH;
  candidates.forEach(c => {
    const dx = Math.abs(p.x - c.x), dy = Math.abs(p.y - c.y);
    if (dx < bestDx) { bestDx = dx; x = c.x; guideX = c; }
    if (dy < bestDy) { bestDy = dy; y = c.y; guideY = c; }
  });
  const guides = [];
  if (guideX) guides.push({ axis: 'x', ref: guideX });
  if (guideY) guides.push({ axis: 'y', ref: guideY });
  return { x, y, guides };
};
// Kandidat titik acuan align-snap: titik tiang lain, titik ujung+tengah support manual lain,
// titik tengah tiap sisi frame SAAT INI (S.verts, dihitung ulang tiap panggilan — otomatis ikut
// kalau sisi berubah panjang karena lekukan/resize). `exclude` mencegah elemen yg sedang digeser
// sendiri jadi kandidat (nge-snap ke diri sendiri selalu "cocok", tak berguna).
const collectAlignCandidates = (S, exclude) => {
  const pts = [];
  (S.tiang || []).forEach((t, i) => { if (!(exclude && exclude.kind === 'tiang' && exclude.i === i)) pts.push({ x: t.x, y: t.y }); });
  (S.supportsManual || []).forEach((m, i) => {
    if (exclude && exclude.kind === 'sup' && exclude.i === i) return;
    pts.push({ x: m.a.x, y: m.a.y }, { x: m.b.x, y: m.b.y }, { x: (m.a.x + m.b.x) / 2, y: (m.a.y + m.b.y) / 2 });
  });
  const skipVerts = (exclude && exclude.kind === 'box' && exclude.vertIdx) || [];
  const n = S.verts.length;
  S.verts.forEach((v, i) => {
    const j = (i + 1) % n;
    if (skipVerts.includes(i) && skipVerts.includes(j)) return; // sisi internal milik kotak yg lagi digeser sendiri
    const w = S.verts[j]; pts.push({ x: (v.x + w.x) / 2, y: (v.y + w.y) / 2 });
  });
  return pts;
};

const DenahConv = {
```

Lalu cari baris ekspos test-hook di akhir objek `DenahConv`:

```js
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
};
```

Ganti jadi:

```js
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
  findAlignSnap, collectAlignCandidates,
};
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_align_snap.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Regresi tes lama**

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `SEMUA LULUS` untuk ketiganya + `syntax OK`.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_align_snap.mjs
git commit -m "feat(denah): mesin snap generik DenahConv.findAlignSnap + collectAlignCandidates"
```

---

### Task 2: Garis bantu putus-putus (align-guide) — infrastruktur render

**Files:**
- Modify: `public/js/denah-editor.js`

**Interfaces:**
- Consumes: `guides` array dari `DenahConv.findAlignSnap` (Task 1).
- Produces (dipakai Task 3, 4, 6): `this._updateAlignGuides(guides, movingPt)`, `this._hideAlignGuides()`.

- [ ] **Step 1: Tambah 2 elemen `<line>` guide ke markup SVG**

Di `public/js/denah-editor.js`, method `render()`, cari:

```js
    s += '</svg>';
    const canvas = this._q('.de-canvas');
```

Ganti jadi:

```js
    // Garis bantu align-snap (Kelompok B) — 2 elemen tetap, disembunyikan/ditampilkan &
    // di-update posisinya lewat _updateAlignGuides()/_hideAlignGuides() selama drag, TANPA render ulang.
    s += `<line id="agx${this.uid}" x1="0" y1="0" x2="0" y2="0" stroke="#facc15" stroke-width="1.5" stroke-dasharray="5,4" style="display:none;pointer-events:none"/>`;
    s += `<line id="agy${this.uid}" x1="0" y1="0" x2="0" y2="0" stroke="#facc15" stroke-width="1.5" stroke-dasharray="5,4" style="display:none;pointer-events:none"/>`;
    s += '</svg>';
    const canvas = this._q('.de-canvas');
```

- [ ] **Step 2: Method `_updateAlignGuides` / `_hideAlignGuides`**

Di `public/js/denah-editor.js`, cari akhir method `render()` (baris penutup sebelum `bindSvg(el) {`):

```js
    this.renderSides(mem);
    this.renderBoxPanel();
    this._changed();
  }

  bindSvg(el) {
```

Ganti jadi (tambah 2 method baru di antaranya):

```js
    this.renderSides(mem);
    this.renderBoxPanel();
    this._changed();
  }

  // Update/sembunyikan garis bantu align-snap (dipakai drag tiang/support-garis/kotak, Kelompok B).
  // movingPt = posisi cm SAAT INI dari elemen yg digeser (ujung garis yg bergerak).
  _updateAlignGuides(guides, movingPt) {
    const PAD = this.PAD, X = x => PAD + x * this.SC, Y = y => PAD + y * this.SC;
    const gx = this._q('#agx' + this.uid), gy = this._q('#agy' + this.uid);
    if (!gx || !gy) return;
    const gRefX = (guides || []).find(g => g.axis === 'x');
    const gRefY = (guides || []).find(g => g.axis === 'y');
    if (gRefX) { gx.setAttribute('x1', X(gRefX.ref.x)); gx.setAttribute('y1', Y(gRefX.ref.y)); gx.setAttribute('x2', X(movingPt.x)); gx.setAttribute('y2', Y(movingPt.y)); gx.style.display = ''; }
    else gx.style.display = 'none';
    if (gRefY) { gy.setAttribute('x1', X(gRefY.ref.x)); gy.setAttribute('y1', Y(gRefY.ref.y)); gy.setAttribute('x2', X(movingPt.x)); gy.setAttribute('y2', Y(movingPt.y)); gy.style.display = ''; }
    else gy.style.display = 'none';
  }
  _hideAlignGuides() {
    const gx = this._q('#agx' + this.uid), gy = this._q('#agy' + this.uid);
    if (gx) gx.style.display = 'none';
    if (gy) gy.style.display = 'none';
  }

  bindSvg(el) {
```

- [ ] **Step 3: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk keempatnya (murni tambahan markup + 2 method baru, tak menyentuh logika lama).

- [ ] **Step 4: Verifikasi manual — kedua garis tersembunyi secara default**

Restart harness kalau perlu:
```bash
php -S 0.0.0.0:8892 -t /root/projects/canopi-app >/tmp/denah-harness.log 2>&1 &
```
Buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`. Checklist: tampilan editor sama seperti sebelumnya (belum ada drag baru yang memanggil `_updateAlignGuides`, jadi kedua garis `display:none` — tak kelihatan apa pun yang berubah).

- [ ] **Step 5: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): infrastruktur render garis bantu align-snap (belum dipakai drag manapun)"
```

---

### Task 3: Drag-pindah Tiang + align-snap

**Files:**
- Modify: `public/js/denah-editor.js`

**Interfaces:**
- Consumes: `DenahConv.findAlignSnap`/`collectAlignCandidates` (Task 1), `this._updateAlignGuides`/`_hideAlignGuides` (Task 2).
- Produces: cabang `drag.type === 'tiang'` di `bindSvg()` (dipakai sbg pola referensi utk Task 4/6, tak dipanggil task lain).

- [ ] **Step 1: Tambah `id` ke circle+label tiang di `render()`**

Cari:

```js
    mem.filter(m => m.jenis === 'tiang').forEach((m, i) => { const c = cmap[m.material]; const p = m.geom.p;
      s += `<circle cx="${X(p.x)}" cy="${Y(p.y)}" r="6" fill="${c}" stroke="#0f2740" stroke-width="1.5" data-id="${m.id}" class="hit"><title>Tiang ${m.material} • ${m.panjang}cm</title></circle>`;
      s += `<text x="${X(p.x) + 9}" y="${Y(p.y) + 4}" fill="#fbbf24" font-size="10" paint-order="stroke" stroke="#0f2740" stroke-width="3">T${i + 1}</text>`; });
```

Ganti jadi:

```js
    mem.filter(m => m.jenis === 'tiang').forEach((m, i) => { const c = cmap[m.material]; const p = m.geom.p;
      s += `<circle id="tc${i}" cx="${X(p.x)}" cy="${Y(p.y)}" r="6" fill="${c}" stroke="#0f2740" stroke-width="1.5" data-id="${m.id}" class="hit"><title>Tiang ${m.material} • ${m.panjang}cm</title></circle>`;
      s += `<text id="tl${i}" x="${X(p.x) + 9}" y="${Y(p.y) + 4}" fill="#fbbf24" font-size="10" paint-order="stroke" stroke="#0f2740" stroke-width="3">T${i + 1}</text>`; });
```

Catatan: `i` di sini = index di `this.S.tiang` (urutan `buildMembers()` mem-push tiang persis sesuai urutan `S.tiang`, tak diacak) — aman dipakai sbg id unik & sbg index array langsung.

- [ ] **Step 2: Ganti pointerdown mode 'tiang'**

Cari:

```js
      } else if (this.mode === 'tiang') {
        this.pushUndo();
        const hit = this.S.tiang.findIndex(p => dist(p, cm) < this.S.grid * 1.5);
        if (hit >= 0) this.S.tiang.splice(hit, 1); else this.S.tiang.push({ x: this.snap(cm.x), y: this.snap(cm.y) });
        this.render();
      }
```

Ganti jadi:

```js
      } else if (this.mode === 'tiang') {
        const hit = this.S.tiang.findIndex(p => dist(p, cm) < this.S.grid * 1.5);
        this.pushUndo();
        if (hit >= 0) {
          // Tunggu ada gerakan jari dulu (lihat pointermove) sebelum diputuskan drag-pindah atau
          // tap-hapus (perilaku lama) — keduanya mulai dari gestur yang sama.
          drag = { type: 'tiang', i: hit, startPt: cm, moved: false,
            tc: el.querySelector('#tc' + hit), tl: el.querySelector('#tl' + hit) };
          el.setPointerCapture(e.pointerId); e.preventDefault();
        } else {
          this.S.tiang.push({ x: this.snap(cm.x), y: this.snap(cm.y) });
          this.render();
        }
      }
```

- [ ] **Step 3: Tambah cabang `pointermove` untuk `drag.type === 'tiang'`**

Cari (akhir cabang `box` di `pointermove`, penutup blok `{` terluar):

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
      }
    });
```

Ganti jadi (tambah cabang `tiang` setelah `box`):

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
        } else if (drag.type === 'tiang') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'tiang', i: drag.i });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap(cm, candidates, TH);
          this.S.tiang[drag.i] = { x: snap.x, y: snap.y };
          this._lastGuides = snap.guides;
          const px2 = PAD + snap.x * this.SC, py2 = PAD + snap.y * this.SC;
          if (drag.tc) { drag.tc.setAttribute('cx', px2); drag.tc.setAttribute('cy', py2); }
          if (drag.tl) { drag.tl.setAttribute('x', px2 + 9); drag.tl.setAttribute('y', py2 + 4); }
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```

- [ ] **Step 4: Tambah cabang di `end()` (pointerup/pointercancel)**

Cari:

```js
      else if (drag.type === 'box') { this.boxPreview.offset = this.snap(this.boxPreview.offset); }
      drag = null; this.render(); };
```

Ganti jadi:

```js
      else if (drag.type === 'box') { this.boxPreview.offset = this.snap(this.boxPreview.offset); }
      else if (drag.type === 'tiang') {
        if (!drag.moved) {
          // Tak gerak = perilaku lama (tap tiang = hapus). Posisi belum diubah krn moved masih false.
          this.S.tiang.splice(drag.i, 1);
        } else {
          // Sama pola fix Kelompok A (vert/sup): kalau sumbu SUDAH persis sama titik align-snap yg
          // aktif barusan, jangan snap-grid lagi di situ — cegah "lurus pas drag, bengkok pas lepas".
          const p = this.S.tiang[drag.i];
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          this.S.tiang[drag.i] = {
            x: (gx && p.x === gx.ref.x) ? p.x : this.snap(p.x),
            y: (gy && p.y === gy.ref.y) ? p.y : this.snap(p.y),
          };
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```

- [ ] **Step 5: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk keempatnya.

- [ ] **Step 6: Verifikasi manual di harness**

Restart harness kalau perlu, buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`. Checklist mode **Tiang**:
1. Ketuk area kosong → tiang baru muncul (perilaku lama, tak berubah).
2. Ketuk tiang yang sudah ada TANPA gerak jari (tap cepat) → tiang hilang (perilaku lama, tak berubah).
3. Tekan tiang yang sudah ada, GESER → tiang ikut jari, TIDAK hilang.
4. Taruh tiang ke-2 dekat sejajar (X atau Y mirip) dengan tiang ke-1 → muncul garis kuning putus-putus, posisi "nempel" pas sejajar persis.
5. Terus geser lepas dari titik sejajar itu → nempelnya lepas, garis bantu hilang, posisi ikut jari lagi (bukan snap paksa).
6. Lepas jari pas dalam kondisi nempel-sejajar → posisi akhir TETAP sejajar persis (tak "kembali miring dikit").
7. Undo → tiang balik ke posisi/jumlah sebelumnya.

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): drag-pindah tiang + align-snap ke tiang/support/tengah-sisi lain"
```

---

### Task 4: Drag-pindah Support manual (garis utuh) + align-snap

**Files:**
- Modify: `public/js/denah-editor.js`

**Interfaces:**
- Consumes: sama seperti Task 3, plus elemen DOM support manual yang sudah ada (`#sm${i}`, `#smh${i}a/b`, `[data-sm][data-end]`).
- Produces: cabang `drag.type === 'supline'`.

- [ ] **Step 1: Tambah `id` ke label support manual di `render()`**

Cari:

```js
      s += `<text x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">S${i + 1} · ${m.panjang}</text>`; });
```

Ganti jadi:

```js
      s += `<text ${manual ? `id="smlbl${m.id.slice(3)}"` : ''} x="${X(mx)}" y="${Y(my) - 4}" fill="#93c5fd" font-size="9" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">S${i + 1} · ${m.panjang}</text>`; });
```

- [ ] **Step 2: Ganti pointerdown untuk tap badan support manual**

Cari:

```js
        if (t.dataset.id && t.dataset.id.startsWith('S')) {
          this.pushUndo(); const id = t.dataset.id;
          if (id.startsWith('Sm_')) { this.S.supportsManual.splice(+id.slice(3), 1); }
          else { this.S.removed[id] = !this.S.removed[id]; }
          this.render();
        }
```

Ganti jadi:

```js
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
```

- [ ] **Step 3: Tambah cabang `pointermove` untuk `drag.type === 'supline'`**

Cari (blok `tiang` yang baru ditambahkan Task 3, sebelum penutup):

```js
          if (drag.tc) { drag.tc.setAttribute('cx', px2); drag.tc.setAttribute('cy', py2); }
          if (drag.tl) { drag.tl.setAttribute('x', px2 + 9); drag.tl.setAttribute('y', py2 + 4); }
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```

Ganti jadi (tambah cabang `supline` setelah `tiang`):

```js
          if (drag.tc) { drag.tc.setAttribute('cx', px2); drag.tc.setAttribute('cy', py2); }
          if (drag.tl) { drag.tl.setAttribute('x', px2 + 9); drag.tl.setAttribute('y', py2 + 4); }
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'supline') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const midStart = { x: (drag.startA.x + drag.startB.x) / 2 + dx, y: (drag.startA.y + drag.startB.y) / 2 + dy };
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'sup', i: drag.i });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap(midStart, candidates, TH);
          const adjX = snap.x - midStart.x, adjY = snap.y - midStart.y;
          const a = { x: drag.startA.x + dx + adjX, y: drag.startA.y + dy + adjY };
          const b = { x: drag.startB.x + dx + adjX, y: drag.startB.y + dy + adjY };
          this.S.supportsManual[drag.i] = { a, b };
          this._lastGuides = snap.guides;
          const ax = X(a.x), ay = Y(a.y), bx = X(b.x), by = Y(b.y);
          if (drag.line) { drag.line.setAttribute('x1', ax); drag.line.setAttribute('y1', ay); drag.line.setAttribute('x2', bx); drag.line.setAttribute('y2', by); }
          if (drag.hit) { drag.hit.setAttribute('x1', ax); drag.hit.setAttribute('y1', ay); drag.hit.setAttribute('x2', bx); drag.hit.setAttribute('y2', by); }
          if (drag.ha) { drag.ha.setAttribute('cx', ax); drag.ha.setAttribute('cy', ay); }
          if (drag.hb) { drag.hb.setAttribute('cx', bx); drag.hb.setAttribute('cy', by); }
          if (drag.hita) { drag.hita.setAttribute('cx', ax); drag.hita.setAttribute('cy', ay); }
          if (drag.hitb) { drag.hitb.setAttribute('cx', bx); drag.hitb.setAttribute('cy', by); }
          if (drag.lbl) { drag.lbl.setAttribute('x', X((a.x + b.x) / 2)); drag.lbl.setAttribute('y', Y((a.y + b.y) / 2) - 4); }
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```

- [ ] **Step 4: Tambah cabang di `end()`**

Cari (blok `tiang` yang ditambahkan Task 3):

```js
          this.S.tiang[drag.i] = {
            x: (gx && p.x === gx.ref.x) ? p.x : this.snap(p.x),
            y: (gy && p.y === gy.ref.y) ? p.y : this.snap(p.y),
          };
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```

Ganti jadi (tambah cabang `supline` setelah `tiang`):

```js
          this.S.tiang[drag.i] = {
            x: (gx && p.x === gx.ref.x) ? p.x : this.snap(p.x),
            y: (gy && p.y === gy.ref.y) ? p.y : this.snap(p.y),
          };
        }
        this._hideAlignGuides();
      }
      else if (drag.type === 'supline') {
        if (!drag.moved) {
          this.S.supportsManual.splice(drag.i, 1);
        } else {
          const m = this.S.supportsManual[drag.i];
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          const mid = { x: (m.a.x + m.b.x) / 2, y: (m.a.y + m.b.y) / 2 };
          // Kalau sumbu itu barusan aktif align-snap (ada di this._lastGuides), JANGAN grid-snap sumbu
          // itu sama sekali -- posisi sekarang sudah pas (dipercaya langsung, TAK dibandingkan nilai
          // persis krn titik tengah di sini hasil rekomputasi arithmetic yg bisa beda dikit scr
          // floating-point dari nilai yg dicocokkan waktu pointermove -- beda dari tiang yg nilainya
          // tersimpan persis tanpa rekomputasi, jadi aman pakai === di situ; di sini TIDAK aman).
          const snappedMid = {
            x: gx ? mid.x : this.snap(mid.x),
            y: gy ? mid.y : this.snap(mid.y),
          };
          // Geser KEDUA ujung dgn OFFSET SAMA persis — garis tetap lurus, arah/panjang tak berubah.
          const shiftX = snappedMid.x - mid.x, shiftY = snappedMid.y - mid.y;
          this.S.supportsManual[drag.i] = {
            a: { x: m.a.x + shiftX, y: m.a.y + shiftY },
            b: { x: m.b.x + shiftX, y: m.b.y + shiftY },
          };
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```

**Catatan revisi (17 Juli, setelah implementasi Task 4):** kode di atas beda dari versi Task 4 sebelumnya — waktu implementasi nyata, review menemukan 2 bug berturutan: (1) cek awal membandingkan endpoint `a` padahal guide dicocokkan ke TITIK TENGAH garis saat `pointermove`; (2) setelah dibetulkan ke titik tengah, perbandingan `===` masih gagal ~40% krn titik tengah di `end()` adalah hasil REKOMPUTASI arithmetic (beda jalur floating-point dari nilai asli) — bukan nilai yang tersimpan persis seperti `tiang`. Fix akhir (dipakai di atas): BUANG perbandingan nilai sama sekali, cukup cek KEHADIRAN guide di `this._lastGuides`. **Task 6 di bawah ini SUDAH memakai pola yang benar ini dari awal** (bukan pola `tiang` yang cek-nilai) karena box-group juga men-drag lewat titik tengah (centroid) hasil rekomputasi, sama seperti supline.

- [ ] **Step 5: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk keempatnya.

- [ ] **Step 6: Verifikasi manual di harness**

Reload harness. Mode **Support** — dulu bikin 1-2 support manual (klik 2 titik). Checklist:
1. Ketuk BADAN garis (bukan titik ujung) tanpa gerak → garis hilang (perilaku lama, tak berubah).
2. Tekan badan garis, geser → seluruh garis (A dan B) ikut pindah bareng, bentuk/arah garis tetap sama (tak berubah panjang/miring).
3. Drag ujung A atau B sendiri (titik kecil di ujung) → masih jalan seperti biasa (per-endpoint, ortho-snap Kelompok A, TAK berubah).
4. Garis kedua digeser mendekati sejajar garis pertama → muncul garis bantu kuning, nempel pas sejajar.
5. Lepas jari pas nempel → tetap sejajar persis (tak melenceng).
6. Label "S{n}·panjang" ikut pindah bareng garis selama drag, angka panjang tak berubah (garis cuma pindah tempat, tak berubah ukuran).

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): drag-pindah support manual (garis utuh) + align-snap"
```

---

### Task 5: Metadata `S.combinedBoxes` + reindex saat `+Sudut`/`−Sudut`

**Files:**
- Modify: `public/js/denah-editor.js`
- Create: `tests/rangka/test_box_reindex.mjs`

**Interfaces:**
- Produces (dipakai Task 6):
  - `DenahConv.shiftBoxesInsert(boxes, at, count) -> boxes baru` — index vertex `>= at` di tiap entry ikut geser `+count` (dipanggil setelah `S.verts.splice(at, 0, ...count item...)`).
  - `DenahConv.shiftBoxesDelete(boxes, at) -> boxes baru` — entry yang salah satu vertex-nya PERSIS `at` dibuang; sisanya yang index-nya `> at` digeser `-1` (dipanggil setelah `S.verts.splice(at, 1)`).
  - `S.combinedBoxes` — array `{verts: [i0, i1, ...]}`, diisi di `applyBoxPreview()`.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_box_reindex.mjs`:

```js
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

const boxes = [{ verts: [3, 4, 5] }, { verts: [7, 8] }];

// Sisip 1 vertex di index 1 (sebelum kedua entry) -> semua index di kedua entry +1
check('insert sebelum semua entry',
  DenahConv.shiftBoxesInsert(boxes, 1, 1),
  [{ verts: [4, 5, 6] }, { verts: [8, 9] }]);

// Sisip 2 vertex tepat di batas antara entry 1 (berakhir di 5) dan entry 2 (mulai di 7) ->
// entry 1 (index < 6) TAK berubah, entry 2 (index >= 6) geser +2
check('insert di antara 2 entry, count=2',
  DenahConv.shiftBoxesInsert(boxes, 6, 2),
  [{ verts: [3, 4, 5] }, { verts: [9, 10] }]);

// Hapus vertex index 4 (MILIK entry 1) -> entry 1 dibuang total, entry 2 (index>4) geser -1
check('delete vertex milik entry -> entry dibuang',
  DenahConv.shiftBoxesDelete(boxes, 4),
  [{ verts: [6, 7] }]);

// Hapus vertex index 0 (di luar semua entry, SEBELUM keduanya) -> kedua entry geser -1, tak ada yg dibuang
check('delete vertex di luar entry -> geser -1, tak dibuang',
  DenahConv.shiftBoxesDelete(boxes, 0),
  [{ verts: [2, 3, 4] }, { verts: [6, 7] }]);

// boxes kosong/undefined -> tak error, hasil array kosong
check('insert pada boxes kosong', DenahConv.shiftBoxesInsert([], 0, 1), []);
check('delete pada boxes kosong', DenahConv.shiftBoxesDelete([], 0), []);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_box_reindex.mjs`
Expected: error `DenahConv.shiftBoxesInsert is not a function`.

- [ ] **Step 3: Tambah kedua fungsi ke `DenahConv`**

Cari:

```js
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
  findAlignSnap, collectAlignCandidates,
};
```

Ganti jadi (tambah 2 fungsi baru di objek `DenahConv`, sebelum baris test-hook):

```js
  // Dipanggil setelah S.verts.splice(at, 0, ...count vertex baru...) (mode "+ Sudut" atau
  // combineBox saat "+ Tambah Kotak"): index vertex combinedBoxes yg >= at ikut geser +count
  // (vertex baru masuk SEBELUM index itu).
  shiftBoxesInsert(boxes, at, count) {
    return (boxes || []).map(bx => ({ verts: bx.verts.map(i => i >= at ? i + count : i) }));
  },
  // Dipanggil setelah S.verts.splice(at, 1) (mode "− Sudut"): entry yg salah satu vertex-nya
  // PERSIS `at` dibuang (kotak itu dianggap bukan satu kesatuan lagi — salah satu sudutnya hilang).
  // Entry lain yg index-nya > at ikut geser -1.
  shiftBoxesDelete(boxes, at) {
    return (boxes || []).filter(bx => !bx.verts.includes(at)).map(bx => ({ verts: bx.verts.map(i => i > at ? i - 1 : i) }));
  },
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
  findAlignSnap, collectAlignCandidates,
};
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_box_reindex.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Pakai `shiftBoxesInsert`/`shiftBoxesDelete` di handler `+Sudut`/`−Sudut`**

Cari:

```js
        if (this.armed === 'delV' && t.dataset.vert != null) { if (this.S.verts.length > 3) { this.pushUndo(); this.S.verts.splice(+t.dataset.vert, 1); } this.armed = null; this.setHint(); this.render(); return; }
        if (this.armed === 'addV' && t.dataset.id && t.dataset.id.startsWith('F')) {
          this.pushUndo(); const i = +t.dataset.id.slice(1); this.S.verts.splice(i + 1, 0, { x: this.snap(cm.x), y: this.snap(cm.y) }); this.armed = null; this.setHint(); this.render(); return; }
```

Ganti jadi:

```js
        if (this.armed === 'delV' && t.dataset.vert != null) {
          if (this.S.verts.length > 3) {
            this.pushUndo();
            const vi = +t.dataset.vert;
            this.S.verts.splice(vi, 1);
            this.S.combinedBoxes = DenahConv.shiftBoxesDelete(this.S.combinedBoxes, vi);
          }
          this.armed = null; this.setHint(); this.render(); return; }
        if (this.armed === 'addV' && t.dataset.id && t.dataset.id.startsWith('F')) {
          this.pushUndo(); const i = +t.dataset.id.slice(1);
          this.S.verts.splice(i + 1, 0, { x: this.snap(cm.x), y: this.snap(cm.y) });
          this.S.combinedBoxes = DenahConv.shiftBoxesInsert(this.S.combinedBoxes, i + 1, 1);
          this.armed = null; this.setHint(); this.render(); return; }
```

- [ ] **Step 6: Catat `combinedBoxes` di `applyBoxPreview()` (dan geser entry lama yang sudah ada)**

Cari:

```js
  applyBoxPreview() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const len = Math.hypot(b.x - a.x, b.y - a.y);
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const result = DenahConv.combineBox(this.S.verts, bp.sisiIdx, off, bp.span, bp.depthMag * bp.depthSign);
    if (!result) { this.setHint('Kotak tidak valid di posisi ini — geser lagi atau kecilkan ukurannya.'); return; }
    this.pushUndo();
    this.S.verts = result;
    this.armed = null; this.boxPreview = null;
    this.setHint();
    this.syncLP();
    this.render();
  }
```

Ganti jadi:

```js
  applyBoxPreview() {
    const bp = this.boxPreview, verts = this.S.verts, n = verts.length;
    const a = verts[bp.sisiIdx], b = verts[(bp.sisiIdx + 1) % n];
    const len = Math.hypot(b.x - a.x, b.y - a.y);
    const off = Math.max(0, Math.min(bp.offset, len - bp.span));
    const result = DenahConv.combineBox(this.S.verts, bp.sisiIdx, off, bp.span, bp.depthMag * bp.depthSign);
    if (!result) { this.setHint('Kotak tidak valid di posisi ini — geser lagi atau kecilkan ukurannya.'); return; }
    this.pushUndo();
    // Catat vertex mana yg jadi 1 kotak (buat drag-kotak-utuh, Task 6) — jumlah vertex baru yg
    // disisipkan sama persis logika `seq` di combineBox: offset>0 -> p1 baru, offset+span<len -> p2 baru,
    // p4+p3 selalu ada.
    const boxStart = bp.sisiIdx + 1;
    let count = 2;
    if (off > 1e-6) count++;
    if (off + bp.span < len - 1e-6) count++;
    this.S.combinedBoxes = DenahConv.shiftBoxesInsert(this.S.combinedBoxes, boxStart, count);
    this.S.combinedBoxes.push({ verts: Array.from({ length: count }, (_, k) => boxStart + k) });
    this.S.verts = result;
    this.armed = null; this.boxPreview = null;
    this.setHint();
    this.syncLP();
    this.render();
  }
```

- [ ] **Step 7: Init `S.combinedBoxes` di `defaultModel()` dan `resetBox()`**

Cari:

```js
  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20, target: 100,
      kotak: 100, autoKotak: true, arah: '2', supportsManual: [], removed: {}, tiang: [],
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '' }, matOverride: {},
    };
  }
```

Ganti jadi:

```js
  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20, target: 100,
      kotak: 100, autoKotak: true, arah: '2', supportsManual: [], removed: {}, tiang: [],
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '' }, matOverride: {}, combinedBoxes: [],
    };
  }
```

Cari:

```js
    this.S.removed = {}; this.S.supportsManual = []; this.S.matOverride = {};
    this.S.tiang = [];   // JANGAN auto-taruh tiang di sudut — user yang tentukan tiang (mode Tiang)
```

Ganti jadi:

```js
    this.S.removed = {}; this.S.supportsManual = []; this.S.matOverride = {}; this.S.combinedBoxes = [];
    this.S.tiang = [];   // JANGAN auto-taruh tiang di sudut — user yang tentukan tiang (mode Tiang)
```

- [ ] **Step 8: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk kelimanya.

- [ ] **Step 9: Verifikasi manual — model lama (tanpa `combinedBoxes`) tak error**

Di harness, panggil dari console browser (F12): `ed.setModel({verts:[{x:0,y:0},{x:400,y:0},{x:400,y:300},{x:0,y:300}], grid:20, target:100, kotak:100, autoKotak:true, arah:'2', supportsManual:[], removed:{}, tiang:[], tinggi:300, matDefault:{frame:'Hollow 4x4',support:'Hollow 4x4',tiang:'Hollow 4x4'}, matOverride:{}})` (model TANPA field `combinedBoxes`, meniru data lama tersimpan sebelum Kelompok B). Expected: editor render normal, tak ada error di console (defensif `(this.S.combinedBoxes || [])` di `collectAlignCandidates`/`shiftBoxes*` sudah menangani ini — cek juga `applyBoxPreview` pakai `DenahConv.shiftBoxesInsert(this.S.combinedBoxes, ...)` yang aman menerima `undefined` krn fungsi sudah `(boxes || [])`).

- [ ] **Step 10: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_box_reindex.mjs
git commit -m "feat(denah): metadata S.combinedBoxes + reindex saat +Sudut/-Sudut (persiapan drag-kotak-utuh)"
```

---

### Task 6: Drag-pindah Kotak support (utuh) + align-snap + snap-tengah-sisi

**Files:**
- Modify: `public/js/denah-editor.js`

**Interfaces:**
- Consumes: `S.combinedBoxes` (Task 5), `DenahConv.findAlignSnap`/`collectAlignCandidates` (Task 1), `_updateAlignGuides`/`_hideAlignGuides` (Task 2).
- Produces: cabang `drag.type === 'boxgroup'`.

- [ ] **Step 1: Tambah `id` ke hit-area sudut poligon di `render()`**

Cari:

```js
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="5" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
```

Ganti jadi:

```js
    S.verts.forEach((v, i) => { const cx = X(v.x), cy = Y(v.y);
      s += `<circle id="vhit${i}" cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="5" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
```

- [ ] **Step 2: Render hit-polygon per kotak, SEBELUM titik sudut (supaya tap tepat di sudut tetap prioritas drag-sudut biasa)**

Cari:

```js
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="13" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
    // tiang
```

Ganti jadi:

```js
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="13" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
    // kotak-support (Gabungan Kotak): hit-area transparan per kotak buat drag-kotak-utuh. Dirender
    // SEBELUM titik sudut (di bawah ini) supaya tap TEPAT di titik sudut tetap prioritas drag-sudut
    // biasa (SVG: elemen belakangan di markup ada di atas utk hit-testing).
    (S.combinedBoxes || []).forEach((bx, k) => {
      const pts = bx.verts.map(i => S.verts[i]).filter(Boolean);
      if (pts.length !== bx.verts.length) return; // index rusak (harusnya sudah tersaring reindex Task 5)
      s += `<polygon points="${pts.map(p => `${X(p.x)},${Y(p.y)}`).join(' ')}" fill="transparent" data-boxgroup="${k}" style="cursor:grab;pointer-events:${this.mode === 'bentuk' ? 'auto' : 'none'}"/>`;
    });
    // tiang
```

**Catatan revisi (17 Juli, setelah implementasi):** kode Step 2 di atas sudah termasuk `pointer-events:${this.mode==='bentuk'?'auto':'none'}` — versi pertama (tanpa ini) sempat lolos ke commit lalu ketahuan review: polygon transparan itu selalu ada di DOM di SEMUA mode, jadi walau taps di mode lain cuma DIABAIKAN oleh handler (`!this.armed && t.dataset.boxgroup`), polygon-nya sendiri tetap MENANG hit-test SVG di atas garis frame/support yang ketiban — blokir tap "Ganti Besi"/"+ Sudut" di sisi kotak itu di mode lain. Fix: `pointer-events:none` kecuali mode Bentuk (satu-satunya mode yang perlu drag-kotak).

- [ ] **Step 3: Tambah pointerdown untuk tap badan kotak**

Cari:

```js
        if (t.dataset.boxprev && this.boxPreview && this.boxPreview.sisiIdx != null) {
          drag = { type: 'box' };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (this.armed === 'delV' && t.dataset.vert != null) {
```

Ganti jadi:

```js
        if (t.dataset.boxprev && this.boxPreview && this.boxPreview.sisiIdx != null) {
          drag = { type: 'box' };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (!this.armed && t.dataset.boxgroup != null) {
          this.pushUndo();
          const k = +t.dataset.boxgroup;
          const bx = this.S.combinedBoxes[k];
          const n = this.S.verts.length;
          const sideSet = new Set();
          bx.verts.forEach(v => { sideSet.add((v - 1 + n) % n); sideSet.add(v % n); });
          const sides = [...sideSet];
          drag = { type: 'boxgroup', k, startPt: cm, moved: false,
            vertIdx: bx.verts.slice(),
            startVerts: bx.verts.map(i => ({ ...this.S.verts[i] })),
            vh: bx.verts.map(i => el.querySelector('#vh' + i)),
            vhit: bx.verts.map(i => el.querySelector('#vhit' + i)),
            sides,
            fl: sides.map(i => el.querySelector('#fl' + i)),
            fll: sides.map(i => el.querySelector('#fll' + i)),
            poly: t };
          el.setPointerCapture(e.pointerId); e.preventDefault();
          return;
        }
        if (this.armed === 'delV' && t.dataset.vert != null) {
```

- [ ] **Step 4: Tambah cabang `pointermove` untuk `drag.type === 'boxgroup'`**

Cari (blok `supline` yang ditambahkan Task 4, sebelum penutup):

```js
          if (drag.lbl) { drag.lbl.setAttribute('x', X((a.x + b.x) / 2)); drag.lbl.setAttribute('y', Y((a.y + b.y) / 2) - 4); }
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```

Ganti jadi (tambah cabang `boxgroup` setelah `supline`):

```js
          if (drag.lbl) { drag.lbl.setAttribute('x', X((a.x + b.x) / 2)); drag.lbl.setAttribute('y', Y((a.y + b.y) / 2) - 4); }
          this._updateAlignGuides(snap.guides, snap);
        } else if (drag.type === 'boxgroup') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) drag.moved = true;
          if (!drag.moved) return;
          const dx = cm.x - drag.startPt.x, dy = cm.y - drag.startPt.y;
          const cx0 = drag.startVerts.reduce((acc, p) => acc + p.x, 0) / drag.startVerts.length + dx;
          const cy0 = drag.startVerts.reduce((acc, p) => acc + p.y, 0) / drag.startVerts.length + dy;
          const candidates = DenahConv.collectAlignCandidates(this.S, { kind: 'box', vertIdx: drag.vertIdx });
          const TH = (this.S.grid || 20) * 0.8;
          const snap = DenahConv.findAlignSnap({ x: cx0, y: cy0 }, candidates, TH);
          const adjX = snap.x - cx0, adjY = snap.y - cy0;
          drag.vertIdx.forEach((vi, idx) => {
            this.S.verts[vi] = { x: drag.startVerts[idx].x + dx + adjX, y: drag.startVerts[idx].y + dy + adjY };
          });
          this._lastGuides = snap.guides;
          drag.vertIdx.forEach((vi, idx) => {
            const p = this.S.verts[vi], px2 = X(p.x), py2 = Y(p.y);
            if (drag.vh[idx]) { drag.vh[idx].setAttribute('cx', px2); drag.vh[idx].setAttribute('cy', py2); }
            if (drag.vhit[idx]) { drag.vhit[idx].setAttribute('cx', px2); drag.vhit[idx].setAttribute('cy', py2); }
          });
          drag.sides.forEach((si, idx) => {
            const n = this.S.verts.length;
            const a = this.S.verts[si], b = this.S.verts[(si + 1) % n];
            if (drag.fl[idx]) { drag.fl[idx].setAttribute('x1', X(a.x)); drag.fl[idx].setAttribute('y1', Y(a.y)); drag.fl[idx].setAttribute('x2', X(b.x)); drag.fl[idx].setAttribute('y2', Y(b.y)); }
            if (drag.fll[idx]) {
              drag.fll[idx].setAttribute('x', X((a.x + b.x) / 2)); drag.fll[idx].setAttribute('y', Y((a.y + b.y) / 2) - 5);
              drag.fll[idx].textContent = 'F' + (si + 1) + ' · ' + (Math.round(dist(a, b) * 10) / 10);
            }
          });
          if (drag.poly) drag.poly.setAttribute('points', drag.vertIdx.map(vi => `${X(this.S.verts[vi].x)},${Y(this.S.verts[vi].y)}`).join(' '));
          this._updateAlignGuides(snap.guides, snap);
        }
      }
    });
```

- [ ] **Step 5: Tambah cabang di `end()`**

Cari (blok `supline` yang ditambahkan Task 4):

```js
          const snappedMid = {
            x: gx ? mid.x : this.snap(mid.x),
            y: gy ? mid.y : this.snap(mid.y),
          };
          // Geser KEDUA ujung dgn OFFSET SAMA persis — garis tetap lurus, arah/panjang tak berubah.
          const shiftX = snappedMid.x - mid.x, shiftY = snappedMid.y - mid.y;
          this.S.supportsManual[drag.i] = {
            a: { x: m.a.x + shiftX, y: m.a.y + shiftY },
            b: { x: m.b.x + shiftX, y: m.b.y + shiftY },
          };
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```

Ganti jadi (tambah cabang `boxgroup` setelah `supline`):

```js
          const snappedMid = {
            x: gx ? mid.x : this.snap(mid.x),
            y: gy ? mid.y : this.snap(mid.y),
          };
          // Geser KEDUA ujung dgn OFFSET SAMA persis — garis tetap lurus, arah/panjang tak berubah.
          const shiftX = snappedMid.x - mid.x, shiftY = snappedMid.y - mid.y;
          this.S.supportsManual[drag.i] = {
            a: { x: m.a.x + shiftX, y: m.a.y + shiftY },
            b: { x: m.b.x + shiftX, y: m.b.y + shiftY },
          };
        }
        this._hideAlignGuides();
      }
      else if (drag.type === 'boxgroup') {
        if (drag.moved) {
          const gx = (this._lastGuides || []).find(g => g.axis === 'x');
          const gy = (this._lastGuides || []).find(g => g.axis === 'y');
          const cen = {
            x: drag.vertIdx.reduce((acc, vi) => acc + this.S.verts[vi].x, 0) / drag.vertIdx.length,
            y: drag.vertIdx.reduce((acc, vi) => acc + this.S.verts[vi].y, 0) / drag.vertIdx.length,
          };
          // PENTING: sama seperti supline (Task 4) — JANGAN bandingkan nilai (===) ke guide.ref di sini.
          // `cen` adalah hasil rekomputasi rata-rata dari vertex yang masing-masing sudah digeser lewat
          // rantai aritmatika terpisah di pointermove — bisa beda dikit scr floating-point dari nilai
          // yang dicocokkan waktu itu, walau axis itu barusan PERSIS ter-align (pelajaran dari bug nyata
          // yang ketemu & diperbaiki 2x di Task 4, kelas bug sama: "lurus pas drag, bengkok pas lepas").
          // Cukup cek KEHADIRAN guide di this._lastGuides: kalau ada, percaya posisi sekarang apa adanya.
          const snappedCen = {
            x: gx ? cen.x : this.snap(cen.x),
            y: gy ? cen.y : this.snap(cen.y),
          };
          const shiftX = snappedCen.x - cen.x, shiftY = snappedCen.y - cen.y;
          drag.vertIdx.forEach(vi => { const p = this.S.verts[vi]; this.S.verts[vi] = { x: p.x + shiftX, y: p.y + shiftY }; });
        }
        this._hideAlignGuides();
      }
      drag = null; this.render(); };
```

- [ ] **Step 6: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk kelimanya.

- [ ] **Step 7: Verifikasi manual di harness**

Reload harness. Mode **Bentuk** — dulu pakai "+ Tambah Kotak" (tab Mode) buat bikin 1 kotak nempel di salah satu sisi (span < panjang sisi, biar ada "sisi governing" yang lebih panjang dari kotak). Checklist:
1. Ketuk BADAN kotak (area dalam, bukan titik sudut) → tekan-geser → SEMUA sudut kotak ikut pindah bareng, bentuk kotak tetap sama (persegi, tak berubah ukuran).
2. Garis frame yang nempel ke kotak ikut ter-update posisinya real-time selama drag (tak ketinggalan/robek dari kotak).
3. Titik sudut kotak (r=5, kelihatan) tetap prioritas: tekan TEPAT di titik sudut → drag-sudut-tunggal biasa (Kelompok A), BUKAN drag-kotak-utuh.
4. Perpanjang sisi governing lewat "Ukur Sisi" (jadi lebih panjang dari sebelumnya) → drag kotak mendekati titik tengah sisi itu → muncul garis bantu kuning, kotak nempel PAS di tengah sisi yang BARU (bukan tengah lama).
5. Lepas jari pas nempel-tengah → kotak tetap persis di tengah (tak melenceng dikit).
6. Undo → kotak balik ke posisi sebelumnya, bentuk poligon utuh (tak corrupt).
7. Drag sudut TUNGGAL kotak (bukan badan) seperti biasa (Kelompok A) → masih jalan normal (regresi check, tak terganggu fitur baru).
8. Hitung Harga → total & rincian besi tak berubah krn drag posisi (regresi check — posisi kotak beda, tapi ukuran/luas total denah TETAP kalau geser murni di dalam sisi yang sama).

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): drag-pindah kotak support (utuh) + align-snap + snap-tengah-sisi"
```

---

### Task 7: Deploy & verifikasi Elvan di production

**Files:** (tidak ada file baru — deploy murni `git push`)

- [ ] **Step 1: Matikan harness lokal**

```bash
pkill -f "php -S 0.0.0.0:8892" 2>/dev/null; echo done
```

- [ ] **Step 2: Push ke production**

```bash
git push
```
Expected: GitHub Actions `deploy.yml` jalan, FTP ke Niagahoster, selesai ±1-2 menit.

- [ ] **Step 3: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata, di HP)**

1. Mode Tiang: tap tiang tanpa gerak → tetap hapus (perilaku lama). Tekan-geser tiang → pindah, muncul garis bantu kuning kalau sejajar tiang lain, lepas → tetap sejajar persis.
2. Mode Support: tekan BADAN garis A-B (bukan ujung) → geser → seluruh garis pindah bareng, tetap lurus/sejajar dirinya sendiri. Drag ujung sendiri (fitur lama) tetap jalan.
3. Kotak support (dari "+ Tambah Kotak"): tekan badan kotak → geser → semua sudut ikut pindah bareng. Perpanjang sisi tempat kotak nempel (lewat "Ukur Sisi" atau lekukan), lalu geser kotak → nempel ke tengah sisi yang BARU.
4. Drag sudut poligon tunggal (Kelompok A) dan drag ujung support manual tunggal (Kelompok A) — masih jalan normal, TAK terganggu fitur baru (regresi check).
5. Undo/Redo untuk ketiga drag baru (tiang/support-garis/kotak) — balik ke posisi sebelumnya dengan benar.
6. Simpan (autosave) → reload halaman → posisi tiang/support/kotak yang sudah dipindah tetap tersimpan benar.
7. Klik "Hitung Harga" → rincian besi & total tetap benar, tak ada regresi dari fitur-fitur sebelumnya (Gabungan Kotak, ortho-snap, dsb).
8. Blok kanopi/manual & blok denah lain di opsi yang sama → tetap normal (regresi check).

- [ ] **Step 4: Update `MEMORI_PROYEK.md`**

Tandai item **#10 Kelompok B** selesai (kalau dikonfirmasi Elvan): drag-pindah tiang/support-garis/kotak + align-snap generik. Catat lanjut ke Kelompok C (saran-kotak-2-arah) sebagai langkah berikutnya. Commit dokumentasi terpisah dari kode.

---

## Ringkasan cakupan spec vs task

| Spec (`2026-07-17-denah-ui-kelompok-b-design.md`) | Task |
|---|---|
| §3.1 Data model (`S.combinedBoxes`, invalidasi) | 5 |
| §3.2 Mekanik drag: Tiang | 3 |
| §3.2 Mekanik drag: Support manual | 4 |
| §3.2 Mekanik drag: Kotak support | 5 (metadata) + 6 (drag) |
| §3.3 Mesin snap generik (kandidat, threshold, soft-snap, guide visual, bug-class fix) | 1 (mesin) + 2 (guide render) + 3/4/6 (integrasi per elemen + fix end()) |
| §4 Alur pakai | 3+4+6, dirangkai lewat checklist manual tiap task & checklist production Task 7 |
| §5 Testing (snap engine & reindex testable node; drag-badan/guide manual browser) | 1, 5 (Node) — 2/3/4/6 (harness lokal) — 7 (production) |
| §6 Yang TIDAK berubah | Global Constraints (satu file, `S.verts`/`DenahConv` kontrak lama tetap, `combinedBoxes` tak dikirim ke mesin hitung) |

**Di luar plan ini:** Kelompok C (saran-kotak-2-arah) — spec §1 sudah menyatakan ini di luar cakupan.
