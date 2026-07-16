# DenahEditor Kelompok A — ribbon layout, zoom, ukuran visual, ortho-snap support — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 4 perbaikan UI mobile di `DenahEditor` (`public/js/denah-editor.js`): ribbon layout (kontrol dikelompokkan jadi tab, kanvas selalu besar & terlihat), zoom & pan (pinch + tombol Reset), ukuran visual (titik sudut dikecilin, garis/label dibesarin), ortho-snap support manual (drag titik ujung ikut lurus otomatis).

**Architecture:** Semua perubahan di **satu file** `public/js/denah-editor.js` (classic-script IIFE, `globalThis.DenahConv`/`globalThis.DenahEditor` — pola yang sudah ada, tidak berubah). Ortho-snap support jadi 1 fungsi geometri murni baru di `DenahConv` (testable via Node). Ribbon = restrukturisasi `shellHTML()` (kontrol dipindah ke dalam 5 panel yang disembunyikan/ditampilkan lewat method baru `_wireRibbon()`) — tidak mengubah `data-role` elemen input yang sudah ada, jadi `_wireControls()` tidak perlu diubah. Zoom/pan = CSS `transform: translate()/scale()` pada `<div class="de-canvas">` (bukan `viewBox`/`SC`), dikendalikan lewat Pointer Events di wrapper baru `.de-canvas-wrap`, method baru `_wireZoom()` — terpisah total dari `bindSvg()` yang sudah ada (drag vertex/support/box tidak disentuh, cuma dibatalkan otomatis kalau jari ke-2 nempel lewat `dispatchEvent(pointercancel)`).

**Tech Stack:** JS vanilla, tanpa framework/bundler. Tes geometri murni via Node (`node tests/rangka/test_ortho_snap.mjs`, pola sama `test_konverter.mjs`/`test_box_union.mjs`). Verifikasi UI/zoom manual lewat harness lokal baru (`tests/rangka/denah_editor_harness.html`, gitignored) yang me-mount `DenahEditor` asli tanpa perlu Laravel/DB — dites di browser sebelum deploy ke production.

## Global Constraints

- **Satu file kode yang berubah:** `public/js/denah-editor.js`. Blade (`resources/views/rab-opsi/index.blade.php`) **TIDAK disentuh** — sudah memuat file ini dengan cache-bust otomatis (`?v={{ filemtime(...) }}`, `index.blade.php:196`), tak perlu langkah cache-bust manual.
- **Semua `data-role` elemen yang sudah ada TETAP** (`inL`, `inP`, `inT`, `inGrid`, `btnReset`, `inArah`, `inKotak`, `btnSaran`, `saranHint`, `matFrame`, `matSupport`, `matTiang`, `btnAddV`, `btnDelV`, `btnAddBox`, `btnUndo`, `btnAddSupport`, `boxPanel`, `hint`, `legend`, `luas`, `sisiPanel`, `matMenu`, `matPick`, `matApply`, `matClear`) — cuma DIPINDAH lokasinya di dalam HTML (masuk ke panel ribbon), isi/perilaku `_wireControls()` **tidak berubah sama sekali**.
- **`S.verts`/model data tetap satu-satunya sumber kebenaran** — keempat perbaikan di plan ini murni presentasi/interaksi, tidak ada satupun yang mengubah `DenahConv.buildMembers`/`luasM2`/`combineBox`/kontrak model yang sudah ada.
- **Emoji dilarang** di kode yang tampil ke user (korup di server Niagahoster, CLAUDE.md) — label teks/simbol biasa saja.
- **`SC`/`viewBox`/koordinat cm↔px (`toCm`, `render()` baris 499-503) TIDAK diubah** — zoom pakai layer CSS transform terpisah di atasnya (spec §3.2.1).
- **Verifikasi UI dilakukan manual** lewat harness lokal (`tests/rangka/denah_editor_harness.html`, dibuat Task 2) sebelum deploy — bukan otomatis (tak ada DOM test runner di repo, sama seperti fitur Gabungan Kotak sebelumnya).

---

### Task 1: `DenahConv._orthoSnapToPoint` — ortho-snap support manual + tes Node

**Files:**
- Modify: `public/js/denah-editor.js`
- Create: `tests/rangka/test_ortho_snap.mjs`

**Interfaces:**
- Produces (dipakai langsung di `bindSvg`, tidak dipakai task lain):
  - `DenahConv._orthoSnapToPoint(p, anchor, TH) -> {x, y}` — kalau `|p.x - anchor.x| < TH`, `x` diganti `anchor.x` (independen untuk `y`/`anchor.y`). Kalau keduanya jauh, `p` dikembalikan apa adanya.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_ortho_snap.mjs`:

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

const TH = 16; // grid 20 * 0.8, threshold yang sama dipakai ortho-snap support di bindSvg

// X dekat anchor (beda 10 < TH), Y jauh (beda 200) -> X snap ke anchor.x, Y tetap
check('snap X ke anchor', DenahConv._orthoSnapToPoint({ x: 110, y: 500 }, { x: 100, y: 300 }, TH), { x: 100, y: 500 });

// Y dekat anchor (beda 5 < TH), X jauh -> Y snap, X tetap
check('snap Y ke anchor', DenahConv._orthoSnapToPoint({ x: 400, y: 305 }, { x: 100, y: 300 }, TH), { x: 400, y: 300 });

// Keduanya dekat (X beda 8, Y beda 3) -> keduanya snap sekaligus (titik pas nempel anchor)
check('snap X dan Y sekaligus', DenahConv._orthoSnapToPoint({ x: 108, y: 303 }, { x: 100, y: 300 }, TH), { x: 100, y: 300 });

// Keduanya jauh -> tak berubah
check('tak snap kalau jauh', DenahConv._orthoSnapToPoint({ x: 250, y: 250 }, { x: 100, y: 300 }, TH), { x: 250, y: 250 });

// Pas di threshold (selisih == TH, bukan < TH) -> tak snap (batas eksklusif)
check('tak snap tepat di threshold', DenahConv._orthoSnapToPoint({ x: 116, y: 500 }, { x: 100, y: 300 }, TH), { x: 116, y: 500 });

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_ortho_snap.mjs`
Expected: error `DenahConv._orthoSnapToPoint is not a function` (fungsi belum ada) → proses exit non-zero.

- [ ] **Step 3: Tambah `orthoSnapToPoint` sebagai helper murni + ekspos di `DenahConv`**

Di `public/js/denah-editor.js`, cari (akhir `isSimplePolygon` + awal `DenahConv`):

```js
};

const DenahConv = {
  buildMembers(S) {
```

Ganti jadi (tambah helper baru sebelum `const DenahConv`):

```js
};
// Snap 1 titik ke sumbu X/Y titik acuan kalau jaraknya < threshold (dipakai ortho-snap
// drag ujung support manual — garis jadi lurus tanpa harus pas manual, pola sama seperti
// ortho-snap drag sudut poligon yang sudah ada di bindSvg()).
const orthoSnapToPoint = (p, anchor, TH) => {
  let { x, y } = p;
  if (Math.abs(x - anchor.x) < TH) x = anchor.x;
  if (Math.abs(y - anchor.y) < TH) y = anchor.y;
  return { x, y };
};

const DenahConv = {
  buildMembers(S) {
```

Lalu cari baris ekspos test-hook di akhir objek `DenahConv`:

```js
    return isSimplePolygon(out) ? out : null;
  },
  _dist: dist, _bbox: bbox,
};
```

Ganti jadi:

```js
    return isSimplePolygon(out) ? out : null;
  },
  _dist: dist, _bbox: bbox, _orthoSnapToPoint: orthoSnapToPoint,
};
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Jalankan tes lama — pastikan tak ada regresi**

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs`
Expected: `SEMUA LULUS` untuk keduanya (helper baru murni tambahan, tak menyentuh `buildMembers`/`luasM2`/`combineBox`).

- [ ] **Step 6: Pakai `orthoSnapToPoint` di drag ujung support manual (`bindSvg`)**

Di `public/js/denah-editor.js`, method `bindSvg(el)`, cari blok `pointermove` untuk `drag.type === 'sup'`:

```js
        } else if (drag.type === 'sup') {
          this.S.supportsManual[drag.i][drag.end] = { x: cm.x, y: cm.y };
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py);
          drag.h.setAttribute('cx', px); drag.h.setAttribute('cy', py);
          drag.hit.setAttribute('cx', px); drag.hit.setAttribute('cy', py);
        } else if (drag.type === 'box') {
```

Ganti jadi (snap ke X/Y titik ujung SATUNYA di support yang sama, bukan `px`/`py` mentah):

```js
        } else if (drag.type === 'sup') {
          const otherEnd = drag.end === 'a' ? 'b' : 'a';
          const anchor = this.S.supportsManual[drag.i][otherEnd];
          const TH = (this.S.grid || 20) * 0.8;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          this.S.supportsManual[drag.i][drag.end] = snapped;
          drag.line.setAttribute(drag.end === 'a' ? 'x1' : 'x2', px2);
          drag.line.setAttribute(drag.end === 'a' ? 'y1' : 'y2', py2);
          drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2);
          drag.hit.setAttribute('cx', px2); drag.hit.setAttribute('cy', py2);
        } else if (drag.type === 'box') {
```

- [ ] **Step 7: Verifikasi sintaks & tes ulang**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK` (tak ada `SyntaxError`).

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya.

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_ortho_snap.mjs
git commit -m "feat(denah): ortho-snap drag ujung support manual (DenahConv._orthoSnapToPoint) + tes node"
```

---

### Task 2: Harness lokal + ukuran visual (titik sudut/garis/label)

**Files:**
- Modify: `public/js/denah-editor.js` (3 atribut di `render()`)
- Modify: `.gitignore`
- Create: `tests/rangka/denah_editor_harness.html` (gitignored)

**Interfaces:** Tidak ada interface baru — perubahan visual murni + alat bantu verifikasi manual dipakai Task 3 & 4.

- [ ] **Step 1: Tambah entry `.gitignore`**

Di `.gitignore`, cari blok:

```
# Harness preview lokal Perancang Rangka (dev only, tak ikut deploy)
tests/rangka/preview_server.php
tests/rangka/*.log
```

Ganti jadi (tambah 1 baris):

```
# Harness preview lokal Perancang Rangka (dev only, tak ikut deploy)
tests/rangka/preview_server.php
tests/rangka/denah_editor_harness.html
tests/rangka/*.log
```

- [ ] **Step 2: Buat harness `DenahEditor` asli (tanpa Laravel/DB)**

Create `tests/rangka/denah_editor_harness.html`:

```html
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
<title>DenahEditor — harness lokal</title>
<style>body{margin:0;background:#f1f5f9;font-family:system-ui,sans-serif}#wrap{max-width:480px;margin:0 auto;padding:10px}</style>
</head>
<body>
<div id="wrap"><div id="root"></div></div>
<script src="/public/js/denah-editor.js"></script>
<script>
new DenahEditor(document.getElementById('root'), {
  besi: [
    { nama: 'Hollow 4x4', harga: 80000 },
    { nama: 'Hollow 5x10', harga: 120000 },
    { nama: 'WF 150x75', harga: 250000 },
    { nama: 'WF 200 12m', harga: 950000 },
  ],
});
</script>
</body>
</html>
```

- [ ] **Step 3: Jalankan harness, pastikan editor lama (sebelum perubahan apapun) tampil normal**

Run (di VPS, biarkan jalan di background selama Task 2-4):
```bash
php -S 0.0.0.0:8892 -t /root/projects/canopi-app >/tmp/denah-harness.log 2>&1 &
```
Buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html` di browser (HP atau desktop dengan device-toolbar/emulasi sentuh).
Expected: tampilan `DenahEditor` sama seperti di `/rab-opsi` sekarang (kotak default 400×300, semua kontrol & toolbar di atas kanvas seperti biasa) — ini baseline SEBELUM Task 2 Step 4-5 mengubah ukuran visual, dan sebelum Task 3/4.

- [ ] **Step 4: Perbesar garis frame & label sisi**

Di `public/js/denah-editor.js`, method `render()`, cari baris garis frame:

```js
      s += `<line id="fl${i}" x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="${c}" stroke-width="4" stroke-linecap="round"><title>${m.material} • ${m.panjang}cm</title></line>`;
```

Ganti `stroke-width="4"` jadi `stroke-width="5"`:

```js
      s += `<line id="fl${i}" x1="${X(a.x)}" y1="${Y(a.y)}" x2="${X(b.x)}" y2="${Y(b.y)}" stroke="${c}" stroke-width="5" stroke-linecap="round"><title>${m.material} • ${m.panjang}cm</title></line>`;
```

Cari baris label sisi frame:

```js
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="10" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
```

Ganti `font-size="10"` jadi `font-size="13"`:

```js
      s += `<text id="fll${i}" x="${X(mx)}" y="${Y(my) - 5}" fill="#e2e8f0" font-size="13" text-anchor="middle" paint-order="stroke" stroke="#0f2740" stroke-width="3">F${i + 1} · ${m.panjang}</text>`; });
```

- [ ] **Step 5: Kecilkan titik sudut yang kelihatan (hit-area TIDAK berubah)**

Cari baris vertex (hit-area transparan `r="24"` TETAP, cuma bulatan tampak `id="vh${i}"` yang berubah):

```js
      s += `<circle cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="10" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
```

Ganti `r="10"` (baris `id="vh${i}"`) jadi `r="5"`:

```js
      s += `<circle cx="${cx}" cy="${cy}" r="24" fill="transparent" data-vert="${i}" class="vhit" style="cursor:grab"/>`;
      s += `<circle id="vh${i}" cx="${cx}" cy="${cy}" r="5" fill="#fff" stroke="#f59e0b" stroke-width="2.5" class="vh" style="pointer-events:none"/>`; });
```

- [ ] **Step 6: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya (perubahan Step 4-5 murni atribut visual SVG, tidak menyentuh `DenahConv`).

- [ ] **Step 7: Verifikasi manual di harness**

Reload `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`. Checklist:
1. Titik sudut kelihatan lebih kecil dari sebelumnya, tapi tap/drag sudut masih semudah sebelumnya (area sentuh tak berubah).
2. Garis frame kelihatan lebih tebal, label F1/F2 dst kelihatan lebih besar/jelas dibanding titik sudut.
3. Drag sudut, tambah/hapus sudut, ganti besi, support, tiang — semua masih jalan normal seperti sebelumnya (regresi check, belum ada perubahan lain).

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js .gitignore
git commit -m "feat(denah): perbesar garis frame+label, kecilkan titik sudut (hit-area tetap) + harness lokal"
```

Catatan: JANGAN `git add tests/rangka/denah_editor_harness.html` — file itu gitignored (baris `.gitignore` Step 1), dan `git add` yang menyebut path gitignored secara eksplisit akan GAGAL (exit 1, "The following paths are ignored..."), bukan cuma dilewati diam-diam. Cukup `git add` 2 file di atas.

---

### Task 3: Layout ribbon (tab Ukuran/Support/Besi/Mode/Ukur Sisi)

**Files:**
- Modify: `public/js/denah-editor.js` (`shellHTML()`, constructor, method baru `_wireRibbon()`)

**Interfaces:**
- Consumes: harness dari Task 2 (`tests/rangka/denah_editor_harness.html`) untuk verifikasi manual.
- Produces: struktur DOM baru `[data-role=ribbonStrip]` + `.de-ribbon-tab[data-tab=...]` + `.de-ribbon-panel[data-panel=...]`, dipakai Task 4 lewat `[data-role=canvasWrap]` (wrapper kanvas baru yang jadi target pinch/pan).

- [ ] **Step 1: Ganti CSS di `shellHTML()`**

Di `public/js/denah-editor.js`, method `static shellHTML()`, cari blok `<style>` sampai `</style>`:

```html
<style>
.de-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.de-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.de-row>label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-card input[type=number],.de-card input[type=text]{width:78px;padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px}
.de-card select{padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff}
.de-tools{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}
.de-tool{padding:7px 11px;border:1px solid #334155;background:#fff;border-radius:8px;font-size:13px;cursor:pointer;user-select:none}
.de-tool.on{background:#1e293b;color:#fff}
.de-mini{padding:6px 9px;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:12px;cursor:pointer;display:inline-block}
.de-hint{font-size:12px;color:#64748b;margin:6px 2px;min-height:16px}
.de-canvas{background:#0f2740;border-radius:10px;padding:6px;overflow:auto}
.de-canvas svg{max-width:100%;touch-action:none;display:block}
.de-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;font-size:12px;color:#475569}
.de-legend b{font-weight:600}
.de-sw{display:inline-block;width:11px;height:11px;border-radius:2px;margin-right:5px;vertical-align:middle}
.de-matbar{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.de-matbar label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-matmenu{position:fixed;z-index:9999;display:none;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px}
.de-matmenu select{width:150px}
.de-matmenu .de-mrow{display:flex;gap:6px;margin-top:6px}
</style>
```

Ganti jadi (tambah blok ribbon + zoom, perbesar tap-target `.de-tool`/`.de-mini`/`.de-tools`):

```html
<style>
.de-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.de-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.de-row>label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-card input[type=number],.de-card input[type=text]{width:78px;padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px}
.de-card select{padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff}
.de-tools{display:flex;flex-wrap:wrap;gap:10px;margin:2px 0}
.de-tool{padding:10px 14px;min-height:40px;box-sizing:border-box;display:inline-flex;align-items:center;border:1px solid #334155;background:#fff;border-radius:8px;font-size:13px;cursor:pointer;user-select:none}
.de-tool.on{background:#1e293b;color:#fff}
.de-mini{padding:9px 13px;min-height:40px;box-sizing:border-box;display:inline-flex;align-items:center;border:1px solid #cbd5e1;background:#fff;border-radius:7px;font-size:12px;cursor:pointer}
.de-hint{font-size:12px;color:#64748b;margin:6px 2px;min-height:16px}
.de-ribbon-tabs{display:flex;border:1px solid #334155;border-radius:8px 8px 0 0;overflow:hidden;background:#1e293b}
.de-ribbon-tab{flex:1;text-align:center;padding:11px 4px;min-height:40px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:12px;color:#cbd5e1;cursor:pointer;user-select:none;border-right:1px solid #334155}
.de-ribbon-tab:last-child{border-right:none}
.de-ribbon-tab.on{background:#0f2740;color:#38bdf8;font-weight:600}
.de-ribbon-strip{border:1px solid transparent;border-top:none;border-radius:0 0 8px 8px;background:#f8fafc;padding:0;margin-bottom:10px;max-height:0;overflow:hidden;transition:max-height .15s ease}
.de-ribbon-strip.open{border-color:#334155;padding:10px 12px;max-height:220px;overflow-y:auto}
.de-ribbon-panel{display:none}
.de-ribbon-panel.on{display:block}
.de-canvas-wrap{position:relative;touch-action:none}
.de-canvas{background:#0f2740;border-radius:10px;padding:6px;overflow:hidden}
.de-canvas svg{max-width:100%;touch-action:none;display:block}
.de-zoom-reset{position:absolute;right:10px;bottom:10px;min-width:44px;min-height:44px;padding:0 14px;border-radius:22px;background:rgba(15,23,42,.85);color:#e2e8f0;border:1px solid #334155;font-size:13px;display:none;align-items:center;justify-content:center;cursor:pointer;user-select:none}
.de-zoom-reset.show{display:flex}
.de-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;font-size:12px;color:#475569}
.de-legend b{font-weight:600}
.de-sw{display:inline-block;width:11px;height:11px;border-radius:2px;margin-right:5px;vertical-align:middle}
.de-matbar{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.de-matbar label{font-size:12px;display:flex;flex-direction:column;gap:3px}
.de-matmenu{position:fixed;z-index:9999;display:none;background:#fff;border:1px solid #334155;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px}
.de-matmenu select{width:150px}
.de-matmenu .de-mrow{display:flex;gap:6px;margin-top:6px}
</style>
```

- [ ] **Step 2: Ganti body HTML di `shellHTML()`**

Di method yang sama, cari blok body (dari `<div class="de-card">` sampai penutup `</div>` sebelum `de-matmenu`):

```html
<div class="de-card">
  <div class="de-row">
    <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
    <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
    <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
    <label>Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
    <span class="de-mini" data-role="btnReset">Reset kotak dari Lebar×Panjang</span>
  </div>
  <div class="de-row" style="margin-top:8px">
    <label>Arah support
      <select data-role="inArah"><option value="2">Grid 2 arah</option><option value="h">1 arah horizontal (melintang)</option><option value="v">1 arah vertikal (membujur)</option></select>
    </label>
    <label>Kotak support (cm)<input type="number" data-role="inKotak" value="100" step="5" min="1"></label>
    <span class="de-mini" data-role="btnSaran">Pakai saran</span>
    <span class="de-hint" data-role="saranHint"></span>
  </div>
  <div class="de-matbar">
    <label>Besi frame<select data-role="matFrame"></select></label>
    <label>Besi support<select data-role="matSupport"></select></label>
    <label>Besi tiang<select data-role="matTiang"></select></label>
  </div>
  <div class="de-tools">
    <span class="de-tool on" data-mode="bentuk">Bentuk</span>
    <span class="de-tool" data-mode="besi">Ganti besi</span>
    <span class="de-tool" data-mode="support">Support</span>
    <span class="de-tool" data-mode="tiang">Tiang</span>
    <span style="width:10px"></span>
    <span class="de-mini" data-role="btnAddV">+ Sudut</span>
    <span class="de-mini" data-role="btnDelV">− Sudut</span>
    <span class="de-mini" data-role="btnAddBox">+ Tambah Kotak</span>
    <span class="de-mini" data-role="btnUndo">Undo</span>
    <span class="de-mini" data-role="btnAddSupport" style="margin-left:6px">+ Support manual</span>
  </div>
  <div class="de-row" data-role="boxPanel" style="display:none;margin-top:8px"></div>
  <div class="de-hint" data-role="hint">Mode Bentuk: seret bulatan sudut untuk mengubah bentuk. Ketuk angka cm di sisi untuk ketik panjang pasti.</div>
  <div class="de-canvas"></div>
  <div class="de-legend" data-role="legend"></div>
  <div style="font-size:12px;color:#64748b;margin-top:4px">Luas denah: <b data-role="luas">–</b></div>
  <div class="de-legend" data-role="sisiPanel" style="margin-top:10px"></div>
</div>
```

Ganti jadi:

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
    <div class="de-ribbon-panel" data-panel="ukuran">
      <div class="de-row">
        <label>Lebar (cm)<input type="number" data-role="inL" value="400" step="10"></label>
        <label>Panjang (cm)<input type="number" data-role="inP" value="300" step="10"></label>
        <label>Tinggi tiang (cm)<input type="number" data-role="inT" value="300" step="10"></label>
        <label>Snap grid<select data-role="inGrid"><option>10</option><option selected>20</option><option>25</option><option>50</option></select></label>
        <span class="de-mini" data-role="btnReset">Reset kotak dari Lebar×Panjang</span>
      </div>
    </div>
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
    <div class="de-ribbon-panel" data-panel="besi">
      <div class="de-matbar">
        <label>Besi frame<select data-role="matFrame"></select></label>
        <label>Besi support<select data-role="matSupport"></select></label>
        <label>Besi tiang<select data-role="matTiang"></select></label>
      </div>
    </div>
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
  <div class="de-hint" data-role="hint">Mode Bentuk: seret bulatan sudut untuk mengubah bentuk. Ketuk angka cm di sisi untuk ketik panjang pasti.</div>
  <div class="de-canvas-wrap" data-role="canvasWrap">
    <div class="de-canvas"></div>
    <span class="de-zoom-reset" data-role="btnZoomReset">Reset</span>
  </div>
  <div class="de-legend" data-role="legend"></div>
  <div style="font-size:12px;color:#64748b;margin-top:4px">Luas denah: <b data-role="luas">–</b></div>
</div>
```

(`sisiPanel` sekarang di dalam panel ribbon "Ukur Sisi", `data-role` tetap sama — `renderSides()` tidak perlu diubah.)

- [ ] **Step 3: Wiring tab ribbon — method baru `_wireRibbon()`**

Di `public/js/denah-editor.js`, cari method `_wireControls() {` (baris pembuka), dan cari method setelahnya `destroy() {`. Tambahkan method baru **di antara keduanya** (setelah `_wireControls()` selesai, sebelum `destroy()`):

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

- [ ] **Step 4: Panggil `_wireRibbon()` di constructor**

Di `constructor(el, opts)`, cari:

```js
    this.el.innerHTML = DenahEditor.shellHTML();
    this._fillMatSelects();
    this._wireControls();
    this.syncInputs();
    this.render();
```

Ganti jadi:

```js
    this.el.innerHTML = DenahEditor.shellHTML();
    this._fillMatSelects();
    this._wireControls();
    this._wireRibbon();
    this.syncInputs();
    this.render();
```

- [ ] **Step 5: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya (Task 3 murni restrukturisasi HTML/CSS + 1 method baru, tak menyentuh `DenahConv`).

- [ ] **Step 6: Verifikasi manual di harness**

Restart harness kalau server lama sudah mati (`php -S 0.0.0.0:8892 -t /root/projects/canopi-app >/tmp/denah-harness.log 2>&1 &`), reload `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`. Checklist:
1. Begitu halaman dibuka: 5 tab (**Ukuran / Support / Besi / Mode / Ukur Sisi**) kelihatan di atas kanvas, TIDAK ada panel yang otomatis terbuka, kanvas langsung kelihatan besar di bawah tab.
2. Ketuk tab **Ukuran** → panel Lebar/Panjang/Tinggi/Grid/Reset muncul di bawah tab, kanvas turun sedikit (tak hilang/ketutup). Ubah Lebar → kanvas ikut berubah bentuknya.
3. Ketuk tab **Ukuran** lagi (tab yang sama) → panel tertutup, kanvas naik lagi.
4. Ketuk tab **Mode** → toolbar Bentuk/Ganti besi/Support/Tiang/+Sudut/−Sudut/+Kotak/Undo/+Support manual muncul, semua tombol kelihatan lebih lega (tak rapat) dibanding sebelumnya.
5. Ketuk tab **Ukur Sisi** → daftar F1..Fn muncul, ubah salah satu angka → bentuk kanvas ikut berubah.
6. Ketuk tab lain sambil salah satu tab masih terbuka (misal dari Ukuran langsung ke Besi) → panel Ukuran otomatis tertutup, panel Besi yang terbuka (cuma 1 panel terbuka dalam satu waktu).
7. Semua fungsi lama (drag sudut, ganti besi per-batang, tambah/hapus support, tiang, Tambah Kotak, Undo) — tetap jalan normal seperti sebelumnya.

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): layout ribbon (tab Ukuran/Support/Besi/Mode/Ukur Sisi) — kanvas selalu besar & terlihat"
```

---

### Task 4: Zoom & pan (pinch + tombol Reset)

**Files:**
- Modify: `public/js/denah-editor.js` (constructor, method baru `_wireZoom()`)

**Interfaces:**
- Consumes: `[data-role=canvasWrap]`, `[data-role=btnZoomReset]`, `.de-canvas` (Task 3).
- Produces: tidak ada API publik baru — murni interaksi kanvas, tak mengubah `getModel()`/`getMembers()`/dst.

- [ ] **Step 1: State awal zoom di constructor**

Di `constructor(el, opts)`, cari:

```js
    this.SC = 1;
    this.PAD = 44;
```

Ganti jadi:

```js
    this.SC = 1;
    this.PAD = 44;
    this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
```

- [ ] **Step 2: Method baru `_wireZoom()`**

Cari method `_wireRibbon() { ... }` yang dibuat Task 3 (tepat sebelum `destroy() {`). Tambahkan method baru **setelah** `_wireRibbon()`, sebelum `destroy()`:

```js
  // Pinch-zoom + pan (CSS transform di atas .de-canvas — TIDAK menyentuh viewBox/SC/toCm,
  // yang tetap dipakai bindSvg() untuk drag vertex/support/box seperti sebelumnya).
  _wireZoom() {
    const wrap = this._q('[data-role=canvasWrap]');
    const resetBtn = this._q('[data-role=btnZoomReset]');
    const canvasEl = () => this._q('.de-canvas');
    const pointers = new Map();
    let pinch = null; // { startDist, startScale, startMidLocal, startTx, startTy }
    let lastTapTime = 0, lastTapX = 0, lastTapY = 0;

    const applyTransform = () => {
      const c = canvasEl();
      if (c) c.style.transform = `translate(${this.zoomTx}px, ${this.zoomTy}px) scale(${this.zoomScale})`;
      resetBtn.classList.toggle('show', Math.abs(this.zoomScale - 1) > 0.01 || Math.abs(this.zoomTx) > 0.5 || Math.abs(this.zoomTy) > 0.5);
    };
    const resetZoom = () => {
      const c = canvasEl();
      if (c) { c.style.transition = 'transform .2s ease'; setTimeout(() => { if (c) c.style.transition = ''; }, 220); }
      this.zoomScale = 1; this.zoomTx = 0; this.zoomTy = 0;
      applyTransform();
    };
    resetBtn.onclick = resetZoom;

    const dist2 = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const mid2 = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

    wrap.addEventListener('pointerdown', e => {
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pointers.size === 2) {
        // batalkan drag 1-jari yg mungkin lagi jalan (vertex/support/box) di bindSvg
        const svg = canvasEl().querySelector('svg');
        if (svg) svg.dispatchEvent(new PointerEvent('pointercancel'));
        const [p1, p2] = [...pointers.values()];
        const rect = wrap.getBoundingClientRect();
        const startMid = mid2(p1, p2);
        pinch = {
          startDist: dist2(p1, p2), startScale: this.zoomScale,
          startMidLocal: { x: startMid.x - rect.left, y: startMid.y - rect.top },
          startTx: this.zoomTx, startTy: this.zoomTy,
        };
      }
    });

    wrap.addEventListener('pointermove', e => {
      if (!pointers.has(e.pointerId)) return;
      pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pointers.size === 2 && pinch) {
        e.preventDefault();
        const [p1, p2] = [...pointers.values()];
        const rect = wrap.getBoundingClientRect();
        const newDist = dist2(p1, p2);
        const newMid = mid2(p1, p2);
        const newMidLocal = { x: newMid.x - rect.left, y: newMid.y - rect.top };
        const newScale = Math.min(4, Math.max(1, pinch.startScale * (newDist / pinch.startDist)));
        const ratio = newScale / pinch.startScale;
        this.zoomTx = newMidLocal.x - (pinch.startMidLocal.x - pinch.startTx) * ratio;
        this.zoomTy = newMidLocal.y - (pinch.startMidLocal.y - pinch.startTy) * ratio;
        this.zoomScale = newScale;
        applyTransform();
      }
    }, { passive: false });

    const clearPointer = e => { pointers.delete(e.pointerId); if (pointers.size < 2) pinch = null; };
    wrap.addEventListener('pointerup', e => {
      const wasSingle = pointers.size === 1 && !pinch;
      clearPointer(e);
      if (wasSingle && this.zoomScale !== 1) {
        const isEmpty = e.target.dataset.vert == null && !e.target.dataset.id && e.target.dataset.sm == null && !e.target.dataset.boxprev;
        const now = Date.now();
        if (isEmpty && now - lastTapTime < 350 && Math.hypot(e.clientX - lastTapX, e.clientY - lastTapY) < 24) {
          resetZoom(); lastTapTime = 0;
        } else { lastTapTime = now; lastTapX = e.clientX; lastTapY = e.clientY; }
      }
    });
    wrap.addEventListener('pointercancel', clearPointer);
  }

```

- [ ] **Step 3: Panggil `_wireZoom()` di constructor**

Di `constructor(el, opts)`, cari (hasil Task 3 Step 4):

```js
    this._wireControls();
    this._wireRibbon();
    this.syncInputs();
```

Ganti jadi:

```js
    this._wireControls();
    this._wireRibbon();
    this._wireZoom();
    this.syncInputs();
```

- [ ] **Step 4: Verifikasi sintaks & tes node (regresi)**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `syntax OK`.

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs`
Expected: `SEMUA LULUS` untuk ketiganya (Task 4 murni interaksi kanvas via CSS transform, tak menyentuh `DenahConv`/`buildMembers`).

- [ ] **Step 5: Verifikasi manual di harness (perangkat sentuh — HP asli atau device-toolbar Chrome DevTools dengan multi-touch)**

Restart harness kalau perlu, buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html` di HP. Checklist:
1. Pinch 2-jari di kanvas → bentuk denah membesar (zoom in), tombol **Reset** muncul di pojok kanan-bawah kanvas.
2. Geser 2-jari (setelah zoom in) → tampilan ikut geser (pan).
3. **Kritis — cek presisi drag saat zoom:** pas masih dalam kondisi zoom-in, coba drag 1 titik sudut dengan 1 jari → titik HARUS ngikutin posisi jari dengan akurat (tidak geser/offset dari titik sentuh). Kalau meleset, laporkan sebelum lanjut (indikasi `toCm()`/`getScreenCTM()` tak menghitung transform CSS dengan benar di browser yang dipakai).
4. Ketuk 2x cepat (double-tap) di area kosong kanvas (bukan di garis/titik) saat sedang zoom-in → tampilan balik ke fit-semula (animasi halus), tombol Reset hilang lagi.
5. Zoom-in lagi, kali ini ketuk tombol **Reset** (bukan double-tap) → balik ke fit-semula juga.
6. Pinch sampai coba zoom lebih dari 4x → berhenti di batas 4x (tak terus membesar tanpa batas).
7. Pinch-out sampai lebih kecil dari awal → berhenti di 1x (tak jadi lebih kecil dari fit-semula).
8. Mode Support: pinch-zoom TIDAK mengganggu tap-drag titik ujung support manual (ortho-snap dari Task 1 tetap jalan setelah zoom).
9. Ganti blok denah lain (kalau ada >1 instance di halaman nyata) atau reload halaman → zoom balik ke 1x (tak diingat, sesuai spec).

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): pinch-zoom + pan kanvas, tombol Reset eksplisit"
```

---

### Task 5: Deploy & verifikasi Elvan di production

**Files:** (tidak ada file baru — deploy murni `git push`)

- [ ] **Step 1: Matikan harness lokal**

```bash
pkill -f "php -S 0.0.0.0:8892" 2>/dev/null; echo done
```

- [ ] **Step 2: Push ke production**

```bash
git push
```
Expected: GitHub Actions `deploy.yml` jalan, FTP ke Niagahoster, selesai ±1-2 menit (alur baku, CLAUDE.md § Deploy Workflow).

- [ ] **Step 3: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata, di HP)**

1. Buka blok Denah (yang sudah ada atau buat baru) → cek ribbon (5 tab) muncul, tak ada panel otomatis terbuka, kanvas langsung kelihatan besar.
2. Coba tiap tab (Ukuran/Support/Besi/Mode/Ukur Sisi) → buka-tutup normal, kanvas tak pernah hilang dari layar.
3. Pinch-zoom ke bagian kecil (misal support/kotak kecil) → kelihatan jelas, drag sudut/besi saat zoom tetap presisi.
4. Tombol Reset + double-tap kosong → sama-sama balik ke fit-semula.
5. Bikin support manual (2 klik) → drag salah satu ujung mendekati ujung satunya → otomatis lurus (ortho-snap).
6. Titik sudut kelihatan lebih kecil, garis frame+label lebih besar/jelas — tapi tap sudut tetap gampang (area sentuh tak berubah).
7. Simpan (autosave) → reload halaman → bentuk & posisi ribbon (semua tab tertutup lagi, sesuai default) normal.
8. Klik "Hitung Harga" → rincian besi tetap benar, tak ada regresi dari fitur Gabungan Kotak (#9) atau fitur lain yang sudah ada.
9. Blok kanopi/manual & denah lain di opsi yang sama → tetap normal (regresi check).

- [ ] **Step 4: Update `MEMORI_PROYEK.md`**

Tandai item **#10 Kelompok A** selesai: ribbon layout + zoom/pan + ukuran visual + ortho-snap support — dikonfirmasi jalan di production. Catat lanjut ke Kelompok B (drag-pindah-besi + snap-tengah) sebagai langkah berikutnya. Commit dokumentasi terpisah dari kode (pola yang sudah dipakai sesi-sesi sebelumnya).

---

## Ringkasan cakupan spec vs task

| Spec (`2026-07-16-denah-ui-kelompok-a-design.md`) | Task |
|---|---|
| §3.1 Layout ribbon (struktur tab, buka/tutup, kanvas selalu besar, ukuran tap, default tertutup) | 3 |
| §3.2 Zoom & pan (pinch, konflik drag 1-jari, reset otomatis, reset manual 2 cara, batas zoom) | 4 |
| §3.3 Ukuran visual (r sudut, hit-area tetap, stroke-width, font-size) | 2 |
| §3.4 Ortho-snap support manual (target snap, threshold, lokasi kode) | 1 |
| §4 Alur pakai | 3 (ribbon) + 4 (zoom) + 1 (ortho-snap), terangkai lewat checklist manual Task 3 Step 6 & Task 4 Step 5 |
| §5 Testing (ortho-snap testable node; layout/zoom/visual manual browser) | 1 (Node), 2/3/4 (harness lokal), 5 (production) |
| §6 Yang TIDAK berubah | Global Constraints (satu file, `data-role` tetap, `S.verts`/`DenahConv` kontrak tetap, `SC`/`viewBox` tetap) |

**Di luar plan ini (tak masuk scope):** Kelompok B (drag-pindah-besi + snap-tengah) dan Kelompok C (saran-kotak-2-arah) — spec §1 sudah menyatakan ini di luar cakupan, butuh brainstorming terpisah (lihat `MEMORI_PROYEK.md` #10).
