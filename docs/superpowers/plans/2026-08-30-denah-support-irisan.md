# Support Beririsan (Menerus vs Putus) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support yang beririsan sebidang dihitung benar — jalur ber-status PUTUS terpecah jadi ruas-ruas nyata (jarak dikurangi tapak besi pemotong), sesuai keputusan Elvan.

**Architecture:** Dimensi profil besi masuk `master_material` (fallback tebak-nama). Editor mengirim peta tapak ke `DenahConv.buildMembers(S, tapak)` — pemecahan irisan adalah POST-STEP setelah logika lama (model lama tanpa field baru = no-op mutlak). UI menumpang panel Support yang ada. Peringatan (putus×putus, ruas ≤0, tapak kosong) dihitung di klien dan ikut tersimpan/terkirim ke halaman cutting list.

**Tech Stack:** PHP 8.3 / Laravel 13, vanilla JS (`public/js/denah-editor.js`), test `php` + `node .mjs` via `scripts/canopi-check`.

**Spec:** `docs/superpowers/specs/2026-08-30-denah-support-irisan-design.md`

## Global Constraints

- Kompat mundur MUTLAK: model tanpa `supMenerus`/`orientasi`/`menerus` → members identik byte-per-byte dgn sekarang (harness ekuivalensi wajib).
- Blok pratinjau lama di `buildMembers` (komentar "VERBATIM, JANGAN diubah") tidak boleh disentuh — pemecahan hanya sebagai post-step.
- SQL production = idempotent manual di phpMyAdmin SEBELUM push kode; kode wajib aman saat kolom belum ada (`Schema::hasColumn`, pola `sumber` di MasterMaterialController).
- Tak ada emoji di blade (pakai SVG/entity). Input font-size 16px (aturan global iOS).
- Test baru WAJIB didaftarkan di `tests/guardrail/manifest.json` di commit yang sama.
- `php scripts/canopi-check --full` hijau sebelum tiap commit di-push.
- Keputusan terkunci spec §Keputusan: frame & balok selalu menerus; putus×putus = peringatan (bukan blokir); orientasi per denah; toleransi gerinda tidak dihitung; satu baris per ruas di cutting list.

---

### Task 1: Kolom profil di Master Material (DB, model, form, badge)

**Files:**
- Create: `docs/sql/2026-08-30-master-material-profil.sql`
- Create: `tests/rangka/test_material_profil.php`
- Modify: `app/Models/MasterMaterial.php` (fillable baris 11-14, casts 16-19)
- Modify: `app/Http/Controllers/MasterMaterialController.php` (store ~49, update ~87)
- Modify: `resources/views/master-material/create.blade.php`, `edit.blade.php`, `index.blade.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Produces: `MasterMaterial::parseProfil(?string $nama): ?array` — statis murni, `[lebar, tinggi]` float cm dari nama ("Hollow 4x8 1mm" → `[4.0, 8.0]`) atau `null`.
- Produces: `$material->profilCm(): ?array` — `[lebar, tinggi]`: kolom DB kalau terisi, else `parseProfil(nama)`, else `null`. Wajib pakai `Schema::hasColumn` guard? TIDAK perlu di method (atribut absen = null di Eloquent), tapi query `get([...])` di controller lain TIDAK boleh minta kolom ini sebelum ada — lihat Task 2.

- [ ] **Step 1: Tulis SQL** — `docs/sql/2026-08-30-master-material-profil.sql`:

```sql
-- FILE: docs/sql/2026-08-30-master-material-profil.sql
-- Jalankan di phpMyAdmin production SEBELUM push kode (pola project; error 1060
-- "Duplicate column" = sudah pernah jalan, aman dilewati).
-- Dimensi profil besi utk hitung tapak ruas support beririsan (spec 2026-08-30).
-- NULL = belum diisi -> sistem menebak dari nama; hollow "banci" (4x8 nyatanya
-- 3,5cm) HARUS diisi manual di sini.
ALTER TABLE `master_material`
  ADD COLUMN `lebar_profil_cm` DECIMAL(5,1) NULL AFTER `harga_pokok`,
  ADD COLUMN `tinggi_profil_cm` DECIMAL(5,1) NULL AFTER `lebar_profil_cm`;
```

- [ ] **Step 2: Tulis test gagal** — `tests/rangka/test_material_profil.php`:

```php
<?php
// FILE: tests/rangka/test_material_profil.php
// Jalankan: php tests/rangka/test_material_profil.php
// parseProfil = CADANGAN tebak-nama; kolom DB = sumber kebenaran (hollow banci!).
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\MasterMaterial;

$fail = false;
function check(string $n, $got, $exp): void {
    global $fail;
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $n" . ($ok ? '' : ' (got ' . var_export($got, true) . ', exp ' . var_export($exp, true) . ')') . "\n";
    if (!$ok) $fail = true;
}

check('Hollow 4x8 1mm', MasterMaterial::parseProfil('Hollow 4x8 1mm'), [4.0, 8.0]);
check('spasi & X besar', MasterMaterial::parseProfil('Hollow 5 X 10 tebal 1,2'), [5.0, 10.0]);
check('desimal koma', MasterMaterial::parseProfil('Hollow 3,5x7,5'), [3.5, 7.5]);
check('tanda kali ×', MasterMaterial::parseProfil('Hollow 4×8'), [4.0, 8.0]);
check('tanpa dimensi -> null', MasterMaterial::parseProfil('WF 150'), null);
check('null -> null', MasterMaterial::parseProfil(null), null);
// "1mm" tak boleh kebaca sbg dimensi: yang diambil pasangan pertama AxB
check('pasangan pertama yang diambil', MasterMaterial::parseProfil('Besi 4x8 grade 2x1'), [4.0, 8.0]);

// profilCm: kolom DB menang atas nama (hollow banci)
$m = new MasterMaterial(['nama' => 'Hollow 4x8 banci']);
$m->lebar_profil_cm = 3.5; $m->tinggi_profil_cm = 7.5;
check('kolom DB menang', $m->profilCm(), [3.5, 7.5]);
$m2 = new MasterMaterial(['nama' => 'Hollow 4x8 1mm']);
check('kolom kosong -> tebak nama', $m2->profilCm(), [4.0, 8.0]);
$m3 = new MasterMaterial(['nama' => 'WF 150']);
check('dua-duanya gagal -> null', $m3->profilCm(), null);

exit($fail ? 1 : 0);
```

- [ ] **Step 3: Jalankan** — `php tests/rangka/test_material_profil.php` → FAIL "undefined method parseProfil".

- [ ] **Step 4: Implementasi di `app/Models/MasterMaterial.php`** — tambah ke fillable: `'lebar_profil_cm', 'tinggi_profil_cm'`; casts: `'lebar_profil_cm' => 'float', 'tinggi_profil_cm' => 'float'`; dua method:

```php
    /** Tebak dimensi profil dari nama ("Hollow 4x8 1mm" -> [4.0, 8.0]). CADANGAN
     *  saja — kolom DB sumber kebenaran (hollow "banci" 4x8 nyatanya 3,5cm). */
    public static function parseProfil(?string $nama): ?array
    {
        if ($nama === null) return null;
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)/u', $nama, $m)) return null;
        return [(float) str_replace(',', '.', $m[1]), (float) str_replace(',', '.', $m[2])];
    }

    /** [lebar, tinggi] cm: kolom DB kalau terisi, else tebak nama, else null. */
    public function profilCm(): ?array
    {
        $l = $this->lebar_profil_cm; $t = $this->tinggi_profil_cm;
        if ($l !== null && $t !== null && (float) $l > 0 && (float) $t > 0) return [(float) $l, (float) $t];
        return self::parseProfil($this->nama);
    }
```

- [ ] **Step 5: Jalankan lagi** → semua PASS. Daftarkan di manifest (`tests/rangka/test_material_profil.php`, runner php).

- [ ] **Step 6: Form & validasi.** Di `store()` dan `update()` MasterMaterialController tambah rules `'lebar_profil_cm' => 'nullable|numeric|min:0.1|max:999', 'tinggi_profil_cm' => 'nullable|numeric|min:0.1|max:999'`; penyimpanan pakai guard (pola `sumber` yang sudah ada di file itu):

```php
        if (Schema::hasColumn('master_material', 'lebar_profil_cm')) {
            $material->lebar_profil_cm  = $request->lebar_profil_cm ?: null;
            $material->tinggi_profil_cm = $request->tinggi_profil_cm ?: null;
            $material->save();
        }
```

Di `create.blade.php` & `edit.blade.php`, setelah input harga_pokok, tambah dua input (ikut gaya form yang ada, name `lebar_profil_cm`/`tinggi_profil_cm`, `type="number" step="0.1"`, label "Lebar profil (cm)" / "Tinggi profil (cm)", hint kecil: "Utk hitung tapak ruas support. Kosong = ditebak dari nama — isi manual utk hollow banci."). Di `index.blade.php` baris material: kalau `$m->kategori === 'rangka_besi'` dan `profilCm()` mengembalikan hasil TEBAK-NAMA (kolom kosong), tampilkan badge kuning kecil `profil?` dengan `title="Dimensi profil belum diisi — sistem menebak dari nama"`; kalau `profilCm() === null`, badge merah `profil!`.

- [ ] **Step 7:** `php scripts/canopi-check --full` hijau → commit `feat(material): kolom profil besi + parseProfil fallback (spec irisan Task 1)`.

---

### Task 2: Server mengirim dimensi besi ke editor

**Files:**
- Modify: `app/Http/Controllers/RabOpsiController.php` (query BESI utk halaman rab-opsi — cari `master_material` di method `index`)
- Modify: `app/Http/Controllers/CuttingController.php::index()` (~baris 34, query `$besi`)
- Modify: `resources/views/rab-opsi/index.blade.php` (mount DenahEditor ~baris 545: `besi:` map)
- Modify: `resources/views/cutting/index.blade.php` (init `ED = new DenahEditor`, map BESI)

**Interfaces:**
- Consumes: `MasterMaterial::parseProfil` (Task 1).
- Produces: elemen `opts.besi` klien berbentuk `{ nama, harga, lebar, tinggi }` — `lebar`/`tinggi` float cm atau `null`. Task 4 bergantung bentuk ini.

- [ ] **Step 1:** Di kedua controller, JANGAN menambah nama kolom ke `get([...])` (kolom bisa belum ada di production saat kode mendarat). Ambil model penuh lalu bentuk payload:

```php
        // CuttingController::index() — ganti query $besi lama:
        $besi = collect();
        try {
            $besi = \App\Models\MasterMaterial::where('kategori', 'rangka_besi')->where('aktif', 1)
                ->orderBy('nama')->get()
                ->map(function ($m) {
                    $p = $m->profilCm();
                    return ['id' => $m->id, 'nama' => $m->nama,
                            'lebar' => $p[0] ?? null, 'tinggi' => $p[1] ?? null];
                })->values();
        } catch (\Throwable $e) { $besi = collect(); }
```

Pola sama di RabOpsiController (pertahankan field yang sudah dikirim di sana — tambahkan `lebar`/`tinggi`, jangan buang `harga_pokok`).

- [ ] **Step 2:** Di kedua blade, map `besi` meneruskan dimensi: rab-opsi → `{ nama:b.nama, harga:Number(b.harga_pokok)||0, lebar:b.lebar??null, tinggi:b.tinggi??null }`; cutting → `{ nama:b.nama, harga:0, lebar:b.lebar??null, tinggi:b.tinggi??null }`.

- [ ] **Step 3:** `php scripts/canopi-check --full` hijau (Blade compile + route) → commit `feat(rab): kirim dimensi profil besi ke editor denah (Task 2)`.

---

### Task 3: DenahConv — pemecahan ruas + konflik + penamaan (INTI, TDD ketat)

**Files:**
- Create: `tests/rangka/test_support_irisan.mjs`
- Modify: `public/js/denah-editor.js` — `buildMembers` (baris 211, signature + post-step), helper baru dekat `jalurSegments` (447), `tests/guardrail/manifest.json`

**Interfaces:**
- Produces: `DenahConv.buildMembers(S, tapakMap = null, warnsOut = null)` — kompat: pemanggil lama tanpa argumen ekstra = perilaku persis lama. `tapakMap = { [namaMaterial]: {l:Number|null, t:Number|null} }`; `warnsOut` array — diisi string peringatan.
- Produces: `DenahConv.tapakCm(mat, tapakMap, orientasi)` → Number cm (0 kalau tak diketahui).
- Produces: `DenahConv.efektifMenerus(S, entry)` → bool (entry locked; `entry.menerus` override, else bawaan `S.supMenerus`).
- Produces: `DenahConv.irisanKonflik(S)` → array `{a:number, b:number}` pasangan `no` entri locked yang sama-sama putus & bersilangan.
- Produces: field model `S.supMenerus = {h:bool, v:bool}` (absen = `{h:true,v:true}`), `S.orientasi` (`'berdiri'` default), `entry.menerus` (bool|undefined).
- Penamaan member: locked TANPA pecah → `nama: 'S'+e.no` (perbaikan dari `'S'` polos — cutting list jadi bisa dilacak); ruas hasil pecah → `'S'+e.no+'·'+i` (i mulai 1, urut posisi). Pratinjau tetap `'S'`.

**Aturan pemecahan (dari spec §2, dipresisikan):**
- Kandidat putus: entri locked axis h/v dgn `efektifMenerus === false`; entri manual LURUS-AXIS (a.y===b.y → 'h', a.x===b.x → 'v') dgn flag sama; jalur pratinjau bila `S.supMenerus[axis] === false`. Manual DIAGONAL selalu menerus (catat komentar).
- Pemotong utk piece 'h' pada y=pos span [x1,x2]: (1) member support MENERUS 'v' yang geom-nya memotong (x di (x1,x2) DAN pos di antara y-range member itu); (2) member balok yang segmennya memotong piece (intersection segmen; balok selalu menerus). Frame TIDAK jadi pemotong interior — pieces sudah berhenti di frame; frame menyumbang TAPAK UJUNG.
- Panjang ruas: titik potong urut `x1 < c1 < ... < ck < x2`; ruas-i = `(c_{i} - c_{i-1}) - tapakKiri/2 - tapakKanan/2`, dgn tapak ujung-luar = tapak material sisi frame tempat ujung menempel (`matOverride['F'+idx] || matDefault.frame` — cari sisi frame yang memuat titik ujung via jarak-titik-ke-segmen < 0.5), tapak interior = tapak material pemotong di titik itu. Ujung manual yang TIDAK menempel frame → tapak 0.
- `tapakCm(mat, map, orientasi)`: dims = map[mat]; kalau `l`&`t` > 0 → orientasi 'tidur' ? max : min; else 0 + (sekali per material) push warn "tapak besi \"X\" belum diketahui — ruas dihitung as-ke-as".
- Ruas ≤ 0 → dibuang + warn "ruas S{no}·{i} ≤ 0 cm (silangan terlalu rapat) — dibuang".
- putus×putus: pasangan itu TIDAK saling memotong (dua-duanya dibiarkan utuh di silangan itu) + hasil `irisanKonflik` dipakai editor utk badge.

- [ ] **Step 1: Tulis test gagal** — `tests/rangka/test_support_irisan.mjs` (pola import test rangka lain: readFileSync + eval + `globalThis.DenahConv`):

```js
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
```

- [ ] **Step 2:** `node tests/rangka/test_support_irisan.mjs` → FAIL (fungsi belum ada / nama masih 'S').

- [ ] **Step 3: Implementasi.** Helper di object DenahConv (dekat `jalurSegments`):

```js
  // ── Support beririsan (spec 2026-08-30) ──
  supMenerusOf(S) { const d = S.supMenerus || {}; return { h: d.h !== false, v: d.v !== false }; },
  efektifMenerus(S, e) {
    if (typeof e.menerus === 'boolean') return e.menerus;
    const ax = e.manual
      ? (e.a.y === e.b.y ? 'h' : (e.a.x === e.b.x ? 'v' : null))  // diagonal: null
      : e.axis;
    if (ax === null) return true;                                  // diagonal selalu menerus
    return this.supMenerusOf(S)[ax];
  },
  tapakCm(mat, tapakMap, orientasi, warnsOut, sudahWarn) {
    const d = tapakMap && tapakMap[mat];
    if (d && d.l > 0 && d.t > 0) return orientasi === 'tidur' ? Math.max(d.l, d.t) : Math.min(d.l, d.t);
    if (warnsOut && sudahWarn && !sudahWarn.has(mat)) { sudahWarn.add(mat);
      warnsOut.push(`tapak besi "${mat}" belum diketahui — ruas dihitung as-ke-as`); }
    return 0;
  },
  irisanKonflik(S) {
    if (!Array.isArray(S.supportsLocked)) return [];
    const out = [];
    const list = S.supportsLocked.filter(e => e.aktif !== false && !this.efektifMenerus(S, e));
    for (let i = 0; i < list.length; i++) for (let j = i + 1; j < list.length; j++) {
      const A = list[i], B = list[j];
      const ah = A.manual ? (A.a.y === A.b.y ? 'h' : 'v') : A.axis;
      const bh = B.manual ? (B.a.y === B.b.y ? 'h' : 'v') : B.axis;
      if (ah === bh) continue;                       // sejajar tak bersilangan
      // uji silang kasar pakai member yang sudah dibangun mahal; cukup rentang jalur:
      const segs = (e, ax) => e.manual ? [{ a: e.a, b: e.b }] : this.jalurSegments(S, ax, e.pos);
      const H = ah === 'h' ? A : B, V = ah === 'h' ? B : A;
      const hs = segs(H, 'h'), vs = segs(V, 'v');
      const silang = hs.some(h => vs.some(v =>
        v.a.x > Math.min(h.a.x, h.b.x) && v.a.x < Math.max(h.a.x, h.b.x) &&
        h.a.y > Math.min(v.a.y, v.b.y) && h.a.y < Math.max(v.a.y, v.b.y)));
      if (silang) out.push({ a: Math.min(A.no, B.no), b: Math.max(A.no, B.no) });
    }
    return out;
  },
```

`buildMembers(S)` → `buildMembers(S, tapakMap = null, warnsOut = null)`. Perubahan di dalam:
1. Locked: `nama: 'S'` → `nama: 'S' + e.no` (kumpulkan pieces per-entri dulu ke array lokal, JANGAN langsung push).
2. Post-step SETELAH semua support+balok terbentuk (blok pratinjau lama tak disentuh — pieces pratinjau dikumpulkan dari `mem` setelah jadi):

```js
    // ── POST-STEP irisan (spec 2026-08-30): pecah jalur PUTUS di silangan dgn
    // pemotong menerus. Model lama tanpa supMenerus/menerus -> tak ada yang putus
    // -> blok ini no-op mutlak (harness ekuivalensi menjaga).
    const orientasi = S.orientasi === 'tidur' ? 'tidur' : 'berdiri';
    const sudahWarn = new Set();
    const tp = (mat) => DenahConv.tapakCm(mat, tapakMap, orientasi, warnsOut, sudahWarn);
    const menerusV = [], menerusH = [];   // pemotong: support menerus + balok
    // ... isi dari mem (jenis support yang efektif menerus, per axis dari geom) + balok (selalu)
    // frame side material utk tapak ujung:
    const frameMatAt = (p) => { /* cari sisi frame berjarak <0.5 dari p -> materialnya; null kalau tak nempel */ };
    // pecah tiap piece putus:
    // potongan interior: pemotong tegak lurus yang benar2 memotong span piece
    // ruas = jarak - tapak/2 kiri - tapak/2 kanan; <=0 -> buang + warn; nama S{no}·{i}
```

(Implementer: tulis lengkap — pieces per entri diberi nomor urut global per-entri berdasar posisi sepanjang sumbu; balok sebagai pemotong pakai uji potong segmen-vs-piece axis-aligned; piece pratinjau pakai id `Sh_/Sv_` — putus hanya bila `supMenerusOf(S)[axis] === false`, nama tetap `'S'`.)

- [ ] **Step 4:** `node tests/rangka/test_support_irisan.mjs` → PASS semua; `node --check public/js/denah-editor.js`; **semua 16 test .mjs rangka lama tetap hijau** (`for f in tests/rangka/*.mjs; do node "$f"; done`). PERHATIAN: test lama yang meng-assert `nama:'S'` utk locked (grep dulu `test_support_lock/pick/jalur_manual`) — kalau ada, sesuaikan ke `'S{no}'` DENGAN komentar alasan (peningkatan penamaan disengaja Task 3).

- [ ] **Step 5:** Daftarkan test di manifest; `php scripts/canopi-check --full` hijau → commit `feat(denah): pemecahan ruas support beririsan di buildMembers (Task 3)`.

---

### Task 4: Editor — tapakMap, UI pilihan, badge konflik

**Files:**
- Modify: `public/js/denah-editor.js` — constructor (~597), shellHTML rowSupArah (~894), `renderSupportPanel` (~1894, pola `slAktif` baris 1999/2025), semua call-site internal `DenahConv.buildMembers(this.S)` / `(S)` (grep dulu, ganti ke helper), `getMembers()` (~3355)

**Interfaces:**
- Consumes: `opts.besi[{nama,lebar,tinggi}]` (Task 2), `buildMembers(S, tapakMap, warnsOut)` + `irisanKonflik` (Task 3).
- Produces: `this._tapakMap()` → `{nama:{l,t}}` dari `this.besi`; `this._members(warnsOut?)` → buildMembers dgn tapak; `this.getWarns()` → array string (warnings buildMembers terakhir + konflik putus×putus, utk Task 5); `getMembers()` publik ikut memakai tapak.

- [ ] **Step 1:** Constructor: `this.tapakMap = {}; (this.besi||[]).forEach(b => { if (b.nama) this.tapakMap[b.nama] = { l: +b.lebar || 0, t: +b.tinggi || 0 }; }); this._lastWarns = [];` Helper:

```js
  _members() {
    const w = [];
    const mem = DenahConv.buildMembers(this.S, this.tapakMap, w);
    const kf = DenahConv.irisanKonflik(this.S);
    kf.forEach(k => w.push(`S${k.a} × S${k.b} dua-duanya PUTUS bersilangan — mustahil fisik, keduanya dibiarkan utuh`));
    this._lastWarns = w;
    return mem;
  }
  getWarns() { return this._lastWarns.slice(); }
```

Ganti SEMUA `DenahConv.buildMembers(this.S)` internal & `getMembers()` ke `this._members()` (grep `buildMembers(this.S)` + `buildMembers(S)` di dalam class; JANGAN sentuh pemanggilan di DenahConv sendiri/test).

- [ ] **Step 2: UI baris baru di shellHTML** — SETELAH `rowSupArah` (baris ~899), SELALU tampil di tab Support (pratinjau & terkunci — jangan ikut disembunyikan oleh logika baris 1556):

```html
      <div class="de-row" data-role="rowSupIris" style="margin-top:8px">
        <label>Menerus<select data-role="inMenerus"><option value="2">Dua arah (tumpuk)</option><option value="h">Horizontal saja</option><option value="v">Vertikal saja</option></select></label>
        <label>Pasang<select data-role="inOrientasi"><option value="berdiri">Berdiri</option><option value="tidur">Tidur</option></select></label>
      </div>
```

Wiring (di `_wireControls`, pola `inArah`): change → `this.pushUndo();` set `this.S.supMenerus = {h: v!=='v', v: v!=='h'}` / `this.S.orientasi`; `this.render();` (render → `_members` → onChange jalur lama). `setModel`/load: sinkronkan nilai select dari S (`supMenerusOf`).

- [ ] **Step 3: Per-jalur di panel terkunci** — di `renderSupportPanel` baris entri (sebelah pola `slAktif` ~1999), tambah select mini:

```js
  <select data-role="slMenerus" data-no="${e2.no}" style="font-size:12px;padding:2px">
    <option value="">bawaan</option>
    <option value="1"${e2.menerus === true ? ' selected' : ''}>menerus</option>
    <option value="0"${e2.menerus === false ? ' selected' : ''}>putus</option>
  </select>
```

handler (sebelah handler slAktif ~2025): `pushUndo()`; set/hapus `e.menerus` (`'' → delete`, `'1' → true`, `'0' → false`); `render()`.

- [ ] **Step 4: Badge konflik/warning** — di `renderSupportPanel` header panel: kalau `this._lastWarns.length`, render `<div style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;border-radius:6px;padding:4px 8px;font-size:11px;margin-bottom:6px">` berisi tiap warn satu baris. (Warna terang di panel putih — patuhi pelajaran mode gelap: set color eksplisit.)

- [ ] **Step 5:** `node --check` + seluruh .mjs rangka + `canopi-check --full` hijau → commit `feat(denah): UI menerus/orientasi + override per jalur + badge konflik (Task 4)`.

---

### Task 5: Peringatan mengalir ke cutting list

**Files:**
- Modify: `resources/views/rab-opsi/index.blade.php` — `bacaBlok` bagian denah (dekat `b.members`, ~baris 710): `b.denah_warns = ed ? ed.getWarns() : [];`
- Modify: `resources/views/cutting/index.blade.php` — form manual: hidden `warns` diisi `JSON.stringify(ED.getWarns())` di `bukaCutting()`
- Modify: `app/Http/Controllers/CuttingController.php` — `cuttingDenahManual` baca `warns` (json array string, maks 20 item × 200 char, sanitasi ke string); `blokDenahDariSnapshot` path (`renderCuttingDenah`): kumpulkan `denah_warns` tiap blok → gabung ke `$peringatan` (pisah `<br>` — view pakai `{{ }}`, jadi kirim array; lihat langkah view)
- Modify: `app/Services/RangkaDesignService.php::blokDenahDariSnapshot` — ikutkan `'warns' => array_slice(array_map('strval', (array)($b['denah_warns'] ?? [])), 0, 20)` di tiap elemen hasil
- Modify: `resources/views/cutting/print-denah.blade.php` — `$peringatan` jadi bisa array: render tiap item baris sendiri dalam `.warnbox`; per blok, render `$bd['warns']` (kalau ada) sebagai warnbox kecil di bawah judul blok
- Modify: `tests/rangka/test_cutting_snapshot.php` — assert `warns` diteruskan

**Interfaces:**
- Consumes: `ed.getWarns()` (Task 4).
- Produces: elemen hasil `blokDenahDariSnapshot` bertambah kunci `warns: string[]`.

- [ ] **Step 1:** Tambah assert di `test_cutting_snapshot.php`: blok dgn `'denah_warns' => ['tapak besi "X" belum diketahui']` → hasil `['warns'][0]` sama; blok tanpa field → `warns === []`. Jalankan → FAIL.
- [ ] **Step 2:** Implementasi keempat file sesuai daftar. Jalankan test → PASS.
- [ ] **Step 3:** `canopi-check --full` hijau → commit `feat(cutting): peringatan irisan ikut ke cutting list (Task 5)`.

---

### Task 6: Penutup — status, checklist manual Elvan

**Files:**
- Modify: `CLAUDE.md` (Utang aktif: tambah entri fitur irisan LIVE + checklist validasi; rujuk spec/plan)
- Modify: `docs/superpowers/specs/2026-08-30-denah-support-irisan-design.md` (Status → LIVE, tanggal)

- [ ] **Step 1:** Update kedua dokumen. Checklist manual utk Elvan (masuk CLAUDE.md):
  1. Master Material → edit satu hollow → isi lebar/tinggi profil → simpan; baris tanpa profil ber-badge kuning.
  2. Denah baru → tab Support → "Menerus: Horizontal saja" → kunci → ruas vertikal terpecah, panjang < jarak as (cek angka vs manual).
  3. Satu jalur di-set "menerus" dari panel → jalur itu utuh lagi; Undo mengembalikan.
  4. Dua jalur silang di-set putus → badge kuning muncul; cutting list menampilkan peringatan yang sama.
  5. "Pasang: Tidur" → ruas memendek lagi (tapak sisi besar).
  6. Buka RAB lama (sebelum fitur) → angka besi TIDAK berubah sama sekali.
- [ ] **Step 2:** `canopi-check --full` → commit `docs: fitur irisan selesai (Task 6)` → push.

---

## Self-review (sudah dijalankan penulis plan)

- Spec coverage: §1a→Task 1-2; §1b+§2→Task 3; §3 UI→Task 4 (+badge Master Material di Task 1); peringatan cutting list→Task 5; §4 testing→test di Task 1/3/5 + regresi .mjs; §5 out-of-scope dihormati (tak ada saran otomatis/pengelompokan).
- Konsistensi nama: `buildMembers(S, tapakMap, warnsOut)`, `tapakCm`, `efektifMenerus`, `supMenerusOf`, `irisanKonflik`, `_members`, `getWarns`, `denah_warns`, kolom `lebar_profil_cm`/`tinggi_profil_cm` — dipakai seragam lintas task.
- Placeholder: satu blok di Task 3 Step 3 sengaja berupa kerangka post-step dgn instruksi presisi (aturan pemecahan lengkap ada di header task — implementer menulis loop-nya dari aturan itu + test yang mengunci angka pasti).
