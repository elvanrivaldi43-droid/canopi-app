# Denah Support — Garis Numerik & Pecah Jadi Manual — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dua perbaikan hasil validasi Elvan 23 Ags atas fitur Support ID Stabil (sudah LIVE): (1) tambah support manual dengan KETIK posisi + ghost preview (ganti tap-2-titik buta yang bikin garis nyasar di HP), (2) tombol "Pecah jadi manual" untuk membuang potongan jalur grid yang tak diperlukan (kasus S2 kepotong coakan, potongan kanan 134cm ikut kehitung padahal tak dipakai).

**Architecture:** Satu fungsi murni bersama `jalurSegments` (posisi → potongan-potongan yang berhenti di perpotongan frame — TIDAK menyeberangi coakan, permintaan eksplisit Elvan) menjadi dasar dua fitur: `manualEntriesFromJalur` (form ketik angka) dan `splitLockedGrid` (pecah entri grid). Hasil dua-duanya = entri MANUAL terpisah per potongan (nomor stabil lanjutan `lockSeq`, bisa dihapus satu-satu, TIDAK ikut frame lagi — konsekuensi sadar yang disetujui). Semua di `public/js/denah-editor.js`, fase terkunci saja.

**Tech Stack:** Vanilla JS classic script IIFE, test node `tests/rangka/*.mjs` pola read+eval.

## Global Constraints

- `public/js/denah-editor.js` classic script IIFE — **DILARANG `export`/`import`**; ekspos via `globalThis`.
- File test baru WAJIB terdaftar di `tests/guardrail/manifest.json` DI COMMIT YANG SAMA (runner `"node"`, tiru entri `tests/rangka/test_support_lock.mjs`).
- Semua mutasi model lewat `this.pushUndo()` SEBELUM mutasi.
- Fase pratinjau & semua test lama tidak boleh berubah hasil; `php scripts/canopi-check` hijau sebelum commit.
- Komentar bahasa Indonesia gaya file; jangan commit `public/hot`.
- Konvensi posisi relatif = sama `describeLockedSupport`: datar = `bb.y0 + cm` ("dari atas"), tegak = `bb.x0 + cm` ("dari kiri").

## File Structure

- Modify: `public/js/denah-editor.js` — `DenahConv` (+3 fungsi murni), `renderSupportPanel` (form + tombol pecah), method preview baru di kelas.
- Create: `tests/rangka/test_support_jalur_manual.mjs`.
- Modify: `tests/guardrail/manifest.json`.

**Bentuk data (sudah ada, dipakai di sini):** `S.supportsLocked[] = {no, axis:'h'|'v', pos, aktif} | {no, manual:true, a:{x,y}, b:{x,y}, aktif}`; `S.lockSeq` = nomor berikutnya; id member/matOverride = `'SL'+no`.

---

### Task 1: Fungsi murni `jalurSegments` + `manualEntriesFromJalur` + `splitLockedGrid`

**Files:**
- Modify: `public/js/denah-editor.js` — objek `DenahConv`, setelah `describeLockedSupport`
- Create: `tests/rangka/test_support_jalur_manual.mjs`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: `scanX`, `scanY`, `bbox` (sudah ada di IIFE).
- Produces (dipakai Task 2):
  - `DenahConv.jalurSegments(S, axis, pos)` → `[{a:{x,y}, b:{x,y}}, ...]` potongan pada posisi absolut `pos`, urut scan; `[]` kalau tak memotong frame.
  - `DenahConv.manualEntriesFromJalur(S, axis, cmRel)` → `{entries: [{no, manual:true, a, b, aktif:true}...], lockSeq}` atau `null` (cmRel bukan angka > 0). MURNI, nomor mulai `S.lockSeq`.
  - `DenahConv.splitLockedGrid(S, no)` → patch `{supportsLocked, lockSeq, matOverride}` (entri grid `no` diganti in-place oleh entri manual per potongan, override `SL{no}` DISALIN ke tiap potongan lalu key lama dihapus, `aktif` diwarisi) atau `null` (no bukan entri grid / 0 potongan). MURNI.

- [ ] **Step 1: Tulis test yang gagal** — buat `tests/rangka/test_support_jalur_manual.mjs`:

```js
// FILE: tests/rangka/test_support_jalur_manual.mjs
// Jalankan: node tests/rangka/test_support_jalur_manual.mjs
// Garis support numerik + pecah jadi manual (follow-up validasi Elvan 23 Ags):
// potongan BERHENTI di perpotongan frame, tak menyeberangi coakan.
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

const rect = () => ({
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
});
// Bentuk coakan: notch di tengah atas (y=100..300 kiri 0..200 dan kanan 400..600 nyambung bawah)
const notch = () => ({ ...rect(),
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 400, y: 300 },
          { x: 400, y: 100 }, { x: 200, y: 100 }, { x: 200, y: 300 }, { x: 0, y: 300 }],
});

// ── jalurSegments ──
check('persegi h: 1 potongan penuh', DenahConv.jalurSegments(rect(), 'h', 150),
  [{ a: { x: 0, y: 150 }, b: { x: 400, y: 150 } }]);
check('persegi v: 1 potongan penuh', DenahConv.jalurSegments(rect(), 'v', 100),
  [{ a: { x: 100, y: 0 }, b: { x: 100, y: 300 } }]);
check('coakan h: 2 potongan, berhenti di frame', DenahConv.jalurSegments(notch(), 'h', 150),
  [{ a: { x: 0, y: 150 }, b: { x: 200, y: 150 } }, { a: { x: 400, y: 150 }, b: { x: 600, y: 150 } }]);
check('di luar frame -> []', DenahConv.jalurSegments(rect(), 'h', 999), []);

// ── manualEntriesFromJalur: relatif tepi, nomor dari lockSeq ──
{
  const S = notch(); S.supportsLocked = []; S.lockSeq = 7;
  const r = DenahConv.manualEntriesFromJalur(S, 'h', 150); // bb.y0=0 -> pos 150
  check('numerik: 2 entri manual nomor 7,8', r.entries.map(e => [e.no, e.manual, e.aktif]),
    [[7, true, true], [8, true, true]]);
  check('numerik: ujung potongan pertama', r.entries[0].a, { x: 0, y: 150 });
  check('numerik: lockSeq maju ke 9', r.lockSeq, 9);
  check('numerik: S tak dimutasi', S.lockSeq, 7);
}
check('numerik: cm 0 -> null (nempel frame = duplikat frame)',
  DenahConv.manualEntriesFromJalur({ ...rect(), supportsLocked: [], lockSeq: 1 }, 'h', 0), null);
check('numerik: NaN -> null',
  DenahConv.manualEntriesFromJalur({ ...rect(), supportsLocked: [], lockSeq: 1 }, 'h', NaN), null);
{
  const S = rect(); S.supportsLocked = []; S.lockSeq = 1;
  check('numerik: di luar frame -> entries kosong (bukan null)',
    DenahConv.manualEntriesFromJalur(S, 'v', 999).entries, []);
}

// ── splitLockedGrid: kasus S2 nyata — buang potongan tak terpakai ──
{
  const S = notch();
  S.supportsLocked = [
    { no: 2, axis: 'h', pos: 150, aktif: true },
    { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 10, y: 0 }, aktif: true },
  ];
  S.lockSeq = 4; S.matOverride = { 'SL2': 'BesiA', 'SL3': 'BesiB' };
  const p = DenahConv.splitLockedGrid(S, 2);
  check('pecah: entri grid diganti 2 manual di posisi yang sama (in-place)',
    p.supportsLocked.map(e => [e.no, !!e.manual]), [[4, true], [5, true], [3, true]]);
  check('pecah: ujung potongan kedua', p.supportsLocked[1].a, { x: 400, y: 150 });
  check('pecah: override disalin ke tiap potongan, key lama hilang',
    [p.matOverride['SL4'], p.matOverride['SL5'], 'SL2' in p.matOverride, p.matOverride['SL3']],
    ['BesiA', 'BesiA', false, 'BesiB']);
  check('pecah: lockSeq maju ke 6', p.lockSeq, 6);
  check('pecah: S tak dimutasi', S.supportsLocked.length, 2);
}
{
  const S = rect();
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 150, aktif: false }];
  S.lockSeq = 2; 
  check('pecah: entri nonaktif -> potongan mewarisi nonaktif',
    DenahConv.splitLockedGrid(S, 1).supportsLocked.map(e => e.aktif), [false]);
  check('pecah: no manual -> null', DenahConv.splitLockedGrid({ ...S, supportsLocked: [{ no: 1, manual: true, a: { x: 0, y: 0 }, b: { x: 1, y: 1 }, aktif: true }] }, 1), null);
  check('pecah: no tak ada -> null', DenahConv.splitLockedGrid(S, 99), null);
}
{
  const S = rect();
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 999, aktif: true }]; S.lockSeq = 2;
  check('pecah: jalur di luar frame (0 potongan) -> null', DenahConv.splitLockedGrid(S, 1), null);
}

process.exit(fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `node tests/rangka/test_support_jalur_manual.mjs` → FAIL.

- [ ] **Step 3: Implementasi** — di `DenahConv` setelah `describeLockedSupport`:

```js
  // Potongan jalur (axis+pos absolut) dari polygon SAAT INI. Tiap potongan BERHENTI di
  // perpotongan frame — TIDAK menyeberangi coakan (keputusan Elvan 23 Ags: potongan yang
  // tak diperlukan harus bisa dibuang sendiri-sendiri). Dasar bersama utk form "+ Garis
  // support" numerik dan "Pecah jadi manual".
  jalurSegments(S, axis, pos) {
    const V = S.verts, out = [];
    if (axis === 'h') {
      const xs = scanX(V, pos);
      for (let s = 0; s + 1 < xs.length; s += 2) out.push({ a: { x: xs[s], y: pos }, b: { x: xs[s + 1], y: pos } });
    } else {
      const ys = scanY(V, pos);
      for (let s = 0; s + 1 < ys.length; s += 2) out.push({ a: { x: pos, y: ys[s] }, b: { x: pos, y: ys[s + 1] } });
    }
    return out;
  },
  // Entri manual dari posisi KETIK (cm relatif tepi atas utk datar / kiri utk tegak — konvensi
  // sama describeLockedSupport). Satu entri per potongan (nomor lanjut lockSeq). MURNI.
  // cmRel harus > 0: 0 = nempel tepi frame = duplikat batang frame, tolak.
  manualEntriesFromJalur(S, axis, cmRel) {
    if (!Number.isFinite(cmRel) || cmRel <= 0) return null;
    const bb = bbox(S.verts);
    const pos = axis === 'h' ? bb.y0 + cmRel : bb.x0 + cmRel;
    let seq = S.lockSeq > 0 ? S.lockSeq : 1;
    const entries = DenahConv.jalurSegments(S, axis, pos)
      .map(sg => ({ no: seq++, manual: true, a: sg.a, b: sg.b, aktif: true }));
    return { entries, lockSeq: seq };
  },
  // "Pecah jadi manual": entri grid `no` diganti (in-place di daftar) oleh entri manual per
  // potongan — override besi disalin ke tiap potongan, aktif diwarisi. Setelah ini potongan
  // tak lagi ikut frame (konsekuensi sadar). MURNI; null kalau no bukan grid / 0 potongan.
  splitLockedGrid(S, no) {
    const list = (S.supportsLocked || []);
    const idx = list.findIndex(e => !e.manual && e.no === no);
    if (idx < 0) return null;
    const e = list[idx];
    const segs = DenahConv.jalurSegments(S, e.axis, e.pos);
    if (!segs.length) return null;
    const mo = { ...(S.matOverride || {}) };
    let seq = S.lockSeq > 0 ? S.lockSeq : 1;
    const news = segs.map(sg => {
      const n2 = seq++;
      if (mo['SL' + no] != null) mo['SL' + n2] = mo['SL' + no];
      return { no: n2, manual: true, a: sg.a, b: sg.b, aktif: e.aktif !== false };
    });
    delete mo['SL' + no];
    const out = list.slice();
    out.splice(idx, 1, ...news);
    return { supportsLocked: out, lockSeq: seq, matOverride: mo };
  },
```

- [ ] **Step 4: Test + regresi** — `node tests/rangka/test_support_jalur_manual.mjs && for f in tests/rangka/test_*.mjs; do node $f || exit 1; done` → semua PASS.

- [ ] **Step 5: Manifest + guardrail** — daftarkan test baru di `tests/guardrail/manifest.json`; `php scripts/canopi-check`.

- [ ] **Step 6: Commit**

```bash
git add public/js/denah-editor.js tests/rangka/test_support_jalur_manual.mjs tests/guardrail/manifest.json
git commit -m "feat(denah): jalurSegments + manualEntriesFromJalur + splitLockedGrid (potongan berhenti di frame)"
```

---

### Task 2: UI panel — form "+ Garis support" numerik + ghost preview + tombol "Pecah jadi manual"

**Files:**
- Modify: `public/js/denah-editor.js` — `renderSupportPanel` (cabang terkunci), method baru `drawSupJalurPreview`/`clearSupJalurPreview` di kelas `DenahEditor`

**Interfaces:**
- Consumes: `DenahConv.jalurSegments/manualEntriesFromJalur/splitLockedGrid` (Task 1), `DenahConv.parseCmValue`, `this.selSup/supPanelOpen/lockSeq`, pola preview `drawTiangPreview` (SVG dashed, `pointer-events:none`, dibersihkan tiap render).
- Produces: form panel `sjAxis/sjPos/sjTambah/sjBatal`, tombol `slPecah` di baris melebar entri grid.

- [ ] **Step 1: Form di `renderSupportPanel` cabang terkunci** — setelah `rows` (sebelum tutup innerHTML), tambah blok form (hanya saat `this.supPanelOpen`):

```js
    const formTambah = !this.supPanelOpen ? '' :
      `<div class="de-tiang-item" style="border-bottom:0">
        <div class="de-tiang-head"><b style="font-size:12px">+ Garis support (ketik posisi)</b></div>
        <div class="de-tiang-fields">
          <label>Arah<select data-role="sjAxis"><option value="h">datar</option><option value="v">tegak</option></select></label>
          <label><span data-role="sjLbl">cm dari atas</span><input type="text" inputmode="decimal" data-role="sjPos"></label>
          <div class="de-tiang-actions"><span class="de-mini de-tiang-apply" data-role="sjTambah">Tambah</span><span class="de-mini" data-role="sjBatal">Batal</span></div>
        </div>
      </div>`;
    panel.innerHTML = head + `<div data-role="slMsg" ...></div>` + rows + formTambah;
```

Ganti tombol Hapus di baris melebar jadi: manual → Hapus (seperti sekarang), grid → tombol baru:

```js
          ${e2.manual ? '<span class="de-mini" data-role="slHapus">Hapus</span>'
                      : '<span class="de-mini" data-role="slPecah">Pecah jadi manual</span>'}
```

- [ ] **Step 2: Wiring form + preview** — di bagian wiring `renderSupportPanel` cabang terkunci:

```js
    const sjAxis = this._q('[data-role=sjAxis]'), sjPos = this._q('[data-role=sjPos]');
    if (sjAxis && sjPos) {
      const updLbl = () => { this._q('[data-role=sjLbl]').textContent = sjAxis.value === 'h' ? 'cm dari atas' : 'cm dari kiri'; };
      const updPreview = () => {
        const cmv = DenahConv.parseCmValue(sjPos.value);
        const r = cmv != null ? DenahConv.manualEntriesFromJalur(this.S, sjAxis.value, cmv) : null;
        this.drawSupJalurPreview(r ? r.entries : []);
        this._q('[data-role=slMsg]').textContent =
          (cmv != null && r && !r.entries.length) ? 'Posisi di luar frame — tak ada garis.' : '';
      };
      sjAxis.onchange = () => { updLbl(); updPreview(); };
      sjPos.oninput = updPreview;
      this._q('[data-role=sjBatal]').onclick = () => { sjPos.value = ''; this.drawSupJalurPreview([]); this._q('[data-role=slMsg]').textContent = ''; };
      this._q('[data-role=sjTambah]').onclick = () => {
        const cmv = DenahConv.parseCmValue(sjPos.value);
        const r = cmv != null ? DenahConv.manualEntriesFromJalur(this.S, sjAxis.value, cmv) : null;
        if (!r) { this._q('[data-role=slMsg]').textContent = 'Isi posisi dengan angka > 0.'; return; }
        if (!r.entries.length) { this._q('[data-role=slMsg]').textContent = 'Posisi di luar frame — tak ada garis.'; return; }
        this.pushUndo();
        this.S.supportsLocked.push(...r.entries);
        this.S.lockSeq = r.lockSeq;
        this.selSup = r.entries[0].no;
        this.setHint(r.entries.length > 1 ? `${r.entries.length} potongan ditambah (berhenti di frame) — hapus yang tak perlu dari panel.` : '');
        this.render();
      };
    }
    const pecahBtn = this._q('[data-role=slPecah]');
    if (pecahBtn) pecahBtn.onclick = () => {
      const p = DenahConv.splitLockedGrid(this.S, this.selSup);
      if (!p) return;
      this.pushUndo();
      const firstNo = this.S.lockSeq > 0 ? this.S.lockSeq : 1;
      Object.assign(this.S, p);
      this.selSup = firstNo;
      this.setHint('Jalur dipecah jadi potongan manual — tiap potongan kini bisa dihapus sendiri (tak ikut frame lagi).');
      this.render();
    };
```

- [ ] **Step 3: Method preview** — di kelas `DenahEditor` dekat `drawTiangPreview` (pola sama: gambar langsung ke SVG tanpa render, `pointer-events:none`; render() otomatis membersihkannya karena svg dibangun ulang):

```js
  // Ghost preview garis support numerik (pola sama drawTiangPreview): dashed cyan per potongan.
  // entries [] / tombol Batal = hapus preview.
  drawSupJalurPreview(entries) {
    const svg = this._q('.de-canvas svg');
    if (!svg) return;
    const old = svg.querySelector('[data-sup-preview]');
    if (old) old.remove();
    if (!entries || !entries.length) return;
    const NS = 'http://www.w3.org/2000/svg';
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-sup-preview', '1');
    g.setAttribute('style', 'pointer-events:none');
    entries.forEach(e => {
      const ln = document.createElementNS(NS, 'line');
      [['x1', this.PAD + e.a.x * this.SC], ['y1', this.PAD + e.a.y * this.SC],
       ['x2', this.PAD + e.b.x * this.SC], ['y2', this.PAD + e.b.y * this.SC]].forEach(([k, v]) => ln.setAttribute(k, v));
      ln.setAttribute('stroke', '#22d3ee'); ln.setAttribute('stroke-width', '3'); ln.setAttribute('stroke-dasharray', '6,4');
      g.appendChild(ln);
    });
    svg.appendChild(g);
  }
```

- [ ] **Step 4: Verifikasi** — `node --check public/js/denah-editor.js && for f in tests/rangka/test_*.mjs; do node $f || exit 1; done && php scripts/canopi-check` → semua PASS. Cek `git status`.

- [ ] **Step 5: Commit**

```bash
git add public/js/denah-editor.js
git commit -m "feat(denah): form + Garis support numerik dgn ghost preview + tombol Pecah jadi manual"
```

---

## Self-Review

- Keputusan Elvan tercakup: potongan berhenti di frame (`jalurSegments` per pasangan scan) — Task 1; potongan tak terpakai bisa dihapus sendiri (entri manual terpisah) — Task 1+2; ketik angka + preview ganti tap buta — Task 2. Konsekuensi "manual tak ikut frame" didokumentasikan di komentar + hint.
- Konsistensi tipe: `manualEntriesFromJalur`/`splitLockedGrid` return shape dipakai Task 2 persis; `selSup` di-set ke nomor entri pertama; `firstNo` diambil SEBELUM `Object.assign` (lockSeq berubah setelahnya).
- Tap-2-titik lama TIDAK dihapus (fallback garis miring) — tak ada perubahan di `bindSvg`.
