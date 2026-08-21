// Muat modul classic-script (globalThis.DenahConv) via read+eval — file browser tak pakai ESM export.
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../../public/js/denah-editor.js', import.meta.url), 'utf8');
(0, eval)(code);            // eval scope global → set globalThis.DenahConv
const { DenahConv } = globalThis;

let fail = false;
const check = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name} (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`);
  if (!ok) fail = true;
};

const checkNearPoint = (name, got, exp, eps = 1e-9) => {
  const ok = Math.abs(got.x - exp.x) <= eps && Math.abs(got.y - exp.y) <= eps;
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name} (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`);
  if (!ok) fail = true;
};

// ---- Task 1: denahOrigin/tiangFromOffset/tiangToOffset ----

// Persegi biasa: origin = kiri-depan (bottom-left bounding box)
const persegi = { verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }] };
check('persegi: origin kiri-depan (0,300)', DenahConv.denahOrigin(persegi), { x: 0, y: 300 });

// Bentuk L: bounding box tetap dipakai sebagai origin, bukan salah satu vertex L
const L = { verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 200 }, { x: 200, y: 200 }, { x: 200, y: 400 }, { x: 0, y: 400 }] };
check('L: origin kiri-depan (0,400)', DenahConv.denahOrigin(L), { x: 0, y: 400 });

// Koordinat negatif (denah bisa digeser jadi negatif di editor)
const neg = { verts: [{ x: -100, y: -50 }, { x: 300, y: -50 }, { x: 300, y: 250 }, { x: -100, y: 250 }] };
check('negatif: origin kiri-depan (-100,250)', DenahConv.denahOrigin(neg), { x: -100, y: 250 });

// Desimal
const desimal = { verts: [{ x: 12.5, y: 3.25 }, { x: 412.5, y: 3.25 }, { x: 412.5, y: 303.25 }, { x: 12.5, y: 303.25 }] };
check('desimal: origin kiri-depan (12.5,303.25)', DenahConv.denahOrigin(desimal), { x: 12.5, y: 303.25 });

// tiangFromOffset: X ke kanan, Y dari depan menuju belakang (naik di gambar)
check('fromOffset persegi (50,80)', DenahConv.tiangFromOffset(persegi, 50, 80), { x: 50, y: 220 });
check('fromOffset negatif (10,10)', DenahConv.tiangFromOffset(neg, 10, 10), { x: -90, y: 240 });
check('fromOffset desimal (0.5,0.25)', DenahConv.tiangFromOffset(desimal, 0.5, 0.25), { x: 13, y: 303 });

// tiangToOffset: X dari kiri, Y dari depan menuju belakang
check('toOffset persegi titik (50,220)', DenahConv.tiangToOffset(persegi, { x: 50, y: 220 }), { dx: 50, dy: 80 });
check('toOffset negatif (-90,240)', DenahConv.tiangToOffset(neg, { x: -90, y: 240 }), { dx: 10, dy: 10 });

// Round-trip point -> offset -> point, untuk beberapa titik termasuk negatif & desimal
[persegi, L, neg, desimal].forEach((S, si) => {
  [{ x: 33.3, y: -12.7 }, { x: 0, y: 0 }, { x: -500, y: 999.9 }].forEach((pt, pi) => {
    const off = DenahConv.tiangToOffset(S, pt);
    const back = DenahConv.tiangFromOffset(S, off.dx, off.dy);
    checkNearPoint(`round-trip S${si} pt${pi}`, back, pt);
  });
});

// ---- Task 2: parseCmValue — parsing angka input panel tiang numerik ----
// Pure function, tanpa DOM (jsdom tidak terpasang) — dipakai commit X/Y saat blur/Enter/change,
// BUKAN per keystroke. Kembalikan number finite, atau null kalau kosong/NaN/non-finite (UI yang
// menolak mutasi & menampilkan pesan berdasarkan null ini).
check('parseCmValue: koma jadi desimal', DenahConv.parseCmValue('12,5'), 12.5);
check('parseCmValue: titik desimal biasa', DenahConv.parseCmValue('12.5'), 12.5);
check('parseCmValue: integer', DenahConv.parseCmValue('50'), 50);
check('parseCmValue: negatif diterima (offset relatif origin)', DenahConv.parseCmValue('-30,25'), -30.25);
check('parseCmValue: spasi di pinggir ditrim', DenahConv.parseCmValue('  40  '), 40);
check('parseCmValue: string kosong -> null', DenahConv.parseCmValue(''), null);
check('parseCmValue: hanya spasi -> null', DenahConv.parseCmValue('   '), null);
check('parseCmValue: bukan angka -> null', DenahConv.parseCmValue('abc'), null);
check('parseCmValue: null -> null', DenahConv.parseCmValue(null), null);
check('parseCmValue: undefined -> null', DenahConv.parseCmValue(undefined), null);
check('parseCmValue: NaN literal -> null', DenahConv.parseCmValue('NaN'), null);
check('parseCmValue: Infinity -> null (non-finite ditolak)', DenahConv.parseCmValue('Infinity'), null);
check('parseCmValue: angka+sampah -> null (jangan terima parsial)', DenahConv.parseCmValue('12,5cm'), null);
check('parseCmValue: 0 valid (bukan falsy-reject)', DenahConv.parseCmValue('0'), 0);

// ---- Task 2: tambah tiang lewat offset X/Y hasil parseCmValue (state-coordinate untuk panel) ----
// UI memakai parseCmValue(input) -> dx/dy lalu DenahConv.tiangFromOffset(S, dx, dy) sebelum
// clampTiang() di level UI (clamp sengaja tak diuji di sini, murni kontrak Task 1 + Task 2).
{
  const S = { verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }] };
  const dx = DenahConv.parseCmValue('50,5');
  const dy = DenahConv.parseCmValue('80');
  checkNearPoint('panel: offset koma dari kiri-depan -> titik tiang', DenahConv.tiangFromOffset(S, dx, dy), { x: 50.5, y: 220 });
}

// ---- Task 3: ghost preview murni — tidak boleh mutasi state atau menambah member ----
{
  const S = {
    verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
    grid: 20, target: 100, kotak: 100, autoKotak: true, arah: '2',
    supportsManual: [], removed: {}, tiang: [], tinggi: 300,
    matDefault: { frame: '', support: '', tiang: '' }, matOverride: {}, combinedBoxes: [],
  };
  const before = JSON.stringify(S);
  const inside = DenahConv.tiangPreviewState(S, 50, 80, { x0: 0, y0: 0, x1: 400, y1: 300 });
  check('preview valid: raw dari kiri-depan', inside.raw, { x: 50, y: 220 });
  check('preview valid: titik tampil sama', inside.point, { x: 50, y: 220 });
  check('preview valid: tidak di-clamp', inside.clamped, false);

  const outside = DenahConv.tiangPreviewState(S, 450, -20, { x0: 0, y0: 0, x1: 400, y1: 300 });
  check('preview luar: raw dipertahankan', outside.raw, { x: 450, y: 320 });
  check('preview luar: tampilan di batas terdekat', outside.point, { x: 400, y: 300 });
  check('preview luar: status clamped', outside.clamped, true);
  check('preview tidak memutasi S', JSON.stringify(S), before);
  check('preview tidak menambah tiang/member', S.tiang.length, 0);
}

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
