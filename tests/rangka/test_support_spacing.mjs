// FILE: tests/rangka/test_support_spacing.mjs
// Jalankan: node tests/rangka/test_support_spacing.mjs
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

// ── mode 'cm' — HARUS identik algoritma langkah lama (backward-compat) ──
// Lama: for (let v = lo + K; v < hi - 1; v += K) push v.
check('cm 0..600 K=100 -> [100,200,300,400,500] (habis rata)',
  DenahConv.posisiSupport(0, 600, 'cm', 100, null, 100), [100, 200, 300, 400, 500]);
check('cm 0..450 K=100 -> [100,200,300,400] (nyisa 50, perilaku lama dipertahankan)',
  DenahConv.posisiSupport(0, 450, 'cm', 100, null, 100), [100, 200, 300, 400]);
// kotak kosong/0 -> pakai kotakFallback (denah lama tanpa kotakH)
check('cm kotak null -> pakai fallback 100',
  DenahConv.posisiSupport(0, 300, 'cm', null, null, 100), [100, 200]);
check('cm kotak 0 -> pakai fallback 100 (guard, bukan loop tak berhenti)',
  DenahConv.posisiSupport(0, 300, 'cm', 0, null, 100), [100, 200]);
// offset lo != 0 (bounding box tak mulai di 0)
check('cm lo=50..350 K=100 -> [150,250] (langkah dari lo+K)',
  DenahConv.posisiSupport(50, 350, 'cm', 100, null, 100), [150, 250]);

// ── mode 'kolom' — bagi rata PERSIS, tanpa sisa ──
// N kolom = N ruas = N-1 garis internal, jarak span/N.
check('kolom 0..450 N=5 -> [90,180,270,360] (semua ruas 90)',
  DenahConv.posisiSupport(0, 450, 'kolom', null, 5, 100), [90, 180, 270, 360]);
check('kolom 0..600 N=6 -> [100,200,300,400,500]',
  DenahConv.posisiSupport(0, 600, 'kolom', null, 6, 100), [100, 200, 300, 400, 500]);
check('kolom N=1 -> [] (0 garis internal, 1 ruas utuh)',
  DenahConv.posisiSupport(0, 400, 'kolom', null, 1, 100), []);
check('kolom lo=100..400 N=3 -> [200,300] (jarak 100, offset dari lo)',
  DenahConv.posisiSupport(100, 400, 'kolom', null, 3, 100), [200, 300]);
// kolom tak valid -> fallback ke perilaku cm dgn kotakFallback (jangan bagi 0/NaN)
check('kolom N=0 -> guard, fallback cm dgn kotakFallback',
  DenahConv.posisiSupport(0, 300, 'kolom', null, 0, 100), [100, 200]);
check('kolom N=NaN -> guard, fallback cm',
  DenahConv.posisiSupport(0, 300, 'kolom', null, NaN, 100), [100, 200]);

// ── guard span ──
check('hi <= lo -> [] (span 0/negatif, tak ada garis)',
  DenahConv.posisiSupport(300, 300, 'cm', 100, null, 100), []);
check('hi < lo -> []',
  DenahConv.posisiSupport(300, 100, 'kolom', null, 3, 100), []);

// ── buildMembers: backward-compat (model lama tanpa field per-sumbu) ──
// Kotak persegi 600x450, arah '2', kotak 100 -- HARUS sama persis perilaku lama.
const modelLama = {
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
};
const supLama = DenahConv.buildMembers(modelLama).filter(m => m.jenis === 'support');
// Horizontal Sh_ (langkah Y 0..450, K=100): garis di y=100,200,300,400 -> 4 baris.
// Vertikal Sv_ (langkah X 0..600, K=100): garis di x=100,200,300,400,500 -> 5 baris.
check('backward-compat: jumlah Sh_ (450/100 langkah lama) = 4',
  supLama.filter(m => m.id.startsWith('Sh_')).length, 4);
check('backward-compat: jumlah Sv_ (600/100 langkah lama) = 5',
  supLama.filter(m => m.id.startsWith('Sv_')).length, 5);
// id pertama Sh_ harus tetap 'Sh_0_0' (kunci S.removed bergantung ini)
check('backward-compat: id Sh_ pertama tetap Sh_0_0',
  supLama.filter(m => m.id.startsWith('Sh_'))[0].id, 'Sh_0_0');

// ── buildMembers: mode kolom per-sumbu ──
// Sisi Y (Panjang 450) mode kolom 5 -> 4 garis Sh_ (ruas 90). Sisi X (Lebar 600) mode kolom 6 -> 5 garis Sv_.
const modelBaru = {
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2',
  modeH: 'kolom', kolomH: 5, modeV: 'kolom', kolomV: 6,
  supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
};
const supBaru = DenahConv.buildMembers(modelBaru).filter(m => m.jenis === 'support');
check('per-sumbu: Sh_ kolomH=5 -> 4 garis', supBaru.filter(m => m.id.startsWith('Sh_')).length, 4);
check('per-sumbu: Sv_ kolomV=6 -> 5 garis', supBaru.filter(m => m.id.startsWith('Sv_')).length, 5);
// Ruas Sh_ semua 90 (450/5): panjang tiap Sh_ TIDAK diuji (itu lebar span X), tapi posisi Y-nya kelipatan 90.
// Cek via geom.a.y garis Sh_ pertama = 90.
check('per-sumbu: Sh_ pertama di y=90 (450/5 rata)',
  supBaru.filter(m => m.id.startsWith('Sh_'))[0].geom.a.y, 90);

if (fail) { console.log('\n=== ADA YANG GAGAL ==='); process.exit(1); }
console.log('\n=== SEMUA TES LULUS ===');
