// FILE: tests/rangka/test_balok_melintang.mjs
// Jalankan: node tests/rangka/test_balok_melintang.mjs
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

const base = () => ({
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  kotak: 100, arah: 'h', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X', balok: 'WF 100' }, matOverride: {},
});

// ── resolveBalokEndpoint ──
{
  const S = base(); S.tiang = [{ x: 10, y: 20 }, { x: 100, y: 200 }];
  check('endpoint tiang', DenahConv.resolveBalokEndpoint(S, { t: 1 }), { x: 100, y: 200 });
  check('endpoint titik bebas (disalin, bukan referensi)', DenahConv.resolveBalokEndpoint(S, { p: { x: 5, y: 6 } }), { x: 5, y: 6 });
  check('endpoint tiang tak ada -> null', DenahConv.resolveBalokEndpoint(S, { t: 9 }), null);
}

// ── buildMembers cabang balok ──
{
  const S = base(); S.tiang = [{ x: 0, y: 0 }, { x: 300, y: 0 }];
  S.balok = [{ no: 1, a: { t: 0 }, b: { t: 1 }, material: 'WF 100' }];
  S.balokSeq = 2;
  const bl = DenahConv.buildMembers(S).filter(m => m.jenis === 'balok');
  check('balok member: id/panjang/material', [bl.length, bl[0].id, bl[0].panjang, bl[0].material], [1, 'B1', 300, 'WF 100']);
  // Ref patah -> dilewati diam-diam, sisanya tetap ada
  S.balok = [
    { no: 5, a: { t: 9 }, b: { t: 1 }, material: 'WF 100' },
    { no: 6, a: { t: 0 }, b: { p: { x: 100, y: 100 } }, material: 'WF 125' },
  ];
  const bl2 = DenahConv.buildMembers(S).filter(m => m.jenis === 'balok');
  check('ref patah dilewati; sisanya jalan', bl2.map(m => m.id), ['B6']);
  check('balok titik bebas: panjang benar', bl2[0].panjang, Math.round(Math.hypot(100, 100)));
}

// ── buildMembers cabang balok baca matOverride (Ganti besi sebelumnya dead state) ──
{
  const S = base(); S.tiang = [{ x: 0, y: 0 }, { x: 300, y: 0 }];
  S.balok = [{ no: 1, a: { t: 0 }, b: { t: 1 }, material: 'WF 100' }];
  S.matOverride = { B1: 'WF 125' };
  const bl3 = DenahConv.buildMembers(S).filter(m => m.jenis === 'balok');
  check('matOverride balok kebaca (bukan b.material mentah)', bl3[0].material, 'WF 125');
}

// ── cascadeTiangRemoval ──
{
  const S = base();
  S.tiang = [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 200, y: 0 }];
  S.balok = [
    { no: 1, a: { t: 0 }, b: { t: 2 }, material: 'WF 100' },       // ke tiang yg dihapus: ujung A dibekukan
    { no: 2, a: { t: 1 }, b: { t: 2 }, material: 'WF 100' },       // ref ke t2 -> shift ke t1 (setelah t0 hilang, t1->t0, t2->t1)
    { no: 3, a: { p: { x: 5, y: 5 } }, b: { t: 1 }, material: 'WF 100' }, // titik bebas tak tersentuh; ref t1 -> shift t0
  ];
  S.balokSeq = 4;
  const p = DenahConv.cascadeTiangRemoval(S, 0);
  check('cascade: tiang tersisa', p.tiang, [{ x: 100, y: 0 }, { x: 200, y: 0 }]);
  check('cascade: B1 ujung A dibekukan ke posisi tiang lama, ujung B di-shift', p.balok[0], { no: 1, a: { p: { x: 0, y: 0 } }, b: { t: 1 }, material: 'WF 100' });
  check('cascade: B2 shift kedua ujung', p.balok[1], { no: 2, a: { t: 0 }, b: { t: 1 }, material: 'WF 100' });
  check('cascade: B3 titik bebas utuh, ref di-shift', p.balok[2], { no: 3, a: { p: { x: 5, y: 5 } }, b: { t: 0 }, material: 'WF 100' });
  check('cascade: balokSeq tak berubah (nomor stabil)', p.balokSeq, 4);
  check('cascade: S asal tak dimutasi', S.tiang.length, 3);
}
{
  // Balok yg KEDUA ujungnya ref ke tiang yg dihapus -> kedua ujungnya jadi titik bebas ke posisi lama.
  const S = base();
  S.tiang = [{ x: 0, y: 0 }, { x: 200, y: 0 }];
  S.balok = [{ no: 1, a: { t: 0 }, b: { t: 0 }, material: 'WF' }]; // sengaja aneh: A=B=t0
  S.balokSeq = 2;
  const p = DenahConv.cascadeTiangRemoval(S, 0);
  check('cascade: kedua ujung ref tiang hilang -> dua-duanya bebas',
    [p.balok[0].a, p.balok[0].b], [{ p: { x: 0, y: 0 } }, { p: { x: 0, y: 0 } }]);
}

// ── hitungBatangWF ──
{
  const mem = [
    { jenis: 'balok', panjang: 480, material: 'WF 100' },
    { jenis: 'balok', panjang: 720, material: 'WF 100' },   // total WF 100: 1200 -> 2 batang
    { jenis: 'balok', panjang: 550, material: 'WF 125' },   // WF 125: 550 -> 1 batang
    { jenis: 'balok', panjang: 300, material: 'Hollow 5x10' }, // non-WF diabaikan
    { jenis: 'frame', panjang: 900, material: 'WF 100' },      // non-balok diabaikan
  ];
  check('batang WF', DenahConv.hitungBatangWF(mem), { 'WF 100': 2, 'WF 125': 1 });
  check('batang standar boleh dikustom', DenahConv.hitungBatangWF([{ jenis: 'balok', panjang: 500, material: 'WF 100' }], 400), { 'WF 100': 2 });
  check('list balok kosong -> objek kosong', DenahConv.hitungBatangWF([]), {});
}

// ── kompat: model lama (tanpa S.balok) TIDAK berubah ──
{
  const S = base();
  const mem = DenahConv.buildMembers(S).map(m => m.jenis);
  check('kompat: fase pratinjau utuh tanpa balok', mem.includes('balok'), false);
}

process.exit(fail ? 1 : 0);
