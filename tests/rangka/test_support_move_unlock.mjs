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
