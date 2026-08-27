// FILE: tests/rangka/test_svg_cetak.mjs
// Jalankan: node tests/rangka/test_svg_cetak.mjs
// Menguji DenahConv.svgCetak(html): remap palet layar (latar gelap) -> palet kertas (latar putih)
// untuk gambar denah yang ikut ke penawaran cetak.
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
const has = (name, html, needle, want = true) => {
  const ok = html.includes(needle) === want;
  console.log((ok ? 'PASS' : 'FAIL') + ` — ${name}` + (ok ? '' : ` (${want ? 'tak ketemu' : 'masih ada'}: ${needle})`));
  if (!ok) fail = true;
};

// --- Latar & grid: gelap -> putih ---
const latar = DenahConv.svgCetak('<rect fill="#0f2740"/><path stroke="#1e3a5f"/>');
has('latar kanvas jadi putih', latar, 'fill="#ffffff"');
has('latar gelap tak tersisa', latar, '#0f2740', false);
has('garis grid jadi abu muda', latar, 'stroke="#e5e7eb"');

// --- Teks: warna terang (dirancang utk latar gelap) -> gelap, halo jadi putih ---
// Satu <text> membawa fill terang DAN stroke halo gelap sekaligus; keduanya harus dibalik
// dalam SATU pass -- kalau diproses berurutan, hasil replace pertama bisa kena replace kedua.
const teks = DenahConv.svgCetak('<text fill="#e2e8f0" stroke="#0f2740">F1 · 400</text>');
check('teks frame: terang->gelap, halo gelap->putih', teks, '<text fill="#111827" stroke="#ffffff">F1 · 400</text>');
has('label support biru muda jadi biru tua', DenahConv.svgCetak('fill="#93c5fd"'), '#1d4ed8');
has('label tiang kuning jadi cokelat', DenahConv.svgCetak('fill="#fbbf24"'), '#b45309');

// --- Palet besi: 8 warna cerah harus SEMUA punya pasangan gelap (tak ada yang lolos pucat) ---
// Ini inti kenapa "latar putih" bukan sekadar tukar background: kuning/lime/tosca hilang di kertas.
const PALET = ['#f59e0b', '#38bdf8', '#a3e635', '#f472b6', '#c084fc', '#fb7185', '#2dd4bf', '#facc15'];
PALET.forEach(w => has(`warna besi ${w} dipetakan ulang`, DenahConv.svgCetak(`stroke="${w}"`), w, false));

// --- Yang bukan warna layar jangan diutak-atik ---
check('warna asing dibiarkan apa adanya', DenahConv.svgCetak('stroke="#123456"'), 'stroke="#123456"');
check('huruf besar tetap kebaca (case-insensitive)', DenahConv.svgCetak('fill="#0F2740"'), 'fill="#ffffff"');
check('input kosong aman', DenahConv.svgCetak(''), '');
check('input bukan string aman', DenahConv.svgCetak(null), '');

process.exit(fail ? 1 : 0);
