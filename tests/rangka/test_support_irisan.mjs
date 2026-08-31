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

// 7) KOMPAT MUNDUR — golden snapshot 4 model LAMA (tanpa supMenerus/orientasi/menerus).
//    Angka di bawah BUKAN karangan: diambil dari denah-editor.js versi SEBELUM Task 3
//    (commit 8f8cfa1^) lalu dibandingkan member-per-member (id/jenis/panjang/material/geom) —
//    keempatnya identik. Satu-satunya perbedaan yang disengaja: nama member support TERKUNCI
//    dulu 'S' polos, kini 'S{no}'. Kalau salah satu angka di sini berubah, post-step irisan
//    bocor ke model lama — itu regresi harga, bukan sekadar test rewel.
const MD = { frame: 'Hollow 5x10', support: 'Hollow 4x8', tiang: 'Hollow 5x10', balok: 'WF 100' };
const nm = (mem) => mem.map(m => m.nama + ':' + m.panjang);
const golden = [
  ['A pratinjau kotak 2 arah + tiang', {
    verts: [{x:0,y:0},{x:400,y:0},{x:400,y:300},{x:0,y:300}],
    grid:20, kotak:100, arah:'2', supportsManual:[], removed:{}, tiang:[{x:0,y:300},{x:400,y:300}],
    balok:[], balokSeq:1, tinggi:250, matDefault:MD, matOverride:{}, combinedBoxes:[],
  }, ["F1:400","F2:300","F3:400","F4:300","S:400","S:400","S:300","S:300","S:300","T1:250","T2:250"]],
  ['B pratinjau bentuk L + manual miring + removed', {
    verts: [{x:0,y:0},{x:600,y:0},{x:600,y:200},{x:300,y:200},{x:300,y:400},{x:0,y:400}],
    grid:20, kotak:150, arah:'h', supportsManual:[{a:{x:50,y:50},b:{x:250,y:350}}], removed:{'Sh_0_0':true},
    tiang:[], balok:[], balokSeq:1, tinggi:300, matDefault:MD, matOverride:{'F2':'Hollow 4x4'}, combinedBoxes:[],
  }, ["F1:600","F2:200","F3:300","F4:200","F5:300","F6:400","S:300","S:361"]],
  ['C terkunci grid + manual + balok + entri nonaktif', {
    verts: [{x:0,y:0},{x:500,y:0},{x:500,y:350},{x:0,y:350}],
    grid:20, kotak:100, arah:'2', supportsManual:[], removed:{},
    tiang:[{x:0,y:350},{x:500,y:350}], balok:[{no:1,a:{t:0},b:{t:1},material:'WF 150'}], balokSeq:2,
    tinggi:280, matDefault:MD, matOverride:{'SL2':'Hollow 4x4'}, combinedBoxes:[],
    supportsLocked:[{no:1,axis:'h',pos:120},{no:2,axis:'v',pos:250},
                    {no:3,manual:true,a:{x:60,y:60},b:{x:60,y:300},aktif:true},
                    {no:4,axis:'h',pos:240,aktif:false}], lockSeq:5,
  }, ["F1:500","F2:350","F3:500","F4:350","S1:500","S2:350","S3:240","T1:280","T2:280","B1:500"]],
  ['D terkunci notch — 1 jalur terbelah jadi 2 potongan ber-id sama', {
    verts: [{x:0,y:0},{x:400,y:0},{x:400,y:400},{x:250,y:400},{x:250,y:150},{x:150,y:150},{x:150,y:400},{x:0,y:400}],
    grid:20, kotak:100, arah:'2', supportsManual:[], removed:{}, tiang:[], balok:[], balokSeq:1,
    tinggi:300, matDefault:MD, matOverride:{}, combinedBoxes:[],
    supportsLocked:[{no:1,axis:'h',pos:300},{no:2,axis:'v',pos:200}], lockSeq:3,
  }, ["F1:400","F2:400","F3:150","F4:250","F5:100","F6:250","F7:150","F8:400","S1:150","S1:150","S2:150"]],
];
golden.forEach(([judul, S, exp]) => {
  check('kompat model lama — ' + judul, nm(DenahConv.buildMembers(S)), exp);
  // Argumen baru diisi TAPI modelnya lama -> post-step wajib no-op, hasil sama persis.
  check('kompat + tapakMap diisi — ' + judul, nm(DenahConv.buildMembers(S, TAPAK, [])), exp);
});

// 8) Ruas <= 0 dibuang + warning (dua pemotong sangat rapat).
//    S3 (v@200, 0..300) dipotong S1 (h@149) & S2 (h@151):
//      ruas1 = 149 - 2,5 (frame) - 2 (S1) = 144,5
//      ruas2 = 2   - 2   (S1)    - 2 (S2) = -2  -> dibuang
//      ruas3 = 149 - 2   (S2)    - 2,5 (frame) = 144,5
const s8 = base(); s8.supMenerus = { h: true, v: false };
s8.supportsLocked = [ { no: 1, axis: 'h', pos: 149 }, { no: 2, axis: 'h', pos: 151 }, { no: 3, axis: 'v', pos: 200 } ];
const w8 = [];
const m8 = sup(DenahConv.buildMembers(s8, TAPAK, w8));
check('ruas tengah <=0 dibuang, sisanya persis 144,5', m8.filter(x => x.nama.startsWith('S3')),
  [{ nama: 'S3·1', panjang: 144.5 }, { nama: 'S3·3', panjang: 144.5 }]);
check('nomor ruas tak dirapatkan (S3·2 hilang, bukan digeser)', m8.map(x => x.nama),
  ['S1', 'S2', 'S3·1', 'S3·3']);
check('warning ruas <=0 menyebut ruas yang mana', w8.some(w => w.includes('S3·2')), true);

// 9) Sambungan T / butt joint: pemotong yang BERHENTI pas di garis ruas TIDAK memecahnya.
//    S1 manual mendatar (200,150)->(400,150) berhenti tepat di jalur v@200. Secara fabrikasi
//    S1-lah yang dipotong tapak di ujungnya; S2 tetap satu batang utuh 300.
const s9 = base(); s9.supMenerus = { h: true, v: false };
s9.supportsLocked = [ { no: 1, manual: true, a: { x: 200, y: 150 }, b: { x: 400, y: 150 }, aktif: true },
                      { no: 2, axis: 'v', pos: 200 } ];
check('butt joint tidak memecah batang yang dilewati',
  sup(DenahConv.buildMembers(s9, TAPAK)),
  [{ nama: 'S1', panjang: 200 }, { nama: 'S2', panjang: 300 }]);

// 10) Penomoran S{n} support manual fase pratinjau TIDAK ikut terpecah. 1 support manual bisa
//     jadi beberapa member ber-id sama (kepotong coakan / dipecah di silangan) — nomornya harus
//     per ID, bukan per member. Dulu: {Sm_0:2, Sm_1:4}.
const s10 = {
  verts: [{x:0,y:0},{x:400,y:0},{x:400,y:300},{x:0,y:300}],
  grid: 20, kotak: 150, arah: 'h', removed: {}, tiang: [], balok: [], balokSeq: 1, tinggi: 300,
  matDefault: { frame: 'Hollow 5x10', support: 'Hollow 4x8', tiang: 'Hollow 5x10', balok: '' },
  matOverride: {}, combinedBoxes: [], supMenerus: { h: true, v: false },
  supportsManual: [ { a: { x: 100, y: 0 }, b: { x: 100, y: 300 } },
                    { a: { x: 300, y: 0 }, b: { x: 300, y: 300 } } ],
};
const m10 = DenahConv.buildMembers(s10, TAPAK);
check('dua support manual tegak masing-masing pecah 2', m10.filter(m => m.id.startsWith('Sm_')).length, 4);
check('nomor S{n} manual tetap 1 dan 2 walau terpecah',
  DenahConv.numberSupportsManual(m10), { Sm_0: 1, Sm_1: 2 });

process.exit(fail ? 1 : 0);
