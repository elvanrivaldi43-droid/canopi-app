// Muat modul classic-script (globalThis.DenahConv) via read+eval — file browser tak pakai ESM export.
import { readFileSync } from 'node:fs';
const code = readFileSync(new URL('../../public/js/denah-editor.js', import.meta.url), 'utf8');
(0, eval)(code);            // eval scope global → set globalThis.DenahConv
const { DenahConv } = globalThis;

let fail = false;
const check = (name, got, exp) => {
  const ok = JSON.stringify(got) === JSON.stringify(exp);
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name} (got ${JSON.stringify(got)}, exp ${JSON.stringify(exp)})`);
  if (!ok) fail = true;
};

// Kotak 400x400, support horizontal, kotak 100 -> 4 frame @400 + 3 support @400 (y=100,200,300)
const kotak = {
  verts: [{x:0,y:0},{x:400,y:0},{x:400,y:400},{x:0,y:400}],
  grid:20, target:100, kotak:100, autoKotak:true, arah:'h',
  supportsManual:[], removed:{}, tiang:[], tinggi:300,
  matDefault:{frame:'F',support:'S',tiang:'T'}, matOverride:{},
};
const m = DenahConv.buildMembers(kotak);
check('kotak: 4 frame', m.filter(x=>x.jenis==='frame').length, 4);
check('kotak: frame semua 400', m.filter(x=>x.jenis==='frame').every(x=>x.panjang===400), true);
check('kotak: 3 support', m.filter(x=>x.jenis==='support').length, 3);
check('kotak: support semua 400', m.filter(x=>x.jenis==='support').every(x=>x.panjang===400), true);
check('kotak: luas 16 m2', DenahConv.luasM2(kotak), 16);

// L-shape cekung: support tak bocor keluar poligon (segmen <= lebar 400)
const L = { ...kotak, verts:[{x:0,y:0},{x:400,y:0},{x:400,y:200},{x:200,y:200},{x:200,y:400},{x:0,y:400}] };
const seg = DenahConv.buildMembers(L).filter(x=>x.jenis==='support');
// L cekung (arah h, kotak 100): support y=100 penuh 400; y=200 & y=300 terpotong notch → 200,200
check('L: support terklip cekung = [200,200,400]', seg.map(s=>s.panjang).sort((a,b)=>a-b), [200,200,400]);

// saranKotak: lebar 700, target 100 -> 700/round(7)=100
check('saranKotak(700,100)=100', DenahConv.saranKotak(700,100), 100);
check('saranKotak(530,100)=106', DenahConv.saranKotak(530,100), 106); // round(5.3)=5 -> 106

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
