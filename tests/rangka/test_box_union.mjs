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

// --- Kasus DITOLAK: depth = 0 (tak ada arah) ---
const invalidDepthZero = DenahConv.combineBox(kotak, 0, 100, 200, 0);
check('ditolak: depth=0 -> null', invalidDepthZero, null);

// --- Kasus DITOLAK: offset negatif ---
const invalidNegOffset = DenahConv.combineBox(kotak, 0, -50, 200, 100);
check('ditolak: offset negatif -> null', invalidNegOffset, null);

// --- Kasus SUDUT: lekukan nempel PAS di ujung sisi kanan (idx 1, 700x400) -> sudut lama
// (700,400) jadi segaris & harus lenyap, sisi bawah otomatis menyusut 700->600 (bukan nambah
// sisi "duri" 100cm palsu). Laporan Elvan 22 Agustus: kotak 100x100 pas di pojok kanan-bawah
// harusnya jadi L bersih 6 titik, bukan 7 titik dgn sisi F5 nyeleneh.
const sudut = DenahConv.combineBox(kotak, 1, 300, 100, 100);
check('sudut: 6 titik (sudut lama lenyap, bukan 7)', sudut && sudut.length, 6);
check('sudut: (700,400) TIDAK ada lagi', sudut && sudut.some(p => p.x === 700 && p.y === 400), false);
check('sudut: titik notch (600,400) ada (sisi bawah menyusut ke situ)', sudut && sudut.some(p => p.x === 600 && p.y === 400), true);
check('sudut: luas jadi 27 m2 (28m2 - notch 1x1m)', sudut && DenahConv.luasM2({ verts: sudut }), 27);

// --- Kasus SUDUT (ujung awal sisi, offset=0) -> sudut lama (700,0) lenyap juga ---
const sudutAwal = DenahConv.combineBox(kotak, 1, 0, 100, 100);
check('sudut awal: 6 titik', sudutAwal && sudutAwal.length, 6);
check('sudut awal: (700,0) TIDAK ada lagi', sudutAwal && sudutAwal.some(p => p.x === 700 && p.y === 0), false);

// --- Kasus TENGAH (bukan sudut) tetap 8 titik, tak ada yg dibuang (regresi utama) ---
const tengah = DenahConv.combineBox(kotak, 1, 100, 100, 100);
check('tengah (bukan sudut): tetap 8 titik', tengah && tengah.length, 8);

// --- combineBoxWithMeta: boxIdx nunjuk ke titik kotak, reindex map sudut lama yg lenyap ke -1 ---
const meta = DenahConv.combineBoxWithMeta(kotak, 1, 300, 100, 100);
check('meta sudut: reindex[2] (sudut lama 700,400) = -1', meta && meta.reindex[2], -1);
check('meta sudut: boxIdx panjang 3 (p1,p4,p3 -- p2 kena skip krn offset+span nempel sudut)', meta && meta.boxIdx.length, 3);
check('meta tengah: tak ada reindex yg -1 (tak ada sudut lama dibuang)', meta2FromTengah(), true);
function meta2FromTengah() {
  const m = DenahConv.combineBoxWithMeta(kotak, 1, 100, 100, 100);
  return m ? !m.reindex.includes(-1) : false;
}

console.log(fail ? '\nADA FAIL' : '\nSEMUA LULUS');
process.exit(fail ? 1 : 0);
