// FILE: tests/rangka/test_support_jalur_manual.mjs
// Jalankan: node tests/rangka/test_support_jalur_manual.mjs
// Garis support numerik + pecah jadi manual (follow-up validasi Elvan 23 Ags):
// potongan BERHENTI di perpotongan frame, tak menyeberangi coakan.
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

const rect = () => ({
  verts: [{ x: 0, y: 0 }, { x: 400, y: 0 }, { x: 400, y: 300 }, { x: 0, y: 300 }],
  kotak: 100, arah: '2', supportsManual: [], removed: {}, tiang: [], tinggi: 300,
  matDefault: { frame: 'X', support: 'X', tiang: 'X' }, matOverride: {},
});
// Bentuk coakan: notch di tengah atas (y=100..300 kiri 0..200 dan kanan 400..600 nyambung bawah)
const notch = () => ({ ...rect(),
  verts: [{ x: 0, y: 0 }, { x: 600, y: 0 }, { x: 600, y: 300 }, { x: 400, y: 300 },
          { x: 400, y: 100 }, { x: 200, y: 100 }, { x: 200, y: 300 }, { x: 0, y: 300 }],
});

// ── jalurSegments ──
check('persegi h: 1 potongan penuh', DenahConv.jalurSegments(rect(), 'h', 150),
  [{ a: { x: 0, y: 150 }, b: { x: 400, y: 150 } }]);
check('persegi v: 1 potongan penuh', DenahConv.jalurSegments(rect(), 'v', 100),
  [{ a: { x: 100, y: 0 }, b: { x: 100, y: 300 } }]);
check('coakan h: 2 potongan, berhenti di frame', DenahConv.jalurSegments(notch(), 'h', 150),
  [{ a: { x: 0, y: 150 }, b: { x: 200, y: 150 } }, { a: { x: 400, y: 150 }, b: { x: 600, y: 150 } }]);
check('di luar frame -> []', DenahConv.jalurSegments(rect(), 'h', 999), []);

// ── manualEntriesFromJalur: relatif tepi, nomor dari lockSeq ──
{
  const S = notch(); S.supportsLocked = []; S.lockSeq = 7;
  const r = DenahConv.manualEntriesFromJalur(S, 'h', 150); // bb.y0=0 -> pos 150
  check('numerik: 2 entri manual nomor 7,8', r.entries.map(e => [e.no, e.manual, e.aktif]),
    [[7, true, true], [8, true, true]]);
  check('numerik: ujung potongan pertama', r.entries[0].a, { x: 0, y: 150 });
  check('numerik: lockSeq maju ke 9', r.lockSeq, 9);
  check('numerik: S tak dimutasi', S.lockSeq, 7);
}
check('numerik: cm 0 -> null (nempel frame = duplikat frame)',
  DenahConv.manualEntriesFromJalur({ ...rect(), supportsLocked: [], lockSeq: 1 }, 'h', 0), null);
check('numerik: NaN -> null',
  DenahConv.manualEntriesFromJalur({ ...rect(), supportsLocked: [], lockSeq: 1 }, 'h', NaN), null);
{
  const S = rect(); S.supportsLocked = []; S.lockSeq = 1;
  check('numerik: di luar frame -> entries kosong (bukan null)',
    DenahConv.manualEntriesFromJalur(S, 'v', 999).entries, []);
}

// ── splitLockedGrid: kasus S2 nyata — buang potongan tak terpakai ──
{
  const S = notch();
  S.supportsLocked = [
    { no: 2, axis: 'h', pos: 150, aktif: true },
    { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 10, y: 0 }, aktif: true },
  ];
  S.lockSeq = 4; S.matOverride = { 'SL2': 'BesiA', 'SL3': 'BesiB' };
  const p = DenahConv.splitLockedGrid(S, 2);
  check('pecah: entri grid diganti 2 manual di posisi yang sama (in-place)',
    p.supportsLocked.map(e => [e.no, !!e.manual]), [[4, true], [5, true], [3, true]]);
  check('pecah: ujung potongan kedua', p.supportsLocked[1].a, { x: 400, y: 150 });
  check('pecah: override disalin ke tiap potongan, key lama hilang',
    [p.matOverride['SL4'], p.matOverride['SL5'], 'SL2' in p.matOverride, p.matOverride['SL3']],
    ['BesiA', 'BesiA', false, 'BesiB']);
  check('pecah: lockSeq maju ke 6', p.lockSeq, 6);
  check('pecah: S tak dimutasi', S.supportsLocked.length, 2);
}
{
  const S = rect();
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 150, aktif: false }];
  S.lockSeq = 2;
  check('pecah: entri nonaktif -> potongan mewarisi nonaktif',
    DenahConv.splitLockedGrid(S, 1).supportsLocked.map(e => e.aktif), [false]);
  check('pecah: no manual -> null', DenahConv.splitLockedGrid({ ...S, supportsLocked: [{ no: 1, manual: true, a: { x: 0, y: 0 }, b: { x: 1, y: 1 }, aktif: true }] }, 1), null);
  check('pecah: no tak ada -> null', DenahConv.splitLockedGrid(S, 99), null);
}
{
  const S = rect();
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 999, aktif: true }]; S.lockSeq = 2;
  check('pecah: jalur di luar frame (0 potongan) -> null', DenahConv.splitLockedGrid(S, 1), null);
}

// ── Hardening (review Task 1): lockSeq absen/stale + entri no eksisting -> nomor baru TIDAK dobel ──
{
  const S = rect(); // 1 potongan
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 50, aktif: true }, { no: 3, manual: true, a: { x: 0, y: 0 }, b: { x: 1, y: 1 }, aktif: true }];
  S.lockSeq = 0; // absen/stale — kalau dipakai apa adanya bakal mulai dari 1 dan tabrakan sama no:1
  const r = DenahConv.manualEntriesFromJalur(S, 'v', 100); // bb.x0=0 -> pos 100
  check('numerik hardening: nomor lanjut dari max no eksisting (4), bukan 1', r.entries.map(e => e.no), [4]);
  check('numerik hardening: lockSeq lanjut ke 5', r.lockSeq, 5);
}
{
  const S = notch(); // 2 potongan utk h @150
  S.supportsLocked = [{ no: 1, axis: 'h', pos: 150, aktif: true }, { no: 2, manual: true, a: { x: 0, y: 0 }, b: { x: 10, y: 0 }, aktif: true }];
  S.lockSeq = 0; // absen/stale — kalau dipakai apa adanya, potongan kedua (no lama+1=2) tabrakan sama entri no:2
  const p = DenahConv.splitLockedGrid(S, 1);
  check('pecah hardening: nomor lanjut dari max no eksisting (3,4), bukan 1,2',
    p.supportsLocked.map(e => e.no), [3, 4, 2]);
  check('pecah hardening: lockSeq lanjut ke 5', p.lockSeq, 5);
}

process.exit(fail ? 1 : 0);
