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

// "+ Sudut": titik baru harus jatuh di GARIS sisi, bukan di posisi tap mentah (laporan Elvan
// 22 Ags — tap meleset dikit dari garis horizontal bikin bentuk "paruh burung" nyeleneh).
const a = { x: 0, y: 0 }, b = { x: 400, y: 0 }; // sisi horizontal

// Tap PERSIS di garis -> hasil sama persis
check('tap persis di garis', DenahConv._closestOnSegment({ x: 150, y: 0 }, a, b), { x: 150, y: 0 });

// Tap meleset ke bawah garis -> Y ditarik balik ke 0 (nempel garis), X tetap
check('tap meleset dari garis -> nempel ke garis', DenahConv._closestOnSegment({ x: 150, y: 45 }, a, b), { x: 150, y: 0 });

// Tap di luar ujung kiri (x negatif) -> diklem ke titik a, bukan diekstrapolasi keluar sisi
check('tap lewat ujung kiri -> klem ke a', DenahConv._closestOnSegment({ x: -50, y: 10 }, a, b), { x: 0, y: 0 });

// Tap di luar ujung kanan (x > 400) -> diklem ke titik b
check('tap lewat ujung kanan -> klem ke b', DenahConv._closestOnSegment({ x: 500, y: -10 }, a, b), { x: 400, y: 0 });

// Sisi diagonal (bukan horizontal/vertikal) -> proyeksi tetap benar
const a2 = { x: 0, y: 0 }, b2 = { x: 300, y: 400 }; // panjang 500
check('sisi diagonal: tap di tengah proyeksi ke garis', DenahConv._closestOnSegment({ x: 300, y: 0 }, a2, b2), { x: 108, y: 144 });

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
