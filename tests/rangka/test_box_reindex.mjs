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
