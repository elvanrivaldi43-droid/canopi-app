# SWE Fase 1 — Fondasi Tahap Produksi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pecah 1 `project` jadi tahap-tahap produksi (potong/las/cat/kirim/instal) yang otomatis terbentuk dari template pas deal, bisa dijalankan (assign PIC manual) dan diselesaikan — fondasi buat Fase 2 (skill+rekomendasi), Fase 3 (kapasitas tim), Fase 4 (evaluasi produktivitas).

**Architecture:** 5 tabel baru (`tahap_master`, `template_tahap`, `template_tahap_item`, `project_tahap`, `project_tahap_pic`) di-generate otomatis saat `RabController::approve()` membuat `Project`. 2 halaman admin baru (Owner-only, pola bulk-edit sama seperti `/addon`) buat kelola master tahap & template. `ProjectController::show()` yang sudah ada diperluas nampilin daftar tahap + tombol "Mulai Tahap"/"Tandai Selesai".

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Blade, MySQL (production hosting shared — SQL production dijalankan manual oleh Elvan di phpMyAdmin, BUKAN via `artisan migrate`).

## Global Constraints

- Tidak ada rekomendasi skill/PIC otomatis di fase ini — PIC dipilih manual dari SEMUA karyawan (`level` 3,5,6, `status`='aktif', pola sama `ProjectController::show()` yang sudah ada). Itu Fase 2.
- Tahap TIDAK wajib berurutan — tidak ada gating status antar tahap.
- Kolom `jumlah_tukang_disarankan`/`jumlah_kenek_disarankan` di `project_tahap` DIBUAT sekarang (schema lengkap) tapi TIDAK DIISI di fase ini (tetap NULL) — diisi mulai Fase 2.
- Gagal generate tahap (template tidak ketemu, atau error apapun) TIDAK BOLEH menggagalkan pembuatan `Project` — selalu try/catch + `Log::warning`, meniru pola yang sudah ada persis di `RabController::approve()`.
- Emoji DILARANG di file blade (pernah bikin korup di server produksi) — semua ikon pakai SVG inline, pola sama `resources/views/partials/sidebar-owner.blade.php`.
- SQL production idempotent (`CREATE TABLE IF NOT EXISTS`) — dijalankan manual oleh Elvan di phpMyAdmin SEBELUM push ke `main` (deploy = FTP sync, bukan `artisan migrate`).
- VPS pengembangan ini TIDAK PUNYA akses DB — semua verifikasi pakai `php -l` (syntax), `php artisan route:list` (routing, jalan tanpa DB), kompilasi Blade manual (lihat Task 1 Step pola), dan tes standalone pure-PHP (`tests/*/test_*.php`, tanpa DB, pola `tests/jadwal-libur/test_libur_service.php`).

---

### Task 1: Migrasi 5 tabel baru

**Files:**
- Create: `database/migrations/2026_08_16_000001_create_tahap_master_table.php`
- Create: `database/migrations/2026_08_16_000002_create_template_tahap_table.php`
- Create: `database/migrations/2026_08_16_000003_create_template_tahap_item_table.php`
- Create: `database/migrations/2026_08_16_000004_create_project_tahap_table.php`
- Create: `database/migrations/2026_08_16_000005_create_project_tahap_pic_table.php`

**Interfaces:**
- Produces: tabel `tahap_master(id, nama, rab_jenis_kerja_id, tipe, urutan, is_active, timestamps)`, `template_tahap(id, nama, jenis_project, is_active, timestamps)`, `template_tahap_item(id, template_tahap_id, tahap_master_id, urutan, timestamps)`, `project_tahap(id, project_id, tahap_master_id, nama_tahap, urutan, status, qty, satuan, tanggal_mulai_target, tanggal_selesai_target, tanggal_mulai_aktual, tanggal_selesai_aktual, jumlah_tukang_disarankan, jumlah_kenek_disarankan, catatan, dibuat_oleh, timestamps)`, `project_tahap_pic(id, project_tahap_id, user_id, peran, ditambahkan_oleh, timestamps)` — dipakai semua task berikutnya.

- [ ] **Step 1: Tulis migration `tahap_master`**

```php
<?php
// database/migrations/2026_08_16_000001_create_tahap_master_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahap_master', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // TANPA foreign key constraint ke rab_jenis_kerja: tabel itu dibuat manual
            // (bukan lewat migration Laravel, tidak ada migration file-nya), tipe kolom
            // id-nya tidak bisa dipastikan dari sini. Konsisten dengan skill_default di
            // tabel yang sama yang juga tanpa FK — link ini opsional & dibaca saja.
            $table->unsignedBigInteger('rab_jenis_kerja_id')->nullable();
            $table->enum('tipe', ['fab', 'inst'])->nullable();
            $table->integer('urutan')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahap_master');
    }
};
```

- [ ] **Step 2: Tulis migration `template_tahap`**

```php
<?php
// database/migrations/2026_08_16_000002_create_template_tahap_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_tahap', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jenis_project');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_tahap');
    }
};
```

- [ ] **Step 3: Tulis migration `template_tahap_item`**

```php
<?php
// database/migrations/2026_08_16_000003_create_template_tahap_item_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_tahap_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_tahap_id')->constrained('template_tahap')->cascadeOnDelete();
            $table->foreignId('tahap_master_id')->constrained('tahap_master')->cascadeOnDelete();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_tahap_item');
    }
};
```

- [ ] **Step 4: Tulis migration `project_tahap`**

```php
<?php
// database/migrations/2026_08_16_000004_create_project_tahap_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tahap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tahap_master_id')->nullable()->constrained('tahap_master')->nullOnDelete();
            $table->string('nama_tahap');
            $table->integer('urutan')->default(0);
            $table->enum('status', ['belum', 'sedang', 'selesai'])->default('belum');
            $table->decimal('qty', 12, 2)->nullable();
            $table->string('satuan')->nullable();
            $table->date('tanggal_mulai_target')->nullable();
            $table->date('tanggal_selesai_target')->nullable();
            $table->date('tanggal_mulai_aktual')->nullable();
            $table->date('tanggal_selesai_aktual')->nullable();
            $table->integer('jumlah_tukang_disarankan')->nullable();
            $table->integer('jumlah_kenek_disarankan')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tahap');
    }
};
```

- [ ] **Step 5: Tulis migration `project_tahap_pic`**

```php
<?php
// database/migrations/2026_08_16_000005_create_project_tahap_pic_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tahap_pic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_tahap_id')->constrained('project_tahap')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('peran', ['tukang', 'kenek']);
            $table->foreignId('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tahap_pic');
    }
};
```

- [ ] **Step 6: Verifikasi syntax semua file (VPS ini tidak punya DB, jadi TIDAK menjalankan `artisan migrate`)**

Run:
```bash
php -l database/migrations/2026_08_16_000001_create_tahap_master_table.php
php -l database/migrations/2026_08_16_000002_create_template_tahap_table.php
php -l database/migrations/2026_08_16_000003_create_template_tahap_item_table.php
php -l database/migrations/2026_08_16_000004_create_project_tahap_table.php
php -l database/migrations/2026_08_16_000005_create_project_tahap_pic_table.php
```
Expected: `No syntax errors detected` di kelima file.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_16_00000*_create_*.php
git commit -m "feat(swe): migrasi 5 tabel fondasi tahap produksi"
```

---

### Task 2: Model Eloquent + relasi di `Project`

**Files:**
- Create: `app/Models/TahapMaster.php`
- Create: `app/Models/TemplateTahap.php`
- Create: `app/Models/TemplateTahapItem.php`
- Create: `app/Models/ProjectTahap.php`
- Create: `app/Models/ProjectTahapPic.php`
- Modify: `app/Models/Project.php`

**Interfaces:**
- Consumes: tabel dari Task 1.
- Produces: `TahapMaster`, `TemplateTahap::items()` (hasMany `TemplateTahapItem` urut `urutan`), `TemplateTahapItem::tahapMaster()`, `ProjectTahap::project()`/`pic()`/`$statusLabel`, `ProjectTahapPic::user()`, `Project::tahap()` (hasMany `ProjectTahap` urut `urutan`), `Project::$jenisProjectOptions` (array `kode => label`) — dipakai Task 3-9.

- [ ] **Step 1: Buat `app/Models/TahapMaster.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahapMaster extends Model
{
    protected $table = 'tahap_master';

    protected $fillable = [
        'nama', 'rab_jenis_kerja_id', 'tipe', 'urutan', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
```

- [ ] **Step 2: Buat `app/Models/TemplateTahap.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateTahap extends Model
{
    protected $table = 'template_tahap';

    protected $fillable = [
        'nama', 'jenis_project', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(TemplateTahapItem::class, 'template_tahap_id')->orderBy('urutan');
    }
}
```

- [ ] **Step 3: Buat `app/Models/TemplateTahapItem.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateTahapItem extends Model
{
    protected $table = 'template_tahap_item';

    protected $fillable = [
        'template_tahap_id', 'tahap_master_id', 'urutan',
    ];

    public function tahapMaster()
    {
        return $this->belongsTo(TahapMaster::class, 'tahap_master_id');
    }
}
```

- [ ] **Step 4: Buat `app/Models/ProjectTahap.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTahap extends Model
{
    protected $table = 'project_tahap';

    protected $fillable = [
        'project_id', 'tahap_master_id', 'nama_tahap', 'urutan', 'status',
        'qty', 'satuan', 'tanggal_mulai_target', 'tanggal_selesai_target',
        'tanggal_mulai_aktual', 'tanggal_selesai_aktual',
        'jumlah_tukang_disarankan', 'jumlah_kenek_disarankan',
        'catatan', 'dibuat_oleh',
    ];

    protected $casts = [
        'qty'                    => 'decimal:2',
        'tanggal_mulai_target'   => 'date',
        'tanggal_selesai_target' => 'date',
        'tanggal_mulai_aktual'   => 'date',
        'tanggal_selesai_aktual' => 'date',
    ];

    public static $statusLabel = [
        'belum'   => 'Belum Mulai',
        'sedang'  => 'Sedang Berjalan',
        'selesai' => 'Selesai',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function pic()
    {
        return $this->hasMany(ProjectTahapPic::class, 'project_tahap_id');
    }

    public function getStatusLabelAttribute()
    {
        return self::$statusLabel[$this->status] ?? $this->status;
    }
}
```

- [ ] **Step 5: Buat `app/Models/ProjectTahapPic.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTahapPic extends Model
{
    protected $table = 'project_tahap_pic';

    protected $fillable = [
        'project_tahap_id', 'user_id', 'peran', 'ditambahkan_oleh',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectTahap()
    {
        return $this->belongsTo(ProjectTahap::class, 'project_tahap_id');
    }

    public function getPeranLabelAttribute()
    {
        return $this->peran === 'tukang' ? 'Tukang' : 'Kenek';
    }
}
```

- [ ] **Step 6: Tambah relasi `tahap()` + konstanta `$jenisProjectOptions` di `app/Models/Project.php`**

Tambahkan method `tahap()` di bagian "Relationships" (setelah method `pembayaran()`):

```php
    public function tahap()
    {
        return $this->hasMany(ProjectTahap::class, 'project_id')->orderBy('urutan');
    }
```

Tambahkan konstanta baru setelah `public static $statusColor = [...];` (dipakai Task 3 untuk refactor `RabController::approve()` — satu sumber kebenaran, bukan duplikat di 2 tempat):

```php
    // Peta produk_kode RAB -> jenis_project. SATU-SATUNYA sumber; RabController::approve()
    // dan TemplateTahapController pakai daftar yang SAMA ini, biar nama jenis_project di
    // template_tahap selalu bisa dicocokkan persis ke project yang baru dibuat.
    public static $jenisProjectOptions = [
        'KANOPI_STD'     => 'Kanopi Standar',
        'KANOPI_DINDING' => 'Kanopi + Dinding',
        'MEZZANINE'      => 'Mezzanine',
        'PAGAR'          => 'Pagar',
        'TRALIS'         => 'Tralis',
        'TENDA_MEMBRANE' => 'Tenda Membrane',
        'AWNING'         => 'Awning',
        'CARPORT'        => 'Carport',
    ];
```

- [ ] **Step 7: Verifikasi syntax**

Run:
```bash
php -l app/Models/TahapMaster.php
php -l app/Models/TemplateTahap.php
php -l app/Models/TemplateTahapItem.php
php -l app/Models/ProjectTahap.php
php -l app/Models/ProjectTahapPic.php
php -l app/Models/Project.php
```
Expected: `No syntax errors detected` di semua file.

- [ ] **Step 8: Commit**

```bash
git add app/Models/TahapMaster.php app/Models/TemplateTahap.php app/Models/TemplateTahapItem.php app/Models/ProjectTahap.php app/Models/ProjectTahapPic.php app/Models/Project.php
git commit -m "feat(swe): model Eloquent tahap produksi + relasi Project::tahap()"
```

---

### Task 3: Refactor peta produk_kode di `RabController::approve()` (behavior-preserving)

**Files:**
- Modify: `app/Http/Controllers/RabController.php`
- Test: `tests/swe/test_jenis_project_options.php`

**Interfaces:**
- Consumes: `Project::$jenisProjectOptions` dari Task 2.
- Produces: `RabController::approve()` menghasilkan `$namaProduk` yang PERSIS SAMA seperti sebelumnya untuk semua `produk_kode` yang dikenal, dan fallback ke `produk_kode` mentah untuk yang tidak dikenal — perilaku tidak berubah, cuma sumber datanya dipindah ke `Project::$jenisProjectOptions` biar tidak ada 2 daftar yang bisa nyimpang.

- [ ] **Step 1: Tulis tes pure buat peta produk_kode (jalankan dulu, harus FAIL karena kode lama refactor belum ada — sebenarnya tes ini menguji `Project::$jenisProjectOptions` yang SUDAH dibuat Task 2, jadi langkah ini menguji nilai konstanta itu benar sebelum dipakai `RabController`)**

```php
<?php
// FILE: tests/swe/test_jenis_project_options.php
// Jalankan: php tests/swe/test_jenis_project_options.php
require __DIR__ . '/../bootstrap.php';

use App\Models\Project;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Sama persis peta match() lama di RabController::approve() — memastikan tidak ada
// satu kode pun yang kelewat/berubah pas dipindah ke konstanta ini.
$expected = [
    'KANOPI_STD'     => 'Kanopi Standar',
    'KANOPI_DINDING' => 'Kanopi + Dinding',
    'MEZZANINE'      => 'Mezzanine',
    'PAGAR'          => 'Pagar',
    'TRALIS'         => 'Tralis',
    'TENDA_MEMBRANE' => 'Tenda Membrane',
    'AWNING'         => 'Awning',
    'CARPORT'        => 'Carport',
];

foreach ($expected as $kode => $namaHarusnya) {
    $check("produk_kode $kode", Project::$jenisProjectOptions[$kode] ?? null, $namaHarusnya);
}

$check('jumlah kode persis 8 (tidak lebih tidak kurang)', count(Project::$jenisProjectOptions), 8);

// Pola fallback lama: default => $rab->produk_kode (dipakai RabController::approve())
$kodeAsing = 'XYZ_TIDAK_DIKENAL';
$check('kode tak dikenal -> fallback ke kode mentah (pola RabController)',
    Project::$jenisProjectOptions[$kodeAsing] ?? $kodeAsing, $kodeAsing);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes, pastikan LULUS (konstanta sudah dibuat di Task 2)**

Run: `php tests/swe/test_jenis_project_options.php`
Expected: `=== SEMUA TES LULUS ===`

- [ ] **Step 3: Ganti blok `match($rab->produk_kode)` di `RabController::approve()` jadi baca dari `Project::$jenisProjectOptions`**

Cari blok ini di `app/Http/Controllers/RabController.php` (sekitar baris 226-236):

```php
            $namaProduk   = match($rab->produk_kode) {
                'KANOPI_STD'     => 'Kanopi Standar',
                'KANOPI_DINDING' => 'Kanopi + Dinding',
                'MEZZANINE'      => 'Mezzanine',
                'PAGAR'          => 'Pagar',
                'TRALIS'         => 'Tralis',
                'TENDA_MEMBRANE' => 'Tenda Membrane',
                'AWNING'         => 'Awning',
                'CARPORT'        => 'Carport',
                default          => $rab->produk_kode,
            };
```

Ganti jadi:

```php
            $namaProduk   = \App\Models\Project::$jenisProjectOptions[$rab->produk_kode] ?? $rab->produk_kode;
```

- [ ] **Step 4: Verifikasi syntax**

Run: `php -l app/Http/Controllers/RabController.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/RabController.php tests/swe/test_jenis_project_options.php
git commit -m "refactor(swe): satu sumber peta produk_kode->jenis_project (Project::\$jenisProjectOptions)"
```

---

### Task 4: `TahapProduksiService` — pilih template yang cocok (logic murni, testable tanpa DB)

**Files:**
- Create: `app/Services/TahapProduksiService.php`
- Test: `tests/swe/test_tahap_produksi_service.php`

**Interfaces:**
- Consumes: `App\Models\Project`, `App\Models\TemplateTahap`, `App\Models\TemplateTahapItem`, `App\Models\ProjectTahap` (Task 2).
- Produces: `TahapProduksiService::pilihTemplateCocok(Collection $templates, string $jenisProject)` (pure, dipakai tes ini) dan `TahapProduksiService::generateUntukProject(Project $project): int` (DB-touching, dipakai Task 5 — return jumlah baris `project_tahap` yang dibuat, 0 kalau tidak ada template cocok).

- [ ] **Step 1: Tulis tes buat `pilihTemplateCocok` (pure, pakai array biasa — tanpa DB)**

```php
<?php
// FILE: tests/swe/test_tahap_produksi_service.php
// Jalankan: php tests/swe/test_tahap_produksi_service.php
require __DIR__ . '/../bootstrap.php';

use App\Services\TahapProduksiService;

$svc = new TahapProduksiService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

// Template dilewatkan sebagai array asosiatif (pure — tidak sentuh DB sama sekali)
$templates = collect([
    ['id' => 1, 'jenis_project' => 'Kanopi Standar', 'is_active' => true],
    ['id' => 2, 'jenis_project' => 'Pagar',          'is_active' => true],
    ['id' => 3, 'jenis_project' => 'Kanopi Standar', 'is_active' => false], // nonaktif, harus dilewati
]);

$hasil = $svc->pilihTemplateCocok($templates, 'Kanopi Standar');
$check('cocok & aktif -> ketemu id 1', $hasil['id'] ?? null, 1);

$hasil2 = $svc->pilihTemplateCocok($templates, 'Mezzanine');
$check('tidak ada yang cocok -> null', $hasil2, null);

$hasil3 = $svc->pilihTemplateCocok($templates, 'Pagar');
$check('cocok satu-satunya -> id 2', $hasil3['id'] ?? null, 2);

// Dua template aktif cocok jenis_project sama -> pilih id TERBESAR (paling baru dibuat)
$templatesDobel = collect([
    ['id' => 5, 'jenis_project' => 'Tralis', 'is_active' => true],
    ['id' => 9, 'jenis_project' => 'Tralis', 'is_active' => true],
]);
$hasil4 = $svc->pilihTemplateCocok($templatesDobel, 'Tralis');
$check('dua kandidat aktif -> pilih id terbesar', $hasil4['id'] ?? null, 9);

// Nonaktif semua -> null, bukan ke-skip jadi ambil yang nonaktif
$templatesNonaktif = collect([
    ['id' => 7, 'jenis_project' => 'Awning', 'is_active' => false],
]);
$check('cuma ada template nonaktif -> null',
    $svc->pilihTemplateCocok($templatesNonaktif, 'Awning'), null);

echo $fail ? "\n=== ADA YANG GAGAL ===\n" : "\n=== SEMUA TES LULUS ===\n";
exit($fail ? 1 : 0);
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL (class belum ada)**

Run: `php tests/swe/test_tahap_produksi_service.php`
Expected: fatal error `Class "App\Services\TahapProduksiService" not found`

- [ ] **Step 3: Buat `app/Services/TahapProduksiService.php`**

```php
<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTahap;
use App\Models\TemplateTahap;
use Illuminate\Support\Collection;

class TahapProduksiService
{
    /**
     * Pilih 1 template yang jenis_project-nya cocok & aktif. Kalau lebih dari
     * satu kandidat cocok, pilih yang id-nya terbesar (paling baru dibuat).
     * Pure: $templates boleh Collection Eloquent ATAU array asosiatif biasa
     * (dites pakai array biar tidak perlu DB).
     *
     * @param Collection $templates
     * @param string $jenisProject
     * @return array|TemplateTahap|null
     */
    public function pilihTemplateCocok(Collection $templates, string $jenisProject)
    {
        return $templates
            ->filter(fn ($t) => $this->ambil($t, 'is_active') && $this->ambil($t, 'jenis_project') === $jenisProject)
            ->sortByDesc(fn ($t) => $this->ambil($t, 'id'))
            ->first();
    }

    /**
     * Generate baris project_tahap dari template yang cocok jenis_project-nya
     * ke project. Tidak ketemu template cocok -> tidak generate apa-apa (return 0),
     * BUKAN error — pemanggil (RabController::approve()) tidak boleh gagal gara-gara ini.
     */
    public function generateUntukProject(Project $project): int
    {
        $templates = TemplateTahap::with('items.tahapMaster')->get();
        $template  = $this->pilihTemplateCocok($templates, (string) $project->jenis_project);

        if (!$template) {
            return 0;
        }

        $jumlah = 0;
        foreach ($template->items as $item) {
            ProjectTahap::create([
                'project_id'      => $project->id,
                'tahap_master_id' => $item->tahap_master_id,
                'nama_tahap'      => $item->tahapMaster->nama,
                'urutan'          => $item->urutan,
                'status'          => 'belum',
                'dibuat_oleh'     => $project->dibuat_oleh,
            ]);
            $jumlah++;
        }

        return $jumlah;
    }

    private function ambil($t, string $key)
    {
        return is_array($t) ? ($t[$key] ?? null) : ($t->$key ?? null);
    }
}
```

- [ ] **Step 4: Jalankan tes lagi, pastikan LULUS**

Run: `php tests/swe/test_tahap_produksi_service.php`
Expected: `=== SEMUA TES LULUS ===`

- [ ] **Step 5: Verifikasi syntax**

Run: `php -l app/Services/TahapProduksiService.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Services/TahapProduksiService.php tests/swe/test_tahap_produksi_service.php
git commit -m "feat(swe): TahapProduksiService — pilih template & generate project_tahap"
```

---

### Task 5: Tempelkan generate tahap ke `RabController::approve()`

**Files:**
- Modify: `app/Http/Controllers/RabController.php`

**Interfaces:**
- Consumes: `TahapProduksiService::generateUntukProject()` (Task 4).
- Produces: setiap `Project` baru yang lahir dari deal otomatis dapat baris `project_tahap` (kalau ada template cocok) — TIDAK ADA output baru yang dikonsumsi task lain (titik akhir alur).

- [ ] **Step 1: Cari titik yang tepat di `app/Http/Controllers/RabController.php`**

Baris ini sudah ada di dalam blok `if (!$existingProject) { try { ... } catch (...) { ... } }` (sekitar baris 258):

```php
                $project = Project::create($data);

                // Update rab_header dengan project_id yang baru terbuat
                if ($project) {
                    $rab->update(['project_id' => $project->id]);
                }
```

- [ ] **Step 2: Tempelkan pemanggilan service PERSIS SESUDAH baris `$rab->update(['project_id' => $project->id]);`, MASIH di dalam try yang sama (biar kalau gagal, ke-catch ke `Log::warning` yang sudah ada, tidak menggagalkan deal)**

```php
                $project = Project::create($data);

                // Update rab_header dengan project_id yang baru terbuat
                if ($project) {
                    $rab->update(['project_id' => $project->id]);

                    // SWE Fase 1: auto-generate tahap produksi dari template yang cocok
                    // jenis_project-nya. Tidak ketemu template -> tidak apa-apa, project
                    // tetap kebuat, Supervisor bisa tambah tahap manual belakangan.
                    app(\App\Services\TahapProduksiService::class)->generateUntukProject($project);
                }
```

- [ ] **Step 3: Verifikasi syntax**

Run: `php -l app/Http/Controllers/RabController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/RabController.php
git commit -m "feat(swe): auto-generate project_tahap saat RAB deal jadi project"
```

---

### Task 6: Halaman kelola `/tahap-master` (Owner)

**Files:**
- Create: `app/Http/Controllers/TahapMasterController.php`
- Create: `resources/views/tahap-master/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar-owner.blade.php`

**Interfaces:**
- Consumes: `App\Models\TahapMaster` (Task 2), tabel `rab_jenis_kerja` (dibaca raw via `DB::table`, sudah ada).
- Produces: rute `tahap-master.index` (`GET /tahap-master`) dan `tahap-master.simpan` (`POST /tahap-master/simpan`) — dipakai Task 7 (template butuh daftar tahap aktif untuk dipilih).

- [ ] **Step 1: Buat `app/Http/Controllers/TahapMasterController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\TahapMaster;

class TahapMasterController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);

        $rows = TahapMaster::orderBy('urutan')->orderBy('id')->get();
        $jenisKerjaOptions = DB::table('rab_jenis_kerja')->orderBy('nama')->get(['id', 'nama']);

        return view('tahap-master.index', compact('rows', 'jenisKerjaOptions'));
    }

    public function simpan(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);

        $tersimpan = 0;
        foreach ((array) $request->input('rows', []) as $row) {
            $nama = trim($row['nama'] ?? '');
            if ($nama === '') continue;

            $tipe = $row['tipe'] ?? '';
            if (!in_array($tipe, ['fab', 'inst'])) $tipe = null;

            $rabJenisKerjaId = $row['rab_jenis_kerja_id'] ?? '';
            $rabJenisKerjaId = is_numeric($rabJenisKerjaId) ? (int) $rabJenisKerjaId : null;

            $data = [
                'nama'               => $nama,
                'rab_jenis_kerja_id' => $rabJenisKerjaId,
                'tipe'               => $tipe,
                'urutan'             => is_numeric($row['urutan'] ?? null) ? (int) $row['urutan'] : 99,
                'is_active'          => !empty($row['is_active']),
            ];

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                TahapMaster::where('id', $id)->update($data);
            } else {
                TahapMaster::create($data);
            }
            $tersimpan++;
        }

        return redirect('/tahap-master')->with('success', "Tersimpan $tersimpan baris tahap.");
    }
}
```

- [ ] **Step 2: Buat `resources/views/tahap-master/index.blade.php`**

```blade
@extends('layouts.app')
@section('title', 'Kelola Tahap Produksi')
@section('page-title', 'Kelola Tahap Produksi')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tm-wrap { padding:14px 12px 60px; }
.tm-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.tm-sub { font-size:12px; color:#64748b; margin:0 0 14px; max-width:760px; }
.tm-scroll { overflow-x:auto; background:#1e293b; border-radius:12px; padding:10px; }
table.tm { border-collapse:collapse; width:100%; min-width:760px; }
table.tm th { font-size:11px; color:#94a3b8; text-align:left; padding:6px; border-bottom:1px solid #334155; white-space:nowrap; }
table.tm td { padding:4px 6px; border-bottom:1px solid #263349; }
table.tm input, table.tm select { background:#0f172a; border:1px solid #334155; border-radius:6px; padding:8px 6px; color:#f1f5f9; font-size:13px; width:100%; min-height:38px; }
table.tm input:focus, table.tm select:focus { border-color:#fbbf24; outline:none; }
.w-nama { min-width:180px; } .w-tipe { width:100px; } .w-jk { min-width:200px; } .w-urut { width:80px; } .w-akt { width:60px; text-align:center; }
.tm-actions { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-gold { background:#fbbf24; color:#0f172a; }
</style>

<div class="tm-wrap">
    <h1 class="tm-title">Kelola Tahap Produksi</h1>
    <p class="tm-sub">Daftar jenis tahap kerja (potong, las, cat, kirim, instal, dll). Tautkan opsional ke "Jenis Kerja RAB" biar Fase 2 nanti bisa hitung saran jumlah orang otomatis — kosongkan kalau tahap ini cuma checklist manual.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('tahap-master.simpan') }}">
        @csrf
        <div class="tm-scroll">
            <table class="tm" id="tblTahap">
                <thead>
                    <tr>
                        <th class="w-nama">Nama Tahap</th>
                        <th class="w-tipe">Tipe</th>
                        <th class="w-jk">Jenis Kerja RAB (opsional)</th>
                        <th class="w-urut">Urutan</th>
                        <th class="w-akt">Aktif</th>
                    </tr>
                </thead>
                <tbody id="tmBody">
                    @foreach($rows as $i => $r)
                    <tr data-id="{{ $r->id }}">
                        <td class="w-nama">
                            <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $r->id }}">
                            <input type="text" name="rows[{{ $i }}][nama]" value="{{ $r->nama }}">
                        </td>
                        <td class="w-tipe">
                            <select name="rows[{{ $i }}][tipe]">
                                <option value="">-</option>
                                <option value="fab"  {{ $r->tipe=='fab'?'selected':'' }}>Fabrikasi</option>
                                <option value="inst" {{ $r->tipe=='inst'?'selected':'' }}>Instalasi</option>
                            </select>
                        </td>
                        <td class="w-jk">
                            <select name="rows[{{ $i }}][rab_jenis_kerja_id]">
                                <option value="">- tidak ditautkan -</option>
                                @foreach($jenisKerjaOptions as $jk)
                                <option value="{{ $jk->id }}" {{ $r->rab_jenis_kerja_id==$jk->id?'selected':'' }}>{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="w-urut"><input type="number" name="rows[{{ $i }}][urutan]" value="{{ $r->urutan }}"></td>
                        <td class="w-akt"><input type="checkbox" name="rows[{{ $i }}][is_active]" value="1" {{ $r->is_active?'checked':'' }}></td>
                    </tr>
                    @endforeach
                    <tr data-id="0">
                        <td class="w-nama">
                            <input type="hidden" name="rows[baru][id]" value="0">
                            <input type="text" name="rows[baru][nama]" placeholder="Nama tahap baru...">
                        </td>
                        <td class="w-tipe">
                            <select name="rows[baru][tipe]">
                                <option value="">-</option>
                                <option value="fab">Fabrikasi</option>
                                <option value="inst">Instalasi</option>
                            </select>
                        </td>
                        <td class="w-jk">
                            <select name="rows[baru][rab_jenis_kerja_id]">
                                <option value="">- tidak ditautkan -</option>
                                @foreach($jenisKerjaOptions as $jk)
                                <option value="{{ $jk->id }}">{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="w-urut"><input type="number" name="rows[baru][urutan]" value="99"></td>
                        <td class="w-akt"><input type="checkbox" name="rows[baru][is_active]" value="1" checked></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="tm-actions">
            <button type="submit" class="btn btn-gold">Simpan Semua</button>
        </div>
    </form>
</div>
@endsection
```

- [ ] **Step 3: Daftarkan rute (Owner-only, pola sama `produktivitas`/`addon`) di `routes/web.php`**

Tambahkan grup baru setelah blok `PRODUKTIVITAS (owner)` yang sudah ada:

```php
// ================================================================
// TAHAP PRODUKSI (SWE Fase 1 — owner)
// ================================================================
Route::middleware(['auth', 'level:1'])->group(function () {
    Route::get('/tahap-master',        [\App\Http\Controllers\TahapMasterController::class, 'index'])->name('tahap-master.index');
    Route::post('/tahap-master/simpan',[\App\Http\Controllers\TahapMasterController::class, 'simpan'])->name('tahap-master.simpan');
});
```

- [ ] **Step 4: Tambah link sidebar Owner di `resources/views/partials/sidebar-owner.blade.php`, persis setelah link `/produktivitas` yang sudah ada**

```blade
<a href="{{ url('/tahap-master') }}"
   class="nav-item {{ request()->is('tahap-master*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
    </svg>
    <span x-show="sidebarOpen">Tahap Produksi</span>
</a>
```

- [ ] **Step 5: Verifikasi syntax + routing + kompilasi Blade**

Run:
```bash
php -l app/Http/Controllers/TahapMasterController.php
php artisan route:list --json | grep -o '"name":"tahap-master[^"]*"'
```
Expected: `No syntax errors detected`, dan muncul `"name":"tahap-master.index"` + `"name":"tahap-master.simpan"`.

Run (kompilasi Blade manual — VPS ini tidak punya `artisan view:cache`, dites langsung pola dari sesi hardening 15 Agustus):
```bash
php -r '
require "vendor/autoload.php";
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$compiled = $compiler->compileString(file_get_contents("resources/views/tahap-master/index.blade.php"));
file_put_contents("/tmp/blade_check_tm.php", $compiled);
'
php -l /tmp/blade_check_tm.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TahapMasterController.php resources/views/tahap-master/index.blade.php routes/web.php resources/views/partials/sidebar-owner.blade.php
git commit -m "feat(swe): halaman kelola /tahap-master (Owner)"
```

---

### Task 7: Halaman kelola `/template-tahap` (Owner)

**Files:**
- Create: `app/Http/Controllers/TemplateTahapController.php`
- Create: `resources/views/template-tahap/index.blade.php`
- Create: `resources/views/template-tahap/create.blade.php`
- Create: `resources/views/template-tahap/edit.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar-owner.blade.php`

**Interfaces:**
- Consumes: `App\Models\TahapMaster`, `TemplateTahap`, `TemplateTahapItem`, `Project::$jenisProjectOptions` (Task 2).
- Produces: rute `template-tahap.*` — SATU-SATUNYA tempat Owner mengisi data yang dipakai `TahapProduksiService::generateUntukProject()` (Task 4) di produksi nyata.

- [ ] **Step 1: Buat `app/Http/Controllers/TemplateTahapController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TemplateTahap;
use App\Models\TemplateTahapItem;
use App\Models\TahapMaster;
use App\Models\Project;

class TemplateTahapController extends Controller
{
    public function index()
    {
        abort_if(Auth::user()->level != 1, 403);
        $templates = TemplateTahap::with('items.tahapMaster')->orderBy('nama')->get();
        return view('template-tahap.index', compact('templates'));
    }

    public function create()
    {
        abort_if(Auth::user()->level != 1, 403);
        $tahapList = TahapMaster::where('is_active', true)->orderBy('urutan')->get();
        $jenisProjectOptions = array_values(Project::$jenisProjectOptions);
        return view('template-tahap.create', compact('tahapList', 'jenisProjectOptions'));
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'nama'          => 'required|string|max:255',
            'jenis_project' => 'required|string|max:255',
            'tahap_ids'     => 'required|array|min:1',
            'tahap_ids.*'   => 'integer|exists:tahap_master,id',
        ]);

        $template = TemplateTahap::create([
            'nama'          => $request->nama,
            'jenis_project' => $request->jenis_project,
            'is_active'     => true,
        ]);

        foreach (array_values($request->tahap_ids) as $urutan => $tahapMasterId) {
            TemplateTahapItem::create([
                'template_tahap_id' => $template->id,
                'tahap_master_id'   => $tahapMasterId,
                'urutan'            => $urutan,
            ]);
        }

        return redirect('/template-tahap')->with('success', 'Template "' . $template->nama . '" tersimpan.');
    }

    public function edit(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $templateTahap->load('items');
        $tahapList = TahapMaster::where('is_active', true)->orderBy('urutan')->get();
        $jenisProjectOptions = array_values(Project::$jenisProjectOptions);
        $selectedIds = $templateTahap->items->pluck('tahap_master_id')->toArray();
        return view('template-tahap.edit', compact('templateTahap', 'tahapList', 'jenisProjectOptions', 'selectedIds'));
    }

    public function update(Request $request, TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $request->validate([
            'nama'          => 'required|string|max:255',
            'jenis_project' => 'required|string|max:255',
            'tahap_ids'     => 'required|array|min:1',
            'tahap_ids.*'   => 'integer|exists:tahap_master,id',
        ]);

        $templateTahap->update([
            'nama'          => $request->nama,
            'jenis_project' => $request->jenis_project,
        ]);

        $templateTahap->items()->delete();
        foreach (array_values($request->tahap_ids) as $urutan => $tahapMasterId) {
            TemplateTahapItem::create([
                'template_tahap_id' => $templateTahap->id,
                'tahap_master_id'   => $tahapMasterId,
                'urutan'            => $urutan,
            ]);
        }

        return redirect('/template-tahap')->with('success', 'Template "' . $templateTahap->nama . '" diupdate.');
    }

    public function destroy(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $nama = $templateTahap->nama;
        $templateTahap->delete();
        return redirect('/template-tahap')->with('success', 'Template "' . $nama . '" dihapus.');
    }

    public function toggleAktif(TemplateTahap $templateTahap)
    {
        abort_if(Auth::user()->level != 1, 403);
        $templateTahap->update(['is_active' => !$templateTahap->is_active]);
        return back()->with('success', 'Status template diupdate.');
    }
}
```

- [ ] **Step 2: Buat `resources/views/template-tahap/index.blade.php`**

```blade
@extends('layouts.app')
@section('title', 'Template Tahap')
@section('page-title', 'Template Tahap')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tt-wrap { padding:14px 12px 60px; }
.tt-title { font-size:18px; font-weight:700; color:#fbbf24; margin:0 0 2px; }
.tt-sub { font-size:12px; color:#64748b; margin:0 0 14px; max-width:760px; }
.tt-card { background:#1e293b; border-radius:12px; padding:14px; margin-bottom:10px; }
.tt-nama { font-size:15px; font-weight:700; color:#f1f5f9; }
.tt-jenis { font-size:12px; color:#94a3b8; margin-top:2px; }
.tt-tahap-list { font-size:12px; color:#cbd5e1; margin-top:8px; }
.tt-badge { display:inline-block; background:#0f172a; border:1px solid #334155; border-radius:999px; padding:3px 10px; margin:2px 4px 0 0; }
.tt-nonaktif { opacity:0.5; }
.tt-actions { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.btn { border:none; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.btn-grey { background:#334155; color:#e2e8f0; }
.btn-red { background:#ef4444; color:#fff; }
</style>

<div class="tt-wrap">
    <h1 class="tt-title">Template Tahap</h1>
    <p class="tt-sub">Paket tahap per jenis project — dipilih otomatis saat RAB deal jadi project, cocok berdasarkan jenis project.</p>

    @if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:8px;padding:10px;font-size:13px;color:#6ee7b7;margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <a href="{{ route('template-tahap.create') }}" class="btn btn-gold" style="margin-bottom:14px;">+ Template Baru</a>

    @forelse($templates as $t)
    <div class="tt-card {{ !$t->is_active ? 'tt-nonaktif' : '' }}">
        <div class="tt-nama">{{ $t->nama }}</div>
        <div class="tt-jenis">Jenis project: {{ $t->jenis_project }} — {{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</div>
        <div class="tt-tahap-list">
            @foreach($t->items as $item)
            <span class="tt-badge">{{ $loop->iteration }}. {{ $item->tahapMaster->nama ?? '(tahap dihapus)' }}</span>
            @endforeach
        </div>
        <div class="tt-actions">
            <a href="{{ route('template-tahap.edit', $t) }}" class="btn btn-grey">Edit</a>
            <form method="POST" action="{{ route('template-tahap.toggle', $t) }}" style="display:inline;">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-grey">{{ $t->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
            <form method="POST" action="{{ route('template-tahap.destroy', $t) }}" style="display:inline;" onsubmit="return confirm('Hapus template ini?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-red">Hapus</button>
            </form>
        </div>
    </div>
    @empty
    <p style="color:#64748b;font-size:13px;">Belum ada template. Klik "+ Template Baru" buat mulai.</p>
    @endforelse
</div>
@endsection
```

- [ ] **Step 3: Buat `resources/views/template-tahap/create.blade.php`**

```blade
@extends('layouts.app')
@section('title', 'Template Baru')
@section('page-title', 'Template Baru')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tf-wrap { padding:14px 12px 60px; max-width:560px; }
.tf-label { font-size:12px; color:#94a3b8; margin:12px 0 4px; display:block; }
.tf-input, .tf-select { width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; font-size:14px; }
.tf-check-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #263349; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; margin-top:16px; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.tf-err { color:#f87171; font-size:12px; margin-top:4px; }
</style>

<div class="tf-wrap">
    <h1 style="font-size:18px;font-weight:700;color:#fbbf24;">Template Tahap Baru</h1>

    @if($errors->any())
    <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:10px;font-size:13px;color:#fca5a5;margin-top:10px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('template-tahap.store') }}">
        @csrf
        <label class="tf-label">Nama Template</label>
        <input type="text" name="nama" class="tf-input" value="{{ old('nama') }}" placeholder="Kanopi Standar" required>

        <label class="tf-label">Jenis Project</label>
        <select name="jenis_project" class="tf-select" required>
            <option value="">- pilih -</option>
            @foreach($jenisProjectOptions as $jp)
            <option value="{{ $jp }}" {{ old('jenis_project')==$jp?'selected':'' }}>{{ $jp }}</option>
            @endforeach
        </select>

        <label class="tf-label">Tahap yang dipakai (urutan checklist = urutan kerja)</label>
        @forelse($tahapList as $tahap)
        <div class="tf-check-row">
            <input type="checkbox" name="tahap_ids[]" value="{{ $tahap->id }}" id="th{{ $tahap->id }}">
            <label for="th{{ $tahap->id }}">{{ $tahap->nama }}</label>
        </div>
        @empty
        <p class="tf-err">Belum ada tahap master. Isi dulu di halaman "Tahap Produksi".</p>
        @endforelse

        <button type="submit" class="btn btn-gold">Simpan Template</button>
    </form>
</div>
@endsection
```

- [ ] **Step 4: Buat `resources/views/template-tahap/edit.blade.php` (sama seperti create, prefill nilai lama)**

```blade
@extends('layouts.app')
@section('title', 'Edit Template')
@section('page-title', 'Edit Template')
@section('sidebar-menu')
    @include('partials.sidebar-owner')
@endsection
@section('content')
<style>
* { box-sizing:border-box; }
.tf-wrap { padding:14px 12px 60px; max-width:560px; }
.tf-label { font-size:12px; color:#94a3b8; margin:12px 0 4px; display:block; }
.tf-input, .tf-select { width:100%; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:10px; color:#f1f5f9; font-size:14px; }
.tf-check-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #263349; }
.btn { border:none; border-radius:10px; padding:12px 18px; min-height:48px; font-size:14px; font-weight:700; cursor:pointer; margin-top:16px; }
.btn-gold { background:#fbbf24; color:#0f172a; }
.tf-err { color:#f87171; font-size:12px; margin-top:4px; }
</style>

<div class="tf-wrap">
    <h1 style="font-size:18px;font-weight:700;color:#fbbf24;">Edit Template — {{ $templateTahap->nama }}</h1>

    @if($errors->any())
    <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:10px;font-size:13px;color:#fca5a5;margin-top:10px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('template-tahap.update', $templateTahap) }}">
        @csrf
        @method('PUT')
        <label class="tf-label">Nama Template</label>
        <input type="text" name="nama" class="tf-input" value="{{ old('nama', $templateTahap->nama) }}" required>

        <label class="tf-label">Jenis Project</label>
        <select name="jenis_project" class="tf-select" required>
            <option value="">- pilih -</option>
            @foreach($jenisProjectOptions as $jp)
            <option value="{{ $jp }}" {{ old('jenis_project', $templateTahap->jenis_project)==$jp?'selected':'' }}>{{ $jp }}</option>
            @endforeach
        </select>

        <label class="tf-label">Tahap yang dipakai (urutan checklist = urutan kerja)</label>
        @forelse($tahapList as $tahap)
        <div class="tf-check-row">
            <input type="checkbox" name="tahap_ids[]" value="{{ $tahap->id }}" id="th{{ $tahap->id }}" {{ in_array($tahap->id, $selectedIds) ? 'checked' : '' }}>
            <label for="th{{ $tahap->id }}">{{ $tahap->nama }}</label>
        </div>
        @empty
        <p class="tf-err">Belum ada tahap master.</p>
        @endforelse

        <button type="submit" class="btn btn-gold">Update Template</button>
    </form>
</div>
@endsection
```

- [ ] **Step 5: Daftarkan rute di `routes/web.php`, di dalam grup `level:1` yang sama dari Task 6 (`TAHAP PRODUKSI`)**

```php
    Route::get('/template-tahap',                     [\App\Http\Controllers\TemplateTahapController::class, 'index'])->name('template-tahap.index');
    Route::get('/template-tahap/create',               [\App\Http\Controllers\TemplateTahapController::class, 'create'])->name('template-tahap.create');
    Route::post('/template-tahap',                      [\App\Http\Controllers\TemplateTahapController::class, 'store'])->name('template-tahap.store');
    Route::get('/template-tahap/{templateTahap}/edit',  [\App\Http\Controllers\TemplateTahapController::class, 'edit'])->name('template-tahap.edit');
    Route::put('/template-tahap/{templateTahap}',       [\App\Http\Controllers\TemplateTahapController::class, 'update'])->name('template-tahap.update');
    Route::delete('/template-tahap/{templateTahap}',    [\App\Http\Controllers\TemplateTahapController::class, 'destroy'])->name('template-tahap.destroy');
    Route::patch('/template-tahap/{templateTahap}/toggle', [\App\Http\Controllers\TemplateTahapController::class, 'toggleAktif'])->name('template-tahap.toggle');
```

- [ ] **Step 6: Tambah link sidebar di `resources/views/partials/sidebar-owner.blade.php`, persis setelah link `/tahap-master` dari Task 6**

```blade
<a href="{{ url('/template-tahap') }}"
   class="nav-item {{ request()->is('template-tahap*') ? 'active' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
    </svg>
    <span x-show="sidebarOpen">Template Tahap</span>
</a>
```

- [ ] **Step 7: Verifikasi syntax + routing + kompilasi Blade**

Run:
```bash
php -l app/Http/Controllers/TemplateTahapController.php
php artisan route:list --json | grep -o '"name":"template-tahap[^"]*"'
```
Expected: `No syntax errors detected`, dan 6 rute `template-tahap.*` muncul.

Run untuk KETIGA file blade baru:
```bash
for f in resources/views/template-tahap/index.blade.php resources/views/template-tahap/create.blade.php resources/views/template-tahap/edit.blade.php; do
  php -r '
    require "vendor/autoload.php";
    $compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
    $compiled = $compiler->compileString(file_get_contents($argv[1]));
    file_put_contents("/tmp/blade_check.php", $compiled);
  ' "$f"
  php -l /tmp/blade_check.php
done
```
Expected: `No syntax errors detected` tiga kali.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TemplateTahapController.php resources/views/template-tahap routes/web.php resources/views/partials/sidebar-owner.blade.php
git commit -m "feat(swe): halaman kelola /template-tahap (Owner)"
```

---

### Task 8: `ProjectController` — mulai & selesaikan tahap

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Models\ProjectTahap`, `ProjectTahapPic` (Task 2).
- Produces: rute `projects.tahap.mulai` (`POST /project-tahap/{projectTahap}/mulai`) dan `projects.tahap.selesai` (`POST /project-tahap/{projectTahap}/selesai`) — dipakai Task 9 (form di halaman detail project).

- [ ] **Step 1: Tambah `use` statement di puncak `app/Http/Controllers/ProjectController.php`**

Cari baris `use` yang sudah ada di puncak file (biasanya `use App\Models\Project;` dkk), tambahkan:

```php
use App\Models\ProjectTahap;
use App\Models\ProjectTahapPic;
```

- [ ] **Step 2: Tambah method `mulaiTahap()` dan `selesaiTahap()`, taruh setelah method `storeTim()` yang sudah ada**

```php
    // ============================================================
    // SWE FASE 1 — MULAI TAHAP (PIC dipilih MANUAL, tanpa rekomendasi skill —
    // itu Fase 2)
    // ============================================================
    public function mulaiTahap(Request $request, ProjectTahap $projectTahap)
    {
        $request->validate([
            'qty'                    => 'nullable|numeric|min:0',
            'satuan'                 => 'nullable|string|max:50',
            'tanggal_selesai_target' => 'nullable|date',
            'pic'                    => 'required|array|min:1',
            'pic.*.user_id'          => 'required|integer|exists:users,id',
            'pic.*.peran'            => 'required|in:tukang,kenek',
        ]);

        $projectTahap->update([
            'qty'                    => $request->qty,
            'satuan'                 => $request->satuan,
            'tanggal_selesai_target' => $request->tanggal_selesai_target,
            'tanggal_mulai_aktual'   => now()->toDateString(),
            'status'                 => 'sedang',
        ]);

        foreach ($request->pic as $picRow) {
            ProjectTahapPic::create([
                'project_tahap_id' => $projectTahap->id,
                'user_id'          => $picRow['user_id'],
                'peran'            => $picRow['peran'],
                'ditambahkan_oleh' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Tahap "' . $projectTahap->nama_tahap . '" dimulai.');
    }

    // ============================================================
    // SWE FASE 1 — TANDAI SELESAI
    // ============================================================
    public function selesaiTahap(ProjectTahap $projectTahap)
    {
        $projectTahap->update([
            'status'                 => 'selesai',
            'tanggal_selesai_aktual' => now()->toDateString(),
        ]);

        return back()->with('success', 'Tahap "' . $projectTahap->nama_tahap . '" ditandai selesai.');
    }
```

- [ ] **Step 3: Perluas eager-load di `show()` — cari baris `$project->load([...])` yang sudah ada, tambahkan `'tahap.pic.user'`**

Baris lama:
```php
        $project->load([
            'rateKondisi',
            'tim.user',
            'rabItems',
            'materialAktual',
            'pembayaran'
        ]);
```

Ganti jadi:
```php
        $project->load([
            'rateKondisi',
            'tim.user',
            'rabItems',
            'materialAktual',
            'pembayaran',
            'tahap.pic.user',
        ]);
```

- [ ] **Step 4: Daftarkan rute di `routes/web.php`, di DALAM grup `Route::middleware(['auth', 'level:1,2,3'])->group(...)` yang sudah ada (bagian PROJECT MANAGEMENT), setelah rute `projects.tim.destroy`**

```php
    // Tahap produksi (SWE Fase 1)
    Route::post('/project-tahap/{projectTahap}/mulai',   [ProjectController::class, 'mulaiTahap'])->name('projects.tahap.mulai');
    Route::post('/project-tahap/{projectTahap}/selesai', [ProjectController::class, 'selesaiTahap'])->name('projects.tahap.selesai');
```

- [ ] **Step 5: Verifikasi syntax + routing**

Run:
```bash
php -l app/Http/Controllers/ProjectController.php
php artisan route:list --json | grep -o '"name":"projects.tahap[^"]*"'
```
Expected: `No syntax errors detected`, dan `"name":"projects.tahap.mulai"` + `"name":"projects.tahap.selesai"` muncul.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProjectController.php routes/web.php
git commit -m "feat(swe): ProjectController::mulaiTahap()/selesaiTahap() (PIC manual)"
```

---

### Task 9: Tampilan "Tahap Produksi" di halaman detail project

**Files:**
- Modify: `resources/views/projects/show.blade.php`

**Interfaces:**
- Consumes: `$project->tahap` (eager-loaded Task 8), rute `projects.tahap.mulai`/`projects.tahap.selesai` (Task 8), `$karyawan` (variabel yang SUDAH ADA dari `ProjectController::show()` — `User::whereIn('level',[3,5,6])->where('status','aktif')`).
- Produces: UI final Fase 1 — Supervisor bisa lihat daftar tahap, mulai (isi qty/satuan/target/PIC), dan tandai selesai, langsung dari halaman project yang sudah ada.

- [ ] **Step 1: Baca struktur `resources/views/projects/show.blade.php` yang sudah ada buat cari titik sisip yang pas (biasanya ada blok section "Tim" sebelum "Material")**

Run: `grep -n "@section\|<h2\|<h3\|Tim Lapangan\|Material Aktual" resources/views/projects/show.blade.php`

- [ ] **Step 2: Sisipkan blok baru "Tahap Produksi" PERSIS SEBELUM blok "Tim Lapangan"/"Material Aktual" (pakai heading yang sama gaya dengan section lain di file ini — sesuaikan class CSS yang sudah ada di file kalau beda dari contoh di bawah)**

```blade
<div class="card" style="margin-bottom:16px;">
    <h2 style="font-size:16px;font-weight:700;color:#fbbf24;margin:0 0 10px;">Tahap Produksi</h2>

    @if($project->tahap->isEmpty())
    <p style="color:#64748b;font-size:13px;">Belum ada tahap produksi untuk project ini (tidak ada template yang cocok jenis project-nya, atau memang belum ditambahkan).</p>
    @else
    <div style="display:flex;flex-direction:column;gap:10px;">
        @foreach($project->tahap as $tahap)
        <div style="background:#0f172a;border:1px solid #334155;border-radius:10px;padding:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <b style="color:#f1f5f9;">{{ $tahap->urutan + 1 }}. {{ $tahap->nama_tahap }}</b>
                    <span style="font-size:11px;color:#94a3b8;margin-left:8px;">{{ $tahap->status_label }}</span>
                </div>
                <div style="display:flex;gap:6px;">
                    @if($tahap->status === 'belum')
                    <button type="button" class="btn btn-gold" style="padding:6px 12px;font-size:12px;" onclick="document.getElementById('mulaiTahap{{ $tahap->id }}').style.display='block'">Mulai Tahap</button>
                    @elseif($tahap->status === 'sedang')
                    <form method="POST" action="{{ route('projects.tahap.selesai', $tahap) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-grey" style="padding:6px 12px;font-size:12px;">Tandai Selesai</button>
                    </form>
                    @endif
                </div>
            </div>

            @if($tahap->pic->isNotEmpty())
            <div style="font-size:12px;color:#cbd5e1;margin-top:6px;">
                PIC: {{ $tahap->pic->map(fn($p) => $p->user->name . ' (' . $p->peran_label . ')')->join(', ') }}
            </div>
            @endif

            @if($tahap->tanggal_mulai_aktual)
            <div style="font-size:11px;color:#64748b;margin-top:4px;">
                Mulai: {{ $tahap->tanggal_mulai_aktual->format('d/m/Y') }}
                @if($tahap->tanggal_selesai_aktual) — Selesai: {{ $tahap->tanggal_selesai_aktual->format('d/m/Y') }} @endif
            </div>
            @endif

            @if($tahap->status === 'belum')
            <div id="mulaiTahap{{ $tahap->id }}" style="display:none;margin-top:10px;border-top:1px solid #263349;padding-top:10px;">
                <form method="POST" action="{{ route('projects.tahap.mulai', $tahap) }}">
                    @csrf
                    <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Qty / Luas (opsional)</label>
                    <input type="number" step="0.01" name="qty" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:6px;padding:8px;color:#f1f5f9;margin-bottom:8px;">

                    <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Satuan (opsional)</label>
                    <input type="text" name="satuan" placeholder="m2" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:6px;padding:8px;color:#f1f5f9;margin-bottom:8px;">

                    <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Target Selesai (opsional)</label>
                    <input type="date" name="tanggal_selesai_target" style="width:100%;background:#1e293b;border:1px solid #334155;border-radius:6px;padding:8px;color:#f1f5f9;margin-bottom:8px;">

                    <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">PIC (pilih manual, minimal 1)</label>
                    @foreach($karyawan as $k)
                    <div style="display:flex;align-items:center;gap:6px;padding:4px 0;">
                        <input type="checkbox" name="pic[{{ $loop->index }}][user_id]" value="{{ $k->id }}" id="pic{{ $tahap->id }}_{{ $k->id }}"
                            onchange="document.getElementById('rp{{ $tahap->id }}_{{ $k->id }}').style.display=this.checked?'inline-block':'none'">
                        <label for="pic{{ $tahap->id }}_{{ $k->id }}" style="font-size:12px;color:#e2e8f0;">{{ $k->name }}</label>
                        <select name="pic[{{ $loop->index }}][peran]" id="rp{{ $tahap->id }}_{{ $k->id }}" style="display:none;font-size:11px;background:#1e293b;border:1px solid #334155;border-radius:6px;color:#f1f5f9;">
                            <option value="tukang">Tukang</option>
                            <option value="kenek">Kenek</option>
                        </select>
                    </div>
                    @endforeach

                    <button type="submit" class="btn btn-gold" style="margin-top:10px;padding:8px 14px;font-size:12px;">Simpan & Mulai</button>
                </form>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
```

**Catatan implementasi:** checkbox yang TIDAK dicentang tetap mengirim `<select name="pic[i][peran]">` walau `display:none` — Laravel tetap menerima field itu ke `$request->pic`, jadi `mulaiTahap()` harus MEMFILTER hanya baris yang checkbox-nya dicentang. Perbaiki `ProjectController::mulaiTahap()` (Task 8) SEBELUM task ini dianggap selesai — tambahkan filter di awal method:

```php
        $picTerpilih = collect($request->input('pic', []))
            ->filter(fn ($row) => !empty($row['user_id']) && $request->has('pic_checked_' . $row['user_id']))
            ->values();
```

Ini butuh cara yang lebih sederhana. **Ganti pendekatan:** checkbox HANYA dikirim kalau dicentang (perilaku HTML standar — checkbox yang tidak dicentang TIDAK ikut ter-submit sama sekali), tapi `<select>` di sampingnya BUKAN checkbox jadi selalu ikut terkirim walau pasangannya tidak dicentang, dan index array `pic[{{ $loop->index }}]` jadi tidak sinkron antara `user_id` (hanya ada kalau checkbox dicentang) dengan `peran` (selalu ada). **Solusi:** validasi `mulaiTahap()` sudah benar (`pic.*.user_id` required per baris yang ADA), tapi baris yang checkbox-nya tidak dicentang akan punya `peran` terisi TANPA `user_id` — itu bikin validasi `pic.*.user_id => required` gagal untuk baris itu. Perbaiki dengan filter di controller SEBELUM validasi jalan:

- [ ] **Step 3: Perbaiki `ProjectController::mulaiTahap()` (dari Task 8) — filter baris `pic` yang `user_id`-nya kosong SEBELUM validasi, biar checkbox yang tidak dicentang tidak ikut kena validasi**

Ganti baris pertama method (sebelum `$request->validate(...)`):

```php
    public function mulaiTahap(Request $request, ProjectTahap $projectTahap)
    {
        // Baris <select> peran selalu ikut ter-submit walau checkbox pasangannya
        // (user_id) tidak dicentang — buang baris yang user_id-nya kosong SEBELUM
        // divalidasi, biar checkbox yang tidak dicentang tidak bikin validasi gagal.
        $picBersih = collect($request->input('pic', []))
            ->filter(fn ($row) => !empty($row['user_id']))
            ->values()
            ->all();
        $request->merge(['pic' => $picBersih]);

        $request->validate([
            'qty'                    => 'nullable|numeric|min:0',
            'satuan'                 => 'nullable|string|max:50',
            'tanggal_selesai_target' => 'nullable|date',
            'pic'                    => 'required|array|min:1',
            'pic.*.user_id'          => 'required|integer|exists:users,id',
            'pic.*.peran'            => 'required|in:tukang,kenek',
        ]);
```

- [ ] **Step 4: Verifikasi kompilasi Blade**

Run:
```bash
php -r '
require "vendor/autoload.php";
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$compiled = $compiler->compileString(file_get_contents("resources/views/projects/show.blade.php"));
file_put_contents("/tmp/blade_check_show.php", $compiled);
'
php -l /tmp/blade_check_show.php
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Verifikasi syntax controller yang diperbaiki**

Run: `php -l app/Http/Controllers/ProjectController.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add resources/views/projects/show.blade.php app/Http/Controllers/ProjectController.php
git commit -m "feat(swe): tampilkan tahap produksi + form mulai/selesai di halaman detail project"
```

---

### Task 10: SQL production (dijalankan manual oleh Elvan di phpMyAdmin SEBELUM deploy)

**Files:**
- Create: `docs/sql/2026-08-16-swe-fase1-tahap-produksi.sql`

**Interfaces:**
- Consumes: struktur dari Task 1 (harus identik).
- Produces: file SQL final yang dikirim ke Elvan — TIDAK ada task lain yang bergantung ke file ini, ini deliverable akhir buat manusia.

- [ ] **Step 1: Tulis SQL idempotent, PERSIS mencerminkan kelima migration di Task 1**

```sql
-- FILE: docs/sql/2026-08-16-swe-fase1-tahap-produksi.sql
-- Jalankan di phpMyAdmin production SEBELUM push ke main (deploy = FTP sync,
-- BUKAN artisan migrate — sesuai pola semua fitur sebelumnya di project ini).
-- Idempotent: aman dijalankan ulang / boleh skip error 1050 "table already exists".

CREATE TABLE IF NOT EXISTS `tahap_master` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `rab_jenis_kerja_id` BIGINT UNSIGNED NULL COMMENT 'tanpa FK sengaja - rab_jenis_kerja dibuat manual, tipe id tidak dipastikan',
  `tipe` ENUM('fab','inst') NULL,
  `urutan` INT NOT NULL DEFAULT 99,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `template_tahap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `jenis_project` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `template_tahap_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_tahap_id` BIGINT UNSIGNED NOT NULL,
  `tahap_master_id` BIGINT UNSIGNED NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `template_tahap_item_template_tahap_id_foreign` (`template_tahap_id`),
  KEY `template_tahap_item_tahap_master_id_foreign` (`tahap_master_id`),
  CONSTRAINT `template_tahap_item_template_tahap_id_foreign` FOREIGN KEY (`template_tahap_id`) REFERENCES `template_tahap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `template_tahap_item_tahap_master_id_foreign` FOREIGN KEY (`tahap_master_id`) REFERENCES `tahap_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tahap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `tahap_master_id` BIGINT UNSIGNED NULL,
  `nama_tahap` VARCHAR(255) NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `status` ENUM('belum','sedang','selesai') NOT NULL DEFAULT 'belum',
  `qty` DECIMAL(12,2) NULL,
  `satuan` VARCHAR(255) NULL,
  `tanggal_mulai_target` DATE NULL,
  `tanggal_selesai_target` DATE NULL,
  `tanggal_mulai_aktual` DATE NULL,
  `tanggal_selesai_aktual` DATE NULL,
  `jumlah_tukang_disarankan` INT NULL,
  `jumlah_kenek_disarankan` INT NULL,
  `catatan` TEXT NULL,
  `dibuat_oleh` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `project_tahap_project_id_foreign` (`project_id`),
  KEY `project_tahap_tahap_master_id_foreign` (`tahap_master_id`),
  KEY `project_tahap_dibuat_oleh_foreign` (`dibuat_oleh`),
  CONSTRAINT `project_tahap_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_tahap_master_id_foreign` FOREIGN KEY (`tahap_master_id`) REFERENCES `tahap_master` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_tahap_dibuat_oleh_foreign` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_tahap_pic` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_tahap_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `peran` ENUM('tukang','kenek') NOT NULL,
  `ditambahkan_oleh` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `project_tahap_pic_project_tahap_id_foreign` (`project_tahap_id`),
  KEY `project_tahap_pic_user_id_foreign` (`user_id`),
  KEY `project_tahap_pic_ditambahkan_oleh_foreign` (`ditambahkan_oleh`),
  CONSTRAINT `project_tahap_pic_project_tahap_id_foreign` FOREIGN KEY (`project_tahap_id`) REFERENCES `project_tahap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_pic_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tahap_pic_ditambahkan_oleh_foreign` FOREIGN KEY (`ditambahkan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Commit**

```bash
git add docs/sql/2026-08-16-swe-fase1-tahap-produksi.sql
git commit -m "docs(swe): SQL production Fase 1 (5 tabel tahap produksi)"
```

---

### Task 11: Regresi akhir — pastikan seluruh Fase 1 utuh

**Files:** tidak ada file baru, hanya verifikasi.

**Interfaces:** tidak ada — task terakhir, murni gate sebelum minta Elvan jalankan SQL & approve push.

- [ ] **Step 1: Syntax check SEMUA file PHP yang disentuh sepanjang Fase 1**

```bash
find app/Models/TahapMaster.php app/Models/TemplateTahap.php app/Models/TemplateTahapItem.php app/Models/ProjectTahap.php app/Models/ProjectTahapPic.php app/Models/Project.php app/Services/TahapProduksiService.php app/Http/Controllers/TahapMasterController.php app/Http/Controllers/TemplateTahapController.php app/Http/Controllers/ProjectController.php app/Http/Controllers/RabController.php database/migrations/2026_08_16_*.php -exec php -l {} \;
```
Expected: `No syntax errors detected` di setiap baris output, TANPA ada baris `Errors parsing`.

- [ ] **Step 2: Jalankan SEMUA tes standalone SWE**

```bash
php tests/swe/test_jenis_project_options.php
php tests/swe/test_tahap_produksi_service.php
```
Expected: `=== SEMUA TES LULUS ===` di keduanya.

- [ ] **Step 3: Jalankan tes REGRESI lama yang paling relevan (RabController & Project disentuh) — pastikan tidak ada yang rusak**

```bash
php tests/jadwal-libur/test_libur_service.php
```
Expected: `=== SEMUA TES LULUS ===` (bukti `Project.php` yang diedit Task 2 tidak merusak fungsi lain yang sudah ada — tes ini dipilih karena sama-sama memuat banyak class lewat classmap `tests/bootstrap.php`, kalau ada typo/error load class di `Project.php` tes lain juga akan ikut gagal).

- [ ] **Step 4: Routing lengkap**

```bash
php artisan route:list --json | grep -oE '"name":"(tahap-master|template-tahap|projects\.tahap)[^"]*"'
```
Expected: 9 baris — `tahap-master.index`, `tahap-master.simpan`, `template-tahap.index`, `template-tahap.create`, `template-tahap.store`, `template-tahap.edit`, `template-tahap.update`, `template-tahap.destroy`, `template-tahap.toggle`, `projects.tahap.mulai`, `projects.tahap.selesai` (11 total, hitung ulang saat verifikasi).

- [ ] **Step 5: Tidak ada commit di step ini** — kalau Step 1-4 semua lulus, Fase 1 siap dilaporkan selesai ke Elvan (SQL Task 10 dijalankan manual dulu di phpMyAdmin, BARU push `main`).
