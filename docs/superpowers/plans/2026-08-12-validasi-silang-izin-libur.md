# Validasi Silang Izin ↔ Jadwal Libur Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Karyawan gak bisa lagi punya ajuan Izin/Sakit/Cuti DAN ajuan Jadwal Libur (Tukar/Skip/Tambah) yang sama-sama aktif (`pending`/`approved`) di tanggal yang sama — kedua controller saling ngecek tabel satunya sebelum simpan ajuan baru.

**Architecture:** 2 titik perubahan independen, satu di tiap controller — masing-masing nambah 1 query pengecekan ke tabel controller satunya, ditaruh setelah pengecekan duplikat internal yang sudah ada. Tidak ada tabel/kolom baru, tidak ada perubahan model.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent query builder (pola sama seperti pengecekan bentrok yang sudah ada di kedua file).

## Global Constraints

- Dinas Luar (`IzinAbsenController::dinasLuar()`) TIDAK ikut divalidasi — di luar cakupan plan ini sama sekali, jangan disentuh.
- Status yang dianggap bentrok: `pending` DAN `approved` saja — `rejected` tidak dianggap bentrok, konsisten sama pola pengecekan duplikat internal yang sudah ada di kedua controller.
- Pengecekan scope per-`user_id` yang sama — 2 karyawan berbeda TIDAK dianggap bentrok walau tanggalnya sama.
- Tidak ada perubahan ke tabel/model — murni query tambahan di controller.

Spec lengkap: `docs/superpowers/specs/2026-08-12-validasi-silang-izin-libur-design.md`

---

### Task 1: `IzinAbsenController::store()` cek `JadwalLibur`

**Files:**
- Modify: `app/Http/Controllers/IzinAbsenController.php`

**Interfaces:**
- Consumes: tabel `jadwal_libur` (kolom `user_id`, `tanggal`, `tanggal_baru`, `status`) — sudah ada, tidak berubah.
- Produces: tidak ada, task independen dari Task 2 (bisa dikerjakan urutan apa pun, tapi plan ini urutkan Task 1 dulu).

- [ ] **Step 1: Tambah `use` statement**

Sebelum:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\IzinAbsen;
use App\Models\Absensi;
use App\Models\User;
use App\Services\TelegramService;
```
Sesudah:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\IzinAbsen;
use App\Models\Absensi;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;
```

- [ ] **Step 2: Tambah pengecekan silang setelah cek duplikat izin yang sudah ada, di `store()`**

Sebelum:
```php
        // Cek duplikat
        $sudahAda = IzinAbsen::where('user_id', $user->id)
                             ->whereDate('tanggal', $request->tanggal)
                             ->whereIn('status', ['pending','approved'])
                             ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya izin pada tanggal tersebut.');
        }

        // Upload foto surat jika ada
```
Sesudah:
```php
        // Cek duplikat
        $sudahAda = IzinAbsen::where('user_id', $user->id)
                             ->whereDate('tanggal', $request->tanggal)
                             ->whereIn('status', ['pending','approved'])
                             ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya izin pada tanggal tersebut.');
        }

        // Cek bentrok sama ajuan Jadwal Libur (Tukar/Skip/Tambah) yang masih aktif
        $bentrokLibur = JadwalLibur::where('user_id', $user->id)
                                   ->whereIn('status', ['pending', 'approved'])
                                   ->where(function ($q) use ($request) {
                                       $q->whereDate('tanggal', $request->tanggal)
                                         ->orWhereDate('tanggal_baru', $request->tanggal);
                                   })
                                   ->exists();

        if ($bentrokLibur) {
            return back()->with('error', 'Tanggal ini sudah ada ajuan jadwal libur (Tukar/Skip/Tambah) yang masih berjalan.');
        }

        // Upload foto surat jika ada
```

- [ ] **Step 3: Cek sintaks**

Run: `php -l app/Http/Controllers/IzinAbsenController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/IzinAbsenController.php
git commit -m "feat: IzinAbsenController tolak ajuan yang bentrok sama Jadwal Libur aktif"
```

---

### Task 2: `JadwalLiburController::store()` cek `IzinAbsen`

**Files:**
- Modify: `app/Http/Controllers/JadwalLiburController.php`

**Interfaces:**
- Consumes: tabel `izin_absen` (kolom `user_id`, `tanggal`, `status`) — sudah ada, tidak berubah.
- Produces: tidak ada.

- [ ] **Step 1: Tambah `use` statement**

Sebelum:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;
```
Sesudah:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\IzinAbsen;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;
```

- [ ] **Step 2: Tambah pengecekan silang setelah cek bentrok Jadwal Libur yang sudah ada, di `store()`**

Sebelum:
```php
        if ($bentrok) {
            return back()->with('error', 'Tanggal yang kamu pilih bentrok sama ajuan lain yang masih berjalan.')->withInput();
        }

        $jadwal = JadwalLibur::create([
```
Sesudah:
```php
        if ($bentrok) {
            return back()->with('error', 'Tanggal yang kamu pilih bentrok sama ajuan lain yang masih berjalan.')->withInput();
        }

        $bentrokIzin = IzinAbsen::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($request, $tanggalBaruInput) {
                $q->whereDate('tanggal', $request->tanggal);
                if ($tanggalBaruInput) {
                    $q->orWhereDate('tanggal', $tanggalBaruInput);
                }
            })
            ->exists();

        if ($bentrokIzin) {
            return back()->with('error', 'Tanggal ini sudah ada ajuan izin/sakit/cuti yang masih berjalan.')->withInput();
        }

        $jadwal = JadwalLibur::create([
```

- [ ] **Step 3: Cek sintaks**

Run: `php -l app/Http/Controllers/JadwalLiburController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/JadwalLiburController.php
git commit -m "feat: JadwalLiburController tolak ajuan yang bentrok sama Izin aktif"
```

---

## Verifikasi manual di production (tidak bisa diuji headless — query DB, sama seperti pola proyek ini)

- [ ] Karyawan A ajuin Cuti tanggal X (pending) → coba ajuin Tukar Libur dengan tanggal lama ATAU tanggal baru = X → harus ditolak dengan pesan yang jelas.
- [ ] Karyawan A ajuin Tukar Libur tanggal lama Y, tanggal baru Z (approved) → coba ajuin Izin di tanggal Y → harus ditolak. Coba lagi di tanggal Z → harus ditolak juga.
- [ ] Karyawan A ajuin Izin tanggal X, Karyawan B (beda orang) ajuin Tukar Libur tanggal X → harus TETAP BISA (beda karyawan, bukan bentrok).
- [ ] Owner catat Dinas Luar (`/izin/dinas-luar` atau endpoint terkait) di tanggal yang sudah ada ajuan Jadwal Libur aktif buat karyawan itu → harus TETAP BISA (Dinas Luar dikecualikan, tidak disentuh plan ini).
- [ ] Ajuan yang sudah `rejected` di salah satu sistem TIDAK menghalangi ajuan baru di sistem satunya pada tanggal yang sama.

## Di luar cakupan

- `dinasLuar()` tidak disentuh (spec §2 poin 2, sengaja).
- Tidak ada UI tambahan (indikator visual) — cuma pesan error saat submit, konsisten sama pola validasi lain di kedua controller.
