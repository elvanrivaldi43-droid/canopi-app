# Gabungkan Ajuan Jadwal Libur Jadi 1 Form (Tukar/Skip/Tambah) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti 2 jenis ajuan jadwal libur (`tambah`/`batal`) yang butuh 2 ajuan terpisah buat kasus "tukar hari libur", jadi 3 jenis eksplisit (**Tukar**/**Skip**/**Tambah**) di 1 form — 1 ajuan = 1 baris data = 1 klik approve/tolak, dengan validasi tanggal otomatis dari sistem (bukan cek manual Owner).

**Architecture:** Tabel `jadwal_libur` dapat kolom baru `tanggal_baru` (nullable) + nilai enum `jenis` baru `'tukar'`. `LiburService::cocokLiburPada()`/`hitungHariKerjaPada()` (logic inti, sudah teruji 13/13) TIDAK disentuh — baris `tukar` di-expand jadi 2 entry override sintetis (batal+tambah) di layer sebelum data itu, lewat method pure baru `expandTukar()`. Controller & 1 blade form direstruktur buat nampilin 3 pilihan jenis dengan field tanggal yang berubah sesuai pilihan.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent + `DB::table` campuran sesuai pola proyek, standalone PHP script buat cek logic murni (pola `tests/jadwal-libur/test_libur_service.php` yang sudah ada — WAJIB tetap 13/13 hijau, ditambah kasus baru).

## Global Constraints

- SQL production dijalankan MANUAL oleh Elvan di phpMyAdmin (bukan `php artisan migrate` — shared hosting), tapi migration file tetap ditulis buat konsistensi repo. SQL manual harus idempotent.
- Minggu dihitung **Senin–Minggu** (bukan Minggu–Sabtu) — pakai `Carbon::MONDAY` di `startOfWeek()`.
- Nilai enum `jenis` LAMA (`'tambah'`, `'batal'`) TIDAK BOLEH diubah namanya di database — cuma label tampilan yang berubah (`'batal'` → tampil "Skip"). Ini biar data yang mungkin sudah ke-submit sejak fitur asli live (11 Agustus siang) tetap valid.
- Tanggal baru (Tukar) BOLEH lebih dulu secara kronologis dari tanggal lama — validasi TIDAK BOLEH memaksa urutan.
- `cocokLiburPada()`/`hitungHariKerjaPada()` di `app/Services/LiburService.php` TIDAK BOLEH diubah sama sekali — sudah teruji 13/13, generik menangani pasangan tanggal+jenis apa pun.

Spec lengkap: `docs/superpowers/specs/2026-08-11-tukar-libur-1-form-design.md`

---

### Task 1: Migrasi DB + model `JadwalLibur`

**Files:**
- Create: `database/migrations/2026_08_11_000003_add_tukar_to_jadwal_libur_table.php`
- Modify: `app/Models/JadwalLibur.php`

**Interfaces:**
- Consumes: tabel `jadwal_libur` yang sudah ada (migrasi `2026_08_11_000002_create_jadwal_libur_table.php`).
- Produces: kolom `tanggal_baru` (nullable DATE) + enum `jenis` yang menerima `'tukar'`; method `JadwalLibur::labelTanggal(string $format = 'd/m/Y'): string` — dipakai Task 5 (blade `index`/`approval`) dan Task 3 (notif Telegram di controller).

- [ ] **Step 1: Tulis migration**

```php
<?php
// FILE: database/migrations/2026_08_11_000003_add_tukar_to_jadwal_libur_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal','tukar') NOT NULL");
        Schema::table('jadwal_libur', function (Blueprint $table) {
            $table->date('tanggal_baru')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_libur', function (Blueprint $table) {
            $table->dropColumn('tanggal_baru');
        });
        DB::statement("ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal') NOT NULL");
    }
};
```

- [ ] **Step 2: Cek sintaks**

Run: `php -l database/migrations/2026_08_11_000003_add_tukar_to_jadwal_libur_table.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Update `$fillable` dan `$casts` di `app/Models/JadwalLibur.php`**

Sebelum:
```php
    protected $fillable = [
        'user_id', 'tanggal', 'jenis', 'alasan',
        'status', 'diproses_oleh', 'diproses_at',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'diproses_at' => 'datetime',
    ];
```
Sesudah:
```php
    protected $fillable = [
        'user_id', 'tanggal', 'tanggal_baru', 'jenis', 'alasan',
        'status', 'diproses_oleh', 'diproses_at',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'tanggal_baru' => 'date',
        'diproses_at'  => 'datetime',
    ];
```

- [ ] **Step 4: Update const `JENIS` — tambah label Tukar, ganti label Batal jadi "Skip" (value tetap `'batal'`, cuma teksnya)**

Sebelum:
```php
    const JENIS = [
        'tambah' => '➕ Tambah Libur',
        'batal'  => '🚫 Batalkan Libur Default',
    ];
```
Sesudah:
```php
    const JENIS = [
        'tambah' => '➕ Tambah Libur',
        'batal'  => '🚫 Skip Libur',
        'tukar'  => '🔄 Tukar Libur',
    ];
```

- [ ] **Step 5: Tambah method `labelTanggal()` — taruh setelah method `jenisLabel()`**

```php
    public function labelTanggal(string $format = 'd/m/Y'): string
    {
        if ($this->jenis === 'tukar') {
            return $this->tanggal->translatedFormat($format) . ' → ' . $this->tanggal_baru->translatedFormat($format);
        }
        return $this->tanggal->translatedFormat($format);
    }
```

- [ ] **Step 6: Cek sintaks**

Run: `php -l app/Models/JadwalLibur.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_11_000003_add_tukar_to_jadwal_libur_table.php app/Models/JadwalLibur.php
git commit -m "feat: kolom tanggal_baru + jenis tukar di JadwalLibur (skema & model)"
```

---

### Task 2: `LiburService` — expand jenis Tukar + jendela waktu + kandidat tanggal

**Files:**
- Modify: `app/Services/LiburService.php`
- Modify: `tests/jadwal-libur/test_libur_service.php` (tambah kasus baru, JANGAN hapus/ubah 13 kasus lama)

**Interfaces:**
- Consumes: `Carbon` (paket `nesbot/carbon`, sudah dipakai di file ini).
- Produces: `expandTukar(array $row): array`, `jendelaTukarSkip(Carbon $sekarang): array` (return `[Carbon $awal, Carbon $akhir]`), `tanggalKandidatLibur(int $hariLiburDefault, Carbon $awal, Carbon $akhir): array` (return array string `Y-m-d`) — dipakai Task 3 (`JadwalLiburController::create()`/`store()`).

- [ ] **Step 1: Tambah 3 method pure baru — taruh setelah `hitungHariKerjaPada()` (baris 38), sebelum komentar `// Wrapper database`**

```php
    public function expandTukar(array $row): array
    {
        if ($row['jenis'] === 'tukar') {
            return [
                ['tanggal' => $row['tanggal'], 'jenis' => 'batal'],
                ['tanggal' => $row['tanggal_baru'], 'jenis' => 'tambah'],
            ];
        }
        return [['tanggal' => $row['tanggal'], 'jenis' => $row['jenis']]];
    }

    public function jendelaTukarSkip(Carbon $sekarang): array
    {
        $awal  = $sekarang->copy()->addDay()->startOfDay();
        $akhir = $sekarang->copy()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->subDay()->endOfDay();
        return [$awal, $akhir];
    }

    public function tanggalKandidatLibur(int $hariLiburDefault, Carbon $awal, Carbon $akhir): array
    {
        $hasil = [];
        $cur   = $awal->copy();
        while ($cur->lte($akhir)) {
            if ($cur->dayOfWeek === $hariLiburDefault) {
                $hasil[] = $cur->format('Y-m-d');
            }
            $cur->addDay();
        }
        return $hasil;
    }
```

- [ ] **Step 2: Ganti isi method `ambilOverride()` (private, di bagian bawah file) — pakai `expandTukar()`, dan query-nya ikut nangkep baris tukar yang salah satu tanggalnya di luar rentang**

Sebelum:
```php
    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        return JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('tanggal', '>=', $dari->format('Y-m-d'))
            ->whereDate('tanggal', '<=', $sampai->format('Y-m-d'))
            ->get(['tanggal', 'jenis'])
            ->map(fn($o) => ['tanggal' => $o->tanggal->format('Y-m-d'), 'jenis' => $o->jenis])
            ->toArray();
    }
```
Sesudah:
```php
    private function ambilOverride(User $user, Carbon $dari, Carbon $sampai): array
    {
        $dariStr   = $dari->format('Y-m-d');
        $sampaiStr = $sampai->format('Y-m-d');

        $rows = JadwalLibur::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($dariStr, $sampaiStr) {
                $q->whereBetween('tanggal', [$dariStr, $sampaiStr])
                  ->orWhereBetween('tanggal_baru', [$dariStr, $sampaiStr]);
            })
            ->get(['tanggal', 'tanggal_baru', 'jenis']);

        $overrides = [];
        foreach ($rows as $o) {
            $overrides = array_merge($overrides, $this->expandTukar([
                'tanggal'      => $o->tanggal->format('Y-m-d'),
                'tanggal_baru' => $o->tanggal_baru?->format('Y-m-d'),
                'jenis'        => $o->jenis,
            ]));
        }
        return $overrides;
    }
```

- [ ] **Step 3: Cek sintaks**

Run: `php -l app/Services/LiburService.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Tambah kasus tes baru di `tests/jadwal-libur/test_libur_service.php` — SISIPKAN sebelum baris `if ($fail) { ... }` di akhir file, JANGAN hapus/ubah 13 `$check(...)` yang sudah ada**

```php
// ── expandTukar ──────────────────────────────────────────
$check('expandTukar: jenis tambah -> 1 entry apa adanya',
    $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => null, 'jenis' => 'tambah']),
    [['tanggal' => '2026-08-11', 'jenis' => 'tambah']]);

$check('expandTukar: jenis batal -> 1 entry apa adanya',
    $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => null, 'jenis' => 'batal']),
    [['tanggal' => '2026-08-11', 'jenis' => 'batal']]);

$check('expandTukar: jenis tukar -> 2 entry (lama jadi batal, baru jadi tambah)',
    $svc->expandTukar(['tanggal' => '2026-08-12', 'tanggal_baru' => '2026-08-19', 'jenis' => 'tukar']),
    [['tanggal' => '2026-08-12', 'jenis' => 'batal'], ['tanggal' => '2026-08-19', 'jenis' => 'tambah']]);

$overridesTukar = $svc->expandTukar(['tanggal' => '2026-08-11', 'tanggal_baru' => '2026-08-14', 'jenis' => 'tukar']);
$check('tukar: tanggal lama (default cocok) jadi TIDAK libur (dipakai cocokLiburPada)',
    $svc->cocokLiburPada(2, $overridesTukar, Carbon::create(2026, 8, 11)), false);
$check('tukar: tanggal baru (default gak cocok) jadi LIBUR (dipakai cocokLiburPada)',
    $svc->cocokLiburPada(2, $overridesTukar, Carbon::create(2026, 8, 14)), true);

// Tukar dengan tanggal baru LEBIH DULU dari tanggal lama (skenario Kamis-minggu-ini -> Rabu-minggu-depan)
$overridesTukarMundur = $svc->expandTukar(['tanggal' => '2026-08-19', 'tanggal_baru' => '2026-08-13', 'jenis' => 'tukar']);
$check('tukar mundur: tanggal baru (13 Agustus) jadi libur walau lebih dulu dari tanggal lama',
    $svc->cocokLiburPada(3, $overridesTukarMundur, Carbon::create(2026, 8, 13)), true);
$check('tukar mundur: tanggal lama (19 Agustus) jadi TIDAK libur',
    $svc->cocokLiburPada(3, $overridesTukarMundur, Carbon::create(2026, 8, 19)), false);

// ── jendelaTukarSkip ──────────────────────────────────────
// 11 Agustus 2026 = Selasa (dipastikan di atas). Minggu ini: Senin 10 - Minggu 16 Agustus. Minggu depan: Senin 17 - Minggu 23 Agustus.
[$jAwal1, $jAkhir1] = $svc->jendelaTukarSkip(Carbon::create(2026, 8, 11));
$check('jendelaTukarSkip dari Selasa 11 Agustus: awal = besok (12 Agustus)', $jAwal1->format('Y-m-d'), '2026-08-12');
$check('jendelaTukarSkip dari Selasa 11 Agustus: akhir = akhir minggu depan (23 Agustus)', $jAkhir1->format('Y-m-d'), '2026-08-23');

// 9 Agustus 2026 = Minggu (akhir "minggu ini" Senin 3 - Minggu 9). Besok (10 Agustus) sudah masuk "minggu depan" (Senin 10 - Minggu 16).
[$jAwal2, $jAkhir2] = $svc->jendelaTukarSkip(Carbon::create(2026, 8, 9));
$check('jendelaTukarSkip dari Minggu 9 Agustus: awal = besok (10 Agustus)', $jAwal2->format('Y-m-d'), '2026-08-10');
$check('jendelaTukarSkip dari Minggu 9 Agustus: akhir = 16 Agustus (cuma minggu depan, sisa minggu ini sudah 0 hari)', $jAkhir2->format('Y-m-d'), '2026-08-16');

// ── tanggalKandidatLibur ──────────────────────────────────
// Default Selasa (2), rentang 12-23 Agustus 2026. Selasa yang jatuh di rentang ini cuma 18 Agustus (11 Agustus sebelum rentang, 25 Agustus sesudah rentang).
$kandidat = $svc->tanggalKandidatLibur(2, Carbon::create(2026, 8, 12), Carbon::create(2026, 8, 23));
$check('tanggalKandidatLibur: Selasa dalam rentang 12-23 Agustus 2026', $kandidat, ['2026-08-18']);
```

- [ ] **Step 5: Jalankan, pastikan SEMUA (13 lama + kasus baru) PASS**

Run: `php tests/jadwal-libur/test_libur_service.php`
Expected: baris terakhir `=== SEMUA TES LULUS ===`, tidak ada `FAIL` di output manapun.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LiburService.php tests/jadwal-libur/test_libur_service.php
git commit -m "feat: LiburService dukung jenis tukar (expandTukar, jendela 2-minggu, kandidat tanggal)"
```

---

### Task 3: `JadwalLiburController` — form context + validasi 3 jenis + notif pakai `labelTanggal()`

**Files:**
- Modify: `app/Http/Controllers/JadwalLiburController.php`

**Interfaces:**
- Consumes: `LiburService::jendelaTukarSkip(Carbon $sekarang): array`, `LiburService::tanggalKandidatLibur(int, Carbon, Carbon): array` (Task 2); `JadwalLibur::labelTanggal(string $format = 'd/m/Y'): string` (Task 1).
- Produces: view `jadwal-libur.create` menerima variabel baru `$punyaLiburDefault` (bool), `$tanggalKandidat` (array string `Y-m-d`), `$jendelaAwal`/`$jendelaAkhir` (Carbon) — dipakai Task 4.

- [ ] **Step 1: Tambah `use` statement di bagian atas file**

Sebelum:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;
```
Sesudah:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JadwalLibur;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\LiburService;
use Carbon\Carbon;
```

- [ ] **Step 2: Ganti method `create()`**

Sebelum:
```php
    public function create()
    {
        $user       = Auth::user();
        $tanggalMin = today()->addDay()->format('Y-m-d');

        return view('jadwal-libur.create', compact('user', 'tanggalMin'));
    }
```
Sesudah:
```php
    public function create()
    {
        $user       = Auth::user();
        $tanggalMin = today()->addDay()->format('Y-m-d');

        $svc = app(LiburService::class);
        [$jendelaAwal, $jendelaAkhir] = $svc->jendelaTukarSkip(now());

        $punyaLiburDefault = $user->hari_libur_default !== null;
        $tanggalKandidat   = $punyaLiburDefault
            ? $svc->tanggalKandidatLibur($user->hari_libur_default, $jendelaAwal, $jendelaAkhir)
            : [];

        return view('jadwal-libur.create', compact(
            'user', 'tanggalMin', 'punyaLiburDefault', 'tanggalKandidat', 'jendelaAwal', 'jendelaAkhir'
        ));
    }
```

- [ ] **Step 3: Ganti method `store()` — validasi jenis diperluas, tambah pengecekan tanggal-cocok-jendela, bentrok diperluas ke `tanggal_baru`**

Sebelum:
```php
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal' => 'required|date|after:today',
            'jenis'   => 'required|in:tambah,batal',
            'alasan'  => 'nullable|string|max:500',
        ]);

        $sudahAda = JadwalLibur::where('user_id', $user->id)
                               ->whereDate('tanggal', $request->tanggal)
                               ->whereIn('status', ['pending', 'approved'])
                               ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Kamu sudah punya ajuan jadwal libur pada tanggal tersebut.');
        }

        $jadwal = JadwalLibur::create([
            'user_id' => $user->id,
            'tanggal' => $request->tanggal,
            'jenis'   => $request->jenis,
            'alasan'  => $request->alasan,
            'status'  => 'pending',
        ]);

        $this->kirimNotifPengajuan($user, $jadwal);

        return redirect()->route('jadwal-libur.index')
            ->with('success', 'Ajuan jadwal libur berhasil dikirim. Menunggu persetujuan Owner/Mandor.');
    }
```
Sesudah:
```php
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'tanggal'      => 'required|date|after:today',
            'tanggal_baru' => 'required_if:jenis,tukar|nullable|date|after:today|different:tanggal',
            'jenis'        => 'required|in:tambah,batal,tukar',
            'alasan'       => 'nullable|string|max:500',
        ]);

        $svc = app(LiburService::class);
        [$jendelaAwal, $jendelaAkhir] = $svc->jendelaTukarSkip(now());
        $tanggal = Carbon::parse($request->tanggal);

        if (in_array($request->jenis, ['batal', 'tukar'])) {
            if ($user->hari_libur_default === null) {
                return back()->with('error', 'Kamu belum punya jadwal libur default, gak bisa ajukan Skip/Tukar.')->withInput();
            }
            if ($tanggal->dayOfWeek !== $user->hari_libur_default) {
                return back()->with('error', 'Tanggal itu bukan hari libur default kamu.')->withInput();
            }
            if ($tanggal->lt($jendelaAwal) || $tanggal->gt($jendelaAkhir)) {
                return back()->with('error', 'Tanggal harus dalam sisa minggu ini atau minggu depan.')->withInput();
            }
        }

        if ($request->jenis === 'tukar') {
            $tanggalBaru = Carbon::parse($request->tanggal_baru);
            if ($tanggalBaru->lt($jendelaAwal) || $tanggalBaru->gt($jendelaAkhir)) {
                return back()->with('error', 'Tanggal pengganti harus dalam sisa minggu ini atau minggu depan.')->withInput();
            }
            if ($tanggalBaru->dayOfWeek === $user->hari_libur_default) {
                return back()->with('error', 'Tanggal pengganti harus hari yang normalnya kamu kerja.')->withInput();
            }
        }

        $tanggalBaruInput = $request->jenis === 'tukar' ? $request->tanggal_baru : null;

        $bentrok = JadwalLibur::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($request, $tanggalBaruInput) {
                $q->whereDate('tanggal', $request->tanggal)
                  ->orWhereDate('tanggal_baru', $request->tanggal);
                if ($tanggalBaruInput) {
                    $q->orWhereDate('tanggal', $tanggalBaruInput)
                      ->orWhereDate('tanggal_baru', $tanggalBaruInput);
                }
            })
            ->exists();

        if ($bentrok) {
            return back()->with('error', 'Tanggal yang kamu pilih bentrok sama ajuan lain yang masih berjalan.')->withInput();
        }

        $jadwal = JadwalLibur::create([
            'user_id'      => $user->id,
            'tanggal'      => $request->tanggal,
            'tanggal_baru' => $tanggalBaruInput,
            'jenis'        => $request->jenis,
            'alasan'       => $request->alasan,
            'status'       => 'pending',
        ]);

        $this->kirimNotifPengajuan($user, $jadwal);

        return redirect()->route('jadwal-libur.index')
            ->with('success', 'Ajuan jadwal libur berhasil dikirim. Menunggu persetujuan Owner/Mandor.');
    }
```

- [ ] **Step 4: Ganti baris tanggal di `kirimNotifPengajuan()` dan `kirimNotifHasil()` — pakai `labelTanggal()`**

Sebelum (`kirimNotifPengajuan`):
```php
            $pesan = "🗓️ *AJUAN JADWAL LIBUR*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
                   . "Jenis: {$jadwal->jenisLabel()}\n"
```
Sesudah:
```php
            $pesan = "🗓️ *AJUAN JADWAL LIBUR*\n"
                   . "Dari: {$user->name} ({$user->jabatan})\n"
                   . "Tanggal: {$jadwal->labelTanggal('l, d F Y')}\n"
                   . "Jenis: {$jadwal->jenisLabel()}\n"
```

Sebelum (`kirimNotifHasil`):
```php
        $pesan = "{$icon} *JADWAL LIBUR {$label}*\n"
               . "Jenis: {$jadwal->jenisLabel()}\n"
               . "Tanggal: {$jadwal->tanggal->format('d/m/Y')}\n"
```
Sesudah:
```php
        $pesan = "{$icon} *JADWAL LIBUR {$label}*\n"
               . "Jenis: {$jadwal->jenisLabel()}\n"
               . "Tanggal: {$jadwal->labelTanggal('l, d F Y')}\n"
```

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Http/Controllers/JadwalLiburController.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/JadwalLiburController.php
git commit -m "feat: JadwalLiburController dukung jenis tukar (validasi jendela, bentrok, notif)"
```

---

### Task 4: Redesain `create.blade.php` — 3 jenis, field tanggal dinamis

**Files:**
- Modify: `resources/views/jadwal-libur/create.blade.php` (rewrite penuh)

**Interfaces:**
- Consumes: `$user`, `$tanggalMin`, `$punyaLiburDefault`, `$tanggalKandidat`, `$jendelaAwal`, `$jendelaAkhir` (semua dari Task 3's `JadwalLiburController::create()`). POST field names harus persis: `jenis` (`tukar`/`batal`/`tambah`), `tanggal`, `tanggal_baru` (cuma buat Tukar), `alasan` — harus cocok sama yang dibaca `JadwalLiburController::store()` (Task 3).
- Produces: tidak ada, konsumen terakhir di rantai form ini.

- [ ] **Step 1: Ganti isi file jadi (rewrite penuh — WAJIB field `disabled` di tiap input tanggal yang gak lagi terlihat, biar cuma field jenis yang aktif yang ke-submit lewat nama `tanggal` yang sama)**

```blade
{{-- FILE: resources/views/jadwal-libur/create.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Ajukan Jadwal Libur</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .content { padding:16px; padding-bottom:40px; max-width:480px; margin:0 auto; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:20px; margin-bottom:16px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
  .form-control { background:#0f172a !important; border:1px solid #475569 !important; color:#f1f5f9 !important; border-radius:8px; }
  .form-control:focus { border-color:#fbbf24 !important; box-shadow:none !important; }
  .jenis-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px; }
  .jenis-item { background:#0f172a; border:2px solid #334155; border-radius:10px; padding:14px 8px; text-align:center; cursor:pointer; transition:all 0.2s; }
  .jenis-item:hover { border-color:#fbbf24; }
  .jenis-item.selected { border-color:#fbbf24; background:rgba(251,191,36,0.05); }
  .jenis-item input { display:none; }
  .jenis-icon { font-size:22px; display:block; margin-bottom:6px; }
  .jenis-label { font-size:13px; font-weight:600; color:#f1f5f9; }
  .jenis-info { font-size:10px; color:#64748b; margin-top:3px; }
  .btn-submit { width:100%; padding:14px; border-radius:10px; border:none; font-weight:700; font-size:16px; background:#fbbf24; color:#0f172a; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .info-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:12px; font-size:12px; color:#a5b4fc; margin-bottom:16px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('jadwal-libur.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Ajukan Jadwal Libur</div>
    <div style="color:#64748b; font-size:12px;">{{ now()->translatedFormat('l, d F Y') }}</div>
  </div>
</div>

<div class="content">

  @if(session('success'))
  <div class="alert-box alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
  <div class="alert-box alert-error">{{ session('error') }}</div>
  @endif
  @if($errors->any())
  <div class="alert-box alert-error">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
  </div>
  @endif

  <div class="info-box">
    ℹ️ <strong>Tukar</strong>: geser 1 hari libur ke tanggal lain. <strong>Skip</strong>: batalkan 1 hari libur tanpa ganti. <strong>Tambah</strong>: libur ekstra di luar jadwal. Butuh persetujuan Owner/Mandor.
  </div>

  <form method="POST" action="{{ route('jadwal-libur.store') }}">
    @csrf

    {{-- Jenis --}}
    <div class="card-dark">
      <div class="section-label">Jenis Ajuan</div>
      <div class="jenis-grid">
        @if($punyaLiburDefault)
        <label class="jenis-item {{ old('jenis')=='tukar'?'selected':'' }}" onclick="pilihJenis('tukar',this)">
          <input type="radio" name="jenis" value="tukar" {{ old('jenis')=='tukar'?'checked':'' }}>
          <span class="jenis-icon">🔄</span>
          <div class="jenis-label">Tukar</div>
          <div class="jenis-info">Geser libur ke tanggal lain</div>
        </label>
        <label class="jenis-item {{ old('jenis')=='batal'?'selected':'' }}" onclick="pilihJenis('batal',this)">
          <input type="radio" name="jenis" value="batal" {{ old('jenis')=='batal'?'checked':'' }}>
          <span class="jenis-icon">🚫</span>
          <div class="jenis-label">Skip</div>
          <div class="jenis-info">Batalkan, tanpa ganti</div>
        </label>
        @endif
        <label class="jenis-item {{ old('jenis')=='tambah'?'selected':'' }}" onclick="pilihJenis('tambah',this)">
          <input type="radio" name="jenis" value="tambah" {{ old('jenis')=='tambah'?'checked':'' }}>
          <span class="jenis-icon">➕</span>
          <div class="jenis-label">Tambah</div>
          <div class="jenis-info">Libur ekstra</div>
        </label>
      </div>
      @if(!$punyaLiburDefault)
      <div style="color:#64748b; font-size:11px;">Kamu belum punya jadwal libur default, jadi cuma bisa ajukan Tambah.</div>
      @endif
    </div>

    {{-- Tukar: 2 tanggal --}}
    <div class="card-dark grup-tanggal" id="grup-tukar" style="display:none;">
      <div class="section-label">Tanggal Lama (dibatalkan)</div>
      <select name="tanggal" class="form-control" disabled>
        <option value="">-- Pilih tanggal libur default kamu --</option>
        @foreach($tanggalKandidat as $tgl)
        <option value="{{ $tgl }}" {{ old('tanggal')==$tgl?'selected':'' }}>{{ \Carbon\Carbon::parse($tgl)->translatedFormat('l, d F Y') }}</option>
        @endforeach
      </select>
      <div class="section-label" style="margin-top:16px;">Tanggal Baru (pengganti)</div>
      <input type="date" name="tanggal_baru" class="form-control" disabled
             min="{{ $jendelaAwal->format('Y-m-d') }}" max="{{ $jendelaAkhir->format('Y-m-d') }}"
             value="{{ old('tanggal_baru') }}">
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Harus hari yang normalnya kamu kerja, dalam sisa minggu ini atau minggu depan.</div>
    </div>

    {{-- Skip: 1 tanggal dari kandidat --}}
    <div class="card-dark grup-tanggal" id="grup-batal" style="display:none;">
      <div class="section-label">Tanggal (dibatalkan)</div>
      <select name="tanggal" class="form-control" disabled>
        <option value="">-- Pilih tanggal libur default kamu --</option>
        @foreach($tanggalKandidat as $tgl)
        <option value="{{ $tgl }}" {{ old('tanggal')==$tgl?'selected':'' }}>{{ \Carbon\Carbon::parse($tgl)->translatedFormat('l, d F Y') }}</option>
        @endforeach
      </select>
    </div>

    {{-- Tambah: 1 tanggal bebas --}}
    <div class="card-dark grup-tanggal" id="grup-tambah" style="display:none;">
      <div class="section-label">Tanggal (libur ekstra)</div>
      <input type="date" name="tanggal" class="form-control" disabled
             min="{{ $tanggalMin }}" value="{{ old('tanggal', $tanggalMin) }}">
      <div style="color:#64748b; font-size:11px; margin-top:6px;">Minimal besok ({{ \Carbon\Carbon::parse($tanggalMin)->translatedFormat('d F Y') }}), bebas jauh ke depan.</div>
    </div>

    {{-- Alasan --}}
    <div class="card-dark">
      <div class="section-label">Alasan <span style="color:#64748b; text-transform:none; letter-spacing:0;">(opsional)</span></div>
      <textarea name="alasan" class="form-control" rows="3"
                placeholder="Mis. tukar sama Budi, ada acara keluarga, dst."
                style="resize:none;">{{ old('alasan') }}</textarea>
    </div>

    <button type="submit" class="btn-submit">📤 Kirim Ajuan</button>
  </form>

</div>

<script>
function pilihJenis(jenis, el) {
  document.querySelectorAll('.jenis-item').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;

  document.querySelectorAll('.grup-tanggal').forEach(g => {
    const aktif = g.id === 'grup-' + jenis;
    g.style.display = aktif ? 'block' : 'none';
    g.querySelectorAll('input, select').forEach(f => f.disabled = !aktif);
  });
}

@if(old('jenis'))
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('.jenis-item.selected');
  if (sel) pilihJenis('{{ old('jenis') }}', sel);
});
@endif
</script>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/jadwal-libur/create.blade.php
git commit -m "feat: redesain form ajuan jadwal libur jadi 3 jenis (Tukar/Skip/Tambah) dengan field dinamis"
```

---

### Task 5: `index.blade.php` & `approval.blade.php` — pakai `labelTanggal()`

**Files:**
- Modify: `resources/views/jadwal-libur/index.blade.php`
- Modify: `resources/views/jadwal-libur/approval.blade.php`

**Interfaces:**
- Consumes: `JadwalLibur::labelTanggal(string $format = 'd/m/Y'): string` (Task 1).
- Produces: tidak ada, konsumen terakhir.

- [ ] **Step 1: Ganti baris tanggal di `resources/views/jadwal-libur/index.blade.php`**

Sebelum:
```blade
        <div style="font-size:12px; color:#64748b; margin-top:2px;">
          {{ $jadwal->tanggal->translatedFormat('l, d F Y') }}
        </div>
```
Sesudah:
```blade
        <div style="font-size:12px; color:#64748b; margin-top:2px;">
          {{ $jadwal->labelTanggal('l, d F Y') }}
        </div>
```

- [ ] **Step 2: Ganti 2 baris tanggal di `resources/views/jadwal-libur/approval.blade.php`**

Sebelum (bagian "Menunggu Persetujuan"):
```blade
    <div style="font-size:13px; color:#94a3b8; margin-bottom:4px;">
      📅 {{ $jadwal->tanggal->translatedFormat('l, d F Y') }}
    </div>
```
Sesudah:
```blade
    <div style="font-size:13px; color:#94a3b8; margin-bottom:4px;">
      📅 {{ $jadwal->labelTanggal('l, d F Y') }}
    </div>
```

Sebelum (bagian "Riwayat Terbaru"):
```blade
        <div style="font-size:11px; color:#64748b;">
          {{ $jadwal->jenisLabel() }} · {{ $jadwal->tanggal->format('d/m/Y') }}
        </div>
```
Sesudah:
```blade
        <div style="font-size:11px; color:#64748b;">
          {{ $jadwal->jenisLabel() }} · {{ $jadwal->labelTanggal() }}
        </div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/jadwal-libur/index.blade.php resources/views/jadwal-libur/approval.blade.php
git commit -m "feat: riwayat & approval jadwal libur nampilin 2 tanggal buat jenis tukar"
```

---

## Verifikasi manual di production (setelah SQL Task 0 dijalankan + deploy — tidak bisa diuji headless, sama seperti fitur interaktif lain di proyek ini)

- [ ] Karyawan tanpa `hari_libur_default` → buka form ajuan, pastikan cuma opsi **Tambah** yang muncul (Tukar/Skip disembunyikan total).
- [ ] Karyawan dengan `hari_libur_default` → pilih **Tukar**, cek dropdown "Tanggal Lama" cuma nampilin tanggal yang beneran hari libur default dia dalam 2 minggu ke depan; cek "Tanggal Baru" gak bisa pilih tanggal di luar jendela 2 minggu (browser block via `min`/`max`) atau di hari libur default (ditolak server dengan pesan error).
- [ ] Ajukan **Tukar** (tanggal lama + tanggal baru) → approve di halaman approval → cek karyawan itu di kode-absen-hari-ini: tanggal lama DAPAT kode (dianggap kerja), tanggal baru TIDAK dapat kode (dianggap libur).
- [ ] Ajukan **Skip** → approve → cek `cron-alpha.php` gak nandain Alpha di tanggal itu meski karyawan gak absen — dia DIANGGAP kerja (harus absen beneran, bukan otomatis dianggap libur).
- [ ] Riwayat (`/jadwal-libur`) dan approval (`/jadwal-libur/approval`) nampilin 2 tanggal ("dari → ke") buat ajuan Tukar, 1 tanggal buat Skip/Tambah.
- [ ] Notif Telegram (ajuan masuk & hasil approve/tolak) buat Tukar nampilin 2 tanggal dengan benar.
- [ ] Coba ajukan tanggal yang bentrok sama ajuan pending/approved lain (baik di sisi tanggal lama maupun tanggal baru) → ditolak dengan pesan error.

## SQL (Task 0, jalan manual di phpMyAdmin production SEBELUM deploy)

```sql
ALTER TABLE jadwal_libur MODIFY COLUMN jenis ENUM('tambah','batal','tukar') NOT NULL;
ALTER TABLE jadwal_libur ADD COLUMN IF NOT EXISTS tanggal_baru DATE NULL AFTER tanggal;
```
(Kalau `ADD COLUMN IF NOT EXISTS` ditolak karena versi MySQL <8.0.29, jalankan tanpa `IF NOT EXISTS` di baris itu saja.)

## Di luar cakupan (dicatat di spec §7, tidak dikerjakan di plan ini)

- Validasi "tanggal baru gak boleh bentrok sama tanggal yang UDAH jadi libur lewat ajuan approved lain (bukan cuma bentrok tanggal yang sama persis)".
- Migrasi data buat baris `jadwal_libur` lama (kalau ada) yang sudah ke-submit sebelum redesign ini — tidak perlu, baris lama tetap konsisten (1 tanggal 1 efek, `tanggal_baru` NULL).
