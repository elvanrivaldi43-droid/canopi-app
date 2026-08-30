// FILE: tests/rangka/test_support_irisan.mjs — jalankan: node tests/rangka/test_support_irisan.mjs
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../../public/js/denah-editor.js', import.meta.url), 'utf8');
(0, eval)(code);
const { DenahConv } = globalThis;
let fail = false;
const check = (n, got, exp) => { const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok?'PASS':'FAIL')+` — ${n}`+(ok?'':` (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`));
  if (!ok) fail = true; };

// Kotak 400x300, frame 5x10 (tapak 5), support 4x8 (tapak 4), orientasi berdiri.
const TAPAK = { 'Hollow 5x10': { l: 5, t: 10 }, 'Hollow 4x8': { l: 4, t: 8 } };
const base = () => ({
  verts: [{x:0,y:0},{x:400,y:0},{x:400,y:300},{x:0,y:300}],
  grid: 20, kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], balok: [], balokSeq: 1,
  tinggi: 300, matDefault: { frame: 'Hollow 5x10', support: 'Hollow 4x8', tiang: 'Hollow 5x10', balok: '' },
  matOverride: {}, combinedBoxes: [],
  supportsLocked: [
    { no: 1, axis: 'h', pos: 150 },            // horizontal tengah
    { no: 2, axis: 'v', pos: 200 },            // vertikal tengah
  ], lockSeq: 3, orientasi: 'berdiri',
});
const sup = (mem) => mem.filter(m => m.jenis === 'support').map(m => ({ nama: m.nama, panjang: m.panjang }));

// 1) Tanpa field baru -> PERSIS lama, kecuali nama locked kini 'S{no}' (peningkatan disengaja)
check('default dua arah menerus: dua jalur utuh',
  sup(DenahConv.buildMembers(base(), TAPAK)),
  [{ nama: 'S1', panjang: 400 }, { nama: 'S2', panjang: 300 }]);

// 2) H menerus, V putus: S2 pecah 2 ruas. Jarak as 150; ujung frame tapak 5 (2.5),
//    interior S1 tapak 4 (2) -> ruas = 150 - 2.5 - 2 = 145.5
const s2 = base(); s2.supMenerus = { h: true, v: false };
check('V putus terpecah dgn tapak beda ujung',
  sup(DenahConv.buildMembers(s2, TAPAK)),
  [{ nama: 'S1', panjang: 400 }, { nama: 'S2·1', panjang: 145.5 }, { nama: 'S2·2', panjang: 145.5 }]);

// 3) Override per jalur: bawaan V putus tapi entri no 2 dipaksa menerus
const s3 = base(); s3.supMenerus = { h: true, v: false }; s3.supportsLocked[1].menerus = true;
check('override per jalur menang atas bawaan',
  sup(DenahConv.buildMembers(s3, TAPAK)),
  [{ nama: 'S1', panjang: 400 }, { nama: 'S2', panjang: 300 }]);

// 4) Orientasi tidur: tapak pakai sisi besar (frame 10, support 8) -> 150-5-4 = 141
const s4 = base(); s4.supMenerus = { h: true, v: false }; s4.orientasi = 'tidur';
check('orientasi tidur pakai sisi besar',
  sup(DenahConv.buildMembers(s4, TAPAK)).slice(1),
  [{ nama: 'S2·1', panjang: 141 }, { nama: 'S2·2', panjang: 141 }]);

// 5) Tapak tak diketahui -> 0 + warning, as-ke-as
const s5 = base(); s5.supMenerus = { h: true, v: false };
const w5 = [];
check('tapak kosong: as-ke-as', sup(DenahConv.buildMembers(s5, {}, w5)).slice(1),
  [{ nama: 'S2·1', panjang: 150 }, { nama: 'S2·2', panjang: 150 }]);
check('warning tapak muncul', w5.length > 0, true);

// 6) putus x putus: dua-duanya dibiarkan utuh + konflik terdeteksi
const s6 = base(); s6.supMenerus = { h: false, v: false };
check('putus x putus tak saling memotong',
  sup(DenahConv.buildMembers(s6, TAPAK)),
  [{ nama: 'S1', panjang: 400 }, { nama: 'S2', panjang: 300 }]);
check('irisanKonflik menangkap pasangan', DenahConv.irisanKonflik(s6), [{ a: 1, b: 2 }]);
check('default tanpa konflik', DenahConv.irisanKonflik(base()), []);

// 7) EKUIVALENSI: model lama (tanpa supMenerus/orientasi/menerus, nama disamakan)
//    buildMembers TANPA argumen ekstra === dgn argumen null (geometri & panjang identik)
const lama = base(); delete lama.orientasi;
check('kompat: tanpa arg ekstra identik', DenahConv.buildMembers(lama), DenahConv.buildMembers(lama, null, null));

// 8) Ruas <= 0 dibuang + warning (dua pemotong sangat rapat)
const s8 = base(); s8.supMenerus = { h: true, v: false };
s8.supportsLocked = [ { no: 1, axis: 'h', pos: 149 }, { no: 2, axis: 'h', pos: 151 }, { no: 3, axis: 'v', pos: 200 } ];
const w8 = [];
const m8 = sup(DenahConv.buildMembers(s8, TAPAK, w8));
check('ruas tengah <=0 dibuang (2-2-2 tapak > jarak 2)', m8.filter(x => x.nama.startsWith('S3')).length, 2);
check('warning ruas <=0 muncul', w8.some(w => w.includes('S3')), true);

process.exit(fail ? 1 : 0);
