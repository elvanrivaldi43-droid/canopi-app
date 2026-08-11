# Jam Masuk/Pulang Per-Karyawan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti patokan telat/lembur dari konstanta hardcode (`JAM_MASUK`/`JAM_LEMBUR`, sama buat semua karyawan) ke kolom `users.jam_masuk`/`jam_pulang` per-karyawan yang sudah ada di DB tapi selama ini dekoratif.

**Architecture:** Perubahan lokal di `AbsensiController` (baca `$user->jam_masuk`/`jam_pulang` bukan konstanta), satu pengecualian gate buat mode luar kota, validasi format di `KaryawanController`, dan satu fix string di blade. Tidak ada tabel/kolom baru — `jam_masuk`/`jam_pulang` sudah ada sejak migrasi `2026_06_01_122708_add_fields_to_users_table.php`.

**Tech Stack:** Laravel 13 / PHP 8.3, `DB::table`/Eloquent campuran sesuai pola proyek, standalone PHP script buat cek logic murni (pola `tests/jadwal-libur/test_libur_service.php`).

## Global Constraints

- SQL harus idempotent — backfill di Task 0 pakai `UPDATE ... WHERE`, aman dijalankan ulang.
- Jangan ubah `JAM_SETENGAH`, `JAM_BUKA_ABSEN` (kecuali exemption luar kota), `JAM_MASUK_SIANG` — tetap seragam per keputusan spec §3.
- `LOG_LEVEL=error` di production — jangan pakai `Log::info()` buat debug sementara, pakai `Log::error()` lalu hapus lagi.
- Emoji jangan dipakai di file blade (riwayat korup di server) — semua perubahan di blade task ini murni string jam, tidak menambah emoji baru.

Spec lengkap: `docs/superpowers/specs/2026-08-11-jam-masuk-pulang-per-karyawan-design.md`

---

## Task 0 (prasyarat manual, BUKAN task kode — untuk Elvan sebelum deploy)

Jalankan SQL ini di phpMyAdmin production SEBELUM kode Task 1-6 di-deploy (backfill supaya hari deploy nol-regresi — field `jam_masuk`/`jam_pulang` selama ini gak berefek jadi isinya sekarang gak bisa dipercaya):

```sql
UPDATE users SET jam_masuk = '07:00:00', jam_pulang = '17:00:00'
WHERE status = 'aktif';
```

---

### Task 1: Telat & lembur baca jam individu, bukan konstanta

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php:139` (`absenMasuk()`)
- Modify: `app/Http/Controllers/AbsensiController.php:350-351` (`absenPulang()`)

**Interfaces:**
- Consumes: `$user` (Eloquent `User`, sudah ada di scope kedua method lewat `Auth::user()`), kolom `$user->jam_masuk`/`$user->jam_pulang` (format `H:i:s` string, sudah ada di DB).
- Produces: tidak ada fungsi baru — perilaku `hitungMenitTelat`/perhitungan lembur sekarang bergantung ke `$user`, dipakai Task 2 & 3 (formMasuk/formPulang) sebagai acuan konsistensi.

- [ ] **Step 1: Baca ulang baris target buat pastikan belum berubah**

Run: `sed -n '135,155p' app/Http/Controllers/AbsensiController.php` dan `sed -n '345,355p' app/Http/Controllers/AbsensiController.php` — pastikan baris 139 masih `$menitTelat = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK);` dan baris 350-351 masih pakai `self::JAM_LEMBUR`.

- [ ] **Step 2: Ganti baris 139**

Sebelum:
```php
$menitTelat   = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK);
```
Sesudah:
```php
$menitTelat   = $this->hitungMenitTelat($jamSekarang, $user->jam_masuk);
```

- [ ] **Step 3: Ganti baris 350-351**

Sebelum:
```php
if ($absen->lembur_approved && now()->format('H:i')>=self::JAM_LEMBUR) {
    $lemburJam  = min(round($this->hitungMenitTelat(now()->format('H:i'),self::JAM_LEMBUR)/60,2),self::LEMBUR_MAX_JAM);
```
Sesudah:
```php
if ($absen->lembur_approved && now()->format('H:i')>=substr($user->jam_pulang,0,5)) {
    $lemburJam  = min(round($this->hitungMenitTelat(now()->format('H:i'),substr($user->jam_pulang,0,5))/60,2),self::LEMBUR_MAX_JAM);
```

Catatan: `$user->jam_pulang` formatnya `H:i:s` (mis. `17:00:00`), sedangkan `hitungMenitTelat`/perbandingan string `>=` di baris ini butuh `H:i` (mis. `17:00`) biar konsisten sama `now()->format('H:i')` dan sama pola `JAM_MASUK` lama yang juga `H:i`. `substr(...,0,5)` motong ke `H:i`.

- [ ] **Step 4: Cek tidak ada regresi sintaks**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "feat: telat & lembur pakai jam individu karyawan, bukan konstanta seragam"
```

---

### Task 2: Gate `JAM_BUKA_ABSEN` dikecualikan buat mode luar kota + cek logic

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php:91-104` (`formMasuk()`)
- Test: `tests/absensi/test_gate_buka_absen.php`

**Interfaces:**
- Consumes: `LuarKota::sedangLuarKota(int $userId): bool` (sudah ada, dipakai persis sama di `absenMasuk()` baris 117).
- Produces: tidak ada fungsi baru dipakai task lain.

- [ ] **Step 1: Tulis script cek logic murni (mirror boolean gate, tanpa DB)**

```php
<?php
// FILE: tests/absensi/test_gate_buka_absen.php
// Jalankan: php tests/absensi/test_gate_buka_absen.php
// Mirror boolean gate di AbsensiController::formMasuk() — JAM_BUKA_ABSEN
// dikecualikan kalau user lagi mode luar kota.

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$diblokir = function (string $jamSekarang, string $jamBuka, bool $sedangLuarKota): bool {
    return $jamSekarang < $jamBuka && !$sedangLuarKota;
};

$check('06:00, bukan luar kota -> diblokir', $diblokir('06:00', '06:30', false), true);
$check('06:00, luar kota aktif -> TIDAK diblokir', $diblokir('06:00', '06:30', true), false);
$check('04:00, luar kota aktif -> TIDAK diblokir', $diblokir('04:00', '06:30', true), false);
$check('07:00, bukan luar kota -> TIDAK diblokir (sudah lewat jam buka)', $diblokir('07:00', '06:30', false), false);
$check('07:00, luar kota aktif -> TIDAK diblokir', $diblokir('07:00', '06:30', true), false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan, pastikan semua PASS**

Run: `php tests/absensi/test_gate_buka_absen.php`
Expected: `SEMUA PASS`, exit code 0.

- [ ] **Step 3: Terapkan exemption yang sama ke `formMasuk()`**

Sebelum (baris 91-97):
```php
public function formMasuk()
{
    $user  = Auth::user();
    $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();

    if ($absen?->jam_masuk) return redirect()->route('absensi.index')->with('info','Kamu sudah absen masuk hari ini.');
    if (now()->format('H:i') < self::JAM_BUKA_ABSEN) return redirect()->route('absensi.index')->with('error','Absen masuk baru bisa mulai jam 06:30');
```
Sesudah:
```php
public function formMasuk()
{
    $user  = Auth::user();
    $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();

    if ($absen?->jam_masuk) return redirect()->route('absensi.index')->with('info','Kamu sudah absen masuk hari ini.');
    if (now()->format('H:i') < self::JAM_BUKA_ABSEN && !LuarKota::sedangLuarKota($user->id)) return redirect()->route('absensi.index')->with('error','Absen masuk baru bisa mulai jam 06:30');
```

- [ ] **Step 4: Cek `LuarKota` sudah ter-import di file ini**

Run: `grep -n "^use App\\\\Models\\\\LuarKota" app/Http/Controllers/AbsensiController.php`
Expected: ada 1 baris hasil (dipakai juga di `absenMasuk()`/`absenPulang()` yang sudah ada, jadi harusnya sudah ter-import — kalau kosong, tambahkan `use App\Models\LuarKota;` di bagian use statement atas file).

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php tests/absensi/test_gate_buka_absen.php
git commit -m "feat: kecualikan gate jam buka absen buat karyawan mode luar kota"
```

---

### Task 3: Label jam lembur di `formPulang()` pakai jam individu

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php:318` (`formPulang()`)

**Interfaces:**
- Consumes: `$user->jam_pulang` (sama seperti Task 1).
- Produces: variabel `$jamLemburMax` yang dikirim ke view `absensi.form-pulang` — dipakai Task 4 (fallback JS) sebagai acuan format `H:i`.

- [ ] **Step 1: Ganti baris 318**

Sebelum:
```php
$jamLemburMax  = self::JAM_LEMBUR;
```
Sesudah:
```php
$jamLemburMax  = substr($user->jam_pulang, 0, 5);
```

- [ ] **Step 2: Cek sintaks**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "feat: label jam lembur di form pulang pakai jam individu"
```

---

### Task 4: Fix fallback JS salah di `form-pulang.blade.php`

**Files:**
- Modify: `resources/views/absensi/form-pulang.blade.php:130`

**Interfaces:**
- Consumes: `auth()->user()->jam_pulang` (kolom yang sama dipakai Task 1 & 3).
- Produces: tidak ada, konsumen terakhir di rantai ini.

- [ ] **Step 1: Ganti fallback**

Sebelum:
```js
const jamPulangNormal = '{{ auth()->user()->jam_pulang ? substr(auth()->user()->jam_pulang, 0, 5) : "16:30" }}';
```
Sesudah:
```js
const jamPulangNormal = '{{ auth()->user()->jam_pulang ? substr(auth()->user()->jam_pulang, 0, 5) : "17:00" }}';
```

Catatan: fallback ini cuma kepakai kalau `jam_pulang` NULL di DB — setelah Task 0 (backfill) semua karyawan aktif punya nilai, jadi fallback ini praktis dead-path, tapi tetap dibenerin biar gak nyesatkan kalau ada karyawan baru yang somehow NULL.

- [ ] **Step 2: Commit**

```bash
git add resources/views/absensi/form-pulang.blade.php
git commit -m "fix: fallback jam pulang di form-pulang.blade.php ikut JAM_LEMBUR lama (17:00, bukan 16:30)"
```

---

### Task 5: Validasi format jam di `KaryawanController`

**Files:**
- Modify: `app/Http/Controllers/KaryawanController.php` (method `store()`, aturan `jam_masuk`/`jam_pulang`)
- Modify: `app/Http/Controllers/KaryawanController.php` (method `update()`, aturan `jam_masuk`/`jam_pulang`)

**Interfaces:**
- Consumes: input form `jam_masuk`/`jam_pulang` (HTML `<input type="time">`, sudah ada di form Karyawan — mengirim format `H:i`).
- Produces: nilai `jam_masuk`/`jam_pulang` yang sekarang dijamin format valid sebelum disimpan — jadi prasyarat implisit buat Task 1 & 3 (yang butuh format `H:i`/`H:i:s` konsisten).

- [ ] **Step 1: Ganti rule di `store()`**

Sebelum:
```php
'jam_masuk'       => 'required',
'jam_pulang'      => 'required',
```
Sesudah:
```php
'jam_masuk'       => 'required|date_format:H:i',
'jam_pulang'      => 'required|date_format:H:i',
```

- [ ] **Step 2: Ganti rule di `update()`**

Sebelum:
```php
'jam_masuk'          => 'required',
'jam_pulang'         => 'required',
```
Sesudah:
```php
'jam_masuk'          => 'required|date_format:H:i',
'jam_pulang'         => 'required|date_format:H:i',
```

- [ ] **Step 3: Cek sintaks**

Run: `php -l app/Http/Controllers/KaryawanController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/KaryawanController.php
git commit -m "feat: validasi format H:i buat field jam_masuk/jam_pulang di form karyawan"
```

---

### Task 6: Hapus konstanta mati

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php:26-32`

**Interfaces:**
- Consumes: hasil Task 1-3 (semua pemakaian `JAM_MASUK`/`JAM_LEMBUR` sudah diganti).
- Produces: tidak ada — task pembersihan akhir.

- [ ] **Step 1: Pastikan nol pemakaian tersisa sebelum hapus**

Run: `grep -n "self::JAM_MASUK\b\|self::JAM_LEMBUR\b\|self::JAM_PULANG\b" app/Http/Controllers/AbsensiController.php`
Expected: kosong (tidak ada baris hasil). Kalau ada baris tersisa, JANGAN lanjut — berarti ada pemakaian yang kelewat di Task 1-3, kembali cek dulu.

- [ ] **Step 2: Hapus 3 baris konstanta**

Sebelum (baris 26-32):
```php
    const JAM_BUKA_ABSEN      = '06:30';
    const JAM_MASUK           = '07:00';
    const JAM_SETENGAH        = '10:00';
    const JAM_MASUK_SIANG     = '13:00';
    const JAM_SKIP_SIANG      = '14:00';
    const JAM_PULANG          = '16:30';
    const JAM_LEMBUR          = '17:00';
```
Sesudah:
```php
    const JAM_BUKA_ABSEN      = '06:30';
    const JAM_SETENGAH        = '10:00';
    const JAM_MASUK_SIANG     = '13:00';
    const JAM_SKIP_SIANG      = '14:00';
```

- [ ] **Step 3: Cek sintaks & jalankan ulang test Task 2**

Run: `php -l app/Http/Controllers/AbsensiController.php && php tests/absensi/test_gate_buka_absen.php`
Expected: `No syntax errors detected` lalu `SEMUA PASS`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "chore: hapus konstanta JAM_MASUK/JAM_LEMBUR/JAM_PULANG yang sudah tak dipakai"
```

---

## Verifikasi manual di production (setelah deploy, tidak bisa diuji headless — sama seperti fitur interaktif lain di proyek ini)

- [ ] Set `jam_masuk` salah satu karyawan ke `08:00` lewat form Edit Karyawan, cek tersimpan (format `date_format:H:i` gak nolak input normal dari `<input type="time">`).
- [ ] Karyawan itu absen masuk jam 07:30 → status HARUS `hadir` (bukan `telat`) karena patokan sekarang 08:00.
- [ ] Karyawan lain dengan `jam_masuk` masih default `07:00` → absen jam 07:30 tetap `telat` seperti biasa (nol-regresi).
- [ ] Aktifkan mode Luar Kota buat 1 karyawan, coba absen masuk jam 05:00 → HARUS lolos (dulu diblokir sampai 06:30).
- [ ] Set `jam_pulang` salah satu karyawan ke `16:00`, minta lembur approved, absen pulang jam 16:30 → lembur mulai dihitung dari 16:00 (0.5 jam), bukan dari 17:00.
- [ ] Slip gaji karyawan yang jamnya TIDAK di-custom tetap sama seperti sebelum deploy (backfill Task 0 bekerja).

## Di luar cakupan (dicatat di spec §7, tidak dikerjakan di plan ini)

- Kompensasi jam dini hari buat luar kota.
- Dugaan bug dobel-potong absen siang (belum dikonfirmasi nyata).
- Redesain alur "absen siang".
