# Denah Support ID Stabil — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Garis support otomatis dapat identitas permanen (S1..Sn) lewat fase pratinjau→terkunci, dikelola via panel ceklis + pindah-ketik-angka, tanpa drag liar.

**Architecture:** Semua perubahan di `public/js/denah-editor.js` (1 file, classic script IIFE). Logika murni baru masuk objek `DenahConv` (testable via node read+eval), UI masuk kelas `DenahEditor`. Model terkunci = key baru `S.supportsLocked[]` + `S.lockSeq` di JSON model yang sama (autosave/server tak berubah). Fase pratinjau (model lama) TIDAK berubah perilaku sama sekali.

**Tech Stack:** Vanilla JS (tanpa dependency), SVG string render, test node murni `tests/rangka/*.mjs` pola read+eval.

**Spec sumber:** `docs/superpowers/specs/2026-08-23-denah-support-id-stabil-design.md` — baca dulu sebelum mulai. Bagian 3 spec = batasan disengaja, JANGAN "diperbaiki".

## Global Constraints

- `public/js/denah-editor.js` adalah classic script IIFE — **DILARANG `export`/`import`** (dimuat `<script>` klasik di blade; `package.json` `"type":"module"` bikin `export` fatal). Ekspos lewat `globalThis` seperti sekarang.
- **Setiap file test baru WAJIB didaftarkan di `tests/guardrail/manifest.json` DI COMMIT YANG SAMA** (deploy #127/#128 merah karena lupa ini). Tiru persis format entri `tests/rangka/test_support_spacing.mjs` yang sudah ada.
- `buildMembers` jalur pratinjau (model tanpa `supportsLocked`) **TIDAK BOLEH berubah hasil** — test lama `test_support_spacing.mjs`, `test_support_pola.mjs`, `test_konverter.mjs` harus tetap lolos.
- Semua mutasi model lewat `this.pushUndo()` SEBELUM mutasi (pola satu titik terpusat yang sudah ada).
- Komentar kode bahasa Indonesia, gaya file yang sudah ada. Tanpa emoji.
- Jalankan `php scripts/canopi-check` sebelum commit terakhir tiap task (reproduksi guardrail lokal).
- Interaksi Frame & Tiang TIDAK disentuh (spec bagian 4).
- Jangan commit `public/hot` (cek `git status` sebelum commit).

## File Structure

- Modify: `public/js/denah-editor.js` — semua task.
  - Objek `DenahConv` (± baris 155–302): fungsi murni baru `isLocked`, `lockSupports`, `unlockSupports`, `moveLockedSupport`, `supportsNearPoint`, `describeLockedSupport`, + cabang terkunci di `buildMembers`.
  - Kelas `DenahEditor`: `shellHTML()` (tombol baru), `_wireControls`, `render()`, `bindSvg()`, `renderSupportPanel()`, `openMatMenu()`, `syncInputs()`/`_syncSupportRows()`.
- Create: `tests/rangka/test_support_lock.mjs` (Task 1), `tests/rangka/test_support_move_unlock.mjs` (Task 2), `tests/rangka/test_support_pick.mjs` (Task 4, ditambah Task 5).
- Modify: `tests/guardrail/manifest.json` (di commit yang sama dengan tiap file test baru).
- Modify (Task 6): `CLAUDE.md` status terkini.

**Bentuk model terkunci (dipakai semua task, hafalkan):**

```js
S.supportsLocked = [           // null/absen = fase pratinjau; array = fase terkunci
  { no: 1, axis: 'h', pos: 150, aktif: true },              // grid: JALUR, bukan ujung
  { no: 5, axis: 'v', pos: 100, aktif: false },             // nonaktif = hilang dari gambar & hitungan
  { no: 10, manual: true, a: {x,y}, b: {x,y}, aktif: true } // manual: ujung nyata
]
S.lockSeq = 11                 // nomor BERIKUTNYA (tak pernah turun; entri baru melanjutkan)
```

ID member/matOverride entri terkunci = `'SL' + no` (mis. `SL3`). Satu jalur grid yang terpotong coakan = beberapa member dengan **id sama** `SL{no}` (panjang per potongan; penjumlahan = hitungan besi — itu memang yang dimau spec 2.2). Label kanvas & panel menampilkan `S{no}`.

---

### Task 1: Model inti terkunci di DenahConv (`isLocked`, `lockSupports`, cabang `buildMembers`)

**Files:**
- Modify: `public/js/denah-editor.js` — objek `DenahConv` (sisipkan setelah `posisiSupport`, ± baris 267) dan `buildMembers` (± baris 211–246)
- Create: `tests/rangka/test_support_lock.mjs`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: `posisiSupport`, `scanX`, `scanY`, `bbox`, `dist` (semua sudah ada di file).
- Produces (dipakai Task 2–5):
  - `DenahConv.isLocked(S)` → boolean (`Array.isArray(S.supportsLocked)`)
  - `DenahConv.lockSupports(S)` → patch object `{ supportsLocked, lockSeq, matOverride, supportsManual: [], removed: {} }` — murni, TIDAK memutasi S; caller `Object.assign(S, patch)`.
  - `buildMembers(S)` fase terkunci: member support ber-id `SL{no}`, per potongan jalur.

- [ ] **Step 1: Tulis test yang gagal** — buat `tests/rangka/test_support_lock.mjs`:

```js
// FILE: tests/rangka/test_support_lock.mjs
// Jalankan: node tests/rangka/test_support_lock.mjs
// Fase terkunci Support ID Stabil (spec 2026-08-23): lockSupports + cabang buildMembers.
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
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
});

// ── isLocked ──
check('isLocked: model lama -> false', DenahConv.isLocked(base()), false);
check('isLocked: supportsLocked [] -> true', DenahConv.isLocked({ ...base(), supportsLocked: [] }), true);

// ── lockSupports: penomoran H atas->bawah dulu, lalu V kiri->kanan, manual melanjutkan ──
{
  const S = base();
  S.supportsManual = [{ a: { x: 10, y: 10 }, b: { x: 10, y: 200 } }];
  const p = DenahConv.lockSupports(S);
  // 600x450 kotak 100: H di y=100..400 (4 garis), V di x=100..500 (5 garis), manual 1 -> total 10
  check('lock: jumlah entri', p.supportsLocked.length, 10);
  check('lock: nomor urut 1..10', p.supportsLocked.map(e => e.no), [1,2,3,4,5,6,7,8,9,10]);
  check('lock: 4 H dulu (pos naik)', p.supportsLocked.slice(0, 4).map(e => [e.axis, e.pos]),
    [['h',100],['h',200],['h',300],['h',400]]);
  check('lock: lalu 5 V (pos naik)', p.supportsLocked.slice(4, 9).map(e => [e.axis, e.pos]),
    [['v',100],['v',200],['v',300],['v',400],['v',500]]);
  check('lock: manual terakhir, ujung nyata', [p.supportsLocked[9].manual, p.supportsLocked[9].a, p.supportsLocked[9].b],
    [true, { x: 10, y: 10 }, { x: 10, y: 200 }]);
  check('lock: semua aktif', p.supportsLocked.every(e => e.aktif), true);
  check('lock: lockSeq = 11', p.lockSeq, 11);
  check('lock: supportsManual dikosongkan', p.supportsManual, []);
  check('lock: removed dikosongkan', p.removed, {});
  check('lock: S asal TIDAK dimutasi', S.supportsManual.length, 1);
}

// ── lockSupports: migrasi removed{} (semua potongan removed -> lahir nonaktif) & matOverride ──
{
  const S = base();
  S.removed = { 'Sh_0_0': true };                        // garis H pertama (y=100), 1 potongan (persegi)
  S.matOverride = { 'Sh_1_0': 'BesiA', 'Sm_0': 'BesiB', 'F0': 'BesiC' };
  S.supportsManual = [{ a: { x: 0, y: 0 }, b: { x: 100, y: 0 } }];
  const p = DenahConv.lockSupports(S);
  check('lock: removed cocok -> nonaktif', p.supportsLocked[0].aktif, false);
  check('lock: garis lain tetap aktif', p.supportsLocked[1].aktif, true);
  check('lock: override grid pindah ke SL2', p.matOverride['SL2'], 'BesiA');
  check('lock: key grid lama hilang', 'Sh_1_0' in p.matOverride, false);
  check('lock: override manual pindah ke SL10', p.matOverride['SL10'], 'BesiB');
  check('lock: override frame tak tersentuh', p.matOverride['F0'], 'BesiC');
}

// ── lockSupports: lockSeq lama dipertahankan (re-lock setelah Susun Ulang melanjutkan nomor) ──
{
  const S = base(); S.arah = 'h'; S.lockSeq = 21;
  const p = DenahConv.lockSupports(S);
  check('re-lock: nomor mulai dari lockSeq lama', p.supportsLocked[0].no, 21);
  check('re-lock: lockSeq maju', p.lockSeq, 25);
}

// ── buildMembers terkunci: ujung dihitung dari polygon SAAT INI ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 150, aktif: true }];
  S.lockSeq = 2;
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked: 1 member id SL1', sup.map(m => m.id), ['SL1']);
  check('locked: panjang = lebar frame', sup[0].panjang, 400);
  // frame dilebarkan -> ujung otomatis memanjang (jalur, bukan ujung tersimpan)
  S.verts = [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 0, y: 300 }];
  check('locked: frame melebar -> support memanjang', DenahConv.buildMembers(S).filter(m => m.jenis === 'support')[0].panjang, 600);
}

// ── buildMembers terkunci: jalur terpotong coakan = beberapa potongan, SATU id, panjang dijumlah ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 400, y: 300 },
             { x: 400, y: 100 }, { x: 200, y: 100 }, { x: 200, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [{ no: 3, axis: 'h', pos: 150, aktif: true }];
  S.lockSeq = 4;
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked coakan: 2 potongan id sama', sup.map(m => m.id), ['SL3', 'SL3']);
  check('locked coakan: jumlah panjang 400', sup.reduce((a, m) => a + m.panjang, 0), 400);
}

// ── buildMembers terkunci: nonaktif keluar dari hitungan; manual & vertikal & matOverride ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [
    { no: 1, axis: 'h', pos: 150, aktif: false },
    { no: 2, axis: 'v', pos: 100, aktif: true },
    { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 0, y: 250 }, aktif: true },
  ];
  S.lockSeq = 4; S.matOverride = { 'SL2': 'BesiK' };
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked: nonaktif tak ikut', sup.map(m => m.id), ['SL2', 'SL3']);
  check('locked: vertikal panjang 300', sup[0].panjang, 300);
  check('locked: matOverride ke-key SL', sup[0].material, 'BesiK');
  check('locked: manual pakai ujung tersimpan', sup[1].panjang, 250);
}

// ── EKUIVALENSI PRATINJAU: model tanpa supportsLocked -> perilaku lama persis ──
{
  const S = base();
  S.supportsManual = [{ a: { x: 0, y: 0 }, b: { x: 50, y: 0 } }];
  const ids = DenahConv.buildMembers(S).filter(m => m.jenis === 'support').map(m => m.id);
  check('pratinjau: id lama Sh_/Sv_/Sm_ tak berubah',
    [ids.filter(i => i.startsWith('Sh_')).length, ids.filter(i => i.startsWith('Sv_')).length, ids.includes('Sm_0')],
    [4, 5, true]);
}

process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `node tests/rangka/test_support_lock.mjs` → FAIL/error (`isLocked` belum ada).

- [ ] **Step 3: Implementasi di `DenahConv`** — sisipkan setelah `posisiSupport` (sebelum `combineBox`):

```js
  // ---- Fase terkunci (Support ID Stabil, 23 Ags) ----
  // supportsLocked = array -> fase terkunci; null/absen -> fase pratinjau (model lama, nol migrasi).
  isLocked(S) { return Array.isArray(S.supportsLocked); },
  // Bekukan susunan support jadi entri ber-ID stabil. MURNI: tidak memutasi S; kembalikan patch
  // untuk Object.assign(S, patch). Penomoran SEKALI di sini: H atas->bawah, lalu V kiri->kanan,
  // manual melanjutkan (spec 2.2). lockSeq lama (kalau ada, dari kunci sebelum Susun Ulang)
  // DIPERTAHANKAN: nomor baru melanjutkan, tak pernah dipakai ulang.
  lockSupports(S) {
    const V = S.verts, bb = bbox(V), K = (S.kotak > 0 ? S.kotak : 100), rem = S.removed || {};
    const mo = { ...(S.matOverride || {}) };
    const entries = [];
    let no = S.lockSeq > 0 ? S.lockSeq - 1 : 0;
    // 1 garis grid lama = banyak id per-potongan (Sh_{li}_{s}). Entri lahir nonaktif kalau SEMUA
    // potongannya kadung di-"Kecualikan"; sebagian doang -> tetap aktif (jalur = 1 kesatuan di
    // model baru, bolong parsial tak bisa direpresentasikan — hilang sadar, sesuai spec 2.5).
    // Override material: ambil dari potongan pertama yang punya, sisanya dibuang.
    const addGrid = (axis, pos, oldPrefix, segCount) => {
      no++;
      let aktif = segCount === 0;
      for (let s2 = 0; s2 < segCount; s2++) if (!rem[oldPrefix + s2]) aktif = true;
      const ovKey = Object.keys(mo).find(k => k.startsWith(oldPrefix));
      if (ovKey != null) { mo['SL' + no] = mo[ovKey]; Object.keys(mo).forEach(k => { if (k.startsWith(oldPrefix)) delete mo[k]; }); }
      entries.push({ no, axis, pos, aktif });
    };
    if (S.arah === 'h' || S.arah === '2') {
      DenahConv.posisiSupport(bb.y0, bb.y1, S.modeH || 'cm', S.kotakH, S.kolomH, K)
        .forEach((Yp, li) => addGrid('h', Yp, 'Sh_' + li + '_', Math.floor(scanX(V, Yp).length / 2)));
    }
    if (S.arah === 'v' || S.arah === '2') {
      DenahConv.posisiSupport(bb.x0, bb.x1, S.modeV || 'cm', S.kotakV, S.kolomV, K)
        .forEach((Xp, li) => addGrid('v', Xp, 'Sv_' + li + '_', Math.floor(scanY(V, Xp).length / 2)));
    }
    (S.supportsManual || []).forEach((m, i) => {
      no++;
      if (mo['Sm_' + i] != null) { mo['SL' + no] = mo['Sm_' + i]; delete mo['Sm_' + i]; }
      entries.push({ no, manual: true, a: { ...m.a }, b: { ...m.b }, aktif: true });
    });
    return { supportsLocked: entries, lockSeq: no + 1, matOverride: mo, supportsManual: [], removed: {} };
  },
```

- [ ] **Step 4: Cabang terkunci di `buildMembers`** — bungkus blok grid + supportsManual yang ada (dari `const addSeg = ...` sampai selesai loop `supportsManual`) dalam `else`, tambahkan cabang `if`:

```js
    if (Array.isArray(S.supportsLocked)) {
      // FASE TERKUNCI (Support ID Stabil, 23 Ags): entri grid nyimpan JALUR {axis,pos} — ujung
      // dihitung DI SINI dari polygon saat ini (frame berubah -> support ikut; coakan -> jalur
      // terbelah jadi beberapa member ber-ID SAMA, panjang per potongan dijumlah utk hitungan besi).
      // Entri nonaktif dilewati total (hilang dari gambar & hitungan, tetap ada di panel).
      S.supportsLocked.forEach(e => {
        if (e.aktif === false) return;
        const id = 'SL' + e.no;
        const mat = (S.matOverride && S.matOverride[id]) || S.matDefault.support;
        const push = (a, b) => mem.push({ id, nama: 'S', jenis: 'support', panjang: Math.round(dist(a, b)), material: mat, geom: { a, b } });
        if (e.manual) push({ ...e.a }, { ...e.b });
        else if (e.axis === 'h') {
          const xs = scanX(V, e.pos);
          for (let s = 0; s + 1 < xs.length; s += 2) push({ x: xs[s], y: e.pos }, { x: xs[s + 1], y: e.pos });
        } else {
          const ys = scanY(V, e.pos);
          for (let s = 0; s + 1 < ys.length; s += 2) push({ x: e.pos, y: ys[s] }, { x: e.pos, y: ys[s + 1] });
        }
      });
    } else {
      // FASE PRATINJAU — blok lama VERBATIM, JANGAN diubah (harness ekuivalensi 25.200 variasi
      // per-sumbu + test_support_spacing harus tetap lolos).
      ... (blok addSeg + posisiSupport + supportsManual yang sudah ada, dipindah masuk ke sini apa adanya) ...
    }
```

(Bagian frame di atas dan tiang di bawah blok ini TIDAK berubah.)

- [ ] **Step 5: Jalankan test baru + regresi** — `node tests/rangka/test_support_lock.mjs && node tests/rangka/test_support_spacing.mjs && node tests/rangka/test_support_pola.mjs && node tests/rangka/test_konverter.mjs` → semua PASS.

- [ ] **Step 6: Daftarkan di manifest** — tambah entri di `tests/guardrail/manifest.json` (tiru persis format entri `tests/rangka/test_support_spacing.mjs`, cuma ganti path jadi `tests/rangka/test_support_lock.mjs`), lalu `php scripts/canopi-check`.

- [ ] **Step 7: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_lock.mjs tests/guardrail/manifest.json
git commit -m "feat(denah): model support terkunci -- lockSupports + cabang buildMembers jalur ID stabil"
```

---

### Task 2: `unlockSupports` (Susun Ulang) + `moveLockedSupport` (pindah angka relatif)

**Files:**
- Modify: `public/js/denah-editor.js` — objek `DenahConv`, tepat setelah `lockSupports`
- Create: `tests/rangka/test_support_move_unlock.mjs`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: bentuk entri `supportsLocked` (Task 1).
- Produces (dipakai Task 3 & 5):
  - `DenahConv.unlockSupports(S)` → patch `{ supportsLocked: null, supportsManual, matOverride, removed: {} }` — murni; `lockSeq` TIDAK di-patch (nilai lama di S tetap, re-lock melanjutkan nomor).
  - `DenahConv.moveLockedSupport(entry, arah, cm)` → entri BARU (tanpa mutasi) atau `null` kalau arah tak valid untuk tipe itu / cm bukan angka > 0. `arah` ∈ `'atas' | 'bawah' | 'kiri' | 'kanan'`.

- [ ] **Step 1: Tulis test yang gagal** — buat `tests/rangka/test_support_move_unlock.mjs`:

```js
// FILE: tests/rangka/test_support_move_unlock.mjs
// Jalankan: node tests/rangka/test_support_move_unlock.mjs
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

// ── moveLockedSupport: relatif + filter arah per sumbu ──
const h = { no: 1, axis: 'h', pos: 150, aktif: true };
const v = { no: 2, axis: 'v', pos: 100, aktif: true };
const m = { no: 3, manual: true, a: { x: 10, y: 20 }, b: { x: 110, y: 20 }, aktif: true };
check('h atas 30 -> pos 120 (y layar mengecil)', DenahConv.moveLockedSupport(h, 'atas', 30).pos, 120);
check('h bawah 30 -> pos 180', DenahConv.moveLockedSupport(h, 'bawah', 30).pos, 180);
check('h kiri -> null (arah terfilter)', DenahConv.moveLockedSupport(h, 'kiri', 30), null);
check('h kanan -> null', DenahConv.moveLockedSupport(h, 'kanan', 30), null);
check('v kanan 25 -> pos 125', DenahConv.moveLockedSupport(v, 'kanan', 25).pos, 125);
check('v kiri 25 -> pos 75', DenahConv.moveLockedSupport(v, 'kiri', 25).pos, 75);
check('v atas -> null', DenahConv.moveLockedSupport(v, 'atas', 25), null);
check('manual bawah 5 -> kedua ujung geser', (() => { const r = DenahConv.moveLockedSupport(m, 'bawah', 5); return [r.a, r.b]; })(),
  [{ x: 10, y: 25 }, { x: 110, y: 25 }]);
check('manual kiri 5 -> kedua ujung geser', (() => { const r = DenahConv.moveLockedSupport(m, 'kiri', 5); return [r.a, r.b]; })(),
  [{ x: 5, y: 20 }, { x: 105, y: 20 }]);
check('entri asal tak dimutasi', [h.pos, m.a.x], [150, 10]);
check('cm 0 -> null', DenahConv.moveLockedSupport(h, 'atas', 0), null);
check('cm NaN -> null', DenahConv.moveLockedSupport(h, 'atas', NaN), null);
check('arah ngawur -> null', DenahConv.moveLockedSupport(h, 'muter', 10), null);

// ── unlockSupports: grid dibuang, manual balik ke supportsManual, override manual di-remap ──
{
  const S = {
    verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
    kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
    matDefault: { frame: 'X', support: 'X', tiang: 'X' },
    matOverride: { 'SL1': 'BesiGrid', 'SL3': 'BesiManual', 'F0': 'BesiF' },
    supportsLocked: [
      { no: 1, axis: 'h', pos: 150, aktif: false },
      { no: 3, manual: true, a: { x: 1, y: 2 }, b: { x: 3, y: 4 }, aktif: true },
      { no: 4, manual: true, a: { x: 5, y: 6 }, b: { x: 7, y: 8 }, aktif: false },
    ],
    lockSeq: 5,
  };
  const p = DenahConv.unlockSupports(S);
  check('unlock: supportsLocked -> null', p.supportsLocked, null);
  check('unlock: manual balik (termasuk nonaktif, data jangan hilang)',
    p.supportsManual, [{ a: { x: 1, y: 2 }, b: { x: 3, y: 4 } }, { a: { x: 5, y: 6 }, b: { x: 7, y: 8 } }]);
  check('unlock: override manual -> Sm_0', p.matOverride['Sm_0'], 'BesiManual');
  check('unlock: override grid hangus (sesuai peringatan reset)', 'SL1' in p.matOverride, false);
  check('unlock: override frame utuh', p.matOverride['F0'], 'BesiF');
  check('unlock: removed dikosongkan', p.removed, {});
  check('unlock: lockSeq TIDAK ikut patch (dipertahankan di S)', 'lockSeq' in p, false);
}

// ── roundtrip: lock -> unlock -> lock lagi melanjutkan nomor ──
{
  const S = {
    verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
    kotak: 100, arah: 'h', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
    matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
  };
  Object.assign(S, DenahConv.lockSupports(S));       // nomor 1,2 (y=100,200)
  Object.assign(S, DenahConv.unlockSupports(S));     // lockSeq 3 tetap di S
  Object.assign(S, DenahConv.lockSupports(S));
  check('roundtrip: nomor baru melanjutkan (3,4)', S.supportsLocked.map(e => e.no), [3, 4]);
}

process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `node tests/rangka/test_support_move_unlock.mjs` → FAIL.

- [ ] **Step 3: Implementasi** — di `DenahConv` setelah `lockSupports`:

```js
  // "Susun Ulang": balik ke fase pratinjau. Entri grid dibuang (input spacing hidup lagi),
  // entri manual balik jadi supportsManual (ujung nyata dipertahankan, termasuk yang nonaktif —
  // model lama tak kenal nonaktif, mending muncul lagi daripada data hilang diam-diam).
  // Override grid ikut hangus (sesuai peringatan "editan per-garis di-reset"); override manual
  // di-remap ke Sm_{i}. lockSeq SENGAJA tidak di-patch: nilai lama tetap di S, kunci berikutnya
  // melanjutkan nomor (spec 2.2). MURNI, tidak memutasi S.
  unlockSupports(S) {
    const mo = { ...(S.matOverride || {}) };
    const manual = [];
    (S.supportsLocked || []).forEach(e => {
      const key = 'SL' + e.no;
      if (!e.manual) { delete mo[key]; return; }
      if (mo[key] != null) { mo['Sm_' + manual.length] = mo[key]; delete mo[key]; }
      manual.push({ a: { ...e.a }, b: { ...e.b } });
    });
    return { supportsLocked: null, supportsManual: manual, matOverride: mo, removed: {} };
  },
  // Pindah entri terkunci = ketik angka RELATIF (spec 2.3). Arah difilter: garis h cuma
  // atas/bawah, v cuma kiri/kanan, manual 4 arah. Return entri BARU atau null (tak valid).
  // "atas" = y layar mengecil (kanvas y tumbuh ke bawah).
  moveLockedSupport(entry, arah, cm) {
    if (!Number.isFinite(cm) || cm <= 0) return null;
    const d = { atas: [0, -cm], bawah: [0, cm], kiri: [-cm, 0], kanan: [cm, 0] }[arah];
    if (!d) return null;
    if (entry.manual) {
      return { ...entry, a: { x: entry.a.x + d[0], y: entry.a.y + d[1] }, b: { x: entry.b.x + d[0], y: entry.b.y + d[1] } };
    }
    if (entry.axis === 'h') return d[0] !== 0 ? null : { ...entry, pos: entry.pos + d[1] };
    if (entry.axis === 'v') return d[1] !== 0 ? null : { ...entry, pos: entry.pos + d[0] };
    return null;
  },
```

- [ ] **Step 4: Jalankan test + regresi** — `node tests/rangka/test_support_move_unlock.mjs && node tests/rangka/test_support_lock.mjs && node tests/rangka/test_support_spacing.mjs` → PASS.

- [ ] **Step 5: Daftarkan di manifest + guardrail** — tambah entri manifest untuk `tests/rangka/test_support_move_unlock.mjs`; `php scripts/canopi-check`.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_move_unlock.mjs tests/guardrail/manifest.json
git commit -m "feat(denah): unlockSupports (Susun Ulang) + moveLockedSupport (pindah angka relatif)"
```

---

### Task 3: UI fase — toggle move, dua pintu kunci, Susun Ulang, sembunyikan spacing

**Files:**
- Modify: `public/js/denah-editor.js` — `constructor`, `shellHTML()`, `_wireControls()`, `_syncSupportRows()`, `render()`

**Interfaces:**
- Consumes: `DenahConv.isLocked`, `lockSupports`, `unlockSupports` (Task 1–2).
- Produces (dipakai Task 4–5):
  - `this.moveOn` (boolean, default `false`) — tool state, BUKAN bagian model/Undo/autosave.
  - `this.selSup` (number|null, default `null`) — `no` entri tersorot; tool state.
  - `this.supPanelOpen` (boolean, default `false`) — lipatan panel; tool state.
  - `this._lockNow()` — method kunci otomatis (pushUndo + patch + hint + buka panel + render). Idempotent (no-op kalau sudah terkunci).
  - Tombol `[data-role=btnMove]` di quickbar, `[data-role=btnSusunUlang]` di panel ribbon Support.

- [ ] **Step 1: State di constructor** — setelah `this.tiangPreview = null;` tambah:

```js
    this.moveOn = false;      // toggle move quickbar (spec 2.3) — penanda alat, bukan data model
    this.selSup = null;       // no entri support terkunci yang tersorot (null = tak ada)
    this.supPanelOpen = false; // lipatan panel daftar Support (dilipat default, spec 2.4)
```

- [ ] **Step 2: Tombol di shellHTML** — di `.de-quickbar`, setelah tombol `data-mode="besi"`, tambah:

```html
    <span class="de-mini" data-role="btnMove" title="Geser/sorot elemen (mati = kanvas murni lihat)" aria-label="Toggle move" style="display:none"><svg class="de-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20M12 2 9 5M12 2l3 3M12 22l-3-3M12 22l3-3M2 12l3-3M2 12l3 3M22 12l-3-3M22 12l-3 3"/></svg></span>
```

Di panel ribbon `data-panel="support"`: bungkus baris pertama (Arah/Ideal/Saran) dengan `data-role="rowSupSpacing"` (tambahkan atribut itu ke `<div class="de-row">` pertama), dan di baris terakhir (Besi support / + Support manual / Pulihkan) tambah tombol:

```html
        <span class="de-mini" data-role="btnSusunUlang" style="display:none">Susun Ulang</span>
```

- [ ] **Step 3: `_lockNow()` + wiring** — method baru di kelas (dekat `resetBox`):

```js
  // Momen kunci otomatis (spec 2.1 opsi b): dipanggil dari toggle move ATAU header panel ajakan.
  // Mutasi model biasa lewat pushUndo -> bisa di-Undo (Undo balikin supportsLocked -> null).
  _lockNow() {
    if (DenahConv.isLocked(this.S)) return;
    this.pushUndo();
    Object.assign(this.S, DenahConv.lockSupports(this.S));
    this.supPanelOpen = true;
    this.setHint('Susunan dikunci — kelola per garis lewat panel Support.');
    this.render();
  }
```

Di `_wireControls()` tambah:

```js
    // Toggle move (spec 2.3): default MATI = kanvas murni lihat/zoom/pan. Menyalakan pertama
    // kali di fase pratinjau = pintu kunci otomatis (spec 2.1). Hanya hidup di tab Support
    // (tahap ini; Frame/Tiang belum, spec bagian 4).
    this._q('[data-role=btnMove]').onclick = () => {
      if (this.mode !== 'support') return;
      this.moveOn = !this.moveOn;
      if (this.moveOn) this._lockNow();      // no-op kalau sudah terkunci
      if (!this.moveOn) this.selSup = null;  // matiin toggle = lepas sorotan
      this.render();
    };
    // "Susun Ulang" (spec 2.1): konfirmasi eksplisit, tak ada regenerate diam-diam. Bisa di-Undo.
    this._q('[data-role=btnSusunUlang]').onclick = () => {
      if (!DenahConv.isLocked(this.S)) return;
      if (!confirm('Susun ulang support? Editan per-garis (nonaktif, pindah posisi, besi per-garis grid) akan di-reset. Bisa di-Undo.')) return;
      this.pushUndo();
      Object.assign(this.S, DenahConv.unlockSupports(this.S));
      this.moveOn = false; this.selSup = null;
      this.setHint('Kembali ke mode susun: atur spacing, lalu kunci lagi saat siap.');
      this.syncInputs();
      this.render();
    };
```

- [ ] **Step 4: Sembunyikan spacing saat terkunci** — di `_syncSupportRows()`, baris pertama jadi:

```js
    const locked = DenahConv.isLocked(this.S);
    const arah = this.S.arah;
    // Terkunci: input spacing disembunyikan total, diganti tombol Susun Ulang (spec 2.1).
    this._q('[data-role=rowSupSpacing]').style.display = locked ? 'none' : '';
    this._q('[data-role=btnSusunUlang]').style.display = locked ? '' : 'none';
    this._q('[data-role=btnRestoreSup]').style.display = locked ? 'none' : '';
    this._q('[data-role=rowSupH]').style.display = (!locked && (arah === 'h' || arah === '2')) ? '' : 'none';
    this._q('[data-role=rowSupV]').style.display = (!locked && (arah === 'v' || arah === '2')) ? '' : 'none';
```

(sisa fungsi tak berubah). Di `render()`, setelah `this._syncVertBtns();` tambah sinkron tombol move + validasi sorotan:

```js
    // Toggle move cuma tampil di tab Support (tahap ini). Sorotan basi (entri sudah dihapus /
    // Undo balikin ke pratinjau) dilepas di sini — SATU titik validasi utk semua jalur mutasi.
    const mv = this._q('[data-role=btnMove]');
    if (mv) { mv.style.display = this.mode === 'support' ? '' : 'none'; mv.classList.toggle('on', this.moveOn); }
    if (this.selSup != null && !(DenahConv.isLocked(this.S) && this.S.supportsLocked.some(e => e.no === this.selSup))) this.selSup = null;
```

Dan `_syncSupportRows()` harus terpanggil tiap render (sekarang cuma via syncInputs) — di `render()` panggil `this._syncSupportRows();` setelah blok di atas (murah, idempotent).

- [ ] **Step 5: Verifikasi** — `node tests/rangka/test_support_lock.mjs && node tests/rangka/test_support_move_unlock.mjs && node tests/rangka/test_support_spacing.mjs && node tests/rangka/test_tiang_numerik.mjs && php scripts/canopi-check` → PASS. Smoke browser cepat via `tests/rangka/denah_editor_harness.html` kalau tersedia (opsional).

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): toggle move + kunci otomatis + Susun Ulang (UI fase terkunci)"
```

---

### Task 4: Interaksi kanvas fase terkunci — sorot toleran, tap-ganti-kandidat, tarik ujung manual, gating

**Files:**
- Modify: `public/js/denah-editor.js` — `DenahConv` (+`supportsNearPoint`), `render()` (layer support), `bindSvg()` (cabang mode support), `openMatMenu()`, handler `btnAddSupport`
- Create: `tests/rangka/test_support_pick.mjs`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: `isLocked`, member `SL{no}` dari `buildMembers` (Task 1), `this.moveOn`/`this.selSup` (Task 3).
- Produces:
  - `DenahConv.supportsNearPoint(mem, pt, th)` → array id support unik (`['SL3','SL1',...]`) urut jarak naik, hanya yang ≤ th (cm). Dipakai sorot toleran + cycling.
  - Sorot kanvas ↔ `this.selSup` (Task 5 menyorot balik dari panel).
  - `+ Support manual` di fase terkunci menambah entri `supportsLocked` manual (nomor lanjut `lockSeq`).

- [ ] **Step 1: Test `supportsNearPoint` (gagal dulu)** — buat `tests/rangka/test_support_pick.mjs`:

```js
// FILE: tests/rangka/test_support_pick.mjs
// Jalankan: node tests/rangka/test_support_pick.mjs
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

// Dua garis mendatar y=100 & y=130, satu tegak x=50 -- member minimal (cuma field yg dipakai).
const mem = [
  { id: 'SL1', jenis: 'support', geom: { a: { x: 0, y: 100 }, b: { x: 400, y: 100 } } },
  { id: 'SL2', jenis: 'support', geom: { a: { x: 0, y: 130 }, b: { x: 400, y: 130 } } },
  { id: 'SL3', jenis: 'support', geom: { a: { x: 50, y: 0 }, b: { x: 50, y: 300 } } },
  { id: 'F0', jenis: 'frame', geom: { a: { x: 0, y: 0 }, b: { x: 400, y: 0 } } },   // bukan support: diabaikan
];
check('terdekat menang, urut jarak', DenahConv.supportsNearPoint(mem, { x: 200, y: 110 }, 24), ['SL1', 'SL2']);
check('di luar threshold tak ikut', DenahConv.supportsNearPoint(mem, { x: 200, y: 110 }, 12), ['SL1']);
check('tap jauh -> kosong', DenahConv.supportsNearPoint(mem, { x: 200, y: 250 }, 24), []);
check('garis tegak kena dari samping', DenahConv.supportsNearPoint(mem, { x: 60, y: 200 }, 24), ['SL3']);
// Jalur terbelah coakan: 2 member id sama -> id muncul SEKALI (jarak terkecil dipakai)
const memSplit = [
  { id: 'SL7', jenis: 'support', geom: { a: { x: 0, y: 50 }, b: { x: 100, y: 50 } } },
  { id: 'SL7', jenis: 'support', geom: { a: { x: 300, y: 50 }, b: { x: 400, y: 50 } } },
];
check('multi-potongan 1 id -> dedup', DenahConv.supportsNearPoint(memSplit, { x: 350, y: 55 }, 24), ['SL7']);

process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `node tests/rangka/test_support_pick.mjs` → FAIL.

- [ ] **Step 3: Implementasi `supportsNearPoint`** — di `DenahConv` setelah `moveLockedSupport`:

```js
  // Sorot toleran fase terkunci (spec 2.3): kandidat id support dalam threshold th (cm) urut
  // jarak titik-ke-ruas naik. Multi-potongan ber-id sama muncul sekali (jarak terkecil).
  // Cycling "tap lagi = kandidat berikutnya" urusan UI, bukan di sini.
  supportsNearPoint(mem, pt, th) {
    const best = {};
    mem.forEach(m => {
      if (m.jenis !== 'support') return;
      const d = dist(pt, closestOnSegment(pt, m.geom.a, m.geom.b));
      if (d <= th && (best[m.id] == null || d < best[m.id])) best[m.id] = d;
    });
    return Object.keys(best).sort((a, b) => best[a] - best[b]);
  },
```

- [ ] **Step 4: Render layer support fase terkunci** — di `render()`, blok `mem.filter(m => m.jenis === 'support').forEach(...)`: tambah variabel `const locked = DenahConv.isLocked(S);` sebelum loop, lalu di dalam loop:

```js
      // Fase terkunci: id member 'SL{no}'. Garis tersorot (this.selSup) menyala kuning-tebal
      // (spec 2.3 "sorot dulu, aksi kemudian" — sinkron dua arah dgn baris panel).
      const lockedEntry = locked && m.id.startsWith('SL');
      const selected = lockedEntry && +m.id.slice(2) === this.selSup;
      const stroke = selected ? '#facc15' : c;
      const sw = selected ? 5 : (manual ? 3 : 2);
```

Ganti atribut `stroke`/`stroke-width` garis tampak memakai `stroke`/`sw`. `lineId` fase terkunci: `'slg' + m.id.slice(2) + '_' + (hitung index potongan per id)` — simpelnya beri counter per id:

```js
    const segCount = {};   // di luar loop
    // di dalam loop:
    const segIdx = segCount[m.id] = (segCount[m.id] || 0) + 1;
    const lineId = lockedEntry ? 'slg' + m.id.slice(2) + '_' + (segIdx - 1) : (manual ? 'sm' + m.id.slice(3) : 'sg_' + m.id);
```

Label fase terkunci: `S{no} · {panjang}` per potongan (`const label = lockedEntry ? 'S' + m.id.slice(2) + ' · ' + m.panjang : (manual ? ... lama ...)`).

Titik ujung (`data-sm` circles): fase terkunci HANYA untuk entri manual yang TERSOROT dan `this.moveOn` nyala — ganti kondisi blok `if (this.mode === 'support') mem.filter(...)` jadi:

```js
    if (this.mode === 'support' && !locked) { /* blok lama Sm_ verbatim */ }
    else if (this.mode === 'support' && locked && this.moveOn && this.selSup != null) {
      const e = S.supportsLocked.find(x => x.no === this.selSup && x.manual && x.aktif !== false);
      if (e) ['a', 'b'].forEach(end => { const p = e[end], cx = X(p.x), cy = Y(p.y);
        s += `<circle cx="${cx}" cy="${cy}" r="22" fill="transparent" data-slend="${e.no}" data-end="${end}" style="cursor:grab"/>`;
        s += `<circle id="slh${e.no}${end}" cx="${cx}" cy="${cy}" r="4" fill="#0f2740" stroke="#facc15" stroke-width="2.5" style="pointer-events:none"/>`; });
    }
```

- [ ] **Step 5: Gating & sorot di `bindSvg()`** — di awal cabang `else if (this.mode === 'support') {`, tambah:

```js
        const locked = DenahConv.isLocked(this.S);
        if (locked) {
          // FASE TERKUNCI (spec 2.3): menu tekan-tahan DIHAPUS; geser garis DIHAPUS (pindah =
          // ketik angka di panel). Yang tersisa di kanvas: (a) + Support manual 2 tap,
          // (b) tarik ujung manual TERSOROT (moveOn nyala), (c) tap toleran = sorot/ganti kandidat.
          // moveOn mati = kanvas murni lihat/zoom/pan, tak ada satupun yang merespons.
          if (this.armed === 'addSupport') {
            if (!this.addSupportPt) { this.addSupportPt = { x: this.snap(cm.x), y: this.snap(cm.y) }; this.setHint('Titik ke-2 support…'); }
            else {
              this.pushUndo();
              const no = this.S.lockSeq || 1;
              this.S.supportsLocked.push({ no, manual: true, a: this.addSupportPt, b: { x: this.snap(cm.x), y: this.snap(cm.y) }, aktif: true });
              this.S.lockSeq = no + 1;      // entri baru melanjutkan nomor (spec 2.2)
              this.addSupportPt = null; this.armed = null; this.selSup = no; this.setHint(); this.render();
            }
            return;
          }
          if (!this.moveOn) return;
          if (t.dataset.slend != null) {
            // Tarik ujung: HANYA manual tersorot (grid tak punya ujung tarikan — otomatis dari frame).
            const no = +t.dataset.slend, end = t.dataset.end;
            const myDrag = { type: 'lockend', no, end, startPt: cm, moved: false,
              h: el.querySelector('#slh' + no + end) };
            drag = myDrag;
            el.setPointerCapture(e.pointerId); e.preventDefault();
            return;
          }
          // Tap toleran: garis terdekat menang; tap lagi di tempat sama = kandidat berikutnya.
          const TH = 24 / this.screenScale(el) / this.SC;
          const ids = DenahConv.supportsNearPoint(mem2, cm, TH);
          if (!ids.length) { if (this.selSup != null) { this.selSup = null; this.render(); } return; }
          const curId = 'SL' + this.selSup;
          const k = ids.indexOf(curId);
          const sameSpot = this._lastPickPt && dist(cm, this._lastPickPt) < TH;
          this._lastPickPt = cm;
          this.selSup = +((k >= 0 && sameSpot) ? ids[(k + 1) % ids.length] : ids[0]).slice(2);
          this.render();
          return;
        }
        // FASE PRATINJAU: blok lama verbatim (data-sm / addSupport / Sm_ / supgrid) — jangan diubah.
```

`mem2` = members segar: tambahkan `const mem2 = locked ? DenahConv.buildMembers(this.S) : null;` tepat sebelum dipakai (murah, hanya saat tap fase terkunci).

Di `pointermove`, tambah cabang tipe `lockend` (cermin tipe `sup`, target entri terkunci):

```js
        } else if (drag.type === 'lockend') {
          if (!drag.moved && dist(cm, drag.startPt) > 4) { drag.moved = true; this.pushUndo(); }
          if (!drag.moved) return;
          const e2 = this.S.supportsLocked.find(x => x.no === drag.no);
          if (!e2) return;
          const anchor = e2[drag.end === 'a' ? 'b' : 'a'];
          const TH = (this.S.grid || 20) * 1.2;
          const snapped = DenahConv._orthoSnapToPoint(cm, anchor, TH);
          e2[drag.end] = snapped;
          const px2 = PAD + snapped.x * this.SC, py2 = PAD + snapped.y * this.SC;
          if (drag.h) { drag.h.setAttribute('cx', px2); drag.h.setAttribute('cy', py2); }
        }
```

Di `end()`, tambah:

```js
      else if (drag.type === 'lockend') {
        if (drag.moved) {
          const e2 = this.S.supportsLocked.find(x => x.no === drag.no);
          if (e2) {
            // Pola sama tipe 'sup': sumbu yg barusan ortho-snap ke anchor JANGAN di-grid-snap lagi.
            const anchor = e2[drag.end === 'a' ? 'b' : 'a'], p = e2[drag.end];
            e2[drag.end] = { x: p.x === anchor.x ? p.x : this.snap(p.x), y: p.y === anchor.y ? p.y : this.snap(p.y) };
          }
        }
      }
```

(garis SVG potongan tersorot tak di-update live per-move di sini — `end()` selalu `render()`, dan ujung punya handle sendiri yang bergerak; cukup.)

- [ ] **Step 6: `openMatMenu` label SL + '+ Support manual' hint** — di `openMatMenu()`, cabang label support:

```js
      if (m.jenis === 'support') {
        code = id.startsWith('SL') ? 'S' + id.slice(2)
             : id.startsWith('Sm_') ? 'S' + DenahConv.numberSupportsManual(mem)[id] : 'grid';
      }
```

dan panjang di label untuk id `SL` = total semua potongan: `const totLen = mem.filter(x => x.id === id).reduce((a2, x) => a2 + x.panjang, 0);` → pakai `totLen` bila `id.startsWith('SL')`, selain itu `m.panjang` seperti sekarang. Mode `besi` (tap batang) tak perlu diubah — hit line `data-id="SL{no}"` sudah mengalir ke `openMatMenu` lewat handler lama.

- [ ] **Step 7: Jalankan semua test rangka + guardrail** — `for f in tests/rangka/test_*.mjs; do node $f || break; done && php scripts/canopi-check` → semua PASS. Daftarkan `tests/rangka/test_support_pick.mjs` di manifest.

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_pick.mjs tests/guardrail/manifest.json
git commit -m "feat(denah): interaksi kanvas fase terkunci -- sorot toleran + tarik ujung manual + gating moveOn"
```

---

### Task 5: Panel daftar Support (ceklis, Semua, Fokus, baris melebar: Pindah/Ganti besi/Hapus)

**Files:**
- Modify: `public/js/denah-editor.js` — `DenahConv` (+`describeLockedSupport`), `renderSupportPanel()` ditulis ulang
- Modify: `tests/rangka/test_support_pick.mjs` (tambah blok test describe — file sudah terdaftar di manifest, tak perlu entri baru)

**Interfaces:**
- Consumes: `isLocked`, `moveLockedSupport`, `supportsLocked`, `this.selSup`/`this.supPanelOpen` (Task 1–4), `this._lockNow()` (Task 3), `openMatMenu` (Task 4), `DenahConv.parseCmValue` (sudah ada).
- Produces: `DenahConv.describeLockedSupport(S, entry)` → string `"datar · 149cm dari atas"` / `"tegak · 100cm dari kiri"` / `"manual · 240cm"`, suffix `" (di luar frame)"` kalau jalur di luar bbox.

- [ ] **Step 1: Test describe (gagal dulu)** — tambahkan di akhir `tests/rangka/test_support_pick.mjs` (sebelum `process.exit`):

```js
// ── describeLockedSupport: teks baris panel ──
const Sd = { verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }] };
check('describe h', DenahConv.describeLockedSupport(Sd, { no: 1, axis: 'h', pos: 149, aktif: true }), 'datar · 149cm dari atas');
check('describe v', DenahConv.describeLockedSupport(Sd, { no: 2, axis: 'v', pos: 100, aktif: true }), 'tegak · 100cm dari kiri');
check('describe manual', DenahConv.describeLockedSupport(Sd, { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 240, y: 0 }, aktif: true }), 'manual · 240cm');
check('describe di luar frame', DenahConv.describeLockedSupport(Sd, { no: 4, axis: 'h', pos: 350, aktif: true }), 'datar · 350cm dari atas (di luar frame)');
```

- [ ] **Step 2: Jalankan, pastikan gagal**, lalu implementasi di `DenahConv` setelah `supportsNearPoint`:

```js
  // Teks info baris panel (spec 2.4) — posisi RELATIF bbox frame saat ini (ikut bentuk).
  // Jalur yang kegeser sampai luar frame tak tergambar (scan kosong) tapi tetap terdaftar;
  // ditandai di sini biar user paham kenapa garisnya "hilang".
  describeLockedSupport(S, e) {
    if (e.manual) return 'manual · ' + Math.round(dist(e.a, e.b)) + 'cm';
    const bb = bbox(S.verts);
    const txt = e.axis === 'h'
      ? 'datar · ' + Math.round(e.pos - bb.y0) + 'cm dari atas'
      : 'tegak · ' + Math.round(e.pos - bb.x0) + 'cm dari kiri';
    const luar = e.axis === 'h' ? (e.pos <= bb.y0 || e.pos >= bb.y1) : (e.pos <= bb.x0 || e.pos >= bb.x1);
    return luar ? txt + ' (di luar frame)' : txt;
  },
```

- [ ] **Step 3: Tulis ulang `renderSupportPanel(mem)`** — dua cabang:

```js
  // Panel Support (spec 2.4). Fase pratinjau: daftar manual lama + SATU baris ajakan kunci
  // (pintu masuk #2 kunci otomatis). Fase terkunci: daftar ceklis S1..Sn lengkap.
  renderSupportPanel(mem) {
    const panel = this._q('[data-role=supportPanel]');
    if (!panel) return;
    if (this.mode !== 'support') { panel.style.display = 'none'; panel.innerHTML = ''; return; }
    const locked = DenahConv.isLocked(this.S);

    if (!locked) {
      // ── PRATINJAU: baris ajakan + daftar manual lama (perilaku 21 Ags dipertahankan) ──
      panel.style.display = '';
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
        `<div class="de-tiang-head" data-role="supLockInvite" style="cursor:pointer">
           <b style="font-size:12px">Support — kelola per garis</b>
           <span class="de-mini">Kunci susunan</span>
         </div>` +
        (manualMem.length ? '<b style="font-size:12px;color:#334155">Daftar Support Manual</b>' + rows : '');
      this._q('[data-role=supLockInvite]').onclick = () => this._lockNow();
      // wiring sFokus/sHapus lama VERBATIM (copy dari implementasi sekarang)
      panel.querySelectorAll('[data-role=sFokus]').forEach(btn => { /* ...blok lama persis... */ });
      panel.querySelectorAll('[data-role=sHapus]').forEach(btn => { /* ...blok lama persis... */ });
      return;
    }

    // ── TERKUNCI: header + toggle Semua + lipat/buka + baris ceklis ──
    panel.style.display = '';
    const entries = this.S.supportsLocked;
    const anyOff = entries.some(e2 => e2.aktif === false);
    const head =
      `<div class="de-tiang-head">
        <b style="font-size:12px">Support (${entries.length})</b>
        <div class="de-tiang-actions">
          <span class="de-mini" data-role="slSemua">${anyOff ? 'Aktifkan semua' : 'Nonaktifkan semua'}</span>
          <span class="de-mini" data-role="slLipat">${this.supPanelOpen ? 'Lipat' : 'Buka'}</span>
        </div>
      </div>`;
    const rows = !this.supPanelOpen ? '' : entries.map(e2 => {
      const sel = e2.no === this.selSup;
      const desc = DenahConv.describeLockedSupport(this.S, e2);
      // Arah difilter per tipe (spec 2.3): h cuma atas/bawah, v cuma kiri/kanan, manual 4 arah.
      const dirs = e2.manual ? ['atas', 'bawah', 'kiri', 'kanan'] : e2.axis === 'h' ? ['atas', 'bawah'] : ['kiri', 'kanan'];
      const editRow = !sel ? '' :
        `<div class="de-tiang-fields" style="margin-top:4px">
          <label>Arah<select data-role="slDir">${dirs.map(d => `<option>${d}</option>`).join('')}</select></label>
          <label>cm<input type="text" inputmode="decimal" data-role="slCm"></label>
          <span class="de-mini de-tiang-apply" data-role="slApply">Terapkan</span>
        </div>
        <div class="de-tiang-actions" style="margin-top:4px">
          <span class="de-mini" data-role="slBesi">Ganti besi</span>
          ${e2.manual ? '<span class="de-mini" data-role="slHapus">Hapus</span>' : ''}
        </div>`;
      return `<div class="de-tiang-item" data-slrow="${e2.no}" style="${sel ? 'background:#fef9c3;border-radius:6px;padding-left:4px;' : ''}${e2.aktif === false ? 'opacity:.45;' : ''}">
        <div class="de-tiang-head">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px">
            <input type="checkbox" data-role="slAktif" data-no="${e2.no}" ${e2.aktif === false ? '' : 'checked'}>
            <b>S${e2.no}</b> <span style="color:#64748b">${desc}</span>
          </label>
          <div class="de-tiang-actions"><span class="de-mini" data-role="slFokus" data-no="${e2.no}">Fokus</span></div>
        </div>${editRow}
      </div>`;
    }).join('');
    panel.innerHTML = head + `<div data-role="slMsg" style="font-size:11px;color:#dc2626"></div>` + rows;

    this._q('[data-role=slLipat]').onclick = () => { this.supPanelOpen = !this.supPanelOpen; this.renderSupportPanel(mem); };
    this._q('[data-role=slSemua]').onclick = () => {
      this.pushUndo();
      entries.forEach(e2 => { e2.aktif = anyOff; });
      this.render();
    };
    panel.querySelectorAll('[data-role=slAktif]').forEach(cb => cb.onchange = () => {
      const e2 = entries.find(x => x.no === +cb.dataset.no);
      if (!e2) return;
      this.pushUndo(); e2.aktif = cb.checked; this.render();
    });
    panel.querySelectorAll('[data-role=slFokus]').forEach(btn => btn.onclick = () => {
      this.selSup = +btn.dataset.no;
      this.supPanelOpen = true;
      this._q('[data-role=canvasWrap]').scrollIntoView({ block: 'center', behavior: 'smooth' });
      this.render();   // render() menyorot garis di kanvas (Task 4) + melebarkan baris ini
    });
    const applyBtn = this._q('[data-role=slApply]');
    if (applyBtn) applyBtn.onclick = () => {
      const e2 = entries.find(x => x.no === this.selSup);
      const cmVal = DenahConv.parseCmValue(this._q('[data-role=slCm]').value);
      const moved = e2 && DenahConv.moveLockedSupport(e2, this._q('[data-role=slDir]').value, cmVal);
      if (!moved) { this._q('[data-role=slMsg]').textContent = 'Isi cm dengan angka > 0.'; return; }
      this.pushUndo();
      entries[entries.indexOf(e2)] = moved;
      this.render();
    };
    const besiBtn = this._q('[data-role=slBesi]');
    if (besiBtn) besiBtn.onclick = (ev) => this.openMatMenu(ev, 'SL' + this.selSup);
    const hapusBtn = this._q('[data-role=slHapus]');
    if (hapusBtn) hapusBtn.onclick = () => {
      const idx = entries.findIndex(x => x.no === this.selSup);
      if (idx < 0) return;
      this.pushUndo();
      delete this.S.matOverride['SL' + this.selSup];
      entries.splice(idx, 1);       // ID stabil: nomor lain TIDAK bergeser, tak ada remap
      this.selSup = null;
      this.render();
    };
  }
```

Catatan implementasi: blok wiring `sFokus`/`sHapus` pratinjau di-copy verbatim dari `renderSupportPanel` lama (jangan ditulis ulang beda). Panel pratinjau sekarang SELALU tampil di mode support (baris ajakan harus terjangkau) — ini mengubah perilaku "sembunyi saat kosong" 22 Ags, disengaja oleh spec 2.1.

- [ ] **Step 4: Sorot kanvas → panel** — sudah otomatis: `render()` memanggil `renderSupportPanel`, baris `sel` melebar + ter-highlight. Pastikan tap kanvas (Task 4) → `this.render()` (sudah). Tambah satu hal: saat sorot dari kanvas, buka panelnya — di handler tap toleran Task 4 setelah set `this.selSup`, tambah `this.supPanelOpen = true;` sebelum `this.render()`.

- [ ] **Step 5: Jalankan test + guardrail** — `node tests/rangka/test_support_pick.mjs && for f in tests/rangka/test_*.mjs; do node $f || break; done && php scripts/canopi-check` → PASS.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_pick.mjs
git commit -m "feat(denah): panel daftar support terkunci -- ceklis aktif/nonaktif, toggle semua, pindah angka, hapus manual"
```

---

### Task 6: Integrasi akhir — Undo/setModel bersih, status CLAUDE.md, checklist manual Elvan

**Files:**
- Modify: `public/js/denah-editor.js` — `undo()`/`redo()`/`setModel()` (validasi state kecil)
- Modify: `CLAUDE.md` — bagian Status Terkini / Utang aktif #2

**Interfaces:**
- Consumes: semua task sebelumnya.
- Produces: branch siap validasi manual + deploy.

- [ ] **Step 1: Bersihkan state alat lintas Undo/setModel** — di `undo()` dan `redo()`, setelah baris `this.boxPreview = null; this.addSupportPt = null;` tambah:

```js
    this._lastPickPt = null;   // cycling tap-ganti-kandidat direset; selSup divalidasi di render()
```

Di `setModel()`: tambah `this.selSup = null; this.moveOn = false; this._lastPickPt = null;` sebelum `this.render()`. (`selSup` basi sudah tervalidasi di `render()` — Task 3 Step 4 — ini hanya melengkapi jalur ganti-model penuh.)

- [ ] **Step 2: Jalankan SEMUA test + guardrail penuh**

```bash
for f in tests/rangka/test_*.mjs; do echo "== $f"; node $f || exit 1; done
php scripts/canopi-check
```

Expected: semua PASS, canopi-check hijau. Cek `git status` — pastikan `public/hot` tidak ikut.

- [ ] **Step 3: Update CLAUDE.md** — Utang aktif #2: tandai "Redesign Support ID Stabil — SELESAI implementasi, MENUNGGU validasi manual Bos di HP"; catat tombol "Pulihkan yang dihapus" masih hidup untuk fase pratinjau saja (pensiun menyusul setelah validasi); sertakan checklist manual di bawah.

- [ ] **Step 4: Commit**

```bash
git add public/js/denah-editor.js CLAUDE.md
git commit -m "chore(denah): rapikan state alat lintas undo/setModel + status Support ID Stabil"
```

- [ ] **Step 5: Checklist validasi manual Elvan di HP** (dilampirkan di laporan akhir, JANGAN diklaim sudah jalan):

1. Buka denah lama (data tersimpan) → tampil & berperilaku PERSIS seperti sebelumnya (fase pratinjau, spacing hidup).
2. Tab Support → tap baris "Support — kelola per garis / Kunci susunan" → muncul "Susunan dikunci", panel daftar S1..Sn kebentuk, input spacing hilang, tombol "Susun Ulang" muncul.
3. Undo → balik ke pratinjau (spacing muncul lagi). Redo → terkunci lagi.
4. Toggle move di quickbar (ikon panah 4 arah, cuma tampil di tab Support): mati → tap/geser garis di kanvas tak melakukan APA-APA; pinch-zoom aman total. Nyala → tap dekat garis = tersorot kuning + baris panel melebar; tap lagi di tempat sama = pindah ke garis tetangga.
5. Baris tersorot → isi arah+cm → Terapkan → garis pindah; garis h cuma punya atas/bawah, v cuma kiri/kanan. Undo mengembalikan.
6. Ceklis baris → garis hilang dari gambar (dan hitungan besi); ceklis lagi → balik. "Nonaktifkan semua"/"Aktifkan semua" bekerja.
7. Ubah bentuk frame (tab Rangka, geser sudut / tambah kotak) → garis support terkunci ikut memanjang/memendek/terbelah sendiri; area frame baru TIDAK dapat garis baru (ini disengaja, spec bagian 3).
8. + Support manual (fase terkunci) → entri baru muncul di panel dengan nomor lanjutan; ujungnya bisa ditarik hanya saat tersorot + toggle move nyala; Hapus di panel benar-benar menghapus (Undo balikin).
9. "Susun Ulang" → ada konfirmasi; setelah OK, spacing hidup lagi, support manual tetap ada; kunci lagi → nomor entri baru MELANJUTKAN (tidak mulai dari 1).
10. Ganti besi per garis (mode Ganti besi di quickbar ATAU tombol "Ganti besi" di panel) → label menu "Support S{n}"; ganti bentuk frame → besi pilihan tetap nempel di garis yang sama (ini inti fitur — ID stabil).

---

## Self-Review (sudah dijalankan penulis plan)

- **Spec coverage:** 2.1 dua fase + dua pintu kunci + Susun Ulang → Task 1/2/3/5. 2.2 jalur + penomoran + migrasi override → Task 1/2. 2.3 toggle move + sorot + pindah angka + tarik ujung manual + menu tekan-tahan dihapus + tanpa crosshair → Task 3/4/5. 2.4 panel ceklis → Task 5. 2.5 kompatibilitas (pratinjau verbatim, Pulihkan tetap di pratinjau, autosave key baru) → Task 1/3 (+ `btnRestoreSup` disembunyikan saat terkunci, Task 3 Step 4). Bagian 5 testing → test file Task 1/2/4/5 + checklist manual Task 6.
- **Keputusan yang diambil plan (tak eksplisit di spec, sudah dicatat inline):** removed parsial → entri tetap aktif; unlock mempertahankan manual nonaktif; jalur di luar frame tetap terdaftar bertanda "(di luar frame)"; panel pratinjau selalu tampil di mode support (baris ajakan).
- **Type consistency:** entri `{no, axis, pos, aktif}` / `{no, manual, a, b, aktif}` dan id `SL{no}` konsisten di semua task; `moveLockedSupport(entry, arah, cm)` dipanggil Task 5 sesuai signature Task 2; `supportsNearPoint(mem, pt, th)` Task 4 = definisi test-nya.
