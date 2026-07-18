# DenahEditor Kelompok C — saran kotak support 2 arah independen — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pecah ukuran kotak support (`S.kotak`, 1 nilai dipaksa dipakai 2 arah) jadi `S.kotakLebar`/`S.kotakPanjang` independen, dengan jalur input tambahan lewat jumlah kolom (bukan cuma cm) — sesuai `docs/superpowers/specs/2026-07-17-denah-ui-kelompok-c-design.md`.

**Architecture:** Semua perubahan tetap di **satu file** `public/js/denah-editor.js` (classic-script IIFE, pola yang sudah ada). Kolom (jumlah bagian) **tidak pernah disimpan** — selalu dihitung ULANG dari cm yang tersimpan lewat fungsi murni baru (`DenahConv.kolomFromKotak`), jadi cm tetap satu-satunya sumber kebenaran. Migrasi data lama (`S.kotak`/`S.autoKotak` → `kotakLebar`/`kotakPanjang`/`autoKotakLebar`/`autoKotakPanjang`) jadi 1 fungsi murni baru (`DenahConv.migrateKotak`, pola sama seperti `shiftBoxesInsert`/`shiftBoxesDelete` dari Kelompok B: testable Node, dipanggil dari 2 titik masuk model `DenahEditor` — constructor & `setModel()`).

**Tech Stack:** JS vanilla, tanpa framework/bundler. Tes geometri/data murni via Node (pola sama `test_konverter.mjs`/`test_align_snap.mjs`). Verifikasi UI manual lewat harness lokal yang SUDAH ADA (`tests/rangka/denah_editor_harness.html`, gitignored, dibuat Kelompok A) sebelum deploy ke production.

## Global Constraints

- **Satu file kode yang berubah:** `public/js/denah-editor.js` (+ file tes baru/dimodifikasi di `tests/rangka/`). Blade (`resources/views/rab-opsi/index.blade.php`) **TIDAK disentuh**.
- **Backward-compat WAJIB:** model denah lama (tersimpan sebelum Kelompok C, cuma punya `S.kotak`/`S.autoKotak`) harus tetap tampil identik saat pertama dibuka — migrasi terjadi otomatis lewat `_migrateKotak()`, dipanggil di constructor DAN `setModel()` (dua-duanya jalur model bisa masuk ke instance).
- **Kolom TIDAK disimpan ke `S`** — murni angka turunan, dihitung ulang tiap kali dari `kotakLebar`/`kotakPanjang` + Lebar/Panjang saat ini. `S.kolomLebar`/`S.kolomPanjang` (atau nama serupa) **JANGAN ditambahkan** ke model data.
- **Pembulatan:** semua cm hasil hitungan (`saranKotak`, `kotakFromKolom`) dibulatkan ke **maksimal 1 angka desimal** (`Math.round(x*10)/10`). Kolom selalu bilangan bulat, minimal 1.
- **1 pengaturan berlaku untuk SELURUH bentuk denah** (termasuk bagian dari "+ Tambah Kotak") — bukan per-area. `DenahConv.buildMembers`'s `scanX`/`scanY`/`combineBox` **tidak diubah sama sekali** — cuma variabel jarak (`K`) yang dipecah jadi 2.
- **Pemetaan arah↔sumbu (WAJIB persis, gampang ketuker):** `arah==='h'` (garis horizontal, ditumpuk sepanjang Y) pakai `S.kotakPanjang`. `arah==='v'` (garis vertikal, ditumpuk sepanjang X) pakai `S.kotakLebar`. `arah==='2'` pakai dua-duanya.
- **Emoji dilarang** di kode yang tampil ke user (CLAUDE.md).
- **Verifikasi UI dilakukan manual** lewat harness lokal (`tests/rangka/denah_editor_harness.html`) sebelum deploy — bukan otomatis.

---

### Task 1: `DenahConv.saranKotak` (1 desimal) + `kotakFromKolom`/`kolomFromKotak`

**Files:**
- Modify: `public/js/denah-editor.js`
- Create: `tests/rangka/test_saran_kotak.mjs`

**Interfaces:**
- Produces (dipakai Task 4 lewat `DenahEditor`):
  - `DenahConv.saranKotak(ukuran, target) -> number` — SUDAH ADA, cuma pembulatan akhir diubah dari bulat-cm jadi 1 desimal. Logika cari jumlah bagian simetris (`n`) tidak berubah.
  - `DenahConv.kotakFromKolom(ukuran, kolom) -> number` **(baru)** — cm dari jumlah kolom, 1 desimal, kolom dipaksa minimal 1.
  - `DenahConv.kolomFromKotak(ukuran, kotak) -> number` **(baru)** — kolom (bilangan bulat, minimal 1) dari cm.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_saran_kotak.mjs`:

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

// saranKotak: pembagian PAS (700/7=100, 530/5=106) -> hasil bulat, sama seperti sebelum Kelompok C
check('saranKotak(700,100)=100', DenahConv.saranKotak(700, 100), 100);
check('saranKotak(530,100)=106', DenahConv.saranKotak(530, 100), 106);
// saranKotak: pembagian TAK PAS (650/7=92.857...) -> sekarang boleh 1 desimal (Kelompok C)
check('saranKotak(650,100)=92.9 (1 desimal, bukan dibulatkan ke 93)', DenahConv.saranKotak(650, 100), 92.9);

// kotakFromKolom: cm dari jumlah kolom yang diketik user
check('kotakFromKolom(700,10)=70', DenahConv.kotakFromKolom(700, 10), 70);
check('kotakFromKolom(700,9)=77.8 (1 desimal)', DenahConv.kotakFromKolom(700, 9), 77.8);
check('kotakFromKolom(700,0)=700 (kolom dipaksa minimal 1, tak bagi-nol)', DenahConv.kotakFromKolom(700, 0), 700);

// kolomFromKotak: kolom dari cm, bilangan bulat, minimal 1
check('kolomFromKotak(700,70)=10', DenahConv.kolomFromKotak(700, 70), 10);
check('kolomFromKotak(700,77.8)=9', DenahConv.kolomFromKotak(700, 77.8), 9);
check('kolomFromKotak(700,0)=700 (kotak 0 dijaga, tak bagi-nol)', DenahConv.kolomFromKotak(700, 0), 700);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_saran_kotak.mjs`
Expected: `saranKotak(650,100)=92.9` FAIL (hasil lama masih 93, bulat) — fungsi baru `kotakFromKolom`/`kolomFromKotak` belum ada, jadi juga error `is not a function`.

- [ ] **Step 3: Ubah `saranKotak`, tambah `kotakFromKolom`/`kolomFromKotak`**

Di `public/js/denah-editor.js`, cari:

```js
  saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n); },
```

Ganti jadi:

```js
  saranKotak(lebar, target) { const n = Math.max(1, Math.round(lebar / target)); return Math.round(lebar / n * 10) / 10; },
  // Cm dari jumlah kolom yang diketik user (Kelompok C) — 1 desimal, kolom dipaksa minimal 1
  // (tak boleh bagi dgn 0/negatif).
  kotakFromKolom(ukuran, kolom) { return Math.round(ukuran / Math.max(1, kolom) * 10) / 10; },
  // Kolom (bilangan bulat) dari cm yang diketik user — kotak dijaga tak pernah 0 (fallback 1, cegah
  // bagi-nol/Infinity kalau input rusak).
  kolomFromKotak(ukuran, kotak) { return Math.max(1, Math.round(ukuran / (kotak || 1))); },
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_saran_kotak.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Regresi tes lama**

Run: `node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs && node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `SEMUA LULUS` untuk kelimanya + `syntax OK`. (`test_konverter.mjs` punya 2 assertion `saranKotak` — `saranKotak(700,100)=100` dan `saranKotak(530,100)=106` — dua-duanya pembagian PAS jadi tetap sama persis meski pembulatan berubah jadi 1 desimal; tak perlu diubah di task ini.)

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_saran_kotak.mjs
git commit -m "feat(denah): saranKotak 1 desimal + kotakFromKolom/kolomFromKotak (Kelompok C)"
```

---

### Task 2: `DenahConv.buildMembers` — pecah jarak 2 arah (`kotakLebar`/`kotakPanjang`)

**Files:**
- Modify: `public/js/denah-editor.js`
- Modify: `tests/rangka/test_konverter.mjs`

**Interfaces:**
- Consumes: tidak ada dari Task 1 (independen — `buildMembers` tak pernah memanggil `saranKotak`/`kotakFromKolom`/`kolomFromKotak`).
- Produces: `DenahConv.buildMembers(S)` sekarang baca `S.kotakLebar` (dipasangkan ke `arah==='v'`, garis vertikal) dan `S.kotakPanjang` (dipasangkan ke `arah==='h'`, garis horizontal) — bukan `S.kotak` lagi. Dipakai Task 4 (model yang dikirim dari UI harus sudah punya field ini, dijamin Task 3's migrasi + `defaultModel()`).

- [ ] **Step 1: Tulis tes yang gagal (tambah ke `test_konverter.mjs`)**

Di `tests/rangka/test_konverter.mjs`, cari baris terakhir sebelum penutup:

```js
// saranKotak: lebar 700, target 100 -> 700/round(7)=100
check('saranKotak(700,100)=100', DenahConv.saranKotak(700,100), 100);
check('saranKotak(530,100)=106', DenahConv.saranKotak(530,100), 106); // round(5.3)=5 -> 106

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

Ganti jadi (tambah blok tes 2-arah sebelum baris penutup):

```js
// saranKotak: lebar 700, target 100 -> 700/round(7)=100
check('saranKotak(700,100)=100', DenahConv.saranKotak(700,100), 100);
check('saranKotak(530,100)=106', DenahConv.saranKotak(530,100), 106); // round(5.3)=5 -> 106

// Kelompok C: 2 arah independen. Kotak 800x400, arah '2', kotakLebar=200 (garis vertikal tiap 200cm
// sepanjang Lebar 800 -> X=200,400,600 -> 3 garis), kotakPanjang=100 (garis horizontal tiap 100cm
// sepanjang Panjang 400 -> Y=100,200,300 -> 3 garis).
const dua = {
  verts: [{x:0,y:0},{x:800,y:0},{x:800,y:400},{x:0,y:400}],
  grid:20, target:100, kotakLebar:200, kotakPanjang:100, autoKotakLebar:true, autoKotakPanjang:true, arah:'2',
  supportsManual:[], removed:{}, tiang:[], tinggi:300,
  matDefault:{frame:'F',support:'S',tiang:'T'}, matOverride:{},
};
const mDua = DenahConv.buildMembers(dua);
const sh = mDua.filter(x => x.id.startsWith('Sh_'));
const sv = mDua.filter(x => x.id.startsWith('Sv_'));
check('2 arah: 3 garis horizontal (pakai kotakPanjang=100 di Panjang=400)', sh.length, 3);
check('2 arah: 3 garis vertikal (pakai kotakLebar=200 di Lebar=800)', sv.length, 3);

// arah 'h' saja -> cuma garis horizontal (kotakPanjang), kotakLebar tak dipakai sama sekali
const hOnly = { ...dua, arah: 'h' };
const mH = DenahConv.buildMembers(hOnly);
check('arah h saja: 3 garis horizontal', mH.filter(x => x.id.startsWith('Sh_')).length, 3);
check('arah h saja: 0 garis vertikal', mH.filter(x => x.id.startsWith('Sv_')).length, 0);

// arah 'v' saja -> cuma garis vertikal (kotakLebar), kotakPanjang tak dipakai sama sekali
const vOnly = { ...dua, arah: 'v' };
const mV = DenahConv.buildMembers(vOnly);
check('arah v saja: 3 garis vertikal', mV.filter(x => x.id.startsWith('Sv_')).length, 3);
check('arah v saja: 0 garis horizontal', mV.filter(x => x.id.startsWith('Sh_')).length, 0);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL (atau salah)**

Run: `node tests/rangka/test_konverter.mjs`
Expected: assertion "2 arah" FAIL — `buildMembers` belum baca `kotakLebar`/`kotakPanjang` (masih fallback ke default 100 buat dua-duanya via `S.kotak` yang sekarang undefined di fixture ini → dua-duanya kepakai 100, bukan 200/100 yang beda) — jumlah garis horizontal vs vertikal jadi SAMA (bukan beda 3 vs 3 dgn K berbeda; dengan K=100 di dua arah pada bentuk 800x400, harusnya 7 vertikal bukan 3 — assertion `sv.length===3` gagal).

- [ ] **Step 3: Ubah `buildMembers` — pecah `K` jadi `KL`/`KP`**

Di `public/js/denah-editor.js`, cari:

```js
  buildMembers(S) {
    // K harus > 0: kotak<=0 (mis. input negatif / model tersimpan rusak) bikin loop scanline tak berhenti → freeze tab.
    const mem = [], V = S.verts, bb = bbox(V), K = (S.kotak > 0 ? S.kotak : 100), rem = S.removed || {};
```

Ganti jadi:

```js
  buildMembers(S) {
    // KL/KP harus > 0: kotak<=0 (mis. input negatif / model tersimpan rusak) bikin loop scanline tak berhenti → freeze tab.
    const mem = [], V = S.verts, bb = bbox(V), rem = S.removed || {};
    const KL = (S.kotakLebar > 0 ? S.kotakLebar : 100), KP = (S.kotakPanjang > 0 ? S.kotakPanjang : 100);
```

Lalu cari:

```js
    if (S.arah === 'h' || S.arah === '2') { let li = 0;
      for (let Y = bb.y0 + K; Y < bb.y1 - 1; Y += K, li++) { const xs = scanX(V, Y);
        for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); } }
    if (S.arah === 'v' || S.arah === '2') { let li = 0;
      for (let X = bb.x0 + K; X < bb.x1 - 1; X += K, li++) { const ys = scanY(V, X);
        for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); } }
```

Ganti jadi (`K` di baris `for` diganti `KP` di blok horizontal, `KL` di blok vertikal — sisanya identik):

```js
    if (S.arah === 'h' || S.arah === '2') { let li = 0;
      for (let Y = bb.y0 + KP; Y < bb.y1 - 1; Y += KP, li++) { const xs = scanX(V, Y);
        for (let s = 0; s + 1 < xs.length; s += 2) addSeg('Sh_' + li + '_' + s, { x: xs[s], y: Y }, { x: xs[s + 1], y: Y }); } }
    if (S.arah === 'v' || S.arah === '2') { let li = 0;
      for (let X = bb.x0 + KL; X < bb.x1 - 1; X += KL, li++) { const ys = scanY(V, X);
        for (let s = 0; s + 1 < ys.length; s += 2) addSeg('Sv_' + li + '_' + s, { x: X, y: ys[s] }, { x: X, y: ys[s + 1] }); } }
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_konverter.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: Regresi tes lain**

Run: `node tests/rangka/test_saran_kotak.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs && node --check public/js/denah-editor.js && echo "syntax OK"`
Expected: `SEMUA LULUS` untuk kelimanya + `syntax OK`.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_konverter.mjs
git commit -m "feat(denah): buildMembers pakai kotakLebar/kotakPanjang independen (Kelompok C)"
```

---

### Task 3: `DenahConv.migrateKotak` + `defaultModel()` + wiring migrasi

**Files:**
- Modify: `public/js/denah-editor.js`
- Create: `tests/rangka/test_migrate_kotak.mjs`

**Interfaces:**
- Produces:
  - `DenahConv.migrateKotak(S) -> {kotakLebar?, kotakPanjang?, autoKotakLebar?, autoKotakPanjang?}` — murni, TIDAK mutasi `S`. Kembalikan cuma field yang PERLU diisi (belum ada di `S`); field yang sudah ada di `S` tidak disebut di hasil (jadi `Object.assign` di pemanggil aman, tak menimpa nilai baru yang sudah ada).
  - `DenahEditor.prototype._migrateKotak()` — instance method, `Object.assign(this.S, DenahConv.migrateKotak(this.S))`. Dipanggil Task 4 tak perlu tahu detail — cukup tahu setelah constructor/`setModel()` jalan, `this.S.kotakLebar`/`kotakPanjang`/`autoKotakLebar`/`autoKotakPanjang` DIJAMIN ada.

- [ ] **Step 1: Tulis tes yang gagal**

Create `tests/rangka/test_migrate_kotak.mjs`:

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

// Data lama (sebelum Kelompok C): cuma S.kotak/S.autoKotak -> dua-duanya (Lebar & Panjang) diisi
// dari nilai lama yang sama (dulu memang 1 nilai dipakai buat dua arah).
check('migrasi dari kotak lama (autoKotak true)',
  DenahConv.migrateKotak({ kotak: 120, autoKotak: true }),
  { kotakLebar: 120, kotakPanjang: 120, autoKotakLebar: true, autoKotakPanjang: true });

check('migrasi dari kotak lama (autoKotak false)',
  DenahConv.migrateKotak({ kotak: 80, autoKotak: false }),
  { kotakLebar: 80, kotakPanjang: 80, autoKotakLebar: false, autoKotakPanjang: false });

// Data lama tanpa kotak sama sekali (mis. model rusak/kosong) -> jatuh ke default 100, auto true
check('migrasi tanpa kotak sama sekali -> default 100',
  DenahConv.migrateKotak({}),
  { kotakLebar: 100, kotakPanjang: 100, autoKotakLebar: true, autoKotakPanjang: true });

// Data BARU (sudah punya kotakLebar/kotakPanjang) -> tak disentuh, hasil kosong (tak ada yg perlu diisi)
check('data baru (sudah ada kotakLebar/Panjang) -> tak ada perubahan',
  DenahConv.migrateKotak({ kotakLebar: 70, kotakPanjang: 130, autoKotakLebar: false, autoKotakPanjang: true }),
  {});

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes — pastikan GAGAL**

Run: `node tests/rangka/test_migrate_kotak.mjs`
Expected: error `DenahConv.migrateKotak is not a function`.

- [ ] **Step 3: Tambah `migrateKotak` ke `DenahConv`**

Di `public/js/denah-editor.js`, cari:

```js
  kotakFromKolom(ukuran, kolom) { return Math.round(ukuran / Math.max(1, kolom) * 10) / 10; },
  // Kolom (bilangan bulat) dari cm yang diketik user — kotak dijaga tak pernah 0 (fallback 1, cegah
  // bagi-nol/Infinity kalau input rusak).
  kolomFromKotak(ukuran, kotak) { return Math.max(1, Math.round(ukuran / (kotak || 1))); },
```

Ganti jadi (tambah `migrateKotak` setelah `kolomFromKotak`):

```js
  kotakFromKolom(ukuran, kolom) { return Math.round(ukuran / Math.max(1, kolom) * 10) / 10; },
  // Kolom (bilangan bulat) dari cm yang diketik user — kotak dijaga tak pernah 0 (fallback 1, cegah
  // bagi-nol/Infinity kalau input rusak).
  kolomFromKotak(ukuran, kotak) { return Math.max(1, Math.round(ukuran / (kotak || 1))); },
  // Data lama (sebelum Kelompok C) cuma punya S.kotak/S.autoKotak (1 nilai dipakai dua arah). Isi
  // kotakLebar/kotakPanjang/autoKotakLebar/autoKotakPanjang dari situ kalau BELUM ADA di S, supaya
  // denah lama tetap tampil identik saat pertama dibuka. Murni — kembalikan cuma field yg perlu
  // diisi, TIDAK mutasi S; pemanggil (DenahEditor._migrateKotak) yang Object.assign ke this.S.
  migrateKotak(S) {
    const out = {};
    if (S.kotakLebar == null) out.kotakLebar = S.kotak > 0 ? S.kotak : 100;
    if (S.kotakPanjang == null) out.kotakPanjang = S.kotak > 0 ? S.kotak : 100;
    if (S.autoKotakLebar == null) out.autoKotakLebar = S.autoKotak !== false;
    if (S.autoKotakPanjang == null) out.autoKotakPanjang = S.autoKotak !== false;
    return out;
  },
```

- [ ] **Step 4: Jalankan tes — pastikan LULUS**

Run: `node tests/rangka/test_migrate_kotak.mjs`
Expected: `SEMUA LULUS`.

- [ ] **Step 5: `defaultModel()` pakai field baru**

Cari:

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

Ganti jadi:

```js
  static defaultModel() {
    return {
      verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
      grid: 20, target: 100,
      kotakLebar: 100, kotakPanjang: 100, autoKotakLebar: true, autoKotakPanjang: true,
      arah: '2', supportsManual: [], removed: {}, tiang: [],
      tinggi: 300, matDefault: { frame: '', support: '', tiang: '' }, matOverride: {}, combinedBoxes: [],
    };
  }
```

- [ ] **Step 6: Instance method `_migrateKotak()` + panggil di constructor & `setModel()`**

Cari method `syncLP()` (tepat sebelum `resizeBox()`):

```js
  // sinkron Lebar/Panjang = bbox
  syncLP() {
```

Ganti jadi (tambah `_migrateKotak()` tepat sebelum `syncLP()`):

```js
  // Panggil setelah this.S diisi dari model manapun (constructor / setModel) — jamin
  // kotakLebar/kotakPanjang/autoKotakLebar/autoKotakPanjang selalu ada (migrasi data lama Kelompok C).
  _migrateKotak() { Object.assign(this.S, DenahConv.migrateKotak(this.S)); }

  // sinkron Lebar/Panjang = bbox
  syncLP() {
```

Cari (constructor, baris assignment `this.S`):

```js
    this.S = this.opts.model ? JSON.parse(JSON.stringify(this.opts.model)) : DenahEditor.defaultModel();
    if (this.besi.length) {
```

Ganti jadi:

```js
    this.S = this.opts.model ? JSON.parse(JSON.stringify(this.opts.model)) : DenahEditor.defaultModel();
    this._migrateKotak();
    if (this.besi.length) {
```

Cari (`setModel`, dekat akhir class):

```js
  setModel(m) { this.armed = null; this.boxPreview = null; this.S = JSON.parse(JSON.stringify(m)); this.syncInputs(); this.render(); }
```

Ganti jadi:

```js
  setModel(m) { this.armed = null; this.boxPreview = null; this.S = JSON.parse(JSON.stringify(m)); this._migrateKotak(); this.syncInputs(); this.render(); }
```

- [ ] **Step 7: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_saran_kotak.mjs && node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs && node tests/rangka/test_migrate_kotak.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk ketujuhnya.

**Catatan:** setelah task ini, `this.S.kotak`/`this.S.autoKotak` (field lama) TETAP ADA kalau model lama dimuat (migrasi cuma MENAMBAH field baru, tak menghapus yang lama) — tapi tak lagi DIBACA di mana pun oleh kode (Task 2 sudah ganti `buildMembers` ke field baru; Task 4 akan menghapus SEMUA pemakaian `S.kotak`/`S.autoKotak` yang tersisa di UI). Field lama itu jadi data mati yang aman diabaikan, tak perlu dihapus eksplisit dari objek (lebih sederhana daripada `delete S.kotak`, dan tak berpengaruh ke `getModel()`/`rab_snapshot` selain menambah 2 field kecil tak terpakai).

- [ ] **Step 8: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_migrate_kotak.mjs
git commit -m "feat(denah): migrateKotak + defaultModel kotakLebar/kotakPanjang (Kelompok C)"
```

---

### Task 4: UI — 2 pasang input (cm + Kolom) per sumbu, sinkron & nonaktif sesuai Arah

**Files:**
- Modify: `public/js/denah-editor.js`

**Interfaces:**
- Consumes: `DenahConv.saranKotak`/`kotakFromKolom`/`kolomFromKotak` (Task 1), `S.kotakLebar`/`kotakPanjang`/`autoKotakLebar`/`autoKotakPanjang` terjamin ada (Task 3).
- Produces: tidak ada API baru untuk task lain — ini task UI terakhir Kelompok C.

- [ ] **Step 1: CSS — gaya input nonaktif**

Cari:

```css
.de-card select{padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff}
```

Ganti jadi:

```css
.de-card select{padding:5px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff}
.de-card input:disabled{opacity:.45;cursor:not-allowed}
```

- [ ] **Step 2: Panel tab Support — 2 pasang input**

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
        <label>Kotak Lebar (cm)<input type="number" data-role="inKotakLebar" value="100" step="5" min="1"></label>
        <label>Kolom X<input type="number" data-role="inKolomX" value="1" step="1" min="1"></label>
        <label>Kotak Panjang (cm)<input type="number" data-role="inKotakPanjang" value="100" step="5" min="1"></label>
        <label>Kolom Y<input type="number" data-role="inKolomY" value="1" step="1" min="1"></label>
        <span class="de-mini" data-role="btnSaran">Pakai saran</span>
      </div>
      <div class="de-hint" data-role="saranHintLebar"></div>
      <div class="de-hint" data-role="saranHintPanjang"></div>
    </div>
```

(Nilai awal `value="100"`/`value="1"` di HTML cuma placeholder statis — langsung ditimpa `syncKotakInputs()` sebelum user lihat apa pun, pola sama seperti `inKotak value="100"` yang lama.)

- [ ] **Step 3: Method baru `syncKotakInputs()`**

Cari method `_migrateKotak()` yang ditambahkan Task 3 (tepat sebelum `syncLP()`):

```js
  // Panggil setelah this.S diisi dari model manapun (constructor / setModel) — jamin
  // kotakLebar/kotakPanjang/autoKotakLebar/autoKotakPanjang selalu ada (migrasi data lama Kelompok C).
  _migrateKotak() { Object.assign(this.S, DenahConv.migrateKotak(this.S)); }

  // sinkron Lebar/Panjang = bbox
  syncLP() {
```

Ganti jadi (tambah `syncKotakInputs()` di antaranya):

```js
  // Panggil setelah this.S diisi dari model manapun (constructor / setModel) — jamin
  // kotakLebar/kotakPanjang/autoKotakLebar/autoKotakPanjang selalu ada (migrasi data lama Kelompok C).
  _migrateKotak() { Object.assign(this.S, DenahConv.migrateKotak(this.S)); }

  // Sinkron ke-4 input Kotak/Kolom Lebar+Panjang dari this.S + hint saran + status aktif/nonaktif
  // sesuai mode Arah (Kelompok C). Kolom SELALU dihitung ulang dari cm (tak pernah disimpan, S.verts
  // §3.1.2) — dipanggil tiap kali S.kotakLebar/kotakPanjang/arah berubah, atau Lebar/Panjang denah
  // berubah. Pemetaan arah->sumbu WAJIB persis: 'v' (garis vertikal, ditumpuk sepanjang X) pakai
  // kotakLebar; 'h' (garis horizontal, ditumpuk sepanjang Y) pakai kotakPanjang.
  syncKotakInputs() {
    const L = +(this._q('[data-role=inL]').value) || 0;
    const P = +(this._q('[data-role=inP]').value) || 0;
    this._q('[data-role=inKotakLebar]').value = this.S.kotakLebar;
    this._q('[data-role=inKolomX]').value = DenahConv.kolomFromKotak(L, this.S.kotakLebar);
    this._q('[data-role=inKotakPanjang]').value = this.S.kotakPanjang;
    this._q('[data-role=inKolomY]').value = DenahConv.kolomFromKotak(P, this.S.kotakPanjang);
    const lebarOn = this.S.arah === 'v' || this.S.arah === '2';
    const panjangOn = this.S.arah === 'h' || this.S.arah === '2';
    this._q('[data-role=inKotakLebar]').disabled = !lebarOn;
    this._q('[data-role=inKolomX]').disabled = !lebarOn;
    this._q('[data-role=inKotakPanjang]').disabled = !panjangOn;
    this._q('[data-role=inKolomY]').disabled = !panjangOn;
    const sugL = DenahConv.saranKotak(L, this.S.target), sugP = DenahConv.saranKotak(P, this.S.target);
    this._q('[data-role=saranHintLebar]').textContent =
      `Lebar: saran ~${this.S.target}cm → ${sugL}cm (${Math.max(1, Math.round(L / this.S.target))} kolom simetris)`;
    this._q('[data-role=saranHintPanjang]').textContent =
      `Panjang: saran ~${this.S.target}cm → ${sugP}cm (${Math.max(1, Math.round(P / this.S.target))} kolom simetris)`;
  }

  // sinkron Lebar/Panjang = bbox
  syncLP() {
```

- [ ] **Step 4: `syncLP()`/`syncInputs()` pakai `syncKotakInputs()`, buang `inKotak` lama**

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

  // sinkron Lebar/Panjang = bbox
  syncLP() {
    const bb = bbox(this.S.verts);
    this._q('[data-role=inL]').value = Math.round(bb.x1 - bb.x0);
    this._q('[data-role=inP]').value = Math.round(bb.y1 - bb.y0);
    this.updSaranHint();
  }
```

Ganti jadi (buang `inKotak` dari `syncInputs`, buang `saranKotak()`/`updSaranHint()` instance method lama, `applySaran()` jadi 2-sumbu, `syncLP()` pakai `syncKotakInputs()`):

```js
  syncInputs() {
    this._q('[data-role=inArah]').value = this.S.arah;
    this._q('[data-role=inGrid]').value = this.S.grid;
    this._q('[data-role=inT]').value = this.S.tinggi;
    this._q('[data-role=matFrame]').value = this.S.matDefault.frame;
    this._q('[data-role=matSupport]').value = this.S.matDefault.support;
    this._q('[data-role=matTiang]').value = this.S.matDefault.tiang;
    this.syncLP();
  }

  applySaran() {
    const L = +(this._q('[data-role=inL]').value) || 0;
    const P = +(this._q('[data-role=inP]').value) || 0;
    this.S.autoKotakLebar = true; this.S.autoKotakPanjang = true;
    this.S.kotakLebar = DenahConv.saranKotak(L, this.S.target);
    this.S.kotakPanjang = DenahConv.saranKotak(P, this.S.target);
    this.syncKotakInputs();
    this.render();
  }

  // sinkron Lebar/Panjang = bbox
  syncLP() {
    const bb = bbox(this.S.verts);
    this._q('[data-role=inL]').value = Math.round(bb.x1 - bb.x0);
    this._q('[data-role=inP]').value = Math.round(bb.y1 - bb.y0);
    this.syncKotakInputs();
  }
```

- [ ] **Step 5: `_wireControls()` — ganti handler `inKotak`, tambah 4 handler baru, update `inArah`**

Cari:

```js
    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this.render(); };
    this._q('[data-role=inKotak]').oninput = e => { this.S.kotak = Math.max(1, +e.target.value) || this.S.kotak; this.S.autoKotak = false; this.render(); };
    this._q('[data-role=inGrid]').onchange = e => { this.S.grid = +e.target.value; this.render(); };
    this._q('[data-role=inT]').oninput = e => { this.S.tinggi = +e.target.value || 300; this.render(); };
    this._q('[data-role=inL]').oninput = () => this.updSaranHint();
```

Ganti jadi:

```js
    this._q('[data-role=inArah]').onchange = e => { this.S.arah = e.target.value; this.syncKotakInputs(); this.render(); };
    this._q('[data-role=inKotakLebar]').oninput = e => {
      this.S.kotakLebar = Math.max(0.1, +e.target.value) || this.S.kotakLebar;
      this.S.autoKotakLebar = false;
      this.syncKotakInputs();
      this.render();
    };
    this._q('[data-role=inKolomX]').oninput = e => {
      const L = +(this._q('[data-role=inL]').value) || 0;
      const kolom = Math.max(1, Math.round(+e.target.value) || 1);
      this.S.kotakLebar = DenahConv.kotakFromKolom(L, kolom);
      this.S.autoKotakLebar = false;
      this.syncKotakInputs();
      this.render();
    };
    this._q('[data-role=inKotakPanjang]').oninput = e => {
      this.S.kotakPanjang = Math.max(0.1, +e.target.value) || this.S.kotakPanjang;
      this.S.autoKotakPanjang = false;
      this.syncKotakInputs();
      this.render();
    };
    this._q('[data-role=inKolomY]').oninput = e => {
      const P = +(this._q('[data-role=inP]').value) || 0;
      const kolom = Math.max(1, Math.round(+e.target.value) || 1);
      this.S.kotakPanjang = DenahConv.kotakFromKolom(P, kolom);
      this.S.autoKotakPanjang = false;
      this.syncKotakInputs();
      this.render();
    };
    this._q('[data-role=inGrid]').onchange = e => { this.S.grid = +e.target.value; this.render(); };
    this._q('[data-role=inT]').oninput = e => { this.S.tinggi = +e.target.value || 300; this.render(); };
    this._q('[data-role=inL]').oninput = () => this.syncKotakInputs();
```

- [ ] **Step 6: `resizeBox()` — auto per sumbu**

Cari:

```js
    if (this.S.autoKotak) this.S.kotak = DenahConv.saranKotak(L, this.S.target);
    this.render();
  }

  resetBox() {
```

Ganti jadi:

```js
    if (this.S.autoKotakLebar) this.S.kotakLebar = DenahConv.saranKotak(L, this.S.target);
    if (this.S.autoKotakPanjang) this.S.kotakPanjang = DenahConv.saranKotak(P, this.S.target);
    this.syncKotakInputs();
    this.render();
  }

  resetBox() {
```

- [ ] **Step 7: Verifikasi sintaks & regresi**

Run: `node --check public/js/denah-editor.js && echo "syntax OK"`
Run: `node tests/rangka/test_saran_kotak.mjs && node tests/rangka/test_konverter.mjs && node tests/rangka/test_box_union.mjs && node tests/rangka/test_ortho_snap.mjs && node tests/rangka/test_align_snap.mjs && node tests/rangka/test_box_reindex.mjs && node tests/rangka/test_migrate_kotak.mjs`
Expected: `syntax OK` + `SEMUA LULUS` untuk ketujuhnya (task ini murni restrukturisasi HTML/CSS/wiring DOM, tak menyentuh `DenahConv`).

Run juga: `grep -n "data-role=inKotak\]'" public/js/denah-editor.js` (bukan `inKotakLebar`/`inKotakPanjang`) dan `grep -n "\.saranKotak()\|updSaranHint" public/js/denah-editor.js` — keduanya harus **kosong** (tak ada sisa referensi ke elemen/method lama yang sudah dihapus).

- [ ] **Step 8: Verifikasi manual di harness**

Restart harness kalau perlu:
```bash
php -S 0.0.0.0:8892 -t /root/projects/canopi-app >/tmp/denah-harness.log 2>&1 &
```
Buka `http://187.77.143.121:8892/tests/rangka/denah_editor_harness.html`. Checklist tab **Support**:
1. Buka tab Support → 2 pasang input kelihatan: Kotak Lebar+Kolom X, Kotak Panjang+Kolom Y, plus hint saran 2 baris (Lebar & Panjang terpisah).
2. Mode Arah default "Grid 2 arah" → ke-4 input AKTIF (tak abu-abu).
3. Ganti Arah ke "1 arah horizontal" → pasangan Lebar (Kotak+Kolom X) jadi ABU-ABU/tak bisa diketik; pasangan Panjang tetap aktif.
4. Ganti Arah ke "1 arah vertikal" → sebaliknya (pasangan Panjang abu-abu, Lebar aktif).
5. Balik ke "Grid 2 arah" → ke-4 aktif lagi.
6. Ketik Kolom X = 10 (denah default Lebar 400cm) → Kotak Lebar (cm) otomatis jadi 40. Kolom Y & Kotak Panjang TAK ikut berubah.
7. Ketik Kotak Panjang = 75 langsung → Kolom Y otomatis ikut terhitung ulang.
8. Ketuk "Pakai saran" → ke-4 kolom terisi dari saran (target 100cm), termasuk Kolom X/Y ikut terhitung.
9. Ubah Lebar (tab Ukuran) → resize → kotak/kolom Lebar yang BELUM disentuh manual ikut update otomatis; kalau sudah pernah diketik manual di langkah 6, tetap diam (Kolom X tetap 10, Kotak Lebar ikut hitung ulang dari Lebar baru supaya kolomnya tetap 10).
10. Kanvas: kotak support kelihatan jadi persegi panjang (bukan cuma persegi) kalau Kotak Lebar ≠ Kotak Panjang.
11. Buat denah baru (Reset), buka lagi dari model lama (kalau ada cara load model lama di harness) atau panggil `ed.setModel({...tanpa kotakLebar/kotakPanjang...})` dari console browser → tak error, tampil dgn kotak default 100/100.
12. Undo/Redo, Gabungan Kotak, drag-pindah (Kelompok B), ortho-snap (Kelompok A) — semua tetap normal (regresi check).
13. Hitung Harga → total & rincian besi tetap benar sesuai kotak support yang baru.

- [ ] **Step 9: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): UI 2 pasang input Kotak/Kolom Lebar+Panjang, sinkron & nonaktif sesuai Arah (Kelompok C)"
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
Expected: GitHub Actions `deploy.yml` jalan, FTP ke Niagahoster, selesai ±1-2 menit.

- [ ] **Step 3: Checklist verifikasi Elvan (di `/rab-opsi`, blok Denah nyata, di HP)**

1. Buka blok Denah lama (yang sudah ada sebelum Kelompok C) → tampil identik seperti sebelumnya (kotak support tak berubah), tak ada error.
2. Buka tab Support → 2 pasang input (Kotak+Kolom, Lebar & Panjang) + hint saran 2 baris.
3. Ganti mode Arah → pasangan yang tak relevan jadi abu-abu otomatis.
4. Ketik jumlah Kolom (misal 10) → cm terhitung otomatis, kanvas ikut berubah bentuk kotak supportnya.
5. Ketik cm langsung → Kolom ikut terhitung otomatis.
6. "Pakai saran" → isi ke-4 kolom sekaligus.
7. Kotak support bisa jadi persegi panjang (Lebar ≠ Panjang) kalau memang diset beda.
8. Kotak tambahan dari "+ Tambah Kotak" ikut memakai pengaturan yang SAMA (1 pengaturan untuk seluruh denah, bukan terpisah).
9. Undo/Redo, drag-pindah besi (Kelompok B), ortho-snap+ribbon+zoom (Kelompok A) — semua tetap normal.
10. Simpan (autosave) → reload halaman → pengaturan kotak Lebar/Panjang yang baru tetap tersimpan.
11. Klik "Hitung Harga" → rincian & total besi benar sesuai kotak support terbaru.

- [ ] **Step 4: Update `MEMORI_PROYEK.md`**

Tandai item **#10 Kelompok C** selesai (kalau dikonfirmasi Elvan): saran kotak support 2 arah independen + input jumlah kolom. Catat kalau semua 3 kelompok (A/B/C) dari #10 sudah selesai. Commit dokumentasi terpisah dari kode.

---

## Ringkasan cakupan spec vs task

| Spec (`2026-07-17-denah-ui-kelompok-c-design.md`) | Task |
|---|---|
| §3.1 Data model (`kotakLebar`/`kotakPanjang`/`autoKotak*`, kolom tak disimpan, migrasi) | 3 |
| §3.2 Mesin hitung (`saranKotak` 1 desimal, `kotakFromKolom`/`kolomFromKotak`, `buildMembers` 2 arah) | 1 (fungsi murni) + 2 (buildMembers) |
| §3.3 UI (2 pasang input, tombol saran, auto per sumbu, nonaktif sesuai Arah) | 4 |
| §4 Alur pakai | 4, dirangkai lewat checklist manual Task 4 Step 8 & production Task 5 |
| §5 Testing (fungsi murni & buildMembers testable node; UI manual browser) | 1, 2, 3 (Node) — 4 (harness lokal) — 5 (production) |
| §6 Yang TIDAK berubah | Global Constraints (satu file, `scanX`/`scanY`/`combineBox` tak disentuh, 1 pengaturan utk seluruh denah) |

**Di luar plan ini:** Tidak ada — Kelompok C cuma 1 item (poin 6 dari 6 permintaan asli), sudah tercakup penuh.
