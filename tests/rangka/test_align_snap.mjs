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

const TH = 16;

// --- findAlignSnap ---
// X dekat kandidat A (beda 5), Y jauh dari semua -> snap X ke A, Y tetap
check('snap X ke kandidat terdekat',
  DenahConv.findAlignSnap({ x: 105, y: 500 }, [{ x: 100, y: 300 }], TH),
  { x: 100, y: 500, guides: [{ axis: 'x', ref: { x: 100, y: 300 } }] });

// Dua kandidat, keduanya dalam threshold X -> pilih yang PALING DEKAT (bukan yang pertama di array)
check('pilih kandidat X terdekat, bukan urutan pertama',
  DenahConv.findAlignSnap({ x: 108, y: 500 }, [{ x: 95, y: 0 }, { x: 100, y: 0 }], TH),
  { x: 100, y: 500, guides: [{ axis: 'x', ref: { x: 100, y: 0 } }] });

// Tak ada kandidat dalam threshold -> tak berubah, guides kosong
check('tak snap kalau semua jauh',
  DenahConv.findAlignSnap({ x: 250, y: 250 }, [{ x: 100, y: 300 }], TH),
  { x: 250, y: 250, guides: [] });

// Pas di threshold (selisih == TH) -> tak snap (batas eksklusif, sama pola _orthoSnapToPoint)
check('tak snap tepat di threshold',
  DenahConv.findAlignSnap({ x: 116, y: 500 }, [{ x: 100, y: 300 }], TH),
  { x: 116, y: 500, guides: [] });

// X dan Y dua-duanya cocok (bisa beda kandidat) -> dua guide sekaligus
check('snap X dan Y dari kandidat berbeda',
  DenahConv.findAlignSnap({ x: 104, y: 306 }, [{ x: 100, y: 999 }, { x: 999, y: 300 }], TH),
  { x: 100, y: 300, guides: [{ axis: 'x', ref: { x: 100, y: 999 } }, { axis: 'y', ref: { x: 999, y: 300 } }] });

// --- collectAlignCandidates ---
const S1 = {
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  tiang: [{ x: 100, y: 100 }, { x: 300, y: 100 }],
  supportsManual: [{ a: { x: 50, y: 50 }, b: { x: 50, y: 150 } }],
};
const cands1 = DenahConv.collectAlignCandidates(S1, null);
check('candidates: 2 tiang + 3 titik support (a,b,tengah) + 4 titik tengah sisi frame = 9',
  cands1.length, 9);

// exclude tiang index 0 -> tiang itu hilang dari daftar, sisanya utuh (8)
check('exclude tiang sendiri',
  DenahConv.collectAlignCandidates(S1, { kind: 'tiang', i: 0 }).length, 8);

// exclude support index 0 -> 3 titik support itu (a,b,tengah) hilang (6)
check('exclude support sendiri',
  DenahConv.collectAlignCandidates(S1, { kind: 'sup', i: 0 }).length, 6);

// exclude box vertIdx [1,2] (sisi 1->2 sepenuhnya milik kotak) -> midpoint sisi 1->2 hilang,
// tapi sisi 0->1 dan 2->3 (cuma 1 ujung milik kotak) TETAP jadi kandidat (governing side)
const S2 = { verts: S1.verts, tiang: [], supportsManual: [] };
check('exclude box: sisi internal hilang, sisi governing tetap',
  DenahConv.collectAlignCandidates(S2, { kind: 'box', vertIdx: [1, 2] }).length, 3);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
