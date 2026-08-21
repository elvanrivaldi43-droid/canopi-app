# SWE Fase 2 — Skill Karyawan & Rekomendasi PIC — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Karyawan punya daftar skill (`rab_skill`) yang sebagian nempel otomatis dari jabatan dan sebagian dicentang manual, lalu panel "Mulai Tahap" (Fase 1) dapat tombol "Hitung Saran" yang menghitung live berapa tukang/kenek dibutuhkan dan menandai kandidat PIC yang skill-nya cocok — tanpa mengubah endpoint `mulaiTahap()` yang sudah ada.

**Architecture:** 1 tabel baru (`user_skill`, pivot user↔rab_skill dengan kolom `sumber`) + 1 kolom baru (`rab_skill.default_role`). Dua service class murni (`SkillKaryawanService`, `RekomendasiPicService`) berisi seluruh logika kalkulasi — testable tanpa DB. Controller cuma menjembatani: baca dari DB, panggil service, simpan/kembalikan hasil. Halaman edit Karyawan dapat checklist skill baru; panel Mulai Tahap yang sudah ada dapat 1 tombol + area hasil, lewat AJAX (pola sama `/rab-blok/hitung`).

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Blade, `DB::table` untuk `rab_skill`/`rab_jenis_kerja` (tabel lama, tanpa model — pola konsisten sudah dipakai `ProduktivitasController`), MySQL production (SQL dijalankan manual oleh Elvan di phpMyAdmin, BUKAN via `artisan migrate`).

## Global Constraints

- `mulaiTahap()` di `ProjectController.php:250` **TIDAK BOLEH DIUBAH** — endpoint baru (`saranPic`) berdiri sendiri, read-only, tidak menyentuh `project_tahap`/`project_tahap_pic`.
- Rekomendasi PIC = **saran, bukan gembok** — kandidat yang skill-nya tidak cocok tetap tampil di checklist yang sudah ada dan tetap bisa dicentang manual.
- `rab_jenis_kerja.skill_default` **sudah** dropdown (`produktivitas/index.blade.php:116-118`) — jangan diubah, hanya dibaca. Matching by nama persis (bukan case-insensitive, bukan fuzzy).
- Kategori tukang/kenek karyawan dideteksi dari `users.jabatan` (teks bebas) via keyword match case-insensitive — **tidak ada field baru** ditambah ke `users`. Mengandung "kenek" → Kenek; mengandung "tukang" → Tukang; tidak mengandung keduanya → `null` (tidak terdeteksi), dan ini HARUS tampil sebagai peringatan eksplisit di UI, tidak boleh diam-diam kosong.
- Skill dengan `default_role = 'manual'` TIDAK PERNAH di-auto-attach, harus dicentang manual — berlaku untuk semua karyawan tanpa terkecuali.
- Kandidat PIC "sibuk" dicek dari `project_tahap_pic` yang `project_tahap`-nya berstatus `'sedang'` di MANAPUN (bukan overlap tanggal) — `tanggal_selesai_target` sering kosong di Fase 1, tidak bisa diandalkan.
- Emoji DILARANG di file Blade (pernah bikin korup di server produksi) — semua badge pakai teks/simbol biasa (✓/✗ sebagai karakter, bukan emoji berwarna) atau SVG inline kalau perlu ikon, pola sama `resources/views/partials/sidebar-owner.blade.php`.
- SQL production idempotent — dijalankan manual oleh Elvan di phpMyAdmin SEBELUM push ke `main` (deploy = FTP sync, bukan `artisan migrate`). ALTER TABLE yang menambah kolom: aman kalau dapat error 1060 "Duplicate column" dan dilewati (bukti sudah pernah jalan) — konsisten CLAUDE.md.
- VPS pengembangan ini TIDAK PUNYA akses DB — semua verifikasi pakai `php -l` (syntax), `php artisan route:list` (routing, jalan tanpa DB), dan tes standalone pure-PHP (`tests/swe/test_*.php`, tanpa DB, pola `tests/jadwal-libur/test_libur_service.php` + `tests/bootstrap.php`).

---

### Task 1: Migrasi skema — `user_skill` + `rab_skill.default_role`

**Files:**
- Create: `database/migrations/2026_08_21_000001_add_default_role_to_rab_skill_table.php`
- Create: `database/migrations/2026_08_21_000002_create_user_skill_table.php`

**Interfaces:**
- Consumes: tabel `rab_skill` (sudah ada di production, dibuat manual, tanpa migration file) dan `users` (sudah ada).
- Produces: kolom `rab_skill.default_role` (enum `tukang`/`kenek`/`tukang_kenek`/`manual`, default `manual`) dan tabel `user_skill` (`id`, `user_id`, `rab_skill_id`, `sumber` enum `default_role`/`manual`, `created_at`) — dipakai Task 2 dan seterusnya.

- [ ] **Step 1: Migration ALTER `rab_skill`**

```php
<?php
// database/migrations/2026_08_21_000001_add_default_role_to_rab_skill_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQL sudah dijalankan manual di production sebelum push (lihat CLAUDE.md).
        // Guard ini biar `artisan migrate` tidak crash "Duplicate column".
        if (Schema::hasColumn('rab_skill', 'default_role')) return;

        Schema::table('rab_skill', function (Blueprint $table) {
            $table->enum('default_role', ['tukang', 'kenek', 'tukang_kenek', 'manual'])
                  ->default('manual')
                  ->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('rab_skill', function (Blueprint $table) {
            $table->dropColumn('default_role');
        });
    }
};
```

- [ ] **Step 2: Migration CREATE `user_skill`**

```php
<?php
// database/migrations/2026_08_21_000002_create_user_skill_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_skill')) return;

        Schema::create('user_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Tanpa FK constraint ke rab_skill: tabel itu dibuat manual (bukan lewat
            // migration Laravel, tidak ada migration file-nya), tipe kolom id-nya tidak
            // bisa dipastikan dari sini. Pola sama tahap_master.rab_jenis_kerja_id.
            $table->unsignedBigInteger('rab_skill_id');
            $table->enum('sumber', ['default_role', 'manual']);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skill');
    }
};
```

- [ ] **Step 3: Verifikasi syntax (tanpa DB)**

Run: `php -l database/migrations/2026_08_21_000001_add_default_role_to_rab_skill_table.php && php -l database/migrations/2026_08_21_000002_create_user_skill_table.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_21_000001_add_default_role_to_rab_skill_table.php database/migrations/2026_08_21_000002_create_user_skill_table.php
git commit -m "feat(swe): migrasi user_skill + rab_skill.default_role (Fase 2)"
```

---

### Task 2: Model `UserSkill` + relasi tambahan di `ProjectTahap`

**Files:**
- Create: `app/Models/UserSkill.php`
- Modify: `app/Models/ProjectTahap.php:1-36`

**Interfaces:**
- Consumes: tabel `user_skill` dari Task 1; model `TahapMaster` (sudah ada, `app/Models/TahapMaster.php`).
- Produces: `UserSkill` (fillable `user_id`, `rab_skill_id`, `sumber`, timestamps mati kecuali `created_at`) dipakai Task 4; `ProjectTahap::tahapMaster(): BelongsTo` dipakai Task 7.

- [ ] **Step 1: Buat model `UserSkill`**

```php
<?php
// FILE: app/Models/UserSkill.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSkill extends Model
{
    protected $table = 'user_skill';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'rab_skill_id', 'sumber',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserSkill $row) {
            $row->created_at ??= now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Tambah relasi `tahapMaster()` di `ProjectTahap`**

Di `app/Models/ProjectTahap.php`, tambahkan method ini setelah `project()` (sebelum `pic()`):

```php
    public function tahapMaster()
    {
        return $this->belongsTo(TahapMaster::class, 'tahap_master_id');
    }
```

- [ ] **Step 3: Verifikasi syntax**

Run: `php -l app/Models/UserSkill.php && php -l app/Models/ProjectTahap.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 4: Commit**

```bash
git add app/Models/UserSkill.php app/Models/ProjectTahap.php
git commit -m "feat(swe): model UserSkill + relasi ProjectTahap::tahapMaster"
```

---

### Task 3: `SkillKaryawanService` — deteksi kategori & sinkronisasi skill (logic murni, testable tanpa DB)

**Files:**
- Create: `app/Services/SkillKaryawanService.php`
- Test: `tests/swe/test_skill_karyawan_service.php`

**Interfaces:**
- Consumes: tidak ada dependency DB — semua data lewat parameter (array/collection).
- Produces:
  - `deteksiKategori(string $jabatan): ?string` — `'tukang'` | `'kenek'` | `null`.
  - `skillOtomatisUntukKategori(?string $kategori, iterable $rabSkillRows): array` — array of int (rab_skill id) yang default_role-nya cocok kategori. `$rabSkillRows` = iterable of object/array dengan `id` dan `default_role`.
  - `susunUserSkill(int $userId, array $skillIdDicentang, ?string $kategori, iterable $rabSkillRows): array` — array of `['user_id'=>, 'rab_skill_id'=>, 'sumber'=>]` siap di-insert, dipakai Task 4.

- [ ] **Step 1: Tulis test (gagal dulu, class belum ada)**

```php
<?php
// FILE: tests/swe/test_skill_karyawan_service.php
// Jalankan: php tests/swe/test_skill_karyawan_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\SkillKaryawanService;

$svc = new SkillKaryawanService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── deteksiKategori ──────────────────────────────────────
$check('mengandung "Tukang" -> tukang', $svc->deteksiKategori('Tukang Las'), 'tukang');
$check('mengandung "Kepala Tukang" -> tukang', $svc->deteksiKategori('Kepala Tukang'), 'tukang');
$check('mengandung "kenek" huruf kecil -> kenek', $svc->deteksiKategori('kenek cat'), 'kenek');
$check('mengandung "KENEK" huruf besar -> kenek (case-insensitive)', $svc->deteksiKategori('KENEK POTONG'), 'kenek');
$check('tidak mengandung keduanya -> null', $svc->deteksiKategori('Admin Sales'), null);
$check('tidak mengandung keduanya (Surveyor) -> null', $svc->deteksiKategori('Surveyor'), null);

// ── skillOtomatisUntukKategori ────────────────────────────
$rabSkill = [
    (object) ['id' => 1, 'default_role' => 'tukang'],
    (object) ['id' => 2, 'default_role' => 'kenek'],
    (object) ['id' => 3, 'default_role' => 'tukang_kenek'],
    (object) ['id' => 4, 'default_role' => 'manual'],
];
$check('kategori tukang -> ambil id 1 (tukang) & 3 (tukang_kenek), urut naik',
    $svc->skillOtomatisUntukKategori('tukang', $rabSkill), [1, 3]);
$check('kategori kenek -> ambil id 2 (kenek) & 3 (tukang_kenek), urut naik',
    $svc->skillOtomatisUntukKategori('kenek', $rabSkill), [2, 3]);
$check('kategori null -> tidak ada yang otomatis nempel',
    $svc->skillOtomatisUntukKategori(null, $rabSkill), []);
$check('skill default_role=manual TIDAK PERNAH otomatis, kategori apapun',
    in_array(4, $svc->skillOtomatisUntukKategori('tukang', $rabSkill)), false);

// ── susunUserSkill ─────────────────────────────────────────
// Karyawan tukang (userId=99), dicentang: id 1 (otomatis untuk tukang), id 3 (otomatis
// tukang_kenek), id 4 (skill manual, dicentang sendiri oleh Admin).
$hasil = $svc->susunUserSkill(99, [1, 3, 4], 'tukang', $rabSkill);
$check('susunUserSkill: 3 baris tersusun', count($hasil), 3);
$check('id 1 -> sumber default_role (otomatis untuk tukang)',
    collect($hasil)->firstWhere('rab_skill_id', 1)['sumber'], 'default_role');
$check('id 3 -> sumber default_role (tukang_kenek juga otomatis untuk tukang)',
    collect($hasil)->firstWhere('rab_skill_id', 3)['sumber'], 'default_role');
$check('id 4 -> sumber manual (default_role=manual, dicentang sendiri)',
    collect($hasil)->firstWhere('rab_skill_id', 4)['sumber'], 'manual');
$check('semua baris punya user_id yang benar',
    collect($hasil)->pluck('user_id')->unique()->all(), [99]);

// Skill id 2 (default_role=kenek) dicentang MANUAL oleh karyawan kategori tukang ->
// tetap tersimpan, tapi sumbernya manual (bukan otomatis untuk kategori dia).
$hasil2 = $svc->susunUserSkill(99, [2], 'tukang', $rabSkill);
$check('skill kenek dicentang manual oleh tukang -> sumber manual (bukan otomatis)',
    $hasil2[0]['sumber'], 'manual');

// Tidak dicentang sama sekali -> array kosong, bukan error.
$check('tidak ada yang dicentang -> array kosong', $svc->susunUserSkill(99, [], 'tukang', $rabSkill), []);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal (class belum ada)**

Run: `php tests/swe/test_skill_karyawan_service.php`
Expected: Fatal error `Class "App\Services\SkillKaryawanService" not found`.

- [ ] **Step 3: Implementasi `SkillKaryawanService`**

```php
<?php
// FILE: app/Services/SkillKaryawanService.php

namespace App\Services;

class SkillKaryawanService
{
    /**
     * Deteksi kategori tukang/kenek dari teks jabatan bebas.
     * Tidak ada field khusus di `users` — sengaja dari keyword match, lihat
     * spec Fase 2 keputusan #5b (data lama contoh: "Tukang Las", "Kepala Tukang",
     * "Admin Sales", "Surveyor").
     */
    public function deteksiKategori(string $jabatan): ?string
    {
        $j = mb_strtolower($jabatan);
        if (str_contains($j, 'kenek')) return 'kenek';
        if (str_contains($j, 'tukang')) return 'tukang';
        return null;
    }

    /**
     * Daftar id rab_skill yang otomatis nempel untuk 1 kategori, berdasarkan
     * kolom rab_skill.default_role. Skill dengan default_role='manual' TIDAK
     * PERNAH masuk sini, berapapun kategorinya.
     */
    public function skillOtomatisUntukKategori(?string $kategori, iterable $rabSkillRows): array
    {
        if ($kategori === null) return [];

        $cocok = $kategori === 'tukang'
            ? ['tukang', 'tukang_kenek']
            : ['kenek', 'tukang_kenek'];

        $ids = [];
        foreach ($rabSkillRows as $row) {
            $defaultRole = is_array($row) ? $row['default_role'] : $row->default_role;
            $id          = is_array($row) ? $row['id'] : $row->id;
            if (in_array($defaultRole, $cocok, true)) $ids[] = (int) $id;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Susun baris user_skill siap-insert dari daftar id yang dicentang di form.
     * Skill yang termasuk daftar otomatis kategori ini -> sumber 'default_role'.
     * Sisanya (dicentang manual, termasuk skill default_role='manual' ATAU skill
     * kategori LAIN yang dicentang manual) -> sumber 'manual'.
     */
    public function susunUserSkill(int $userId, array $skillIdDicentang, ?string $kategori, iterable $rabSkillRows): array
    {
        $otomatis = $this->skillOtomatisUntukKategori($kategori, $rabSkillRows);

        $hasil = [];
        foreach (array_unique($skillIdDicentang) as $skillId) {
            $skillId = (int) $skillId;
            $hasil[] = [
                'user_id'      => $userId,
                'rab_skill_id' => $skillId,
                'sumber'       => in_array($skillId, $otomatis, true) ? 'default_role' : 'manual',
            ];
        }
        return $hasil;
    }
}
```

- [ ] **Step 4: Jalankan test lagi, pastikan lulus**

Run: `php tests/swe/test_skill_karyawan_service.php`
Expected: `=== SEMUA TES LULUS ===`, semua baris `PASS`.

- [ ] **Step 5: Verifikasi syntax**

Run: `php -l app/Services/SkillKaryawanService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SkillKaryawanService.php tests/swe/test_skill_karyawan_service.php
git commit -m "feat(swe): SkillKaryawanService — deteksi kategori & sinkronisasi user_skill"
```

---

### Task 4: Wire `SkillKaryawanService` ke `KaryawanController` (edit & update)

**Files:**
- Modify: `app/Http/Controllers/KaryawanController.php:1-14` (import), `:151-160` (`edit()`), `:162-223` (`update()`)

**Interfaces:**
- Consumes: `SkillKaryawanService` (Task 3), `UserSkill` model (Task 2), tabel `rab_skill` via `DB::table`.
- Produces: variabel view `$rabSkill`, `$userSkillIds`, `$kategoriTerdeteksi` dikonsumsi Task 5 (view checklist).

- [ ] **Step 1: Tambah import di puncak file**

Di `app/Http/Controllers/KaryawanController.php`, tambahkan setelah `use Illuminate\Support\Facades\Mail;`:

```php
use Illuminate\Support\Facades\DB;
use App\Services\SkillKaryawanService;
use App\Models\UserSkill;
```

- [ ] **Step 2: Perluas `edit()` — kirim data skill ke view**

Ganti method `edit()` (baris 151-160) jadi:

```php
    public function edit(User $karyawan)
    {
        $this->pastikanBolehKelola($karyawan);

        $levels    = $this->levels;
        $banks     = $this->banks;
        $tunjangan = \App\Models\TunjanganMaster::where('aktif', true)->get();
        $karyawan->load('tunjangan');

        $svc          = new SkillKaryawanService();
        $rabSkill     = DB::table('rab_skill')->where('is_active', true)->orderBy('urutan')->orderBy('nama')->get();
        $userSkillIds = UserSkill::where('user_id', $karyawan->id)->pluck('rab_skill_id')->all();
        $kategori     = $svc->deteksiKategori($karyawan->jabatan ?? '');

        // Karyawan yang belum pernah disimpan skill-nya (baru dibuat, atau dibuat
        // sebelum Fase 2 ada) -> tampilkan PREVIEW skill otomatis kategori dia biar
        // form pertama kali dibuka sudah kecentang wajar, bukan kosong melompong.
        // Ini cuma tampilan, belum tersimpan sampai form di-submit.
        if (empty($userSkillIds)) {
            $userSkillIds = $svc->skillOtomatisUntukKategori($kategori, $rabSkill);
        }

        return view('karyawan.edit', compact('karyawan', 'levels', 'tunjangan', 'banks', 'rabSkill', 'userSkillIds', 'kategori'));
    }
```

- [ ] **Step 3: Sinkronkan skill di `update()`**

Di `app/Http/Controllers/KaryawanController.php`, cari baris (akhir blok tunjangan, sebelum `return redirect()->route('karyawan.show', $karyawan)`):

```php
        return redirect()->route('karyawan.show', $karyawan)
            ->with('success', 'Data karyawan berhasil diperbarui.');
```

Ganti jadi (tambahkan blok sinkronisasi skill SEBELUM `return`):

```php
        // Skill disinkronkan SETELAH $karyawan->update() di atas, biar deteksi
        // kategori pakai jabatan yang BARU (kalau jabatan ikut diubah di form ini).
        $svc      = new SkillKaryawanService();
        $rabSkill = DB::table('rab_skill')->where('is_active', true)->get();
        $kategori = $svc->deteksiKategori($karyawan->jabatan);
        $baris    = $svc->susunUserSkill($karyawan->id, (array) $request->input('skill', []), $kategori, $rabSkill);

        UserSkill::where('user_id', $karyawan->id)->delete();
        if (!empty($baris)) {
            UserSkill::insert(array_map(fn ($b) => $b + ['created_at' => now()], $baris));
        }

        return redirect()->route('karyawan.show', $karyawan)
            ->with('success', 'Data karyawan berhasil diperbarui.');
```

- [ ] **Step 4: Verifikasi syntax**

Run: `php -l app/Http/Controllers/KaryawanController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/KaryawanController.php
git commit -m "feat(swe): sinkronisasi skill karyawan di KaryawanController::edit/update"
```

---

### Task 5: Checklist skill di halaman edit Karyawan (view)

**Files:**
- Modify: `resources/views/karyawan/edit.blade.php` (tambah section baru, dekat field `jabatan` di baris ~120)

**Interfaces:**
- Consumes: `$rabSkill`, `$userSkillIds`, `$kategori` dari Task 4.
- Produces: input `name="skill[]"` (checkbox) dibaca `$request->input('skill', [])` di Task 4 Step 3.

- [ ] **Step 1: Lihat konteks persis field jabatan buat nentuin titik sisip**

Run: `grep -n 'name="jabatan"' -A 3 resources/views/karyawan/edit.blade.php`
Expected: baris field jabatan (sekitar baris 120) + 2-3 baris sesudahnya (penutup div).

- [ ] **Step 2: Sisipkan section checklist skill PERSIS setelah blok field jabatan selesai (setelah `</div>` penutup field jabatan, sebelum field berikutnya)**

```blade
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px;">Skill (untuk rekomendasi PIC SWE)</label>

                    @if($kategori === null)
                    <div style="background:#78350f22; border:1px solid #b45309; border-radius:6px; padding:8px 10px; margin-bottom:8px; font-size:12px; color:#fbbf24;">
                        Kategori lapangan tidak terdeteksi dari jabatan "{{ $karyawan->jabatan }}" — skill standar TIDAK nempel otomatis. Centang manual di bawah kalau perlu.
                    </div>
                    @endif

                    @forelse($rabSkill as $s)
                    <div style="display:flex; align-items:center; gap:6px; padding:3px 0;">
                        <input type="checkbox" name="skill[]" value="{{ $s->id }}" id="skill{{ $s->id }}"
                            {{ in_array($s->id, $userSkillIds) ? 'checked' : '' }}>
                        <label for="skill{{ $s->id }}" style="font-size:12px; color:#e2e8f0;">
                            {{ $s->nama }}
                            @if(in_array($s->default_role, ['tukang','kenek','tukang_kenek']))
                            <span style="font-size:10px; color:#64748b;">(otomatis {{ str_replace('_', '/', $s->default_role) }})</span>
                            @endif
                        </label>
                    </div>
                    @empty
                    <div style="font-size:12px; color:#64748b;">Belum ada skill di master data. Isi dulu di halaman Kelola Produktivitas.</div>
                    @endforelse
                </div>
```

- [ ] **Step 3: Verifikasi Blade compile (tanpa server, cek syntax `@if/@forelse/@empty` seimbang)**

Run: `php -r '$c = file_get_contents("resources/views/karyawan/edit.blade.php"); $o = substr_count($c,"@if")+substr_count($c,"@forelse"); $c2 = substr_count($c,"@endif")+substr_count($c,"@endforelse"); echo ($o === $c2 ? "SEIMBANG ($o/$c2)\n" : "TIDAK SEIMBANG: buka=$o tutup=$c2\n");'`
Expected: `SEIMBANG (n/n)`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/karyawan/edit.blade.php
git commit -m "feat(swe): checklist skill di halaman edit Karyawan"
```

---

### Task 6: `RekomendasiPicService` — hitung jumlah saran & urutkan kandidat (logic murni, testable tanpa DB)

**Files:**
- Create: `app/Services/RekomendasiPicService.php`
- Test: `tests/swe/test_rekomendasi_pic_service.php`

**Interfaces:**
- Consumes: tidak ada dependency DB.
- Produces:
  - `hitungJumlahDisarankan(?float $qty, ?float $produktivitas, ?int $timDefault, ?int $targetHari): ?int` — `null` kalau input tidak lengkap/tidak valid buat dihitung.
  - `urutkanKandidat(array $kandidat): array` — tiap elemen `['user_id'=>int,'cocok'=>bool,'sibuk'=>bool, ...field lain dipertahankan]`. Urutan: cocok & tidak sibuk dulu, lalu cocok & sibuk, lalu tidak cocok — urutan asli dalam grup yang sama dipertahankan (stable sort).

- [ ] **Step 1: Tulis test (gagal dulu, class belum ada)**

```php
<?php
// FILE: tests/swe/test_rekomendasi_pic_service.php
// Jalankan: php tests/swe/test_rekomendasi_pic_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\RekomendasiPicService;

$svc = new RekomendasiPicService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// ── hitungJumlahDisarankan ───────────────────────────────
// qty=40, produktivitas=8/hari -> estimasi default 5 hari. Tim default 2 orang.
// Target 5 hari (sama persis estimasi) -> multiplier 1 -> tetap 2 orang.
$check('target = estimasi default -> jumlah tim default',
    $svc->hitungJumlahDisarankan(40, 8, 2, 5), 2);

// Target dipepetin jadi 2.5 hari (setengah dari estimasi) -> butuh 2x orang -> 4.
$check('target setengah dari estimasi -> 2x tim default',
    $svc->hitungJumlahDisarankan(40, 8, 2, 3), 4); // 5/3=1.67 * 2 = 3.33 -> ceil 4

// Target lebih longgar dari estimasi (10 hari, estimasi cuma 5) -> multiplier < 1,
// tapi minimal tetap 1 orang, tidak boleh 0.
$check('target sangat longgar -> minimal 1 orang, tidak 0',
    $svc->hitungJumlahDisarankan(40, 8, 2, 20), 1); // 5/20=0.25 * 2 = 0.5 -> ceil 1, bukan 0

// Input tidak lengkap -> null, bukan division by zero / exception.
$check('qty null -> null', $svc->hitungJumlahDisarankan(null, 8, 2, 5), null);
$check('produktivitas null -> null', $svc->hitungJumlahDisarankan(40, null, 2, 5), null);
$check('produktivitas 0 -> null (hindari division by zero)', $svc->hitungJumlahDisarankan(40, 0, 2, 5), null);
$check('targetHari null -> null', $svc->hitungJumlahDisarankan(40, 8, 2, null), null);
$check('targetHari 0 -> null (hindari division by zero)', $svc->hitungJumlahDisarankan(40, 8, 2, 0), null);
$check('targetHari negatif (target sudah lewat) -> null', $svc->hitungJumlahDisarankan(40, 8, 2, -1), null);
$check('timDefault null -> null', $svc->hitungJumlahDisarankan(40, 8, null, 5), null);

// ── urutkanKandidat ────────────────────────────────────────
$kandidat = [
    ['user_id' => 1, 'cocok' => false, 'sibuk' => false], // tidak cocok
    ['user_id' => 2, 'cocok' => true,  'sibuk' => true],  // cocok, sibuk
    ['user_id' => 3, 'cocok' => true,  'sibuk' => false], // cocok, kosong
    ['user_id' => 4, 'cocok' => false, 'sibuk' => true],  // tidak cocok
    ['user_id' => 5, 'cocok' => true,  'sibuk' => false], // cocok, kosong (urutan dipertahankan vs id 3)
];
$urut = $svc->urutkanKandidat($kandidat);
$check('urutan: cocok&kosong dulu (3,5), lalu cocok&sibuk (2), lalu tidak cocok (1,4)',
    array_column($urut, 'user_id'), [3, 5, 2, 1, 4]);

$check('urutkanKandidat array kosong -> array kosong', $svc->urutkanKandidat([]), []);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php tests/swe/test_rekomendasi_pic_service.php`
Expected: Fatal error `Class "App\Services\RekomendasiPicService" not found`.

- [ ] **Step 3: Implementasi `RekomendasiPicService`**

```php
<?php
// FILE: app/Services/RekomendasiPicService.php

namespace App\Services;

class RekomendasiPicService
{
    /**
     * hari_estimasi_default = qty / produktivitas
     * multiplier            = hari_estimasi_default / target_hari
     * jumlah_disarankan     = ceil(tim_default * multiplier), minimal 1
     *
     * null kalau salah satu input kosong/tidak valid buat dihitung (bukan exception) —
     * pemanggil (controller) yang memutuskan pesan ke user, service ini murni angka.
     */
    public function hitungJumlahDisarankan(?float $qty, ?float $produktivitas, ?int $timDefault, ?int $targetHari): ?int
    {
        if ($qty === null || $produktivitas === null || $timDefault === null || $targetHari === null) return null;
        if ($produktivitas <= 0 || $targetHari <= 0) return null;

        $hariEstimasiDefault = $qty / $produktivitas;
        $multiplier          = $hariEstimasiDefault / $targetHari;

        return max(1, (int) ceil($timDefault * $multiplier));
    }

    /**
     * Urutkan kandidat PIC: cocok & tidak sibuk dulu, lalu cocok & sibuk, lalu
     * tidak cocok paling bawah. Urutan asli dalam grup yang sama dipertahankan
     * (usort PHP 8+ stable).
     */
    public function urutkanKandidat(array $kandidat): array
    {
        $peringkat = function (array $k): int {
            if ($k['cocok'] && !$k['sibuk']) return 0;
            if ($k['cocok'] && $k['sibuk'])  return 1;
            return 2;
        };

        usort($kandidat, fn ($a, $b) => $peringkat($a) <=> $peringkat($b));
        return $kandidat;
    }
}
```

- [ ] **Step 4: Jalankan test lagi, pastikan lulus**

Run: `php tests/swe/test_rekomendasi_pic_service.php`
Expected: `=== SEMUA TES LULUS ===`, semua baris `PASS`.

- [ ] **Step 5: Verifikasi syntax**

Run: `php -l app/Services/RekomendasiPicService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/RekomendasiPicService.php tests/swe/test_rekomendasi_pic_service.php
git commit -m "feat(swe): RekomendasiPicService — jumlah saran & urutan kandidat PIC"
```

---

### Task 7: Endpoint `saranPic()` di `ProjectController` + route

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php` (tambah method baru setelah `mulaiTahap()`, sebelum `selesaiTahap()` — sekitar baris 294)
- Modify: `routes/web.php:399-400` (tambah 1 route di group `level:1,2,3` yang sama)

**Interfaces:**
- Consumes: `RekomendasiPicService` (Task 6), `TahapMaster`/`ProjectTahap::tahapMaster()` (Task 2), tabel `rab_jenis_kerja`/`rab_skill`/`user_skill`, `ProjectTahapPic`.
- Produces: JSON `{jumlah_tukang_disarankan, jumlah_kenek_disarankan, kandidat: [{user_id, cocok, sibuk}], pesan}` dikonsumsi Task 8 (JS).

- [ ] **Step 1: Tambah route**

Di `routes/web.php`, ubah blok:

```php
    // Tahap produksi (SWE Fase 1)
    Route::post('/project-tahap/{projectTahap}/mulai',   [ProjectController::class, 'mulaiTahap'])->name('projects.tahap.mulai');
    Route::post('/project-tahap/{projectTahap}/selesai', [ProjectController::class, 'selesaiTahap'])->name('projects.tahap.selesai');
```

jadi:

```php
    // Tahap produksi (SWE Fase 1)
    Route::post('/project-tahap/{projectTahap}/mulai',   [ProjectController::class, 'mulaiTahap'])->name('projects.tahap.mulai');
    Route::post('/project-tahap/{projectTahap}/selesai', [ProjectController::class, 'selesaiTahap'])->name('projects.tahap.selesai');

    // Rekomendasi PIC (SWE Fase 2) — read-only, TIDAK mengubah project_tahap/project_tahap_pic
    Route::post('/project-tahap/{projectTahap}/saran-pic', [ProjectController::class, 'saranPic'])->name('projects.tahap.saran-pic');
```

- [ ] **Step 2: Tambah import di puncak `ProjectController.php`**

Cek dulu import yang sudah ada:

Run: `grep -n "^use " app/Http/Controllers/ProjectController.php`

Tambahkan (kalau belum ada) baris berikut setelah import `use` terakhir:

```php
use App\Services\RekomendasiPicService;
```

- [ ] **Step 3: Implementasi `saranPic()`**

Sisipkan method baru PERSIS setelah `mulaiTahap()` berakhir (setelah baris `}` penutup `mulaiTahap()`, sebelum komentar `// SWE FASE 1 — TANDAI SELESAI`):

```php
    // ============================================================
    // SWE FASE 2 — HITUNG SARAN PIC (read-only, tidak mengubah apapun —
    // mulaiTahap() di atas TIDAK disentuh sama sekali)
    // ============================================================
    public function saranPic(Request $request, ProjectTahap $projectTahap)
    {
        $request->validate([
            'qty'                    => 'nullable|numeric|min:0',
            'tanggal_selesai_target' => 'nullable|date',
        ]);

        $tahapMaster = $projectTahap->tahapMaster;
        if (!$tahapMaster || !$tahapMaster->rab_jenis_kerja_id) {
            return response()->json([
                'jumlah_tukang_disarankan' => null,
                'jumlah_kenek_disarankan'  => null,
                'kandidat'                 => [],
                'pesan'                    => 'Tahap ini tidak tertaut ke jenis kerja RAB, saran jumlah tidak bisa dihitung. Pilih PIC manual.',
            ]);
        }

        $rabJenisKerja = DB::table('rab_jenis_kerja')->where('id', $tahapMaster->rab_jenis_kerja_id)->first();
        $svc           = new RekomendasiPicService();

        $isInst        = $tahapMaster->tipe === 'inst';
        $produktivitas = $isInst ? $rabJenisKerja?->produktivitas_inst : $rabJenisKerja?->produktivitas_per_hari;
        $timTukang     = $isInst ? $rabJenisKerja?->jml_tukang_inst    : $rabJenisKerja?->jml_tukang;
        $timKenek      = $isInst ? $rabJenisKerja?->jml_kenek_inst     : $rabJenisKerja?->jml_kenek;

        $qty        = $request->qty !== null ? (float) $request->qty : null;
        $targetHari = $request->tanggal_selesai_target
            ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($request->tanggal_selesai_target)->startOfDay(), false)
            : null;

        $jumlahTukang = $svc->hitungJumlahDisarankan($qty, $produktivitas ? (float) $produktivitas : null, $timTukang !== null ? (int) $timTukang : null, $targetHari);
        $jumlahKenek  = $svc->hitungJumlahDisarankan($qty, $produktivitas ? (float) $produktivitas : null, $timKenek  !== null ? (int) $timKenek  : null, $targetHari);

        // Skill yang jadi acuan cocok/tidak: exact match nama (dropdown skill_default
        // sudah menjamin nilainya valid, tidak perlu fuzzy/case-insensitive).
        $skillId = $rabJenisKerja?->skill_default
            ? DB::table('rab_skill')->where('nama', $rabJenisKerja->skill_default)->value('id')
            : null;

        $karyawan = User::whereIn('level', [3, 5, 6])->where('status', 'aktif')->orderBy('name')->get();

        $userSkillMap = DB::table('user_skill')
            ->whereIn('user_id', $karyawan->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $sibukUserIds = ProjectTahapPic::whereHas('projectTahap', fn ($q) => $q->where('status', 'sedang'))
            ->pluck('user_id')
            ->unique();

        $kandidat = $karyawan->map(function (User $k) use ($skillId, $userSkillMap, $sibukUserIds) {
            $skillIdsKaryawan = ($userSkillMap[$k->id] ?? collect())->pluck('rab_skill_id')->all();
            return [
                'user_id' => $k->id,
                'name'    => $k->name,
                'cocok'   => $skillId !== null && in_array($skillId, $skillIdsKaryawan, true),
                'sibuk'   => $sibukUserIds->contains($k->id),
            ];
        })->all();

        $kandidat = $svc->urutkanKandidat($kandidat);

        return response()->json([
            'jumlah_tukang_disarankan' => $jumlahTukang,
            'jumlah_kenek_disarankan'  => $jumlahKenek,
            'kandidat'                 => $kandidat,
            'pesan'                    => null,
        ]);
    }
```

- [ ] **Step 4: Verifikasi syntax**

Run: `php -l app/Http/Controllers/ProjectController.php && php -l routes/web.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 5: Verifikasi route terdaftar (tanpa DB)**

Run: `php artisan route:list --json | grep -o '"name":"projects.tahap[^"]*"'`
Expected: 3 baris — `"name":"projects.tahap.mulai"`, `"name":"projects.tahap.selesai"`, `"name":"projects.tahap.saran-pic"`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProjectController.php routes/web.php
git commit -m "feat(swe): endpoint saranPic() — rekomendasi jumlah & kandidat PIC"
```

---

### Task 8: UI "Hitung Saran" di panel Mulai Tahap

**Files:**
- Modify: `resources/views/projects/show.blade.php:184-212`

**Interfaces:**
- Consumes: route `projects.tahap.saran-pic` (Task 7), checkbox `pic[i][user_id]` yang sudah ada (`show.blade.php:200`).
- Produces: tidak ada — ini titik akhir alur (UI murni).

- [ ] **Step 1: Sisipkan tombol "Hitung Saran" + area hasil setelah field Target Selesai, sebelum label PIC**

Ganti blok (baris 194-197):

```blade
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">Target Selesai (opsional)</label>
                        <input type="date" name="tanggal_selesai_target" style="width:100%; background:#1e293b; border:1px solid #334155; border-radius:6px; padding:8px; color:#f1f5f9; margin-bottom:8px;">

                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">PIC (pilih manual, minimal 1)</label>
```

jadi:

```blade
                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">Target Selesai (opsional)</label>
                        <input type="date" name="tanggal_selesai_target" id="target{{ $tahap->id }}" style="width:100%; background:#1e293b; border:1px solid #334155; border-radius:6px; padding:8px; color:#f1f5f9; margin-bottom:8px;">

                        <button type="button" onclick="hitungSaranPic({{ $tahap->id }})"
                            style="background:#334155; color:#e2e8f0; padding:6px 12px; border-radius:6px; border:none; font-size:11px; font-weight:600; cursor:pointer; margin-bottom:8px;">Hitung Saran</button>
                        <div id="saran{{ $tahap->id }}" style="font-size:11px; color:#94a3b8; margin-bottom:8px;"></div>

                        <label style="font-size:11px; color:#94a3b8; display:block; margin-bottom:3px;">PIC (pilih manual, minimal 1)</label>
```

- [ ] **Step 2: Tandai tiap baris checklist PIC dengan `data-user-id` (buat dicari JS)**

Ganti (baris 198-208):

```blade
                        @foreach($karyawan as $k)
                        <div style="display:flex; align-items:center; gap:6px; padding:4px 0;">
```

jadi:

```blade
                        @foreach($karyawan as $k)
                        <div data-user-id="{{ $k->id }}" style="display:flex; align-items:center; gap:6px; padding:4px 0;">
```

(baris-baris di dalamnya, `input`/`label`/`select`, tetap persis seperti semula — tidak diubah.)

- [ ] **Step 3: Tambah `<span>` badge kosong di tiap baris kandidat (diisi JS setelah "Hitung Saran" diklik)**

Ganti baris label (baris 202):

```blade
                            <label for="pic{{ $tahap->id }}_{{ $k->id }}" style="font-size:12px; color:#e2e8f0;">{{ $k->name }}</label>
```

jadi:

```blade
                            <label for="pic{{ $tahap->id }}_{{ $k->id }}" style="font-size:12px; color:#e2e8f0;">{{ $k->name }}</label>
                            <span class="badge-cocok" style="font-size:10px;"></span>
```

- [ ] **Step 4: Tambah script `hitungSaranPic()` — taruh di akhir file, sebelum `@endsection`/penutup**

Run dulu: `tail -20 resources/views/projects/show.blade.php` untuk lihat penutup file persis (`@endsection`, `</script>` existing, atau langsung EOF).

Tambahkan sebelum penutup section/script yang sudah ada (atau di akhir file kalau tidak ada `<script>` pembungkus lain):

```blade
<script>
function hitungSaranPic(tahapId) {
    const qty    = document.querySelector(`#mulaiTahap${tahapId} input[name="qty"]`).value;
    const target = document.getElementById(`target${tahapId}`).value;
    const hasilEl = document.getElementById(`saran${tahapId}`);
    hasilEl.textContent = 'Menghitung...';

    fetch(`/project-tahap/${tahapId}/saran-pic`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ qty: qty || null, tanggal_selesai_target: target || null }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.pesan) {
            hasilEl.textContent = data.pesan;
            return;
        }
        const t = data.jumlah_tukang_disarankan;
        const k = data.jumlah_kenek_disarankan;
        hasilEl.textContent = (t !== null && k !== null)
            ? `Disarankan: ${t} tukang, ${k} kenek`
            : 'Isi Qty & Target Selesai dulu buat hitung jumlah orang.';

        const container = document.getElementById(`mulaiTahap${tahapId}`);
        data.kandidat.forEach(kand => {
            const row = container.querySelector(`[data-user-id="${kand.user_id}"]`);
            if (!row) return;
            const badge = row.querySelector('.badge-cocok');
            if (kand.cocok && !kand.sibuk) { badge.textContent = '✓ cocok'; badge.style.color = '#6ee7b7'; }
            else if (kand.cocok && kand.sibuk) { badge.textContent = '✓ cocok, sibuk'; badge.style.color = '#fbbf24'; }
            else { badge.textContent = 'skill gak cocok'; badge.style.color = '#64748b'; }
        });
    })
    .catch(() => { hasilEl.textContent = 'Gagal menghitung saran, coba lagi.'; });
}
</script>
```

- [ ] **Step 5: Pastikan halaman punya `<meta name="csrf-token">` (dicek, bukan ditambah kalau sudah ada — pola standar Laravel Blade layout)**

Run: `grep -rn 'csrf-token' resources/views/layouts/ 2>/dev/null`
Expected: minimal 1 baris `<meta name="csrf-token" content="{{ csrf_token() }}">` di layout utama. Kalau tidak ketemu, tambahkan baris itu di `<head>` layout utama (`resources/views/layouts/app.blade.php` atau sejenisnya) SEBELUM lanjut ke step berikutnya — banyak halaman lain di project ini sudah pakai AJAX (`ProduktivitasController`, `CuttingController`) jadi kemungkinan besar sudah ada.

- [ ] **Step 6: Verifikasi Blade seimbang**

Run: `php -r '$c = file_get_contents("resources/views/projects/show.blade.php"); $o = substr_count($c,"@foreach")+substr_count($c,"@if"); $c2 = substr_count($c,"@endforeach")+substr_count($c,"@endif"); echo ($o === $c2 ? "SEIMBANG ($o/$c2)\n" : "TIDAK SEIMBANG: buka=$o tutup=$c2\n");'`
Expected: `SEIMBANG (n/n)`.

- [ ] **Step 7: Commit**

```bash
git add resources/views/projects/show.blade.php
git commit -m "feat(swe): tombol Hitung Saran + badge kandidat PIC di panel Mulai Tahap"
```

---

### Task 9: SQL production (dijalankan manual oleh Elvan di phpMyAdmin SEBELUM deploy)

**Files:**
- Create: `docs/sql/2026-08-21-swe-fase2-skill-rekomendasi-pic.sql`

**Interfaces:**
- Consumes: struktur dari Task 1 (harus identik).
- Produces: file SQL final buat Elvan — tidak ada task lain yang bergantung ke file ini.

- [ ] **Step 1: Tulis SQL, PERSIS mencerminkan Task 1**

```sql
-- FILE: docs/sql/2026-08-21-swe-fase2-skill-rekomendasi-pic.sql
-- Jalankan di phpMyAdmin production SEBELUM push ke main (deploy = FTP sync,
-- BUKAN artisan migrate — sesuai pola semua fitur sebelumnya di project ini).
-- Jalankan 2 statement ini SATU-SATU (bukan sekaligus), biar kalau salah satu
-- sudah pernah jalan (error 1060 "Duplicate column" / 1050 "Table already
-- exists"), tinggal skip baris itu dan lanjut ke baris berikutnya.

ALTER TABLE `rab_skill`
  ADD COLUMN `default_role` ENUM('tukang','kenek','tukang_kenek','manual') NOT NULL DEFAULT 'manual' AFTER `nama`;

CREATE TABLE IF NOT EXISTS `user_skill` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `rab_skill_id` BIGINT UNSIGNED NOT NULL COMMENT 'tanpa FK sengaja - rab_skill dibuat manual, tipe id tidak dipastikan',
  `sumber` ENUM('default_role','manual') NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `user_skill_user_id_foreign` (`user_id`),
  CONSTRAINT `user_skill_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Commit**

```bash
git add docs/sql/2026-08-21-swe-fase2-skill-rekomendasi-pic.sql
git commit -m "docs(swe): SQL production Fase 2 (rab_skill.default_role + user_skill)"
```

---

### Task 10: Regresi akhir — pastikan seluruh Fase 2 utuh

**Files:** Tidak ada file baru — task verifikasi murni.

**Interfaces:**
- Consumes: seluruh Task 1-9.
- Produces: konfirmasi siap deploy.

- [ ] **Step 1: Jalankan semua tes standalone Fase 2**

Run: `php tests/swe/test_skill_karyawan_service.php && php tests/swe/test_rekomendasi_pic_service.php`
Expected: `=== SEMUA TES LULUS ===` untuk keduanya.

- [ ] **Step 2: Jalankan ulang tes Fase 1 (pastikan tidak ada regresi)**

Run: `php tests/swe/test_jenis_project_options.php && php tests/swe/test_tahap_produksi_service.php`
Expected: `=== SEMUA TES LULUS ===` untuk keduanya (kalau nama fungsi output beda, sesuaikan — intinya tidak ada FAIL).

- [ ] **Step 3: Syntax check semua file PHP yang disentuh Fase 2**

Run:
```bash
for f in app/Models/UserSkill.php app/Models/ProjectTahap.php app/Services/SkillKaryawanService.php app/Services/RekomendasiPicService.php app/Http/Controllers/KaryawanController.php app/Http/Controllers/ProjectController.php routes/web.php database/migrations/2026_08_21_000001_add_default_role_to_rab_skill_table.php database/migrations/2026_08_21_000002_create_user_skill_table.php; do
  php -l "$f" || echo "GAGAL: $f"
done
```
Expected: `No syntax errors detected` untuk setiap file, tidak ada baris `GAGAL`.

- [ ] **Step 4: Verifikasi routing lengkap**

Run: `php artisan route:list --json | grep -oE '"name":"(karyawan\.(edit|update)|projects\.tahap\.[^"]*)"'`
Expected: baris `projects.tahap.mulai`, `projects.tahap.selesai`, `projects.tahap.saran-pic`, `karyawan.edit`, `karyawan.update` — semua muncul.

- [ ] **Step 5: Ringkasan ke Elvan**

Tulis ringkasan singkat (bahasa awam dulu): 2 tabel/kolom baru (kirim `docs/sql/2026-08-21-swe-fase2-skill-rekomendasi-pic.sql` buat dijalankan di phpMyAdmin SEBELUM push), fitur checklist skill di halaman Karyawan, dan tombol "Hitung Saran" di panel Mulai Tahap project. Ingatkan: SQL harus jalan duluan sebelum kode di-push, urutannya SQL dulu baru `git push`.
