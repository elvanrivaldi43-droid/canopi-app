// FILE: tests/rangka/test_support_label.mjs
// Jalankan: node tests/rangka/test_support_label.mjs
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

// Persegi 400x300 searah jarum jam di koordinat layar (y ke bawah): (0,0)->(400,0)->(400,300)->(0,300)
const V = [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }];
// outwardNormal(V, a, b) -> vektor satuan arah LUAR polygon dari sisi a->b
check('sisi atas -> normal ke atas (y negatif)', DenahConv.outwardNormal(V, V[0], V[1]), { x: 0, y: -1 });
check('sisi kanan -> normal ke kanan', DenahConv.outwardNormal(V, V[1], V[2]), { x: 1, y: 0 });
check('sisi bawah -> normal ke bawah', DenahConv.outwardNormal(V, V[2], V[3]), { x: 0, y: 1 });
check('sisi kiri -> normal ke kiri', DenahConv.outwardNormal(V, V[3], V[0]), { x: -1, y: 0 });
// Winding kebalik (CCW) -> hasil HARUS tetap arah luar (deteksi pakai point-in-polygon, bukan asumsi winding)
const Vr = [...V].reverse();
check('winding kebalik: sisi bawah tetap normal ke bawah', DenahConv.outwardNormal(Vr, { x: 400, y: 300 }, { x: 0, y: 300 }), { x: 0, y: 1 });

// supportLabelText(fullText, shortText, lenSvg, selected) -> teks yang dipakai sesuai ruang
check('muat penuh', DenahConv.supportLabelText('S4 · 700', 'S4', 700, false), 'S4 · 700');
check('sempit -> nomor saja', DenahConv.supportLabelText('S4 · 38', 'S4', 40, false), 'S4');
check('super pendek -> kosong', DenahConv.supportLabelText('S4 · 38', 'S4', 12, false), '');
check('tersorot -> selalu penuh', DenahConv.supportLabelText('S4 · 38', 'S4', 12, true), 'S4 · 38');

process.exit(fail ? 1 : 0);
