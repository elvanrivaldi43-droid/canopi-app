// FILE: tests/rangka/test_support_pola.mjs
// Jalankan: node tests/rangka/test_support_pola.mjs
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

// ── lockSupportAxis ──────────────────────────────────────
// Support horizontal (Sh_, a.y===b.y) -> cuma boleh naik-turun (Y berubah, X tetap).
const shA = { x: 50, y: 100 }, shB = { x: 250, y: 100 };
check('Sh_ digeser dx=30,dy=-15 -> X TIDAK berubah, Y berubah -15 di kedua ujung',
  DenahConv.lockSupportAxis('Sh_2_4', shA, shB, 30, -15),
  { a: { x: 50, y: 85 }, b: { x: 250, y: 85 } });

// Support vertikal (Sv_, a.x===b.x) -> cuma boleh kiri-kanan (X berubah, Y tetap).
const svA = { x: 150, y: 20 }, svB = { x: 150, y: 220 };
check('Sv_ digeser dx=40,dy=25 -> Y TIDAK berubah, X berubah +40 di kedua ujung',
  DenahConv.lockSupportAxis('Sv_1_2', svA, svB, 40, 25),
  { a: { x: 190, y: 20 }, b: { x: 190, y: 220 } });

// dx/dy = 0 -> tidak berubah sama sekali (identitas).
check('dx=0,dy=0 -> posisi identik',
  DenahConv.lockSupportAxis('Sh_0_0', shA, shB, 0, 0),
  { a: shA, b: shB });

// ── numberSupportsManual ──────────────────────────────────
// mem gabungan: 2 grid support duluan, lalu 2 manual -> manual dapat nomor SENDIRI (1,2),
// TIDAK ikut kehitung grid-nya (bukan 3,4).
const mem1 = [
  { id: 'Sh_0_0', jenis: 'support' },
  { id: 'Sv_0_0', jenis: 'support' },
  { id: 'Sm_0', jenis: 'support' },
  { id: 'Sm_1', jenis: 'support' },
];
check('2 grid + 2 manual -> manual bernomor 1,2 (bukan 3,4)',
  DenahConv.numberSupportsManual(mem1), { Sm_0: 1, Sm_1: 2 });

// Grid support TIDAK masuk hasil sama sekali (bukan cuma dilewati nomornya, tapi memang tak ada key-nya).
check('grid support tidak punya entri di hasil',
  Object.prototype.hasOwnProperty.call(DenahConv.numberSupportsManual(mem1), 'Sh_0_0'), false);

// Frame/tiang ikut campur di mem (kasus nyata dari buildMembers) -> tetap diabaikan, tidak bikin nomor meleset.
const mem2 = [
  { id: 'F0', jenis: 'frame' },
  { id: 'Sm_0', jenis: 'support' },
  { id: 'T0', jenis: 'tiang' },
  { id: 'Sm_1', jenis: 'support' },
];
check('frame/tiang tercampur -> tidak pengaruhi nomor manual',
  DenahConv.numberSupportsManual(mem2), { Sm_0: 1, Sm_1: 2 });

// Tidak ada manual sama sekali -> object kosong, bukan error.
check('tidak ada support manual -> object kosong',
  DenahConv.numberSupportsManual([{ id: 'Sh_0_0', jenis: 'support' }]), {});

if (fail) { console.log('\n=== ADA YANG GAGAL ==='); process.exit(1); }
console.log('\n=== SEMUA TES LULUS ===');
