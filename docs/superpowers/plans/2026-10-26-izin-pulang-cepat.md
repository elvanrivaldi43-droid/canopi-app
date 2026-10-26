# Izin Pulang Cepat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pulang sebelum jam pulang jadwal = setengah hari, kecuali diizinkan atasan dan pulang >= 14:00; denda checkpoint tak dicatat setelah pulang.

**Architecture:** Aturan = fungsi statis murni (dites tanpa DB) di `AbsensiController` & `Absensi`. Ajuan disimpan di 3 kolom baru baris `absensi` hari itu. Persetujuan di halaman Persetujuan Izin yang sudah `level:1,3`.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, MySQL (SQL manual phpMyAdmin), tes skrip PHP polos + `tests/guardrail/manifest.json`.

**Spec:** `docs/superpowers/specs/2026-10-26-izin-pulang-cepat-design.md`

## Global Constraints

- Tanpa toleransi menit: pulang < `users.jam_pulang` (fallback `'17:00'`) = pulang cepat.
- Gaji penuh hanya bila diizinkan DAN pulang >= `14:00`.
- Penyetuju: level 5/6 → Owner (1) atau Mandor (3); level 2/3/4/7 → Owner saja; tak pernah diri sendiri.
- Persetujuan ditolak bila karyawan sudah absen pulang (darurat = Koreksi Owner).
- Tidak ada emoji di blade/pesan baru (aturan CLAUDE.md).
- SQL idempotent, dijalankan Elvan SEBELUM push.
- Tes baru WAJIB didaftarkan di `tests/guardrail/manifest.json` (urutan abjad di blok `tests/absensi/`), commit yang sama.
- `php scripts/canopi-check --full` hijau sebelum push.

## Review Focus

1. Jam pulang tepat sama dengan jadwal (`17:00` vs `17:00:00`) → harus normal, bukan setengah hari (format string beda panjang).
2. Karyawan berstatus `setengah_hari` sejak masuk (>=10:00) lalu diizinkan pulang 15:00 → tetap setengah hari (aturan 1 menang).
3. Mandor mengajukan → hanya Owner; Mandor lain TIDAK boleh menyetujui (level pemilik 3).
4. Ajuan `menunggu` dari kemarin tak boleh disetujui hari ini (kedaluwarsa).
5. Denda checkpoint: pulang 12:45 → denda Lapor Progress (batas 12:30) TETAP berlaku, denda Kembali Kerja (batas 14:00) tidak.

---

### Task 1: Aturan murni + tes

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (konstanta dekat `JAM_SETENGAH` baris ~32; fungsi statis dekat `gpsSiangWajib`)
- Modify: `app/Models/Absensi.php`
- Create: `tests/absensi/test_pulang_cepat.php`
- Modify: `tests/guardrail/manifest.json`

**Interfaces — Produces:**
- `AbsensiController::JAM_PULANG_CEPAT_PENUH = '14:00'`
- `AbsensiController::statusSaatPulang(string $statusSekarang, int $menitKerja, string $jamPulang, string $jamPulangJadwal, bool $diizinkan): string`
- `AbsensiController::dendaCheckpointBerlaku(?string $jamPulang, string $batas): bool`
- `Absensi::bolehAjukanPulangCepat(bool $sudahMasuk, bool $sudahPulang, string $jamSekarang, string $jamPulangJadwal, ?string $statusAjuan): ?string`
- `Absensi::alasanTolakProsesPulangCepat(?string $status, string $tanggal, string $hariIni, bool $sudahPulang, int $pemilikId, int $aktorId, int $pemilikLevel, int $aktorLevel): ?string`
- `Absensi::LEVEL_PULANG_CEPAT_MANDOR = [5, 6]`

- [ ] **Step 1: Tulis tes yang gagal** — `tests/absensi/test_pulang_cepat.php`

```php
<?php
// FILE: tests/absensi/test_pulang_cepat.php
// Jalankan: php tests/absensi/test_pulang_cepat.php
// Spec docs/superpowers/specs/2026-10-26-izin-pulang-cepat-design.md (keputusan Elvan 26 Okt).
require __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AbsensiController as A;
use App\Models\Absensi;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// statusSaatPulang(status, menitKerja, jamPulang, jadwal, diizinkan)
$check('pulang tepat jadwal = normal',          A::statusSaatPulang('hadir', 480, '17:00:00', '17:00:00', false), 'hadir');
$check('pulang tepat jadwal (H:i) = normal',    A::statusSaatPulang('hadir', 480, '17:00:05', '17:00', false), 'hadir');
$check('1 menit sebelum jadwal tanpa izin',     A::statusSaatPulang('hadir', 470, '16:59:00', '17:00:00', false), 'setengah_hari');
$check('telat, pulang normal tetap telat',      A::statusSaatPulang('telat', 450, '17:10:00', '17:00:00', false), 'telat');
$check('izin, pulang 13:59 = setengah',         A::statusSaatPulang('hadir', 360, '13:59:00', '17:00:00', true), 'setengah_hari');
$check('izin, pulang 14:00 = penuh',            A::statusSaatPulang('hadir', 360, '14:00:00', '17:00:00', true), 'hadir');
$check('tanpa izin, pulang 15:00 = setengah',   A::statusSaatPulang('hadir', 420, '15:00:00', '17:00:00', false), 'setengah_hari');
$check('<225 menit walau izin = setengah',      A::statusSaatPulang('hadir', 200, '14:30:00', '17:00:00', true), 'setengah_hari');
$check('sudah setengah sejak masuk tetap',      A::statusSaatPulang('setengah_hari', 300, '15:00:00', '17:00:00', true), 'setengah_hari');
$check('masuk siang, pulang normal tetap 1/2',  A::statusSaatPulang('setengah_hari', 300, '17:00:00', '17:00:00', false), 'setengah_hari');

// dendaCheckpointBerlaku(jamPulang, batas)
$check('belum pulang -> denda berlaku',         A::dendaCheckpointBerlaku(null, '12:30'), true);
$check('pulang 11:00 -> progress 12:30 bebas',  A::dendaCheckpointBerlaku('11:00:00', '12:30'), false);
$check('pulang 12:45 -> progress 12:30 kena',   A::dendaCheckpointBerlaku('12:45:00', '12:30'), true);
$check('pulang 12:45 -> kembali 14:00 bebas',   A::dendaCheckpointBerlaku('12:45:00', '14:00'), false);

// bolehAjukanPulangCepat(sudahMasuk, sudahPulang, jamSekarang, jadwal, statusAjuan)
$check('ajukan normal lolos',                   Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', null), null);
$check('ajukan lagi setelah ditolak lolos',     Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'ditolak'), null);
$check('belum masuk ditolak',                   is_string(Absensi::bolehAjukanPulangCepat(false, false, '10:00', '17:00:00', null)), true);
$check('sudah pulang ditolak',                  is_string(Absensi::bolehAjukanPulangCepat(true, true, '10:00', '17:00:00', null)), true);
$check('sudah lewat jadwal ditolak',            is_string(Absensi::bolehAjukanPulangCepat(true, false, '17:00', '17:00:00', null)), true);
$check('masih menunggu ditolak',                is_string(Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'menunggu')), true);
$check('sudah disetujui ditolak',               is_string(Absensi::bolehAjukanPulangCepat(true, false, '10:00', '17:00:00', 'disetujui')), true);

// alasanTolakProsesPulangCepat(status, tanggal, hariIni, sudahPulang, pemilikId, aktorId, pemilikLevel, aktorLevel)
$h = '2026-10-26';
$p = fn($pemilikLv, $aktorLv, $st = 'menunggu', $tgl = '2026-10-26', $pulang = false, $pemilikId = 10, $aktorId = 1)
    => Absensi::alasanTolakProsesPulangCepat($st, $tgl, $h, $pulang, $pemilikId, $aktorId, $pemilikLv, $aktorLv);
$check('owner utk teknisi lolos',               $p(5, 1), null);
$check('owner utk admin lolos',                 $p(2, 1), null);
$check('mandor utk teknisi lolos',              $p(5, 3), null);
$check('mandor utk driver lolos',               $p(6, 3), null);
$check('mandor utk admin ditolak',              is_string($p(2, 3)), true);
$check('mandor utk marketing ditolak',          is_string($p(4, 3)), true);
$check('mandor utk toko ditolak',               is_string($p(7, 3)), true);
$check('mandor utk mandor lain ditolak',        is_string($p(3, 3)), true);
$check('admin tak bisa proses',                 is_string($p(5, 2)), true);
$check('diri sendiri ditolak',                  is_string($p(1, 1, 'menunggu', $h, false, 1, 1)), true);
$check('sudah pulang ditolak',                  is_string($p(5, 1, 'menunggu', $h, true)), true);
$check('kemarin kedaluwarsa',                   is_string($p(5, 1, 'menunggu', '2026-10-25')), true);
$check('sudah diproses ditolak',                is_string($p(5, 1, 'disetujui')), true);
$check('tanpa ajuan ditolak',                   is_string($p(5, 1, null)), true);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
```

Tambahkan ke manifest tepat setelah entri `tests/absensi/test_pilih_pertanyaan_progress.php` (blok absensi, abjad: `pilih` < `pulang`):

```json
    {
      "path": "tests/absensi/test_pulang_cepat.php",
      "runner": "php",
      "requires_db": false,
      "manual": false
    },
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php tests/absensi/test_pulang_cepat.php`
Expected: fatal "Call to undefined method ... statusSaatPulang" (atau `__callStatic` pada Absensi).

- [ ] **Step 3: Implementasi** — di `AbsensiController` (dekat konstanta jam & `gpsSiangWajib`):

```php
    const JAM_PULANG_CEPAT_PENUH = '14:00'; // diizinkan & pulang >= ini = gaji penuh (Elvan 26 Okt)

    // Status saat absen pulang (spec 2026-10-26-izin-pulang-cepat). Tanpa toleransi menit.
    public static function statusSaatPulang(string $statusSekarang, int $menitKerja, string $jamPulang, string $jamPulangJadwal, bool $diizinkan): string
    {
        if ($statusSekarang === 'setengah_hari' || $menitKerja < 225) return 'setengah_hari';
        $jam = substr($jamPulang, 0, 5);
        if ($jam >= substr($jamPulangJadwal, 0, 5)) return $statusSekarang;
        if ($diizinkan && $jam >= self::JAM_PULANG_CEPAT_PENUH) return $statusSekarang;
        return 'setengah_hari';
    }

    // Denda checkpoint tak dicatat bila sudah absen pulang sebelum batasnya (Elvan 26 Okt).
    public static function dendaCheckpointBerlaku(?string $jamPulang, string $batas): bool
    {
        return !$jamPulang || substr($jamPulang, 0, 5) >= $batas;
    }
```

Di `Absensi` (setelah relasi):

```php
    // ─── Izin pulang cepat (spec 2026-10-26) ───────────────────────────
    const LEVEL_PULANG_CEPAT_MANDOR = [5, 6]; // Teknisi & Driver: Mandor juga boleh menyetujui

    public static function bolehAjukanPulangCepat(bool $sudahMasuk, bool $sudahPulang, string $jamSekarang, string $jamPulangJadwal, ?string $statusAjuan): ?string
    {
        if (!$sudahMasuk)  return 'Absen masuk dulu.';
        if ($sudahPulang)  return 'Kamu sudah absen pulang hari ini.';
        if (substr($jamSekarang, 0, 5) >= substr($jamPulangJadwal, 0, 5)) return 'Sudah jam pulang, langsung absen pulang saja.';
        if (in_array($statusAjuan, ['menunggu', 'disetujui'], true)) return 'Hari ini sudah ada ajuan pulang cepat.';
        return null;
    }

    public static function alasanTolakProsesPulangCepat(?string $status, string $tanggal, string $hariIni, bool $sudahPulang, int $pemilikId, int $aktorId, int $pemilikLevel, int $aktorLevel): ?string
    {
        if ($status !== 'menunggu')  return 'Ajuan ini sudah diproses.';
        if ($tanggal !== $hariIni)   return 'Ajuan sudah kedaluwarsa (bukan untuk hari ini).';
        if ($sudahPulang)            return 'Karyawan sudah absen pulang. Kalau darurat, pakai Koreksi Owner.';
        if ($pemilikId === $aktorId) return 'Tidak bisa memproses ajuan sendiri.';
        if ($aktorLevel === 1)       return null;
        if ($aktorLevel === 3 && in_array($pemilikLevel, self::LEVEL_PULANG_CEPAT_MANDOR, true)) return null;
        return 'Ajuan ini hanya bisa diproses Owner.';
    }
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php tests/absensi/test_pulang_cepat.php`
Expected: `SEMUA PASS`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php app/Models/Absensi.php tests/absensi/test_pulang_cepat.php tests/guardrail/manifest.json
git commit -m "feat(absensi): aturan murni izin pulang cepat + tes"
```

### Task 2: SQL + sambungkan aturan ke absen pulang & denda checkpoint

**Files:**
- Create: `docs/sql/2026-10-26-absensi-pulang-cepat.sql`
- Modify: `app/Models/Absensi.php` (`$fillable`)
- Modify: `app/Http/Controllers/AbsensiController.php` — `index()` (~baris 97-118), `formPulang()` (~750), `absenPulang()` (~789-790, 841-842)
- Modify: `resources/views/absensi/form-pulang.blade.php` (setelah kartu "Info jam masuk tadi", sebelum alert GPS)

**Interfaces — Consumes:** Task 1 `statusSaatPulang`, `dendaCheckpointBerlaku`.

- [ ] **Step 1: SQL**

```sql
-- docs/sql/2026-10-26-absensi-pulang-cepat.sql
-- Izin pulang cepat (spec 2026-10-26). Aman diulang (IF NOT EXISTS). Jalankan SEBELUM push.
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_status VARCHAR(10) NULL;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_alasan VARCHAR(255) NULL;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pulang_cepat_oleh BIGINT UNSIGNED NULL;
```

- [ ] **Step 2: Fillable** — tambah di akhir `$fillable` Absensi:

```php
        // Izin pulang cepat (26 Oktober)
        'pulang_cepat_status','pulang_cepat_alasan','pulang_cepat_oleh',
```

- [ ] **Step 3: Denda checkpoint** — di `index()` tambahkan syarat pada KEDUA blok:
  - blok Lapor Progress: `&& self::dendaCheckpointBerlaku($absenHariIni->jam_pulang, self::JAM_BATAS_LAPOR_PROGRESS)`
  - blok Kembali Kerja: `&& self::dendaCheckpointBerlaku($absenHariIni->jam_pulang, self::JAM_SKIP_SIANG)`

- [ ] **Step 4: absenPulang** — ganti

```php
        $setengahHari = $absen->status!=='setengah_hari' && $menitKerja<225;
```
dengan
```php
        // Pulang sebelum jadwal = setengah hari kecuali diizinkan & >= 14:00 (spec 2026-10-26).
        $setengahHari = $absen->status!=='setengah_hari'
            && self::statusSaatPulang($absen->status ?? 'hadir', $menitKerja, $jamPulang, $user->jam_pulang ?? '17:00', $absen->pulang_cepat_status === 'disetujui') === 'setengah_hari';
```
(sisa fungsi tak berubah: `$statusBaru`, nominal, pesan "(setengah hari)" sudah memakai `$setengahHari`).

- [ ] **Step 5: formPulang** — sebelum `return view(...)`:

```php
        $statusNanti = self::statusSaatPulang($absen->status ?? 'hadir', $this->hitungMenitKerja($absen->jam_masuk, now()->format('H:i:s')), now()->format('H:i:s'), $user->jam_pulang ?? '17:00', $absen->pulang_cepat_status === 'disetujui');
        $peringatanSetengah = $absen->status !== 'setengah_hari' && $statusNanti === 'setengah_hari'
            ? ($absen->pulang_cepat_status === 'disetujui'
                ? 'Diizinkan pulang cepat, tapi pulang sebelum jam '.self::JAM_PULANG_CEPAT_PENUH.' tetap dihitung SETENGAH HARI.'
                : 'Belum diizinkan atasan. Pulang sekarang dihitung SETENGAH HARI.')
            : null;
```
dan tambahkan `'peringatanSetengah'` ke `compact(...)`.

- [ ] **Step 6: View** — `form-pulang.blade.php`, setelah kartu jam masuk:

```blade
    @if($peringatanSetengah)
    <div style="padding:14px 16px;border-radius:12px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.35);margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;color:#F59E0B;">{{ $peringatanSetengah }}</div>
        <div style="font-size:12px;color:#94A3B8;margin-top:4px;">Kalau darurat, lapor ke Owner untuk dikoreksi.</div>
    </div>
    @endif
```

- [ ] **Step 7: Verifikasi**

Run: `php tests/absensi/test_pulang_cepat.php && php scripts/canopi-check --full`
Expected: `SEMUA PASS` lalu `PASS: canopi-check full selesai`

- [ ] **Step 8: Commit**

```bash
git add docs/sql/2026-10-26-absensi-pulang-cepat.sql app/Models/Absensi.php app/Http/Controllers/AbsensiController.php resources/views/absensi/form-pulang.blade.php
git commit -m "feat(absensi): pulang sebelum jadwal = setengah hari kecuali diizinkan >= 14:00"
```

### Task 3: Karyawan mengajukan pulang cepat

**Files:**
- Modify: `routes/web.php` (grup absensi, sebelah `ajukan-luar-kantor` baris ~115)
- Modify: `app/Http/Controllers/AbsensiController.php` (method baru setelah `ajukanLuarKantor`)
- Modify: `resources/views/absensi/index.blade.php` (setelah blok ajuan luar kantor, baris ~196)

**Interfaces — Consumes:** `Absensi::bolehAjukanPulangCepat`, `Absensi::LEVEL_PULANG_CEPAT_MANDOR`. **Produces:** route `absensi.ajukan-pulang-cepat`.

- [ ] **Step 1: Route**

```php
    Route::post('/ajukan-pulang-cepat',     [AbsensiController::class, 'ajukanPulangCepat'])->name('ajukan-pulang-cepat');
```

- [ ] **Step 2: Controller**

```php
    public function ajukanPulangCepat(Request $request)
    {
        $user = Auth::user();
        $request->validate(['alasan' => 'required|string|max:255']);
        $absen = Absensi::where('user_id', $user->id)->whereDate('tanggal', today())->first();

        if ($alasan = Absensi::bolehAjukanPulangCepat((bool) $absen?->jam_masuk, (bool) $absen?->jam_pulang, now()->format('H:i'), $user->jam_pulang ?? '17:00', $absen?->pulang_cepat_status)) {
            return back()->with('error', $alasan);
        }

        $absen->update(['pulang_cepat_status' => 'menunggu', 'pulang_cepat_alasan' => $request->alasan, 'pulang_cepat_oleh' => null]);

        // Penerima = yang boleh menyetujui (Absensi::alasanTolakProsesPulangCepat)
        $level = in_array((int) $user->level, Absensi::LEVEL_PULANG_CEPAT_MANDOR, true) ? [1, 3] : [1];
        $penerima = User::whereIn('level', $level)->where('status', 'aktif')
            ->where('id', '!=', $user->id)->whereNotNull('telegram_chat_id')->get();
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,
                "*AJUAN PULANG CEPAT*\n"
                . "Dari: {$user->name}\n"
                . "Alasan: {$request->alasan}\n"
                . "Tanggal: " . today()->format('d/m/Y') . "\n"
                . "---\n"
                . "Setujui/tolak di: app.kanopibsd.co.id/izin/approval");
        }

        return back()->with('success', 'Ajuan pulang cepat terkirim. Tunggu persetujuan.');
    }
```

- [ ] **Step 3: View** — `absensi/index.blade.php`, setelah `@endif` blok ajuan luar kantor:

```blade
        @if($absenHariIni?->jam_masuk && !$absenHariIni->jam_pulang && now()->format('H:i') < substr($user->jam_pulang ?? '17:00', 0, 5))
        <div style="margin-top:12px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);">
            @if($absenHariIni->pulang_cepat_status === 'menunggu')
            <div style="font-size:13px;color:#93C5FD;">Ajuan pulang cepat menunggu persetujuan atasan.</div>
            @elseif($absenHariIni->pulang_cepat_status === 'disetujui')
            <div style="font-size:13px;color:#6EE7B7;">Diizinkan pulang cepat. Gaji penuh hanya bila pulang jam {{ \App\Http\Controllers\AbsensiController::JAM_PULANG_CEPAT_PENUH }} ke atas.</div>
            @else
                @if($absenHariIni->pulang_cepat_status === 'ditolak')
                <div style="font-size:12px;color:#FCA5A5;margin-bottom:8px;">Ajuan pulang cepat sebelumnya ditolak.</div>
                @endif
            <form method="POST" action="{{ route('absensi.ajukan-pulang-cepat') }}" onsubmit="this.querySelector('button').disabled=true">
                @csrf
                <label for="alasanPulang" style="display:block;font-size:12px;color:#94A3B8;margin-bottom:6px;">Perlu pulang sebelum jam {{ substr($user->jam_pulang ?? '17:00', 0, 5) }}? Tanpa izin = setengah hari.</label>
                <input id="alasanPulang" name="alasan" required maxlength="255" placeholder="Alasan, mis. demam tinggi"
                       style="width:100%;box-sizing:border-box;padding:10px;border-radius:8px;border:1px solid #334155;background:#0F172A;color:#E2E8F0;font-size:16px;margin-bottom:8px;">
                <button type="submit"
                        style="width:100%;padding:12px;border-radius:10px;border:none;font-weight:700;font-size:14px;color:#0F1117;background:#FCD34D;min-height:44px;cursor:pointer;">
                    Ajukan Pulang Cepat
                </button>
            </form>
            @endif
        </div>
        @endif
```

- [ ] **Step 4: Verifikasi** — `php scripts/canopi-check --full` → `PASS: canopi-check full selesai`; `php artisan route:list --name=ajukan-pulang-cepat` → 1 route POST.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/AbsensiController.php resources/views/absensi/index.blade.php
git commit -m "feat(absensi): karyawan ajukan izin pulang cepat"
```

### Task 4: Atasan menyetujui / menolak

**Files:**
- Modify: `routes/web.php` (di dalam `Route::middleware('level:1,3')` grup izin)
- Modify: `app/Http/Controllers/IzinAbsenController.php` (`approval()` + 2 method baru)
- Modify: `resources/views/izin/approval.blade.php` (alert error + bagian baru sebelum "Pending")
- Modify: `tests/keamanan/test_akses_izin.php`

**Interfaces — Consumes:** `Absensi::alasanTolakProsesPulangCepat`. **Produces:** routes `izin.pulang-cepat.setujui`, `izin.pulang-cepat.tolak`.

- [ ] **Step 1: Tes route gagal dulu** — di `test_akses_izin.php`, ubah array baris pertama loop:

```php
foreach (['izin.approval', 'izin.approve', 'izin.reject', 'izin.dinas-luar', 'izin.pulang-cepat.setujui', 'izin.pulang-cepat.tolak'] as $route) {
```
dan tambahkan sebelum `echo $fail`:

```php
// Ajukan pulang cepat = semua karyawan (cek level di server, bukan middleware)
$adaLevel = false;
foreach ($mw['absensi.ajukan-pulang-cepat'] ?? ['level:<ROUTE TIDAK ADA>'] as $m) {
    if (str_starts_with((string) $m, 'level:')) $adaLevel = true;
}
$check('`absensi.ajukan-pulang-cepat` TIDAK dikunci level', $adaLevel, false);
```

Run: `php tests/keamanan/test_akses_izin.php` → Expected: FAIL untuk 2 route pulang-cepat.

- [ ] **Step 2: Route** — di dalam grup `level:1,3`:

```php
        Route::post('/pulang-cepat/{absensi}/setujui', [IzinAbsenController::class, 'setujuiPulangCepat'])->name('pulang-cepat.setujui');
        Route::post('/pulang-cepat/{absensi}/tolak',   [IzinAbsenController::class, 'tolakPulangCepat'])->name('pulang-cepat.tolak');
```

- [ ] **Step 3: Controller** — `approval()` tambahkan sebelum `return`:

```php
        $pulangCepat = Absensi::whereDate('tanggal', today())
                              ->where('pulang_cepat_status', 'menunggu')
                              ->with('user')->get();
```
`compact('pending','riwayat','pulangCepat')`. Method baru:

```php
    public function setujuiPulangCepat($id) { return $this->prosesPulangCepat($id, 'disetujui'); }
    public function tolakPulangCepat($id)   { return $this->prosesPulangCepat($id, 'ditolak'); }

    private function prosesPulangCepat($id, string $hasil)
    {
        $absen = Absensi::with('user')->findOrFail($id);
        $aktor = Auth::user();
        if ($alasan = Absensi::alasanTolakProsesPulangCepat($absen->pulang_cepat_status, $absen->tanggal->format('Y-m-d'), today()->format('Y-m-d'),
                (bool) $absen->jam_pulang, (int) $absen->user_id, (int) $aktor->id, (int) $absen->user?->level, (int) $aktor->level)) {
            return back()->with('error', $alasan);
        }

        $absen->update(['pulang_cepat_status' => $hasil, 'pulang_cepat_oleh' => $aktor->id]);

        if ($absen->user?->telegram_chat_id) {
            $pesan = $hasil === 'disetujui'
                ? "Izin pulang cepat kamu DISETUJUI oleh {$aktor->name}. Gaji penuh hanya bila pulang jam " . \App\Http\Controllers\AbsensiController::JAM_PULANG_CEPAT_PENUH . " ke atas."
                : "Izin pulang cepat kamu DITOLAK oleh {$aktor->name}. Pulang sebelum jadwal dihitung setengah hari.";
            app(TelegramService::class)->kirim($absen->user->telegram_chat_id, $pesan);
        }

        return back()->with('success', 'Ajuan pulang cepat ' . $absen->user?->name . ' ' . $hasil . '.');
    }
```

- [ ] **Step 4: View** — `izin/approval.blade.php`: setelah blok `session('success')`:

```blade
  @if(session('error'))
  <div class="alert-success" style="background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#fca5a5;">{{ session('error') }}</div>
  @endif

  @if($pulangCepat->count() > 0)
  <div class="section-title">Pulang Cepat Menunggu ({{ $pulangCepat->count() }})</div>
  @foreach($pulangCepat as $pc)
  @php $tolakAlasan = \App\Models\Absensi::alasanTolakProsesPulangCepat($pc->pulang_cepat_status, $pc->tanggal->format('Y-m-d'), today()->format('Y-m-d'), (bool) $pc->jam_pulang, (int) $pc->user_id, (int) auth()->id(), (int) $pc->user?->level, (int) auth()->user()->level); @endphp
  <div class="card-dark">
    <div style="font-size:15px; font-weight:700; color:#f1f5f9;">{{ $pc->user?->name }}</div>
    <div style="font-size:12px; color:#64748b;">{{ $pc->user?->jabatan }} · masuk {{ substr($pc->jam_masuk, 0, 5) }}</div>
    <div style="font-size:13px; color:#e2e8f0; margin:8px 0 12px;">{{ $pc->pulang_cepat_alasan }}</div>
    @if(!$tolakAlasan)
    <div class="d-flex gap-2">
      <form method="POST" action="{{ route('izin.pulang-cepat.setujui', $pc->id) }}" class="flex-fill">@csrf<button class="btn-approve w-100">Setujui</button></form>
      <form method="POST" action="{{ route('izin.pulang-cepat.tolak', $pc->id) }}" class="flex-fill">@csrf<button class="btn-reject w-100">Tolak</button></form>
    </div>
    @else
    <div style="font-size:12px; color:#64748b;">{{ $tolakAlasan }}</div>
    @endif
  </div>
  @endforeach
  @endif
```

- [ ] **Step 5: Verifikasi**

Run: `php tests/keamanan/test_akses_izin.php && php scripts/canopi-check --full`
Expected: `SEMUA PASS` lalu `PASS: canopi-check full selesai`

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/IzinAbsenController.php resources/views/izin/approval.blade.php tests/keamanan/test_akses_izin.php
git commit -m "feat(izin): atasan setujui/tolak pulang cepat"
```

### Task 5: Catatan CLAUDE.md

**Files:** Modify: `CLAUDE.md` (blok TITIK LANJUT 20 Okt, setelah catatan 24 Okt izin sakit)

- [ ] **Step 1:** Tambahkan:

```
      26 Okt: IZIN PULANG CEPAT (spec/plan `docs/superpowers/*/2026-10-26-izin-pulang-cepat*`). Pulang < jam pulang
      jadwal = setengah hari, KECUALI diizinkan atasan & pulang >= 14:00 (`AbsensiController::statusSaatPulang`), tanpa
      toleransi. Penyetuju: teknisi/driver -> Mandor/Owner; lainnya -> Owner. Darurat setelah pulang = Koreksi Owner.
      Denda checkpoint tak dicatat bila sudah pulang sebelum batasnya. SQL `docs/sql/2026-10-26-absensi-pulang-cepat.sql`.
      BELUM divalidasi di HP; UMUMKAN ke karyawan (pulang 5 menit lebih awal tanpa izin = setengah hari).
```

- [ ] **Step 2:** `php scripts/canopi-check --full` → hijau. Commit `docs: catatan izin pulang cepat`.
