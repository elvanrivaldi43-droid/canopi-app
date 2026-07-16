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
