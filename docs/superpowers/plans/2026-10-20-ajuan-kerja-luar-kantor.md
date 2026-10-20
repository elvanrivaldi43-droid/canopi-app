# Ajuan Kerja di Luar Kantor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin Operasional (2) & Admin Toko Besi (7) bisa mengajukan sendiri kerja di luar kantor untuk hari ini (setelah absen masuk); setelah disetujui Owner/Admin Operasional lain, lapor siang/kembali kerja/pulang hari itu bebas lokasi.

**Architecture:** Pakai ulang tabel `luar_kota`: ajuan = baris satu-hari berstatus `menunggu` → `aktif` (disetujui) / `ditolak`. Semua pengecualian GPS sudah lewat `LuarKota::sedangLuarKota()` yang hanya membaca `aktif`, jadi `AbsensiController` bagian absen tidak disentuh. Aturan boleh/tidak = 2 fungsi statis murni di `LuarKota` yang dites tanpa DB.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, tes PHP polos (`php tests/...`) terdaftar di `tests/guardrail/manifest.json`, `php scripts/canopi-check --full`.

**Spec:** `docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md`

## Global Constraints

- Yang boleh mengajukan: level yang ada di `AbsensiController::LEVEL_GPS_SIANG` (= `[2, 7]`).
- Penyetuju: level 1 & 2 (middleware `level:1,2`); tak boleh memproses ajuan sendiri (dicek di server).
- Ajuan hanya untuk hari ini: `tanggal_mulai = tanggal_selesai = today()`; hanya bisa diproses di tanggal itu.
- Tanpa kolom/tabel baru. `dibuat_oleh` = penyetuju/penolak setelah diproses; `keterangan` = alasan tolak.
- Route absensi karyawan TIDAK boleh diberi middleware `level:` (tes regresi di `tests/kerja-hari-libur/test_route_middleware.php`).
- JANGAN pakai emoji di file blade baru/ubahan (korup di server — CLAUDE.md). Pakai teks/SVG.
- Fitur TIDAK di-push sebelum Task 0 (prasyarat DB) beres.
- Tiap tes baru didaftarkan di `tests/guardrail/manifest.json` (urut abjad path), kalau tidak `test_manifest.php` gagal.

## Review Focus

1. Tap dobel tombol Ajukan → dua baris `menunggu`; harus satu. (Task 2: tombol di-disable saat submit; server cek `adaAktifAtauMenunggu`.)
2. Admin Operasional menyetujui ajuannya sendiri lewat POST langsung → harus ditolak server. (Task 1 tes `alasanTolakProses`.)
3. Ajuan kemarin yang tak diproses lalu disetujui hari ini → harus ditolak "kedaluwarsa". (Task 1 tes.)
4. Ajuan `menunggu`/`ditolak` tidak boleh membebaskan GPS. (Task 1 tes regresi scope.)
5. Supervisor (level 3) bisa membuka `/luar-kota` → melihat ajuan tapi POST setujui/tolak harus 403. (Task 3 tes route.)

---

### Task 0: Prasyarat database (DIKERJAKAN ELVAN, blocking push)

**Files:**
- Create (hanya bila hasil `enum`): `docs/sql/2026-10-20-luar-kota-status-ajuan.sql`

- [ ] **Step 1: Elvan menjalankan di phpMyAdmin (`u8221523_canopi_new`, tab SQL)**

```sql
SHOW COLUMNS FROM luar_kota LIKE 'status';
```

- [ ] **Step 2: Putuskan dari kolom Type**

- `varchar(...)` → tak perlu apa-apa, tandai Task 0 selesai.
- `enum(...)` → buat file SQL berikut, salin SEMUA nilai lama persis dari hasil SHOW lalu tambah `'menunggu','ditolak'`. Contoh bila hasilnya `enum('aktif','selesai','dibatalkan')` dengan default `aktif`:

```sql
-- docs/sql/2026-10-20-luar-kota-status-ajuan.sql
-- Tambah status ajuan kerja di luar kantor. Aman diulang (MODIFY ke definisi yang sama).
ALTER TABLE luar_kota
  MODIFY status ENUM('aktif','selesai','dibatalkan','menunggu','ditolak') NOT NULL DEFAULT 'aktif';
```

Elvan menjalankannya di phpMyAdmin, lalu cek ulang `SHOW COLUMNS` memuat `menunggu` & `ditolak`.

- [ ] **Step 3: Commit (bila ada file SQL)**

```bash
git add docs/sql/2026-10-20-luar-kota-status-ajuan.sql
git commit -m "docs(sql): status menunggu & ditolak untuk luar_kota"
```

---

### Task 1: Aturan murni di model `LuarKota` + label status

**Files:**
- Modify: `app/Models/LuarKota.php` (tambah 2 fungsi statis; `labelStatus()`/`warnaStatus()` tambah 2 status)
- Create: `tests/absensi/test_ajuan_luar_kantor.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Produces:
  - `LuarKota::bolehAjukan(int $level, bool $sudahMasuk, bool $sudahPulang, bool $adaAktifAtauMenunggu): ?string` — `null` = boleh, selain itu pesan penolakan.
  - `LuarKota::alasanTolakProses(string $status, string $tanggal, int $pemilikId, int $aktorId, string $hariIni): ?string` — tanggal format `Y-m-d`; `null` = boleh disetujui/ditolak.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php
// FILE: tests/absensi/test_ajuan_luar_kantor.php
// Jalankan: php tests/absensi/test_ajuan_luar_kantor.php
// Spec: docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md
require __DIR__ . '/../bootstrap.php';

use App\Models\LuarKota;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};
$boleh = fn (?string $alasan) => $alasan === null;

// ── bolehAjukan(level, sudahMasuk, sudahPulang, adaAktifAtauMenunggu) ──
$check('admin ops, sudah masuk -> boleh',        $boleh(LuarKota::bolehAjukan(2, true, false, false)), true);
$check('toko besi, sudah masuk -> boleh',        $boleh(LuarKota::bolehAjukan(7, true, false, false)), true);
foreach ([1, 3, 4, 5, 6] as $lv) {
    $check("level $lv -> ditolak (bukan level wajib GPS)", $boleh(LuarKota::bolehAjukan($lv, true, false, false)), false);
}
$check('belum absen masuk -> ditolak',           $boleh(LuarKota::bolehAjukan(2, false, false, false)), false);
$check('sudah absen pulang -> ditolak',          $boleh(LuarKota::bolehAjukan(2, true, true, false)), false);
$check('sudah ada aktif/menunggu -> ditolak',    $boleh(LuarKota::bolehAjukan(2, true, false, true)), false);

// ── alasanTolakProses(status, tanggal, pemilikId, aktorId, hariIni) ──
$h = '2026-10-20';
$check('menunggu, hari ini, orang lain -> boleh',  $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 9, $h)), true);
$check('ajuan sendiri -> ditolak',                 $boleh(LuarKota::alasanTolakProses('menunggu', $h, 5, 5, $h)), false);
$check('ajuan kemarin -> ditolak (kedaluwarsa)',   $boleh(LuarKota::alasanTolakProses('menunggu', '2026-10-19', 5, 9, $h)), false);
foreach (['aktif', 'ditolak', 'selesai', 'dibatalkan'] as $st) {
    $check("status $st -> ditolak (sudah diproses)", $boleh(LuarKota::alasanTolakProses($st, $h, 5, 9, $h)), false);
}

// ── Regresi: hanya status `aktif` yang membebaskan GPS ──
$q = LuarKota::query()->aktifPadaTanggal();
$check('scope aktifPadaTanggal memfilter status', str_contains($q->toSql(), '`status` = ?'), true);
$check('scope aktifPadaTanggal binding status = aktif', $q->getBindings()[0] ?? null, 'aktif');

// ── Label ──
$check('label menunggu', (new LuarKota(['status' => 'menunggu']))->labelStatus(), 'Menunggu');
$check('label ditolak',  (new LuarKota(['status' => 'ditolak']))->labelStatus(), 'Ditolak');

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
```

Tambahkan ke `tests/guardrail/manifest.json` tepat SEBELUM entri `tests/absensi/test_gate_buka_absen.php`:

```json
    {
      "path": "tests/absensi/test_ajuan_luar_kantor.php",
      "runner": "php",
      "requires_db": false,
      "manual": false
    },
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php tests/absensi/test_ajuan_luar_kantor.php`
Expected: Error `Call to undefined method App\Models\LuarKota::bolehAjukan()`

- [ ] **Step 3: Implementasi minimal di `app/Models/LuarKota.php`**

Tambahkan sebelum `// Static: cek apakah user sedang luar kota hari ini` (atau sebelum `sedangLuarKota`):

```php
    // ─── Ajuan kerja di luar kantor (spec 2026-10-20) ──────────────────
    // Hanya level yang wajib GPS siang yang perlu (dan boleh) minta pengecualian.
    public static function bolehAjukan(int $level, bool $sudahMasuk, bool $sudahPulang, bool $adaAktifAtauMenunggu): ?string
    {
        if (!\App\Http\Controllers\AbsensiController::gpsSiangWajib($level)) {
            return 'Fitur ini khusus Admin Operasional & Admin Toko Besi.';
        }
        if (!$sudahMasuk)          return 'Absen masuk dulu di kantor, baru ajukan kerja di luar kantor.';
        if ($sudahPulang)          return 'Kamu sudah absen pulang hari ini.';
        if ($adaAktifAtauMenunggu) return 'Hari ini sudah ada ajuan atau mode luar kota.';
        return null;
    }

    public static function alasanTolakProses(string $status, string $tanggal, int $pemilikId, int $aktorId, string $hariIni): ?string
    {
        if ($status !== 'menunggu')  return 'Ajuan ini sudah diproses.';
        if ($tanggal !== $hariIni)   return 'Ajuan sudah kedaluwarsa (bukan untuk hari ini).';
        if ($pemilikId === $aktorId) return 'Tidak bisa memproses ajuan sendiri.';
        return null;
    }
```

Di `labelStatus()` dan `warnaStatus()` tambahkan sebelum `default`:

```php
            'menunggu'   => 'Menunggu',
            'ditolak'    => 'Ditolak',
```

```php
            'menunggu'   => '#3b82f6',
            'ditolak'    => '#ef4444',
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php tests/absensi/test_ajuan_luar_kantor.php`
Expected: `SEMUA PASS`. (Bila 2 cek scope gagal karena format `toSql()` berbeda, cetak `$q->toSql()` dan sesuaikan string pembanding — JANGAN ubah scope.)

- [ ] **Step 5: Commit**

```bash
git add app/Models/LuarKota.php tests/absensi/test_ajuan_luar_kantor.php tests/guardrail/manifest.json
git commit -m "feat(luar-kota): aturan ajuan kerja di luar kantor + status menunggu/ditolak"
```

---

### Task 2: Karyawan mengajukan dari halaman Absensi

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (`index()` kirim `$ajuanLuar`; method baru `ajukanLuarKantor`)
- Modify: `routes/web.php` (grup `absensi`, tanpa `level:`)
- Modify: `resources/views/absensi/index.blade.php` (blok setelah `@endif` rantai tombol aksi, ±baris 170)
- Create: `tests/keamanan/test_akses_luar_kota.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces:**
- Consumes: `LuarKota::bolehAjukan(...)`, `AbsensiController::gpsSiangWajib(int): bool`
- Produces: route `absensi.ajukan-luar-kantor` (POST, field `lokasi`); variabel view `$ajuanLuar` (`?LuarKota`, status `menunggu`/`ditolak` hari ini)

- [ ] **Step 1: Tulis tes route yang gagal**

```php
<?php
// FILE: tests/keamanan/test_akses_luar_kota.php
// Jalankan: php tests/keamanan/test_akses_luar_kota.php
// Spec: docs/superpowers/specs/2026-10-20-ajuan-kerja-luar-kantor-design.md
$base = dirname(__DIR__, 2);

$fail  = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$routes = json_decode((string) shell_exec('cd ' . escapeshellarg($base) . ' && php artisan route:list --json 2>/dev/null'), true);
if (!is_array($routes)) { echo "FAIL — tidak bisa membaca route:list\n"; exit(1); }
$r = [];
foreach ($routes as $x) if (!empty($x['name'])) $r[$x['name']] = $x;
$mw = fn (string $n) => $r[$n]['middleware'] ?? ['<ROUTE TIDAK ADA>'];

// Mengajukan: semua karyawan login (aturan level dicek di server via LuarKota::bolehAjukan)
$check('ajukan-luar-kantor terdaftar', isset($r['absensi.ajukan-luar-kantor']), true);
$check('ajukan-luar-kantor POST', $r['absensi.ajukan-luar-kantor']['method'] ?? null, 'POST');
$check('ajukan-luar-kantor dilindungi auth', in_array('auth', $mw('absensi.ajukan-luar-kantor'), true), true);
$adaLevel = false;
foreach ($mw('absensi.ajukan-luar-kantor') as $m) if (str_starts_with((string) $m, 'level:')) $adaLevel = true;
$check('ajukan-luar-kantor TIDAK dikunci level', $adaLevel, false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
```

Daftarkan di manifest tepat SEBELUM entri `tests/keamanan/test_akses_payroll_rekap.php` (setelah `test_akses_izin.php`):

```json
    {
      "path": "tests/keamanan/test_akses_luar_kota.php",
      "runner": "php",
      "requires_db": false,
      "manual": false
    },
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php tests/keamanan/test_akses_luar_kota.php`
Expected: `FAIL — ajukan-luar-kantor terdaftar`

- [ ] **Step 3: Route** — di grup `absensi` (`routes/web.php`), setelah baris `cek-gps`:

```php
    // Ajuan kerja di luar kantor (Admin Ops & Toko Besi). Sengaja tanpa level: — aturan di LuarKota::bolehAjukan.
    Route::post('/ajukan-luar-kantor',      [AbsensiController::class, 'ajukanLuarKantor'])->name('ajukan-luar-kantor');
```

- [ ] **Step 4: Controller** — di `AbsensiController.php`, pastikan `use App\Models\User;` ada (tambah bila belum). Di `index()` ganti baris return dengan:

```php
        $ajuanLuar     = LuarKota::where('user_id', $user->id)
            ->whereDate('tanggal_mulai', today())
            ->whereIn('status', ['menunggu', 'ditolak'])
            ->latest('id')->first();

        return view('absensi.index', compact('user','absenHariIni','riwayat','stats','fase','luarKotaAktif','ajuanLuar'));
```

Tambah method baru setelah `cekGps()`/`gpsSiangWajib()`:

```php
    public function ajukanLuarKantor(Request $request)
    {
        $user = Auth::user();
        $request->validate(['lokasi' => 'required|string|max:255']);

        $absen = Absensi::where('user_id', $user->id)->whereDate('tanggal', today())->first();
        $ada   = LuarKota::where('user_id', $user->id)
            ->whereIn('status', ['aktif', 'menunggu'])
            ->whereDate('tanggal_mulai', '<=', today())
            ->whereDate('tanggal_selesai', '>=', today())
            ->exists();

        if ($alasan = LuarKota::bolehAjukan((int) $user->level, (bool) $absen?->jam_masuk, (bool) $absen?->jam_pulang, $ada)) {
            return back()->with('error', $alasan);
        }

        LuarKota::create([
            'user_id'         => $user->id,
            'dibuat_oleh'     => $user->id,
            'tanggal_mulai'   => today(),
            'tanggal_selesai' => today(),
            'lokasi'          => $request->lokasi,
            'status'          => 'menunggu',
        ]);

        $penerima = User::whereIn('level', [1, 2])->where('status', 'aktif')
            ->where('id', '!=', $user->id)->whereNotNull('telegram_chat_id')->get();
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,
                "📍 *AJUAN KERJA DI LUAR KANTOR*\n"
                . "Dari: {$user->name}\n"
                . "Tujuan: {$request->lokasi}\n"
                . "Tanggal: " . today()->format('d/m/Y') . "\n"
                . "---\n"
                . "Setujui/tolak di: app.kanopibsd.co.id/luar-kota");
        }

        return back()->with('success', 'Ajuan terkirim. Tunggu persetujuan Owner/Admin.');
    }
```

(Cek `use App\Services\TelegramService;` ada di atas file; tambah bila belum. Emoji di string PHP Telegram boleh — larangan emoji hanya untuk blade.)

- [ ] **Step 5: View** — di `resources/views/absensi/index.blade.php`, tepat SETELAH `@endif` penutup rantai tombol aksi (yang diikuti `</div>` lalu `{{-- Stats Bulan Ini --}}`), sebelum `</div>`:

```blade
        @if(\App\Http\Controllers\AbsensiController::gpsSiangWajib((int) $user->level) && $absenHariIni?->jam_masuk && !$absenHariIni->jam_pulang && !$luarKotaAktif)
        <div style="margin-top:12px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);">
            @if($ajuanLuar?->status === 'menunggu')
            <div style="font-size:13px;color:#93C5FD;">
                Ajuan kerja di luar kantor ({{ $ajuanLuar->lokasi }}) menunggu persetujuan Owner/Admin.
            </div>
            @else
                @if($ajuanLuar?->status === 'ditolak')
                <div style="font-size:12px;color:#FCA5A5;margin-bottom:8px;">
                    Ajuan sebelumnya ditolak: {{ $ajuanLuar->keterangan }}
                </div>
                @endif
            <form method="POST" action="{{ route('absensi.ajukan-luar-kantor') }}" onsubmit="this.querySelector('button').disabled=true">
                @csrf
                <label for="lokasiLuar" style="display:block;font-size:12px;color:#94A3B8;margin-bottom:6px;">Perlu kerja di luar kantor hari ini?</label>
                <input id="lokasiLuar" name="lokasi" required maxlength="255" placeholder="Tujuan, mis. ke supplier besi Cikupa"
                       style="width:100%;box-sizing:border-box;padding:10px;border-radius:8px;border:1px solid #334155;background:#0F172A;color:#E2E8F0;font-size:14px;margin-bottom:8px;">
                <button type="submit"
                        style="width:100%;padding:12px;border-radius:10px;border:none;font-weight:700;font-size:14px;color:#0F1117;background:#93C5FD;min-height:44px;cursor:pointer;">
                    Ajukan Kerja di Luar Kantor
                </button>
            </form>
            @endif
        </div>
        @endif
```

- [ ] **Step 6: Jalankan tes + cek penuh**

Run: `php tests/keamanan/test_akses_luar_kota.php && php scripts/canopi-check --full`
Expected: `SEMUA PASS` lalu `PASS: canopi-check full selesai` (termasuk `test_route_middleware.php` yang menjaga route absen tak dikunci level, dan Blade compile).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php routes/web.php resources/views/absensi/index.blade.php tests/keamanan/test_akses_luar_kota.php tests/guardrail/manifest.json
git commit -m "feat(absensi): admin & toko besi ajukan kerja di luar kantor (hari ini)"
```

---

### Task 3: Owner/Admin menyetujui atau menolak di halaman Luar Kota

**Files:**
- Modify: `app/Http/Controllers/LuarKotaController.php` (`index()` kirim `$ajuanMenunggu`; method `setujui`, `tolak`)
- Modify: `routes/web.php` (di dalam grup `luar-kota`, middleware tambahan `level:1,2`)
- Modify: `resources/views/luar-kota/index.blade.php` (bagian "Ajuan Menunggu" sebelum `{{-- Sedang luar kota hari ini --}}`; tampilkan `session('error')`)
- Modify: `tests/keamanan/test_akses_luar_kota.php`

**Interfaces:**
- Consumes: `LuarKota::alasanTolakProses(...)`, `LuarKota::labelStatus()`
- Produces: route `luar-kota.setujui` (POST `/luar-kota/{id}/setujui`), `luar-kota.tolak` (POST `/luar-kota/{id}/tolak`, field `alasan`)

- [ ] **Step 1: Tambah tes route yang gagal** — di `tests/keamanan/test_akses_luar_kota.php`, sebelum baris `echo $fail ? ...`:

```php
// Memproses: Owner & Admin Operasional saja (Supervisor level 3 boleh LIHAT halaman, tak boleh proses)
foreach (['luar-kota.setujui', 'luar-kota.tolak'] as $n) {
    $check("$n terdaftar", isset($r[$n]), true);
    $check("$n POST", $r[$n]['method'] ?? null, 'POST');
    $check("$n dikunci level:1,2", in_array('level:1,2', $mw($n), true), true);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php tests/keamanan/test_akses_luar_kota.php`
Expected: `FAIL — luar-kota.setujui terdaftar`

- [ ] **Step 3: Route** — di grup `luar-kota` (`routes/web.php`), setelah baris `batalkan`:

```php
    // Ajuan kerja di luar kantor: Owner & Admin Ops saja; "bukan ajuan sendiri" dicek di controller.
    Route::post('/{id}/setujui', [App\Http\Controllers\LuarKotaController::class, 'setujui'])->middleware('level:1,2')->name('setujui');
    Route::post('/{id}/tolak',   [App\Http\Controllers\LuarKotaController::class, 'tolak'])->middleware('level:1,2')->name('tolak');
```

- [ ] **Step 4: Controller** — `LuarKotaController.php`. Di `index()` sebelum `return view(...)`:

```php
        // Ajuan kerja di luar kantor; 7 hari ke belakang cukup (yang lewat tampil "Kedaluwarsa")
        $ajuanMenunggu = LuarKota::with('karyawan')
            ->where('status', 'menunggu')
            ->whereDate('tanggal_mulai', '>=', today()->subDays(7))
            ->orderByDesc('tanggal_mulai')
            ->get();
```

dan ubah return menjadi `return view('luar-kota.index', compact('daftar', 'status', 'sedangLuarKota', 'ajuanMenunggu'));`

Tambah method setelah `batalkan()`:

```php
    // ─── AJUAN KERJA DI LUAR KANTOR: setujui / tolak ───────────────────
    public function setujui($id)
    {
        $lk = LuarKota::with('karyawan')->findOrFail($id);
        if ($alasan = LuarKota::alasanTolakProses($lk->status, $lk->tanggal_mulai->toDateString(), (int) $lk->user_id, (int) Auth::id(), today()->toDateString())) {
            return back()->with('error', $alasan);
        }

        $lk->update(['status' => 'aktif', 'dibuat_oleh' => Auth::id()]);

        app(TelegramService::class)->kirim($lk->karyawan?->telegram_chat_id,
            "✅ *AJUAN KERJA DI LUAR KANTOR DISETUJUI*\n"
            . "Tujuan: {$lk->lokasi}\n"
            . "Hari ini lapor siang, lanjut kerja, dan absen pulang bebas lokasi (GPS tetap dicatat).");

        return back()->with('success', "Ajuan {$lk->karyawan?->name} disetujui.");
    }

    public function tolak(Request $request, $id)
    {
        $request->validate(['alasan' => 'required|string|max:255']);

        $lk = LuarKota::with('karyawan')->findOrFail($id);
        if ($alasan = LuarKota::alasanTolakProses($lk->status, $lk->tanggal_mulai->toDateString(), (int) $lk->user_id, (int) Auth::id(), today()->toDateString())) {
            return back()->with('error', $alasan);
        }

        $lk->update(['status' => 'ditolak', 'dibuat_oleh' => Auth::id(), 'keterangan' => $request->alasan]);

        app(TelegramService::class)->kirim($lk->karyawan?->telegram_chat_id,
            "❌ *AJUAN KERJA DI LUAR KANTOR DITOLAK*\n"
            . "Tujuan: {$lk->lokasi}\n"
            . "Alasan: {$request->alasan}");

        return back()->with('success', "Ajuan {$lk->karyawan?->name} ditolak.");
    }
```

- [ ] **Step 5: View** — `resources/views/luar-kota/index.blade.php`. Tepat setelah blok `@if(session('success')) ... @endif` (±baris 108-110) tambahkan:

```blade
    @if(session('error'))
    <div class="alert-success" style="background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.3);color:#FCA5A5;">{{ session('error') }}</div>
    @endif
```

Tepat SEBELUM `{{-- Sedang luar kota hari ini --}}` tambahkan:

```blade
    {{-- Ajuan kerja di luar kantor (Admin Ops & Toko Besi) --}}
    @if($ajuanMenunggu->count() > 0)
    <div class="hari-ini-card">
        <div class="hari-ini-title">Ajuan Kerja di Luar Kantor ({{ $ajuanMenunggu->count() }})</div>
        @foreach($ajuanMenunggu as $aj)
        @php
            $kedaluwarsa = !$aj->tanggal_mulai->isToday();
            $bisaProses  = !$kedaluwarsa && in_array((int) auth()->user()->level, [1, 2]) && (int) $aj->user_id !== (int) auth()->id();
        @endphp
        <div style="padding:10px 0;border-top:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:14px;font-weight:600;">{{ $aj->karyawan?->name }} — {{ $aj->lokasi }}</div>
            <div style="font-size:12px;color:#94A3B8;">
                {{ $aj->tanggal_mulai->format('d/m/Y') }} · {{ $kedaluwarsa ? 'Kedaluwarsa' : 'Menunggu' }}
            </div>
            @if($bisaProses)
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <form method="POST" action="{{ route('luar-kota.setujui', $aj->id) }}" onsubmit="this.querySelector('button').disabled=true">
                    @csrf
                    <button type="submit" style="padding:8px 14px;border-radius:8px;border:none;background:#22c55e;color:#0F1117;font-weight:700;min-height:40px;">Setujui</button>
                </form>
                <form method="POST" action="{{ route('luar-kota.tolak', $aj->id) }}" style="display:flex;gap:6px;flex:1;min-width:220px;" onsubmit="this.querySelector('button').disabled=true">
                    @csrf
                    <input name="alasan" required maxlength="255" placeholder="Alasan tolak" aria-label="Alasan tolak"
                           style="flex:1;padding:8px;border-radius:8px;border:1px solid #334155;background:#0F172A;color:#E2E8F0;">
                    <button type="submit" style="padding:8px 14px;border-radius:8px;border:none;background:#ef4444;color:white;font-weight:700;min-height:40px;">Tolak</button>
                </form>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
```

Juga tambahkan dua tombol filter status, setelah link filter `dibatalkan`:

```blade
        <a href="{{ route('luar-kota.index', ['status'=>'ditolak']) }}"
           class="filter-btn {{ $status=='ditolak'?'active':'' }}">Ditolak</a>
```

- [ ] **Step 6: Jalankan tes + cek penuh**

Run: `php tests/keamanan/test_akses_luar_kota.php && php tests/absensi/test_ajuan_luar_kantor.php && php scripts/canopi-check --full`
Expected: semua `SEMUA PASS`, `PASS: canopi-check full selesai`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/LuarKotaController.php routes/web.php resources/views/luar-kota/index.blade.php tests/keamanan/test_akses_luar_kota.php
git commit -m "feat(luar-kota): owner & admin setujui/tolak ajuan kerja di luar kantor"
```

---

### Task 4: Rilis + validasi nyata + catatan status

**Files:**
- Modify: `CLAUDE.md` (bagian "Utang aktif / resume point": titik lanjut 20 Okt)

- [ ] **Step 1: Pastikan Task 0 selesai** (hasil SHOW `varchar`, atau SQL ENUM sudah dijalankan Elvan). Kalau belum → STOP, jangan push.

- [ ] **Step 2: Push & cek deploy**

```bash
git pull --ff-only && php scripts/canopi-check --full && git push
curl -s "https://api.github.com/repos/elvanrivaldi43-droid/canopi-app/actions/runs?per_page=1" | grep -E '"(head_sha|status|conclusion)"'
```
Expected: `head_sha` = HEAD, `completed`, `success`. Lalu `curl -s -o /dev/null -w "%{http_code}\n" -X POST https://app.kanopibsd.co.id/luar-kota/1/setujui` → `419` atau `302` (route ada, CSRF/login menjaga), BUKAN `404`.

- [ ] **Step 3: Checklist validasi Elvan di HP** (tulis di CLAUDE.md, dicentang Elvan)

1. Akun Admin/Toko Besi, sudah absen masuk → tombol "Ajukan Kerja di Luar Kantor" muncul; isi tujuan → pesan sukses; Owner dapat Telegram.
2. Sebelum disetujui, lapor siang dari luar kantor → masih "Di luar radius!" (benar).
3. Owner buka `/luar-kota` → bagian Ajuan → Setujui → karyawan dapat Telegram; lapor siang dari luar → "Lokasi valid".
4. Ajuan kedua: Tolak tanpa alasan → form menolak; dengan alasan → karyawan melihat alasannya di halaman Absensi.
5. Besok: tak ada tombol/status sisa, GPS kembali wajib.

- [ ] **Step 4: Update CLAUDE.md & commit**

Tambah di atas `**>>> TITIK LANJUT 30 Sep 2026` sebuah blok `**>>> TITIK LANJUT 20 Okt 2026 <<<**` berisi: 3 commit hari ini (`16d129a` lapor siang bebas lokasi kecuali level 2/7; `b574d1b` tambal approval izin & dinas luar → level:1,3; fitur ajuan kerja di luar kantor), checklist Step 3, dan catatan "butir 1 & 2 titik lanjut 30 Sep sudah divalidasi Elvan 20 Okt".

```bash
git add CLAUDE.md && git commit -m "docs: titik lanjut 20 Okt (ajuan kerja di luar kantor live, checklist validasi)" && git push
```
