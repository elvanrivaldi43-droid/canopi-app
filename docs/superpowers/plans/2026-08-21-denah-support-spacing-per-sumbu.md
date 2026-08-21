# DenahEditor — Spacing Support Per-Sumbu — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Spacing support grid horizontal & vertikal jadi independen, tiap sumbu bisa mode "cm per kotak" (lama, boleh nyisa) atau "jumlah kolom" (dibagi rata pas), tombol "Pakai saran" dihitung per-sumbu, ada input "ideal per kotak" yang bisa diatur, dan baris sumbu yang tak dipakai (saat arah 1) disembunyikan — semua backward-compatible dengan denah lama tanpa migrasi.

**Architecture:** Satu file `public/js/denah-editor.js`. Logika penempatan garis diekstrak ke fungsi pure baru `DenahConv.posisiSupport()` (testable tanpa DOM), dipakai `buildMembers`. UI + wiring di class `DenahEditor`. Field model baru semua opsional dengan fallback ke `S.kotak` lama.

**Tech Stack:** JavaScript vanilla (SVG string + Pointer Events), Node.js untuk test standalone (`tests/rangka/*.mjs`, pola `readFileSync`+`eval`).

## Global Constraints

- `public/js/denah-editor.js` tetap 1 file (tidak dipecah).
- **Backward-compat WAJIB:** denah lama (model tanpa field `modeH/kotakH/kolomH/modeV/kotakV/kolomV/idealKotak`) harus menghasilkan garis support PERSIS sama seperti sebelum perubahan ini — id `Sh_{li}_{s}`/`Sv_{li}_{s}` tidak boleh bergeser (kunci `S.removed` bergantung padanya). Fallback: field per-sumbu absen → pakai `S.kotak` dengan algoritma langkah lama.
- Field model baru semua OPSIONAL; `S.kotak`/`S.autoKotak`/`S.arah`/`S.target` lama TIDAK dihapus dari model.
- Konvensi arah (JANGAN dibalik): **Horizontal** = garis `Sh_`, loop `arah==='h'||'2'`, melangkah sepanjang Y (`bb.y0→bb.y1`), span = `bb.y1-bb.y0` (= Panjang). **Vertikal** = garis `Sv_`, loop `arah==='v'||'2'`, melangkah sepanjang X (`bb.x0→bb.x1`), span = `bb.x1-bb.x0` (= Lebar).
- Guard matematis: span ≤ 0, kotak ≤ 0, kolom < 1, atau NaN → JANGAN sampai loop tak berhenti / bagi nol; kembalikan fallback aman.
- Emoji DILARANG di file ini (aturan CLAUDE.md).
- VPS ini TIDAK punya browser/DOM — `posisiSupport` (pure) ditest otomatis; UI/wiring diverifikasi `node --check` + review manual + checklist manual Bos di Task 5. Verifikasi tiap task: `node --check public/js/denah-editor.js` + test `.mjs` terkait.

---

### Task 1: `DenahConv.posisiSupport` — penempatan garis (logika murni, testable tanpa DOM)

**Files:**
- Modify: `public/js/denah-editor.js` (tambah fungsi ke `DenahConv`, setelah `saranKotak` di baris ~183)
- Test: `tests/rangka/test_support_spacing.mjs`

**Interfaces:**
- Produces: `DenahConv.posisiSupport(lo, hi, mode, kotak, kolom, kotakFallback): number[]` — array koordinat garis internal. Dipakai `buildMembers` (Task 2).

- [ ] **Step 1: Tulis test (gagal dulu, fungsi belum ada)**

```js
// FILE: tests/rangka/test_support_spacing.mjs
// Jalankan: node tests/rangka/test_support_spacing.mjs
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

// ── mode 'cm' — HARUS identik algoritma langkah lama (backward-compat) ──
// Lama: for (let v = lo + K; v < hi - 1; v += K) push v.
check('cm 0..600 K=100 -> [100,200,300,400,500] (habis rata)',
  DenahConv.posisiSupport(0, 600, 'cm', 100, null, 100), [100, 200, 300, 400, 500]);
check('cm 0..450 K=100 -> [100,200,300,400] (nyisa 50, perilaku lama dipertahankan)',
  DenahConv.posisiSupport(0, 450, 'cm', 100, null, 100), [100, 200, 300, 400]);
// kotak kosong/0 -> pakai kotakFallback (denah lama tanpa kotakH)
check('cm kotak null -> pakai fallback 100',
  DenahConv.posisiSupport(0, 300, 'cm', null, null, 100), [100, 200]);
check('cm kotak 0 -> pakai fallback 100 (guard, bukan loop tak berhenti)',
  DenahConv.posisiSupport(0, 300, 'cm', 0, null, 100), [100, 200]);
// offset lo != 0 (bounding box tak mulai di 0)
check('cm lo=50..350 K=100 -> [150,250] (langkah dari lo+K)',
  DenahConv.posisiSupport(50, 350, 'cm', 100, null, 100), [150, 250]);

// ── mode 'kolom' — bagi rata PERSIS, tanpa sisa ──
// N kolom = N ruas = N-1 garis internal, jarak span/N.
check('kolom 0..450 N=5 -> [90,180,270,360] (semua ruas 90)',
  DenahConv.posisiSupport(0, 450, 'kolom', null, 5, 100), [90, 180, 270, 360]);
check('kolom 0..600 N=6 -> [100,200,300,400,500]',
  DenahConv.posisiSupport(0, 600, 'kolom', null, 6, 100), [100, 200, 300, 400, 500]);
check('kolom N=1 -> [] (0 garis internal, 1 ruas utuh)',
  DenahConv.posisiSupport(0, 400, 'kolom', null, 1, 100), []);
check('kolom lo=100..400 N=3 -> [200,300] (jarak 100, offset dari lo)',
  DenahConv.posisiSupport(100, 400, 'kolom', null, 3, 100), [200, 300]);
// kolom tak valid -> fallback ke perilaku cm dgn kotakFallback (jangan bagi 0/NaN)
check('kolom N=0 -> guard, fallback cm dgn kotakFallback',
  DenahConv.posisiSupport(0, 300, 'kolom', null, 0, 100), [100, 200]);
check('kolom N=NaN -> guard, fallback cm',
  DenahConv.posisiSupport(0, 300, 'kolom', null, NaN, 100), [100, 200]);

// ── guard span ──
check('hi <= lo -> [] (span 0/negatif, tak ada garis)',
  DenahConv.posisiSupport(300, 300, 'cm', 100, null, 100), []);
check('hi < lo -> []',
  DenahConv.posisiSupport(300, 100, 'kolom', null, 3, 100), []);

if (fail) { console.log('\n=== ADA YANG GAGAL ==='); process.exit(1); }
console.log('\n=== SEMUA TES LULUS ===');
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `node tests/rangka/test_support_spacing.mjs`
Expected: error `DenahConv.posisiSupport is not a function`.

- [ ] **Step 3: Implementasi `posisiSupport`**

Di `public/js/denah-editor.js`, cari:
```js
  saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n); },
```
Tambahkan PERSIS setelah baris itu:
```js
  // Posisi garis support internal sepanjang 1 sumbu (lo..hi). Dua mode (Spacing Per-Sumbu, 21 Ags):
  //  - 'kolom': N kolom = N ruas sama besar = N-1 garis di jarak span/N. Bagi rata PERSIS, tanpa sisa.
  //  - 'cm'   : langkah kotak cm dari lo (algoritma LAMA, backward-compat) -- boleh sisa ruas terakhir.
  // kotakFallback dipakai kalau kotak kosong/<=0 (denah lama tanpa kotakH) atau kolom tak valid.
  // Semua jalur di-guard biar tak pernah bagi-nol / loop tak berhenti (span<=0, kotak<=0, kolom<1, NaN).
  posisiSupport(lo, hi, mode, kotak, kolom, kotakFallback) {
    const span = hi - lo;
    if (!(span > 0)) return [];
    const out = [];
    if (mode === 'kolom' && Number.isFinite(kolom) && kolom >= 1) {
      const K = span / kolom;
      for (let i = 1; i < kolom; i++) out.push(lo + K * i);
      return out;
    }
    // mode 'cm' (atau kolom tak valid -> fallback ke cm)
    const K = (kotak > 0 ? kotak : (kotakFallback > 0 ? kotakFallback : 100));
    for (let v = lo + K; v < hi - 1; v += K) out.push(v);
    return out;
  },
```

- [ ] **Step 4: Jalankan test lagi, pastikan lulus**

Run: `node tests/rangka/test_support_spacing.mjs`
Expected: `=== SEMUA TES LULUS ===`, semua `PASS`.

- [ ] **Step 5: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit 0).

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_spacing.mjs
git commit -m "feat(denah): DenahConv.posisiSupport -- penempatan garis support per-sumbu (cm/kolom)"
```

---

### Task 2: Integrasikan `posisiSupport` ke `buildMembers` (behavior-preserving untuk denah lama)

**Files:**
- Modify: `public/js/denah-editor.js` — dua loop support di `buildMembers` (~baris 166-172)
- Test: tambah kasus ke `tests/rangka/test_support_spacing.mjs`

**Interfaces:**
- Consumes: `DenahConv.posisiSupport` (Task 1).

- [ ] **Step 1: Tulis test backward-compat + per-sumbu di `buildMembers` (gagal dulu — masih pakai loop lama)**

Tambahkan ke AKHIR `tests/rangka/test_support_spacing.mjs` (sebelum blok `if (fail)`):

```js
// ── buildMembers: backward-compat (model lama tanpa field per-sumbu) ──
// Kotak persegi 600x450, arah '2', kotak 100 -- HARUS sama persis perilaku lama.
const modelLama = {
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
};
const supLama = DenahConv.buildMembers(modelLama).filter(m => m.jenis === 'support');
// Horizontal Sh_ (langkah Y 0..450, K=100): garis di y=100,200,300,400 -> 4 baris.
// Vertikal Sv_ (langkah X 0..600, K=100): garis di x=100,200,300,400,500 -> 5 baris.
check('backward-compat: jumlah Sh_ (450/100 langkah lama) = 4',
  supLama.filter(m => m.id.startsWith('Sh_')).length, 4);
check('backward-compat: jumlah Sv_ (600/100 langkah lama) = 5',
  supLama.filter(m => m.id.startsWith('Sv_')).length, 5);
// id pertama Sh_ harus tetap 'Sh_0_0' (kunci S.removed bergantung ini)
check('backward-compat: id Sh_ pertama tetap Sh_0_0',
  supLama.filter(m => m.id.startsWith('Sh_'))[0].id, 'Sh_0_0');

// ── buildMembers: mode kolom per-sumbu ──
// Sisi Y (Panjang 450) mode kolom 5 -> 4 garis Sh_ (ruas 90). Sisi X (Lebar 600) mode kolom 6 -> 5 garis Sv_.
const modelBaru = {
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2',
  modeH: 'kolom', kolomH: 5, modeV: 'kolom', kolomV: 6,
  supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
};
const supBaru = DenahConv.buildMembers(modelBaru).filter(m => m.jenis === 'support');
check('per-sumbu: Sh_ kolomH=5 -> 4 garis', supBaru.filter(m => m.id.startsWith('Sh_')).length, 4);
check('per-sumbu: Sv_ kolomV=6 -> 5 garis', supBaru.filter(m => m.id.startsWith('Sv_')).length, 5);
// Ruas Sh_ semua 90 (450/5): panjang tiap Sh_ TIDAK diuji (itu lebar span X), tapi posisi Y-nya kelipatan 90.
// Cek via geom.a.y garis Sh_ pertama = 90.
check('per-sumbu: Sh_ pertama di y=90 (450/5 rata)',
  supBaru.filter(m => m.id.startsWith('Sh_'))[0].geom.a.y, 90);
```

- [ ] **Step 2: Jalankan test, pastikan kasus baru gagal / hasil tidak sesuai**

Run: `node tests/rangka/test_support_spacing.mjs`
Expected: kasus `modelBaru` (mode kolom) FAIL karena `buildMembers` masih pakai loop `S.kotak` lama; kasus `modelLama` mungkin sudah lulus. Yang penting: minimal ada FAIL di kasus mode kolom.

- [ ] **Step 3: Ganti dua loop support di `buildMembers`**

Cari:
```js
    if (S.arah === 'h' || S.arah === '2') { let li = 0;
      for (let Y = bb.y0 + K; Y < bb.y1 - 1; Y += K, li++) { const xs = scanX(V, Y);
        for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); } }
    if (S.arah === 'v' || S.arah === '2') { let li = 0;
      for (let X = bb.x0 + K; X < bb.x1 - 1; X += K, li++) { const ys = scanY(V, X);
        for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); } }
```
Ganti jadi:
```js
    // Posisi garis per-sumbu (Spacing Per-Sumbu, 21 Ags). K lama jadi kotakFallback -- denah lama
    // tanpa modeH/kotakH tetap ke jalur 'cm' + fallback, hasil PERSIS sama (id Sh_/Sv_ tak bergeser).
    if (S.arah === 'h' || S.arah === '2') {
      const posH = DenahConv.posisiSupport(bb.y0, bb.y1, S.modeH || 'cm', S.kotakH, S.kolomH, K);
      posH.forEach((Y, li) => { const xs = scanX(V, Y);
        for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); });
    }
    if (S.arah === 'v' || S.arah === '2') {
      const posV = DenahConv.posisiSupport(bb.x0, bb.x1, S.modeV || 'cm', S.kotakV, S.kolomV, K);
      posV.forEach((X, li) => { const ys = scanY(V, X);
        for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); });
    }
```

- [ ] **Step 4: Jalankan test lagi, pastikan semua lulus**

Run: `node tests/rangka/test_support_spacing.mjs`
Expected: `=== SEMUA TES LULUS ===`, termasuk backward-compat DAN mode kolom.

- [ ] **Step 5: Regresi test lama (pastikan tak ada yang rusak)**

Run: `node tests/rangka/test_support_pola.mjs && node tests/rangka/test_tiang_numerik.mjs && node tests/rangka/test_denah_blok.php 2>/dev/null; php tests/rangka/test_denah_blok.php`
Expected: semua PASS. (`test_denah_blok.php` menguji engine biaya PHP — perubahan ini murni JS penempatan garis, harusnya tak berpengaruh; ini regresi jaga-jaga.)

- [ ] **Step 6: Verifikasi syntax + commit**

Run: `node --check public/js/denah-editor.js`
```bash
git add public/js/denah-editor.js tests/rangka/test_support_spacing.mjs
git commit -m "feat(denah): buildMembers pakai posisiSupport per-sumbu (backward-compat denah lama)"
```

---

### Task 3: UI tab Support — input ideal, baris per-sumbu (cm/kolom), ganti field lama

**Files:**
- Modify: `public/js/denah-editor.js` — HTML panel support (~baris 346-358)

**Interfaces:**
- Produces: elemen `data-role`: `inIdeal`, `rowSupH`/`rowSupV`, `modeH`/`modeV`, `inKotakH`/`inKotakV`, `inKolomH`/`inKolomV`, `lblKotakH`/`lblKolomH`/`lblKotakV`/`lblKolomV` — dikonsumsi wiring Task 4.

- [ ] **Step 1: Ganti isi panel support (baris atas + 2 baris per-sumbu), hapus field `inKotak` lama**

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
      <div class="de-row" style="margin-top:8px">
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
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
        <label>Ideal per kotak (cm)<input type="number" data-role="inIdeal" value="100" step="5" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
        <span class="de-hint" data-role="saranHint"></span>
      </div>
      <div class="de-row" data-role="rowSupH" style="margin-top:8px">
        <span style="font-size:12px;color:#334155;min-width:64px">Horizontal</span>
        <label>Mode<select data-role="modeH"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakH">Kotak (cm)<input type="number" data-role="inKotakH" step="5" min="1"></label>
        <label data-role="lblKolomH" style="display:none">Jumlah kolom<input type="number" data-role="inKolomH" step="1" min="1"></label>
      </div>
      <div class="de-row" data-role="rowSupV" style="margin-top:6px">
        <span style="font-size:12px;color:#334155;min-width:64px">Vertikal</span>
        <label>Mode<select data-role="modeV"><option value="cm">cm per kotak</option><option value="kolom">jumlah kolom</option></select></label>
        <label data-role="lblKotakV">Kotak (cm)<input type="number" data-role="inKotakV" step="5" min="1"></label>
        <label data-role="lblKolomV" style="display:none">Jumlah kolom<input type="number" data-role="inKolomV" step="1" min="1"></label>
      </div>
      <div class="de-row" style="margin-top:8px">
        <span class="de-mini" data-role="btnAddSupport">+ Support manual</span>
      </div>
    </div>
```

- [ ] **Step 2: Verifikasi syntax**

Run: `node --check public/js/denah-editor.js`
Expected: tidak ada output (exit 0).

- [ ] **Step 3: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): UI tab Support -- input ideal + baris per-sumbu (cm/kolom), ganti field kotak tunggal"
```

---

### Task 4: Wiring — handler input baru, `applySaran` per-sumbu, `syncInputs`, show/hide baris

**Files:**
- Modify: `public/js/denah-editor.js` — `_wireControls()` (~baris 454-461), `syncInputs()` (~baris 751-760), `applySaran()`/`saranKotak()`/`updSaranHint()` (~baris 762-779), `resizeBox()` (~baris 803), method baru `_syncSupportRows()`

**Interfaces:**
- Consumes: elemen `data-role` dari Task 3, `DenahConv.saranKotak` (existing), `DenahConv._bbox` (existing).

- [ ] **Step 1: Ganti handler lama `inArah`/`inKotak`, tambah handler input baru di `_wireControls()`**

Cari:
```js
    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this.render(); };
    this._q('[data-role=inKotak]').oninput = e => { this.S.kotak = Math.max(1, +e.target.value) || this.S.kotak; this.S.autoKotak = false; this.render(); };
```
Ganti jadi:
```js
    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inIdeal]').oninput = e => { this.S.idealKotak = Math.max(1, +e.target.value) || 100; this.updSaranHint(); };
    // Per-sumbu: mode dropdown ganti field yang tampil (cm vs kolom) + tulis ke model + render.
    this._q('[data-role=modeH]').onchange = e => { this.S.modeH = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=modeV]').onchange = e => { this.S.modeV = e.target.value; this._syncSupportRows(); this.render(); };
    this._q('[data-role=inKotakH]').oninput = e => { this.S.modeH = 'cm'; this.S.kotakH = Math.max(1, +e.target.value) || this.S.kotakH; this.render(); };
    this._q('[data-role=inKotakV]').oninput = e => { this.S.modeV = 'cm'; this.S.kotakV = Math.max(1, +e.target.value) || this.S.kotakV; this.render(); };
    this._q('[data-role=inKolomH]').oninput = e => { this.S.modeH = 'kolom'; this.S.kolomH = Math.max(1, Math.floor(+e.target.value)) || this.S.kolomH; this.render(); };
    this._q('[data-role=inKolomV]').oninput = e => { this.S.modeV = 'kolom'; this.S.kolomV = Math.max(1, Math.floor(+e.target.value)) || this.S.kolomV; this.render(); };
```

- [ ] **Step 2: Tambah method `_syncSupportRows()` (show/hide baris per arah + field per mode)**

Tambahkan method baru PERSIS setelah `syncInputs()` (sebelum komentar `// ---- Kotak saran`):
```js

  // Tampil/sembunyikan baris setelan per-sumbu (Spacing Per-Sumbu, 21 Ags):
  //  - arah '2' -> dua baris; 'h' -> horizontal saja; 'v' -> vertikal saja (yang lain disembunyikan).
  //  - tiap baris: mode 'cm' tampilkan input Kotak, mode 'kolom' tampilkan input Jumlah kolom.
  _syncSupportRows() {
    const arah = this.S.arah;
    this._q('[data-role=rowSupH]').style.display = (arah === 'h' || arah === '2') ? '' : 'none';
    this._q('[data-role=rowSupV]').style.display = (arah === 'v' || arah === '2') ? '' : 'none';
    const modeH = this.S.modeH || 'cm', modeV = this.S.modeV || 'cm';
    this._q('[data-role=lblKotakH]').style.display = modeH === 'cm' ? '' : 'none';
    this._q('[data-role=lblKolomH]').style.display = modeH === 'kolom' ? '' : 'none';
    this._q('[data-role=lblKotakV]').style.display = modeV === 'cm' ? '' : 'none';
    this._q('[data-role=lblKolomV]').style.display = modeV === 'kolom' ? '' : 'none';
  }
```

- [ ] **Step 3: Perbarui `syncInputs()` — isi input baru dari model (fallback ke `S.kotak` buat denah lama)**

Cari:
```js
  syncInputs() {
    this._q('[data-role=inArah]').value = this.S.arah;
    this._q('[data-role=inKotak]').value = this.S.kotak;
    this._q('[data-role=inGrid]').value = this.S.grid;
    this._q('[data-role=inT]').value = this.S.tinggi;
    this._q('[data-role=matFrame]').value = this.S.matDefault.frame;
    this._q('[data-role=matSupport]').value = this.S.matDefault.support;
    this._q('[data-role=matTiang]').value = this.S.matDefault.tiang;
    this.syncLP();
  }
```
Ganti jadi:
```js
  syncInputs() {
    this._q('[data-role=inArah]').value = this.S.arah;
    this._q('[data-role=inIdeal]').value = this.S.idealKotak != null ? this.S.idealKotak : 100;
    // Denah lama tanpa kotakH/kotakV -> tampilkan S.kotak sebagai nilai efektif (yang lagi tergambar).
    this._q('[data-role=modeH]').value = this.S.modeH || 'cm';
    this._q('[data-role=modeV]').value = this.S.modeV || 'cm';
    this._q('[data-role=inKotakH]').value = this.S.kotakH != null ? this.S.kotakH : this.S.kotak;
    this._q('[data-role=inKotakV]').value = this.S.kotakV != null ? this.S.kotakV : this.S.kotak;
    this._q('[data-role=inKolomH]').value = this.S.kolomH != null ? this.S.kolomH : '';
    this._q('[data-role=inKolomV]').value = this.S.kolomV != null ? this.S.kolomV : '';
    this._q('[data-role=inGrid]').value = this.S.grid;
    this._q('[data-role=inT]').value = this.S.tinggi;
    this._q('[data-role=matFrame]').value = this.S.matDefault.frame;
    this._q('[data-role=matSupport]').value = this.S.matDefault.support;
    this._q('[data-role=matTiang]').value = this.S.matDefault.tiang;
    this._syncSupportRows();
    this.syncLP();
  }
```

- [ ] **Step 4: Ganti `saranKotak()`/`applySaran()`/`updSaranHint()` jadi per-sumbu**

Cari seluruh blok:
```js
  // ---- Kotak saran (pakai DenahConv.saranKotak — Task 1) ----
  saranKotak() {
    const L = +(this._q('[data-role=inL]').value) || 0;
    if (L <= 0) return this.S.kotak;
    return DenahConv.saranKotak(L, this.S.target);
  }
  applySaran() {
    this.S.autoKotak = true;
    this.S.kotak = this.saranKotak();
    this._q('[data-role=inKotak]').value = this.S.kotak;
    this.updSaranHint();
    this.render();
  }
  updSaranHint() {
    const sug = this.saranKotak();
    const L = +(this._q('[data-role=inL]').value) || 0;
    this._q('[data-role=saranHint]').textContent = `saran ~${this.S.target}cm → ${sug}cm (${Math.max(1, Math.round(L / this.S.target))} bagian simetris)`;
  }
```
Ganti jadi:
```js
  // ---- Saran spacing per-sumbu (Spacing Per-Sumbu, 21 Ags) ----
  // Ideal per kotak diambil dari S.idealKotak (default 100). Dihitung TERPISAH:
  //  - Horizontal (garis Sh_, melangkah sepanjang Y) pakai span Y = Panjang.
  //  - Vertikal   (garis Sv_, melangkah sepanjang X) pakai span X = Lebar.
  _idealKotak() { return this.S.idealKotak > 0 ? this.S.idealKotak : 100; }
  applySaran() {
    const bb = DenahConv._bbox(this.S.verts);
    const spanY = bb.y1 - bb.y0, spanX = bb.x1 - bb.x0;
    const ideal = this._idealKotak();
    if (spanY > 0) { this.S.modeH = 'cm'; this.S.kotakH = DenahConv.saranKotak(spanY, ideal); }
    if (spanX > 0) { this.S.modeV = 'cm'; this.S.kotakV = DenahConv.saranKotak(spanX, ideal); }
    this.syncInputs();
    this.render();
  }
  updSaranHint() {
    const bb = DenahConv._bbox(this.S.verts);
    const spanY = bb.y1 - bb.y0, spanX = bb.x1 - bb.x0, ideal = this._idealKotak();
    const kH = spanY > 0 ? DenahConv.saranKotak(spanY, ideal) : 0;
    const kV = spanX > 0 ? DenahConv.saranKotak(spanX, ideal) : 0;
    this._q('[data-role=saranHint]').textContent = `saran ~${ideal}cm -> H ${kH}cm, V ${kV}cm`;
  }
```

- [ ] **Step 5: Perbarui `resizeBox()` — hapus referensi `autoKotak`/`S.kotak` auto (sekarang saran manual per-sumbu)**

Cari:
```js
    this.S.supportsManual = (this.S.supportsManual || []).map(m => ({ a: sc(m.a), b: sc(m.b) }));
    if (this.S.autoKotak) this.S.kotak = DenahConv.saranKotak(L, this.S.target);
    this.render();
```
Ganti jadi:
```js
    this.S.supportsManual = (this.S.supportsManual || []).map(m => ({ a: sc(m.a), b: sc(m.b) }));
    this.render();
```
(Auto-recompute spacing saat resize DIHAPUS -- spacing sekarang eksplisit per-sumbu via input/saran, tidak lagi auto-ikut resize. Ini keputusan sadar: mode 'kolom' tetap rata otomatis walau ukuran berubah, mode 'cm' tetap nilai yang diketik user.)

- [ ] **Step 6: Verifikasi syntax + regresi**

Run: `node --check public/js/denah-editor.js && node tests/rangka/test_support_spacing.mjs && node tests/rangka/test_support_pola.mjs`
Expected: syntax bersih, kedua test `=== SEMUA TES LULUS ===`.

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): wiring spacing per-sumbu -- handler input, applySaran per-sumbu, show/hide baris"
```

---

### Task 5: Regresi akhir + daftar manifest + checklist manual

**Files:** Tidak ada file baru (mungkin edit `tests/guardrail/manifest.json`).

**Interfaces:** Consumes Task 1-4.

- [ ] **Step 1: Daftarkan test baru ke manifest guardrail**

`tests/guardrail/test_manifest.php` mewajibkan tiap `*.mjs` di `tests/` terdaftar. Tambahkan entri `tests/rangka/test_support_spacing.mjs` ke array `tests` di `tests/guardrail/manifest.json` (format sama entri `.mjs` lain: `runner: "node"`, `requires_db: false`, `manual: false`). Baca file dulu, tiru format entri `test_support_pola.mjs`.

Run: `php tests/guardrail/test_manifest.php`
Expected: `PASS: manifest valid — N tes otomatis, ...` (N bertambah 1).

- [ ] **Step 2: Jalankan seluruh guardrail penuh**

Run: `./scripts/canopi-check --full`
Expected: `PASS: canopi-check full selesai`, tidak ada FAIL.

- [ ] **Step 3: Checklist verifikasi manual (WAJIB dijalankan Bos di browser/HP — tak bisa diotomasi dari VPS ini)**

Tulis checklist ini ke pesan akhir buat Bos (bahasa awam, dikelompokkan biar ringkas):

**A. Saran per-sumbu (kasus 600×450):** Buat denah 600×450, tab Support, arah "Grid 2 arah". Pastikan ada input "Ideal per kotak" (default 100), dan 2 baris setelan (Horizontal & Vertikal). Klik "Pakai saran" → pastikan Horizontal & Vertikal terisi angka yang bikin masing-masing sisi rata (harusnya sisi 600 → 100cm, sisi 450 → 90cm), gambar simetris dua-duanya, gak ada lagi kolom sisa 50cm.

**B. Mode jumlah kolom:** Di baris (mis. Vertikal), ganti mode ke "jumlah kolom", isi 5 → pastikan sisi itu terbagi jadi 5 ruas sama besar. Ganti ideal per kotak ke angka lain (mis. 80), klik "Pakai saran" lagi → pastikan hasilnya ikut berubah sesuai angka ideal baru.

**C. Arah 1 sembunyikan baris:** Ganti arah ke "1 arah horizontal" → pastikan baris setelan Vertikal HILANG (cuma Horizontal yang tampil). Ganti ke "1 arah vertikal" → kebalikannya. Balik ke "Grid 2 arah" → dua baris muncul lagi.

**D. Denah lama tak berubah + regresi:** Buka denah/opsi LAMA yang sudah ada sebelumnya → pastikan tampilan support-nya TIDAK berubah dari sebelumnya (masih seperti dulu). Pastikan juga interaksi support (geser/tahan/menu dari sesi sebelumnya), Frame, dan Tiang semua masih normal.

**Jangan tandai selesai ke Bos sebelum checklist A-D benar-benar dicoba dan lolos.**
