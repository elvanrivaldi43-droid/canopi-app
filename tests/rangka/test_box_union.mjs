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

// Kotak dasar 700x400: (0,0)-(700,0)-(700,400)-(0,400)
const kotak = [{ x: 0, y: 0 }, { x: 700, y: 0 }, { x: 700, y: 400 }, { x: 0, y: 400 }];

// --- Kasus NAMBAH: tempel di sisi kanan (idx 1), menjorok KELUAR (depth negatif -> +x) ---
const tambah = DenahConv.combineBox(kotak, 1, 75, 250, -150);
check('nambah: 8 titik', tambah && tambah.length, 8);
check('nambah: luas jadi 31.75 m2 (28m2 + tab 1.5x2.5m)', tambah && DenahConv.luasM2({ verts: tambah }), 31.75);
check('nambah: titik menonjol (850,75) ada', tambah && tambah.some(p => p.x === 850 && p.y === 75), true);

// --- Kasus LEKUKAN: tempel di sisi bawah (idx 2), menjorok ke DALAM (depth positif -> -y) ---
const lekukan = DenahConv.combineBox(kotak, 2, 200, 300, 100);
check('lekukan: 8 titik', lekukan && lekukan.length, 8);
check('lekukan: luas jadi 25 m2 (28m2 - notch 2x1m)', lekukan && DenahConv.luasM2({ verts: lekukan }), 25);
check('lekukan: titik notch (500,300) ada', lekukan && lekukan.some(p => p.x === 500 && p.y === 300), true);

// --- Kasus DITOLAK: offset+span melebihi panjang sisi (700) ---
const invalidRange = DenahConv.combineBox(kotak, 0, 600, 200, 100);
check('ditolak: offset+span > panjang sisi -> null', invalidRange, null);

// --- Kasus DITOLAK: lekukan kelewat dalam, nembus sisi seberang (self-intersect) ---
const invalidDeep = DenahConv.combineBox(kotak, 2, 200, 300, 500);
check('ditolak: lekukan nembus sisi seberang -> null', invalidDeep, null);

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
