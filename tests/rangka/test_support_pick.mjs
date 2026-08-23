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

// ── describeLockedSupport: teks baris panel ──
const Sd = { verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }] };
check('describe h', DenahConv.describeLockedSupport(Sd, { no: 1, axis: 'h', pos: 149, aktif: true }), 'datar · 149cm dari atas');
check('describe v', DenahConv.describeLockedSupport(Sd, { no: 2, axis: 'v', pos: 100, aktif: true }), 'tegak · 100cm dari kiri');
check('describe manual', DenahConv.describeLockedSupport(Sd, { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 240, y: 0 }, aktif: true }), 'manual · 240cm');
check('describe di luar frame', DenahConv.describeLockedSupport(Sd, { no: 4, axis: 'h', pos: 350, aktif: true }), 'datar · 350cm dari atas (di luar frame)');

process.exit(fail ? 1 : 0);
