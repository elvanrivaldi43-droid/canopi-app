// FILE: tests/rangka/test_support_lock.mjs
// Jalankan: node tests/rangka/test_support_lock.mjs
// Fase terkunci Support ID Stabil (spec 2026-08-23): lockSupports + cabang buildMembers.
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
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 450 }, { x: 0, y: 450 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
});

// ── isLocked ──
check('isLocked: model lama -> false', DenahConv.isLocked(base()), false);
check('isLocked: supportsLocked [] -> true', DenahConv.isLocked({ ...base(), supportsLocked: [] }), true);

// ── lockSupports: penomoran H atas->bawah dulu, lalu V kiri->kanan, manual melanjutkan ──
{
  const S = base();
  S.supportsManual = [{ a: { x: 10, y: 10 }, b: { x: 10, y: 200 } }];
  const p = DenahConv.lockSupports(S);
  // 600x450 kotak 100: H di y=100..400 (4 garis), V di x=100..500 (5 garis), manual 1 -> total 10
  check('lock: jumlah entri', p.supportsLocked.length, 10);
  check('lock: nomor urut 1..10', p.supportsLocked.map(e => e.no), [1,2,3,4,5,6,7,8,9,10]);
  check('lock: 4 H dulu (pos naik)', p.supportsLocked.slice(0, 4).map(e => [e.axis, e.pos]),
    [['h',100],['h',200],['h',300],['h',400]]);
  check('lock: lalu 5 V (pos naik)', p.supportsLocked.slice(4, 9).map(e => [e.axis, e.pos]),
    [['v',100],['v',200],['v',300],['v',400],['v',500]]);
  check('lock: manual terakhir, ujung nyata', [p.supportsLocked[9].manual, p.supportsLocked[9].a, p.supportsLocked[9].b],
    [true, { x: 10, y: 10 }, { x: 10, y: 200 }]);
  check('lock: semua aktif', p.supportsLocked.every(e => e.aktif), true);
  check('lock: lockSeq = 11', p.lockSeq, 11);
  check('lock: supportsManual dikosongkan', p.supportsManual, []);
  check('lock: removed dikosongkan', p.removed, {});
  check('lock: S asal TIDAK dimutasi', S.supportsManual.length, 1);
}

// ── lockSupports: migrasi removed{} (semua potongan removed -> lahir nonaktif) & matOverride ──
{
  const S = base();
  S.removed = { 'Sh_0_0': true };                        // garis H pertama (y=100), 1 potongan (persegi)
  S.matOverride = { 'Sh_1_0': 'BesiA', 'Sm_0': 'BesiB', 'F0': 'BesiC' };
  S.supportsManual = [{ a: { x: 0, y: 0 }, b: { x: 100, y: 0 } }];
  const p = DenahConv.lockSupports(S);
  check('lock: removed cocok -> nonaktif', p.supportsLocked[0].aktif, false);
  check('lock: garis lain tetap aktif', p.supportsLocked[1].aktif, true);
  check('lock: override grid pindah ke SL2', p.matOverride['SL2'], 'BesiA');
  check('lock: key grid lama hilang', 'Sh_1_0' in p.matOverride, false);
  check('lock: override manual pindah ke SL10', p.matOverride['SL10'], 'BesiB');
  check('lock: override frame tak tersentuh', p.matOverride['F0'], 'BesiC');
}

// ── lockSupports: lockSeq lama dipertahankan (re-lock setelah Susun Ulang melanjutkan nomor) ──
{
  const S = base(); S.arah = 'h'; S.lockSeq = 21;
  const p = DenahConv.lockSupports(S);
  check('re-lock: nomor mulai dari lockSeq lama', p.supportsLocked[0].no, 21);
  check('re-lock: lockSeq maju', p.lockSeq, 25);
}

// ── buildMembers terkunci: ujung dihitung dari polygon SAAT INI ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 150, aktif: true }];
  S.lockSeq = 2;
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked: 1 member id SL1', sup.map(m => m.id), ['SL1']);
  check('locked: panjang = lebar frame', sup[0].panjang, 400);
  // frame dilebarkan -> ujung otomatis memanjang (jalur, bukan ujung tersimpan)
  S.verts = [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 0, y: 300 }];
  check('locked: frame melebar -> support memanjang', DenahConv.buildMembers(S).filter(m => m.jenis === 'support')[0].panjang, 600);
}

// ── buildMembers terkunci: jalur terpotong coakan = beberapa potongan, SATU id, panjang dijumlah ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 400, y: 300 },
             { x: 400, y: 100 }, { x: 200, y: 100 }, { x: 200, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [{ no: 3, axis: 'h', pos: 150, aktif: true }];
  S.lockSeq = 4;
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked coakan: 2 potongan id sama', sup.map(m => m.id), ['SL3', 'SL3']);
  check('locked coakan: jumlah panjang 400', sup.reduce((a, m) => a + m.panjang, 0), 400);
}

// ── buildMembers terkunci: nonaktif keluar dari hitungan; manual & vertikal & matOverride ──
{
  const S = base();
  S.verts = [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }];
  S.supportsLocked = [
    { no: 1, axis: 'h', pos: 150, aktif: false },
    { no: 2, axis: 'v', pos: 100, aktif: true },
    { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 0, y: 250 }, aktif: true },
  ];
  S.lockSeq = 4; S.matOverride = { 'SL2': 'BesiK' };
  const sup = DenahConv.buildMembers(S).filter(m => m.jenis === 'support');
  check('locked: nonaktif tak ikut', sup.map(m => m.id), ['SL2', 'SL3']);
  check('locked: vertikal panjang 300', sup[0].panjang, 300);
  check('locked: matOverride ke-key SL', sup[0].material, 'BesiK');
  check('locked: manual pakai ujung tersimpan', sup[1].panjang, 250);
}

// ── EKUIVALENSI PRATINJAU: model tanpa supportsLocked -> perilaku lama persis ──
{
  const S = base();
  S.supportsManual = [{ a: { x: 0, y: 0 }, b: { x: 50, y: 0 } }];
  const ids = DenahConv.buildMembers(S).filter(m => m.jenis === 'support').map(m => m.id);
  check('pratinjau: id lama Sh_/Sv_/Sm_ tak berubah',
    [ids.filter(i => i.startsWith('Sh_')).length, ids.filter(i => i.startsWith('Sv_')).length, ids.includes('Sm_0')],
    [4, 5, true]);
}

process.exit(fail ? 1 : 0);
