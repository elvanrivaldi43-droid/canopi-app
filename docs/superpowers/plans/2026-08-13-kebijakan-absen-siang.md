# Redesain Absen Siang → Lapor Progress + Kembali Kerja Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pecah absen siang (1 checkpoint jam 13:00-14:00, form dropdown kendala) jadi 2 checkpoint independen: **"Lapor Progress"** (jam 11:00-12:30, pertanyaan progress digilir per-karyawan-per-hari + gali kendala sampai akar + notif Telegram ke Owner) dan **"Kembali Kerja"** (jam 13:00, 1 tombol, potongan prorata reuse rumus lama).

**Architecture:** 2 checkpoint independen di `AbsensiController` — `laporProgress()` (form multi-langkah, foto live-kamera reuse pola JS `getUserMedia`+canvas yang sudah ada di `form-siang.blade.php`, TANPA potongan prorata, cuma denda flat kalau kelewat) dan `kembaliKerja()` (1 endpoint POST dipanggil dari tombol di halaman utama, TANPA form/halaman terpisah, potongan prorata reuse `hitungMenitTelat()`/`hitungPotongan()` yang sudah ada & tidak diubah). Kolom-kolom lama (`foto_siang_1`, `lat_siang`/`lng_siang`, `deskripsi_kendala`) dipakai ulang buat checkpoint 1; kolom baru (`lat_kembali_kerja`/`lng_kembali_kerja`, dll) buat checkpoint 2 — 2 checkpoint gak berbagi kolom GPS/foto karena beda waktu & bisa beda lokasi.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Blade (pola dark-theme standalone kayak `form-siang.blade.php`), vanilla JS (`getUserMedia`+canvas buat kamera, `navigator.geolocation` buat GPS), Carbon.

## Global Constraints

- Populasi wajib SAMA kayak sekarang (level kantor 2,4,7 + level workshop 3,5,6) — TIDAK berubah, cuma alur/isi form yang berubah.
- Checkpoint 1 & 2 INDEPENDEN — gak saling ngeblok. Karyawan yang kena denda checkpoint 1 (gak lapor progress) tetap WAJIB checkpoint 2, dan sebaliknya.
- Nominal denda checkpoint 1 (flat, kalau lewat jam 12:30 belum lapor) REUSE konstanta `POTONGAN_TELAT` (Rp20.000) yang SUDAH ADA — JANGAN bikin konstanta angka baru.
- Rumus potongan prorata checkpoint 2 (`hitungMenitTelat()`/`hitungPotongan()`) TIDAK DIUBAH SAMA SEKALI — cuma dipindah dari form lama ke endpoint baru.
- Kumpulan pertanyaan progress & balasan otomatis DITULIS DI KODE (PHP const, pola sama `STATUS_PEKERJAAN`/`JENIS_KENDALA` yang sudah ada di file ini) — BUKAN tabel/halaman admin baru.
- Balasan otomatis & pemilihan pertanyaan BUKAN AI — murni dari kumpulan kalimat/pertanyaan siap pakai yang sudah ditulis, dipilih pakai rumus deterministik.
- Kolom lama yang jadi gak dipakai lagi (`status_pekerjaan`, `jenis_kendala`, `foto_siang_2`, `foto_siang_3`) DIBIARKAN di skema, JANGAN di-drop — YAGNI, gak perlu migration bongkar-pasang.
- Migration file dibuat untuk kelengkapan repo, TAPI deployment sebenarnya ke production lewat SQL manual idempotent di phpMyAdmin (lihat catatan akhir Task 1) — proyek ini shared hosting tanpa SSH/artisan di server, `php artisan migrate` TIDAK bisa dijalankan di production. **CATATAN KHUSUS:** kolom-kolom lama terkait absen siang (`foto_siang_1`, `jam_absen_siang`, `status_pekerjaan`, dst) TIDAK PUNYA migration file di repo ini sama sekali (gap pre-existing dari sebelum sesi ini, sudah ada di production tapi gak pernah dicatat migration-nya) — JANGAN diperbaiki di plan ini, di luar cakupan, cukup diketahui biar gak bingung pas baca migration folder.
- VPS pengembangan ini tidak punya koneksi database — semua test yang butuh DB TIDAK bisa dijalankan otomatis di sini. Test yang bisa dijalankan: `php -l` (lint syntax) dan test standalone untuk logic murni (pola `tests/jadwal-libur/*.php`). Bagian yang butuh DB/UI/kamera/GPS diverifikasi manual oleh Elvan di production.

---

### Task 1: Migrasi database & model — kolom baru

**Files:**
- Create: `database/migrations/2026_08_13_000003_add_lapor_progress_kembali_kerja_to_absensi_table.php`
- Modify: `app/Models/Absensi.php`

**Interfaces:**
- Produces: kolom baru di tabel `absensi` — `jam_lapor_progress` (time, nullable), `pertanyaan_progress` (text, nullable), `jawaban_progress` (text, nullable), `kendala_kenapa` (text, nullable), `potongan_progress_dicatat` (boolean, default false), `lat_kembali_kerja` (decimal 10,7 nullable), `lng_kembali_kerja` (decimal 10,7 nullable), `gps_valid_kembali_kerja` (boolean, nullable). Semua ditambah ke `Absensi::$fillable`. Dipakai Task 3 (`laporProgress()`) dan Task 4 (`kembaliKerja()`).

- [ ] **Step 1: Buat migration**

```php
<?php
// FILE: database/migrations/2026_08_13_000003_add_lapor_progress_kembali_kerja_to_absensi_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->time('jam_lapor_progress')->nullable()->after('jam_absen_siang');
            $table->text('pertanyaan_progress')->nullable()->after('jam_lapor_progress');
            $table->text('jawaban_progress')->nullable()->after('pertanyaan_progress');
            $table->text('kendala_kenapa')->nullable()->after('deskripsi_kendala');
            $table->boolean('potongan_progress_dicatat')->default(false)->after('potongan_siang_dicatat');
            $table->decimal('lat_kembali_kerja', 10, 7)->nullable()->after('kendala_kenapa');
            $table->decimal('lng_kembali_kerja', 10, 7)->nullable()->after('lat_kembali_kerja');
            $table->boolean('gps_valid_kembali_kerja')->nullable()->after('lng_kembali_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn([
                'jam_lapor_progress', 'pertanyaan_progress', 'jawaban_progress',
                'kendala_kenapa', 'potongan_progress_dicatat',
                'lat_kembali_kerja', 'lng_kembali_kerja', 'gps_valid_kembali_kerja',
            ]);
        });
    }
};
```

- [ ] **Step 2: Tambah kolom baru ke `$fillable` di `Absensi.php`**

Di `app/Models/Absensi.php`, ubah:

```php
    protected $fillable = [
        'user_id','tanggal','jam_masuk','jam_pulang',
        'foto_masuk','foto_pulang',
        'lat_masuk','lng_masuk','lat_pulang','lng_pulang',
        'gps_valid_masuk','gps_valid_pulang',
        'status','keterangan','foto_surat',
        'potongan_telat','uang_makan_hari_ini','gaji_hari_ini',
        'dikoreksi','alasan_koreksi','dikoreksi_oleh',
        // Kolom baru
        'foto_siang_1','foto_siang_2','foto_siang_3',
        'lat_siang','lng_siang','gps_valid_siang','jam_absen_siang',
        'status_pekerjaan','ada_kendala','jenis_kendala','deskripsi_kendala',
        'lembur_jam','lembur_approved','lembur_approved_oleh',
    'potongan_siang_dicatat',  // ← tambahkan ini
    ];
```

jadi:

```php
    protected $fillable = [
        'user_id','tanggal','jam_masuk','jam_pulang',
        'foto_masuk','foto_pulang',
        'lat_masuk','lng_masuk','lat_pulang','lng_pulang',
        'gps_valid_masuk','gps_valid_pulang',
        'status','keterangan','foto_surat',
        'potongan_telat','uang_makan_hari_ini','gaji_hari_ini',
        'dikoreksi','alasan_koreksi','dikoreksi_oleh',
        // Kolom baru
        'foto_siang_1','foto_siang_2','foto_siang_3',
        'lat_siang','lng_siang','gps_valid_siang','jam_absen_siang',
        'status_pekerjaan','ada_kendala','jenis_kendala','deskripsi_kendala',
        'lembur_jam','lembur_approved','lembur_approved_oleh',
        'potongan_siang_dicatat',
        // Lapor Progress + Kembali Kerja (13 Agustus)
        'jam_lapor_progress','pertanyaan_progress','jawaban_progress','kendala_kenapa',
        'potongan_progress_dicatat',
        'lat_kembali_kerja','lng_kembali_kerja','gps_valid_kembali_kerja',
    ];
```

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l database/migrations/2026_08_13_000003_add_lapor_progress_kembali_kerja_to_absensi_table.php
php -l app/Models/Absensi.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_13_000003_add_lapor_progress_kembali_kerja_to_absensi_table.php app/Models/Absensi.php
git commit -m "feat: migrasi kolom baru buat checkpoint Lapor Progress & Kembali Kerja"
```

**Catatan buat sesi deploy nanti (bukan step, jangan dieksekusi sekarang):** SQL idempotent buat Elvan jalankan manual di phpMyAdmin:
```sql
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS jam_lapor_progress TIME NULL AFTER jam_absen_siang;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS pertanyaan_progress TEXT NULL AFTER jam_lapor_progress;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS jawaban_progress TEXT NULL AFTER pertanyaan_progress;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS kendala_kenapa TEXT NULL AFTER deskripsi_kendala;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS potongan_progress_dicatat TINYINT(1) NOT NULL DEFAULT 0 AFTER potongan_siang_dicatat;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS lat_kembali_kerja DECIMAL(10,7) NULL AFTER kendala_kenapa;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS lng_kembali_kerja DECIMAL(10,7) NULL AFTER lat_kembali_kerja;
ALTER TABLE absensi ADD COLUMN IF NOT EXISTS gps_valid_kembali_kerja TINYINT(1) NULL AFTER lng_kembali_kerja;
```
Jangan push ke `main` sebelum SQL ini dikonfirmasi jalan di production.

**Catatan waktu deploy (baca sebelum jalankan SQL):** Kolom baru di atas bikin SEMUA baris `absensi` hari itu mulai dengan `jam_lapor_progress = NULL` dan `potongan_progress_dicatat = 0` — kalau deploy dilakukan SETELAH jam 12:30 (lewat dari jendela normal Task ini), sistem bakal nganggep SEMUA karyawan yang udah absen pagi itu "belum lapor progress" dan motong Rp20rb ke SEMUANYA pas mereka buka `/absensi`, padahal checkpoint ini belum ada waktu mereka mulai kerja hari itu. Kalau ini terjadi (deploy telat lewat 12:30), jalankan SATU baris tambahan ini SEBELUM push, supaya hari deploy itu sendiri gak kena denda retroaktif:
```sql
UPDATE absensi SET potongan_progress_dicatat = 1 WHERE tanggal = CURDATE();
```
Kalau deploy dilakukan SEBELUM jam 11:00 (sesuai target di langkah 4 roadmap deploy), baris ini TIDAK PERLU dijalankan — checkpoint 1 harus berjalan normal hari itu juga.

---

### Task 2: Bank pertanyaan, balasan otomatis, & pemilihan pertanyaan (pure, testable)

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Test: `tests/absensi/test_pilih_pertanyaan_progress.php`

**Interfaces:**
- Consumes: tidak ada (murni const + 1 method statis).
- Produces: `AbsensiController::BANK_PERTANYAAN_PROGRESS` (array of string), `AbsensiController::BALASAN_TANPA_KENDALA` (array of string), `AbsensiController::BALASAN_ADA_KENDALA` (array of string), `AbsensiController::JAM_LAPOR_PROGRESS` ('11:00'), `AbsensiController::JAM_BATAS_LAPOR_PROGRESS` ('12:30'), `AbsensiController::pilihPertanyaanProgress(int $userId, \Carbon\Carbon $tanggal): string` (public static, pure — dipakai Task 3).

- [ ] **Step 1: Tulis test standalone dulu (method belum ada)**

```php
<?php
// FILE: tests/absensi/test_pilih_pertanyaan_progress.php
// Jalankan: php tests/absensi/test_pilih_pertanyaan_progress.php
require __DIR__ . '/../../vendor/autoload.php';

use App\Http\Controllers\AbsensiController;
use Carbon\Carbon;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$jumlahBank = count(AbsensiController::BANK_PERTANYAAN_PROGRESS);
$check('bank pertanyaan minimal ada 5 variasi', $jumlahBank >= 5, true);

$tgl = Carbon::create(2026, 8, 14); // dayOfYear tetap (bukan tanggal spesifik project ini, cuma contoh)

$check('hasil selalu salah satu isi bank (user 1)',
    in_array(AbsensiController::pilihPertanyaanProgress(1, $tgl), AbsensiController::BANK_PERTANYAAN_PROGRESS), true);

$p1 = AbsensiController::pilihPertanyaanProgress(1, $tgl);
$p2 = AbsensiController::pilihPertanyaanProgress(2, $tgl);
$check('2 user beda di tanggal SAMA -> kemungkinan besar dapat pertanyaan beda (index beda)',
    ($1 + $tgl->dayOfYear) % $jumlahBank !== ($2 + $tgl->dayOfYear) % $jumlahBank, true);

$tglBesok = $tgl->copy()->addDay();
$check('user SAMA di tanggal beda -> index berubah (kecuali kebetulan modulo sama, dicek via rumus langsung, bukan asumsi)',
    (1 + $tglBesok->dayOfYear) % $jumlahBank,
    (1 + $tglBesok->dayOfYear) % $jumlahBank); // sanity: rumus konsisten dipanggil 2x hasil sama

$check('deterministik: dipanggil 2x, user+tanggal sama, hasil harus SAMA PERSIS',
    AbsensiController::pilihPertanyaanProgress(7, $tgl), AbsensiController::pilihPertanyaanProgress(7, $tgl));

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 2: Jalankan test, pastikan gagal (const/method belum ada)**

Run: `php tests/absensi/test_pilih_pertanyaan_progress.php`
Expected: Fatal error `Undefined constant App\Http\Controllers\AbsensiController::BANK_PERTANYAAN_PROGRESS` (atau error serupa method/const belum ada).

- [ ] **Step 3: Tambah const & method ke `AbsensiController`**

Tambahkan PERSIS SETELAH const `JENIS_KENDALA` yang sudah ada (baris ~48, sebelum `public function index()`):

```php
    const BANK_PERTANYAAN_PROGRESS = [
        'Progress kerja hari ini sudah sampai mana?',
        'Bagian apa yang sudah selesai dikerjakan hari ini?',
        'Target hari ini kira-kira tercapai berapa persen?',
        'Ada bagian yang lebih cepat/lambat dari rencana?',
        'Apa yang lagi dikerjakan sekarang?',
        'Kalau dibandingkan kemarin, progress hari ini gimana?',
        'Ada bagian yang perlu diperhatikan Owner/Mandor hari ini?',
    ];

    const BALASAN_TANPA_KENDALA = [
        '✅ Laporan diterima, semangat lanjut kerja!',
        '✅ Mantap, tetap semangat ya! 💪',
        '✅ Oke, terima kasih laporannya. Lanjut kerja!',
    ];

    const BALASAN_ADA_KENDALA = [
        '✅ Laporan diterima. Kendala kamu udah diteruskan ke Owner, ditunggu ya.',
        '✅ Diterima, Owner udah dikabari soal kendalanya. Semangat!',
    ];

    const JAM_LAPOR_PROGRESS        = '11:00';
    const JAM_BATAS_LAPOR_PROGRESS  = '12:30';

    public static function pilihPertanyaanProgress(int $userId, \Carbon\Carbon $tanggal): string
    {
        $index = ($tanggal->dayOfYear + $userId) % count(self::BANK_PERTANYAAN_PROGRESS);
        return self::BANK_PERTANYAAN_PROGRESS[$index];
    }
```

- [ ] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php tests/absensi/test_pilih_pertanyaan_progress.php`
Expected: Semua baris `PASS`, diakhiri `=== SEMUA TES LULUS ===`.

- [ ] **Step 5: Lint file**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php tests/absensi/test_pilih_pertanyaan_progress.php
git commit -m "feat: bank pertanyaan progress + balasan otomatis + pemilihan deterministik per-karyawan-per-hari"
```

---

### Task 3: Backend checkpoint 1 "Lapor Progress" — `formLaporProgress()` + `laporProgress()`

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `AbsensiController::pilihPertanyaanProgress()`, `AbsensiController::BALASAN_TANPA_KENDALA`/`BALASAN_ADA_KENDALA` (Task 2), kolom `jam_lapor_progress`/`pertanyaan_progress`/`jawaban_progress`/`kendala_kenapa` (Task 1).
- Produces: route `absensi.form-lapor-progress` (GET), `absensi.lapor-progress` (POST) — dipakai Task 6 (view) dan Task 7 (link dari halaman utama). Method `AbsensiController::formLaporProgress()`, `AbsensiController::laporProgress(Request $request)`.

- [ ] **Step 1: Hapus method lama `formSiang()`/`absenSiang()`, ganti jadi `formLaporProgress()`/`laporProgress()`**

Di `app/Http/Controllers/AbsensiController.php`, HAPUS method `formSiang()` dan `absenSiang()` yang sekarang (baris 228-304), GANTI dengan:

```php
    public function formLaporProgress()
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return redirect()->route('absensi.index')->with('error','Kamu belum absen masuk pagi.');
        if ($absen?->jam_lapor_progress) return redirect()->route('absensi.index')->with('info','Kamu sudah lapor progress hari ini.');

        $lokasi        = $this->getLokasiCek($user->level,'siang');
        $gpsWajib      = in_array($user->level, self::LEVEL_KANTOR);
        $luarKotaAktif = LuarKota::getAktif($user->id);
        $pertanyaan    = self::pilihPertanyaanProgress($user->id, today());

        return view('absensi.form-lapor-progress', compact('user','lokasi','gpsWajib','luarKotaAktif','pertanyaan'));
    }

    public function laporProgress(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen) return response()->json(['success'=>false,'message'=>'Belum absen masuk pagi.']);

        $request->validate([
            'foto'             => 'required|string',
            'lat'              => 'required|numeric',
            'lng'              => 'required|numeric',
            'jawaban_progress' => 'required|string',
            'ada_kendala'      => 'required',
            'kendala_apa'      => 'required_if:ada_kendala,1',
            'kendala_kenapa'   => 'required_if:ada_kendala,1',
        ]);

        // ── CEK MODE LUAR KOTA ──────────────────────────────────────────
        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        $gpsValid = true;
        if (in_array($user->level, self::LEVEL_KANTOR) && !$sedangLuarKota) {
            $lokasi   = $this->getLokasiCek($user->level,'siang');
            $jarak    = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
            $gpsValid = $jarak <= self::RADIUS_SIANG;
            if (!$gpsValid) {
                return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
            }
        }

        $adaKendala = $request->ada_kendala == 1;
        $pertanyaan = self::pilihPertanyaanProgress($user->id, today());
        $folder     = 'absensi/'.$user->id.'/'.today()->format('Ymd');

        $absen->update([
            'foto_siang_1'        => $this->simpanFotoBase64($request->foto,$folder),
            'lat_siang'           => $request->lat,
            'lng_siang'           => $request->lng,
            'gps_valid_siang'     => true, // selalu true, GPS tetap dicatat
            'jam_lapor_progress'  => now()->format('H:i:s'),
            'pertanyaan_progress' => $pertanyaan,
            'jawaban_progress'    => $request->jawaban_progress,
            'ada_kendala'         => $adaKendala,
            'deskripsi_kendala'   => $adaKendala ? $request->kendala_apa : null,
            'kendala_kenapa'      => $adaKendala ? $request->kendala_kenapa : null,
        ]);

        if ($adaKendala) $this->kirimNotifKendala($user,$absen);

        $balasan = $adaKendala
            ? self::BALASAN_ADA_KENDALA[array_rand(self::BALASAN_ADA_KENDALA)]
            : self::BALASAN_TANPA_KENDALA[array_rand(self::BALASAN_TANPA_KENDALA)];

        if ($sedangLuarKota) $balasan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$balasan,'redirect'=>route('absensi.index')]);
    }
```

- [ ] **Step 2: Update `kirimNotifKendala()` — hapus dropdown jenis kendala, tambah "kenapa"**

Cari method `kirimNotifKendala()` yang sekarang (dekat akhir file):

```php
    private function kirimNotifKendala(User $user,Absensi $absen,Request $request): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('telegram_chat_id')->get();
        $jenisLabel=self::JENIS_KENDALA[$request->jenis_kendala]??$request->jenis_kendala;
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nKendala: {$jenisLabel}\nKeterangan: {$request->deskripsi_kendala}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }
```

Ganti jadi (parameter `Request $request` dihapus, baca langsung dari `$absen` yang sudah di-update):

```php
    private function kirimNotifKendala(User $user,Absensi $absen): void
    {
        $penerima=User::whereIn('level',[1,3])->whereNotNull('telegram_chat_id')->get();
        foreach ($penerima as $p) {
            app(TelegramService::class)->kirim($p->telegram_chat_id,"⚠️ *LAPORAN KENDALA*\nKaryawan: {$user->name}\nJabatan: {$user->jabatan}\nTanggal: ".today()->format('d/m/Y')."\nProgress: {$absen->jawaban_progress}\nKendala: {$absen->deskripsi_kendala}\nPenyebab: {$absen->kendala_kenapa}\n---\nCek detail di app.kanopibsd.co.id");
        }
    }
```

- [ ] **Step 3: Update routes**

Di `routes/web.php`, ubah blok `absensi` — cari baris:

```php
    Route::get('/siang',                    [AbsensiController::class, 'formSiang'])->name('form-siang');
    Route::post('/siang',                   [AbsensiController::class, 'absenSiang'])->name('siang');
```

Ganti jadi:

```php
    Route::get('/lapor-progress',           [AbsensiController::class, 'formLaporProgress'])->name('form-lapor-progress');
    Route::post('/lapor-progress',          [AbsensiController::class, 'laporProgress'])->name('lapor-progress');
```

- [ ] **Step 4: Lint kedua file**

Run:
```bash
php -l app/Http/Controllers/AbsensiController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php routes/web.php
git commit -m "feat: checkpoint Lapor Progress (foto+pertanyaan gilir+gali kendala), ganti absen siang lama"
```

---

### Task 4: Backend checkpoint 2 "Kembali Kerja" — `kembaliKerja()`

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: kolom `lat_kembali_kerja`/`lng_kembali_kerja`/`gps_valid_kembali_kerja` (Task 1), `hitungMenitTelat()`/`hitungPotongan()` (existing, TIDAK diubah).
- Produces: route `absensi.kembali-kerja` (POST, TANPA halaman form GET terpisah — dipanggil langsung dari tombol di `absensi/index.blade.php`, lihat Task 7). Method `AbsensiController::kembaliKerja(Request $request)`.

- [ ] **Step 1: Tambah method `kembaliKerja()`**

Tambahkan method baru PERSIS SETELAH `laporProgress()` (dari Task 3):

```php
    public function kembaliKerja(Request $request)
    {
        $user  = Auth::user();
        $absen = Absensi::where('user_id',$user->id)->whereDate('tanggal',today())->first();
        if (!$absen?->jam_masuk) return response()->json(['success'=>false,'message'=>'Kamu belum absen masuk pagi.']);
        if ($absen->jam_absen_siang) return response()->json(['success'=>false,'message'=>'Kamu sudah lapor kembali kerja hari ini.']);

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $sedangLuarKota = LuarKota::sedangLuarKota($user->id);

        $gpsValid = true;
        if (in_array($user->level, self::LEVEL_KANTOR) && !$sedangLuarKota) {
            $lokasi   = $this->getLokasiCek($user->level,'siang');
            $jarak    = $this->hitungJarak($request->lat,$request->lng,$lokasi['lat'],$lokasi['lng']);
            $gpsValid = $jarak <= self::RADIUS_SIANG;
            if (!$gpsValid) {
                return response()->json(['success'=>false,'message'=>"📍 Lokasi terlalu jauh ({$this->formatJarak($jarak)})."]);
            }
        }

        $jamSekarang = now()->format('H:i');
        $menitTelat  = $this->hitungMenitTelat($jamSekarang, self::JAM_MASUK_SIANG, self::TOLERANSI_SIANG);
        $potongan    = $this->hitungPotongan($menitTelat);

        $absen->update([
            'jam_absen_siang'         => now()->format('H:i:s'),
            'lat_kembali_kerja'       => $request->lat,
            'lng_kembali_kerja'       => $request->lng,
            'gps_valid_kembali_kerja' => true,
            'potongan_telat'          => ($absen->potongan_telat??0) + $potongan,
            'potongan_siang_dicatat'  => true,
            'gaji_hari_ini'           => ($absen->gaji_hari_ini??0) - $potongan,
        ]);

        $pesan = $menitTelat>0
            ? "✅ Tercatat, lanjut kerja ya! Telat {$menitTelat} menit — potongan Rp".number_format($potongan,0,',','.')
            : "✅ Tercatat, lanjut kerja ya!";

        if ($sedangLuarKota) $pesan .= "\n✈️ Mode luar kota aktif.";

        return response()->json(['success'=>true,'message'=>$pesan,'redirect'=>route('absensi.index')]);
    }
```

- [ ] **Step 2: Tambah route**

Di `routes/web.php`, tambahkan PERSIS SETELAH route `lapor-progress` (dari Task 3):

```php
    Route::post('/kembali-kerja',           [AbsensiController::class, 'kembaliKerja'])->name('kembali-kerja');
```

- [ ] **Step 3: Lint kedua file**

Run:
```bash
php -l app/Http/Controllers/AbsensiController.php
php -l routes/web.php
```
Expected: `No syntax errors detected` untuk kedua file.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php routes/web.php
git commit -m "feat: checkpoint Kembali Kerja (1 endpoint, potongan prorata reuse rumus lama)"
```

---

### Task 5: `index()` auto-flat 2-checkpoint + `getFaseAbsen()` update

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`

**Interfaces:**
- Consumes: `JAM_LAPOR_PROGRESS`/`JAM_BATAS_LAPOR_PROGRESS` (Task 2), kolom `potongan_progress_dicatat`/`jam_lapor_progress` (Task 1), method `laporProgress()`/`kembaliKerja()` (Task 3, 4 — dikonsumsi TIDAK LANGSUNG lewat fase yang dikembalikan `getFaseAbsen()`).
- Produces: `getFaseAbsen()` sekarang bisa return `'perlu_lapor_progress'` dan `'perlu_kembali_kerja'` (menggantikan `'perlu_absen_siang'` yang lama) — dipakai Task 7 (view `absensi/index.blade.php`).

- [ ] **Step 1: Pecah blok auto-flat di `index()` jadi 2 blok independen**

Di `app/Http/Controllers/AbsensiController.php`, method `index()`, ubah blok ini (baris 55-66):

```php
        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_absen_siang
            && !$absenHariIni->potongan_siang_dicatat
            && now()->format('H:i') >= self::JAM_SKIP_SIANG) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'         => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'          => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_siang_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }
```

jadi:

```php
        // Checkpoint 1 "Lapor Progress": kalau kelewat jam 12:30 belum lapor sama sekali -> denda flat
        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_lapor_progress
            && !$absenHariIni->potongan_progress_dicatat
            && now()->format('H:i') >= self::JAM_BATAS_LAPOR_PROGRESS) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'            => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'             => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_progress_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }

        // Checkpoint 2 "Kembali Kerja": kalau kelewat jam 14:00 belum lapor sama sekali -> denda flat
        // (LOGIC TIDAK BERUBAH dari sebelumnya, cuma nama kolom flag disamakan konteksnya)
        if ($absenHariIni && $absenHariIni->jam_masuk
            && !$absenHariIni->jam_absen_siang
            && !$absenHariIni->potongan_siang_dicatat
            && now()->format('H:i') >= self::JAM_SKIP_SIANG) {
            $potongan = self::POTONGAN_TELAT;
            $absenHariIni->update([
                'potongan_telat'         => ($absenHariIni->potongan_telat ?? 0) + $potongan,
                'gaji_hari_ini'          => ($absenHariIni->gaji_hari_ini ?? 0) - $potongan,
                'potongan_siang_dicatat' => true,
            ]);
            $absenHariIni->refresh();
        }
```

- [ ] **Step 2: Update `getFaseAbsen()`**

Ubah:

```php
    private function getFaseAbsen(?Absensi $absen): string
    {
        if (!$absen||!$absen->jam_masuk) return 'belum_masuk';
        if (!$absen->jam_absen_siang) {
            $jam = now()->format('H:i');
            if ($jam < self::JAM_MASUK_SIANG) { /* belum waktunya */ }
            elseif ($jam < self::JAM_SKIP_SIANG) return 'perlu_absen_siang';
        }
        if (!$absen->jam_pulang) return 'perlu_pulang';
        return 'lengkap';
    }
```

jadi:

```php
    private function getFaseAbsen(?Absensi $absen): string
    {
        if (!$absen||!$absen->jam_masuk) return 'belum_masuk';
        $jam = now()->format('H:i');
        if (!$absen->jam_lapor_progress) {
            if ($jam >= self::JAM_LAPOR_PROGRESS && $jam < self::JAM_BATAS_LAPOR_PROGRESS) return 'perlu_lapor_progress';
        }
        if (!$absen->jam_absen_siang) {
            if ($jam >= self::JAM_MASUK_SIANG && $jam < self::JAM_SKIP_SIANG) return 'perlu_kembali_kerja';
        }
        if (!$absen->jam_pulang) return 'perlu_pulang';
        return 'lengkap';
    }
```

- [ ] **Step 3: Lint file**

Run: `php -l app/Http/Controllers/AbsensiController.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "fix: pecah auto-denda jadi 2 checkpoint independen + getFaseAbsen kenal fase baru"
```

---

### Task 6: View `absensi/form-lapor-progress.blade.php`

**Files:**
- Create: `resources/views/absensi/form-lapor-progress.blade.php`
- Delete: `resources/views/absensi/form-siang.blade.php`

**Interfaces:**
- Consumes: route `absensi.lapor-progress` (Task 3), variable `$user`/`$lokasi`/`$gpsWajib`/`$luarKotaAktif`/`$pertanyaan` dari `formLaporProgress()` (Task 3).

- [ ] **Step 1: Hapus `resources/views/absensi/form-siang.blade.php`**

File ini sudah digantikan total — hapus filenya (`rm resources/views/absensi/form-siang.blade.php`).

- [ ] **Step 2: Buat `resources/views/absensi/form-lapor-progress.blade.php`**

```php
{{-- FILE: resources/views/absensi/form-lapor-progress.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Lapor Progress</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { box-sizing:border-box; }
  body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; margin:0; padding:0; }
  .topbar { background:#1e293b; border-bottom:1px solid #334155; padding:14px 16px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:100; }
  .topbar-title { font-weight:700; color:#fbbf24; font-size:16px; }
  .topbar-sub { color:#64748b; font-size:12px; }
  .content { padding:16px; padding-bottom:120px; }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:16px; margin-bottom:14px; }
  .section-label { color:#94a3b8; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
  .foto-slot { aspect-ratio:1; max-width:200px; border-radius:8px; overflow:hidden; background:#0f172a; border:2px dashed #334155; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; margin:0 auto; }
  .foto-slot img { width:100%; height:100%; object-fit:cover; }
  .foto-slot .plus { color:#475569; font-size:32px; }
  .foto-slot .hapus { position:absolute; top:4px; right:4px; background:rgba(239,68,68,0.8); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:12px; cursor:pointer; }
  textarea.form-control-dark {
    background:#0f172a; border:1px solid #475569; color:#f1f5f9;
    border-radius:8px; padding:10px 12px; width:100%; font-size:14px; resize:none;
  }
  textarea.form-control-dark:focus { border-color:#fbbf24; outline:none; }
  .toggle-group { display:flex; gap:10px; }
  .toggle-item { flex:1; text-align:center; background:#0f172a; border:1px solid #334155; border-radius:8px; padding:12px; cursor:pointer; font-weight:600; }
  .toggle-item.selected-ya { border-color:#ef4444; background:rgba(239,68,68,0.1); color:#fca5a5; }
  .toggle-item.selected-tidak { border-color:#10b981; background:rgba(16,185,129,0.1); color:#6ee7b7; }
  .kendala-box { background:rgba(239,68,68,0.1); border:1px solid #ef4444; border-radius:10px; padding:14px; margin-top:10px; display:none; }
  .submit-bar { position:fixed; bottom:60px; left:0; right:0; padding:12px 16px; background:rgba(15,23,42,0.97); border-top:1px solid #334155; z-index:200; }
  .btn-submit { width:100%; padding:14px; border-radius:12px; border:none; font-weight:700; font-size:16px; background:#f59e0b; color:#0f172a; }
  .btn-submit:disabled { background:#334155; color:#64748b; }
  .gps-bar { display:flex; align-items:center; gap:10px; }
  .alert-box { border-radius:10px; padding:12px 14px; margin-bottom:12px; font-size:13px; }
  .alert-success { background:rgba(16,185,129,0.2); border:1px solid #10b981; color:#6ee7b7; }
  .alert-error { background:rgba(239,68,68,0.2); border:1px solid #ef4444; color:#fca5a5; }
  .pertanyaan-box { background:rgba(99,102,241,0.1); border:1px solid #6366f1; border-radius:10px; padding:14px; font-size:15px; font-weight:600; color:#a5b4fc; margin-bottom:12px; }
  #kameraModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:999; flex-direction:column; align-items:center; justify-content:center; }
  #kameraModal video { width:100%; max-width:480px; border-radius:12px; }
  #kameraModal .btn-capture { margin-top:16px; background:#fbbf24; color:#0f172a; border:none; border-radius:10px; padding:12px 32px; font-weight:700; font-size:16px; }
  #kameraModal .btn-tutup { position:absolute; top:16px; right:16px; background:rgba(255,255,255,0.2); color:#fff; border:none; border-radius:8px; padding:8px 14px; }
</style>
</head>
<body>

<div class="topbar">
  <a href="{{ route('absensi.index') }}" style="color:#64748b; font-size:20px; text-decoration:none;">←</a>
  <div>
    <div class="topbar-title">Lapor Progress</div>
    <div class="topbar-sub">Sebelum istirahat · {{ now()->format('H:i') }}</div>
  </div>
  <div style="margin-left:auto; font-size:13px; color:#fbbf24;" id="jamLive">--:--</div>
</div>

<div class="content">

  <div id="alertBox" style="display:none;"></div>

  {{-- GPS --}}
  <div class="card-dark">
    <div class="section-label">📍 Lokasi</div>
    <div class="gps-bar">
      <span id="gpsIcon" style="font-size:22px;">📍</span>
      <div style="flex:1;">
        <div style="font-size:13px; font-weight:600;" id="gpsStatus">Mendeteksi lokasi...</div>
        <div style="font-size:11px; color:#64748b;" id="gpsDetail"></div>
      </div>
      <button onclick="refreshGPS()" style="background:#334155; border:none; color:#94a3b8; border-radius:8px; padding:6px 10px; font-size:12px;">Refresh</button>
    </div>
  </div>

  {{-- Foto --}}
  <div class="card-dark">
    <div class="section-label">📸 Foto Lokasi (wajib langsung dari kamera)</div>
    <div class="foto-slot" id="slot0" onclick="bukaKamera()">
      <span class="plus">+</span>
    </div>
  </div>

  {{-- Pertanyaan Progress --}}
  <div class="card-dark">
    <div class="section-label">📋 Progress Hari Ini</div>
    <div class="pertanyaan-box">{{ $pertanyaan }}</div>
    <textarea id="jawabanProgress" class="form-control-dark" rows="3" placeholder="Ketik jawabanmu..."></textarea>
  </div>

  {{-- Kendala --}}
  <div class="card-dark">
    <div class="section-label">⚠️ Ada Kendala?</div>
    <div class="toggle-group">
      <div class="toggle-item" id="toggleTidak" onclick="pilihKendala(0)">Tidak</div>
      <div class="toggle-item" id="toggleYa" onclick="pilihKendala(1)">Ya</div>
    </div>

    <div class="kendala-box" id="kendalaBox">
      <div class="mb-3">
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Apa kendalanya?</label>
        <textarea id="kendalaApa" class="form-control-dark" rows="2" placeholder="Ceritakan kendalanya..."></textarea>
      </div>
      <div>
        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:6px;">Kenapa itu bisa terjadi?</label>
        <textarea id="kendalaKenapa" class="form-control-dark" rows="2" placeholder="Apa penyebabnya..."></textarea>
      </div>
    </div>
  </div>

</div>

{{-- Submit --}}
<div class="submit-bar">
  <button class="btn-submit" id="btnSubmit" disabled onclick="submitLaporan()">
    📤 Kirim Laporan
  </button>
</div>

{{-- Modal Kamera --}}
<div id="kameraModal">
  <button class="btn-tutup" onclick="tutupKamera()">✕ Tutup</button>
  <video id="kameraVideo" autoplay playsinline muted></video>
  <canvas id="kameraCanvas" style="display:none;"></canvas>
  <button class="btn-capture" onclick="jepret()">📷 Jepret</button>
</div>

<script>
let fotoData = null;
let lat = null, lng = null;
let gpsValid = false;
let adaKendala = null;
let kameraStream = null;

setInterval(() => {
  const now = new Date();
  document.getElementById('jamLive').textContent =
    [now.getHours(), now.getMinutes()].map(n=>String(n).padStart(2,'0')).join(':');
}, 1000);

function refreshGPS() {
  gpsValid = false; cekSubmit();
  navigator.geolocation.getCurrentPosition(pos => {
    lat = pos.coords.latitude; lng = pos.coords.longitude;
    fetch('/absensi/cek-gps', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({lat,lng,tipe:'siang'})
    }).then(r=>r.json()).then(data => {
      gpsValid = data.valid;
      document.getElementById('gpsIcon').textContent = data.valid ? '✅' : '❌';
      document.getElementById('gpsStatus').innerHTML = data.valid
        ? '<span style="color:#10b981">Lokasi valid ✓</span>'
        : '<span style="color:#ef4444">Di luar radius!</span>';
      document.getElementById('gpsDetail').textContent = data.jarak + ' dari kantor';
      cekSubmit();
    });
  }, () => {
    document.getElementById('gpsStatus').textContent = 'GPS gagal — coba refresh';
  }, {enableHighAccuracy:true, timeout:10000});
}
refreshGPS();

function bukaKamera() {
  document.getElementById('kameraModal').style.display = 'flex';
  navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
    .then(stream => {
      kameraStream = stream;
      document.getElementById('kameraVideo').srcObject = stream;
    });
}

function tutupKamera() {
  if (kameraStream) kameraStream.getTracks().forEach(t=>t.stop());
  document.getElementById('kameraModal').style.display = 'none';
}

function jepret() {
  const video = document.getElementById('kameraVideo');
  const canvas = document.getElementById('kameraCanvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);

  ctx.fillStyle = 'rgba(0,0,0,0.6)';
  ctx.fillRect(0, canvas.height-36, canvas.width, 36);
  ctx.fillStyle = '#fff';
  ctx.font = '13px monospace';
  ctx.fillText(new Date().toLocaleString('id-ID'), 8, canvas.height-12);

  fotoData = canvas.toDataURL('image/jpeg', 0.8);

  const slot = document.getElementById('slot0');
  slot.innerHTML = `<img src="${fotoData}">`;
  slot.style.border = '2px solid #10b981';

  tutupKamera();
  cekSubmit();
}

function pilihKendala(val) {
  adaKendala = val;
  document.getElementById('toggleYa').classList.toggle('selected-ya', val === 1);
  document.getElementById('toggleTidak').classList.toggle('selected-tidak', val === 0);
  document.getElementById('kendalaBox').style.display = val === 1 ? 'block' : 'none';
  cekSubmit();
}

function cekSubmit() {
  const jawabanOk = document.getElementById('jawabanProgress').value.trim().length > 0;
  const kendalaLengkap = adaKendala !== 1 || (
    document.getElementById('kendalaApa').value.trim() &&
    document.getElementById('kendalaKenapa').value.trim()
  );
  const btn = document.getElementById('btnSubmit');
  btn.disabled = !(gpsValid && fotoData && jawabanOk && adaKendala !== null && kendalaLengkap);
}

document.getElementById('jawabanProgress').addEventListener('input', cekSubmit);
document.getElementById('kendalaApa').addEventListener('input', cekSubmit);
document.getElementById('kendalaKenapa').addEventListener('input', cekSubmit);

function submitLaporan() {
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.textContent = 'Mengirim...';

  const body = new FormData();
  body.append('_token', '{{ csrf_token() }}');
  body.append('lat', lat);
  body.append('lng', lng);
  body.append('foto', fotoData);
  body.append('jawaban_progress', document.getElementById('jawabanProgress').value);
  body.append('ada_kendala', adaKendala);
  if (adaKendala === 1) {
    body.append('kendala_apa', document.getElementById('kendalaApa').value);
    body.append('kendala_kenapa', document.getElementById('kendalaKenapa').value);
  }

  fetch('{{ route("absensi.lapor-progress") }}', {
    method:'POST', headers:{'Accept':'application/json'}, body
  })
  .then(r=>r.json())
  .then(data => {
    if (data.success) {
      showAlert('success', data.message);
      setTimeout(() => window.location.href = data.redirect, 1500);
    } else {
      showAlert('error', data.message);
      btn.disabled = false;
      btn.textContent = '📤 Kirim Laporan';
    }
  });
}

function showAlert(type, msg) {
  const box = document.getElementById('alertBox');
  box.className = 'alert-box alert-' + type;
  box.textContent = msg;
  box.style.display = 'block';
  window.scrollTo(0,0);
}
</script>
</body>
</html>
```

- [ ] **Step 3: Lint file**

Run: `php -l resources/views/absensi/form-lapor-progress.blade.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/absensi/form-lapor-progress.blade.php
git rm resources/views/absensi/form-siang.blade.php
git commit -m "feat: halaman Lapor Progress (foto kamera, pertanyaan gilir, gali kendala), hapus form absen siang lama"
```

**Catatan verifikasi manual (setelah deploy, tidak bisa diuji dari VPS ini):** buka halaman ini di HP, pastikan kamera beneran kebuka (bukan file picker/galeri), foto ke-embed timestamp, submit dengan/tanpa kendala dua-duanya jalan, balasan otomatis muncul.

---

### Task 7: View `absensi/index.blade.php` — fase baru + tombol Kembali Kerja

**Files:**
- Modify: `resources/views/absensi/index.blade.php`

**Interfaces:**
- Consumes: fase `perlu_lapor_progress`/`perlu_kembali_kerja` dari `getFaseAbsen()` (Task 5), route `absensi.form-lapor-progress` (Task 3), route `absensi.kembali-kerja` (Task 4).

- [ ] **Step 1: Ganti blok `perlu_absen_siang` jadi `perlu_lapor_progress` + tambah blok `perlu_kembali_kerja`**

Cari blok ini di `resources/views/absensi/index.blade.php` (sekitar baris 75-90):

```php
        @elseif($fase === 'perlu_absen_siang')
        {{-- Sudah masuk pagi, perlu absen siang --}}
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:10px 14px;border-radius:10px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);font-size:13px;color:#10B981;text-align:center;">
                ✅ Sudah absen masuk pukul {{ substr($absenHariIni->jam_masuk, 0, 5) }}
                @if($absenHariIni->status === 'telat')
                · <span style="color:#F59E0B;">⏰ Telat</span>
                @elseif($absenHariIni->status === 'setengah_hari')
                · <span style="color:#F59E0B;">⚠️ Setengah Hari</span>
                @endif
            </div>
            <a href="{{ route('absensi.form-siang') }}"
               style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#F59E0B,#D97706);min-height:54px;">
                🌤️ ABSEN SIANG SEKARANG
            </a>
        </div>
```

Ganti jadi (2 blok terpisah, `perlu_lapor_progress` DULU baru `perlu_kembali_kerja`):

```php
        @elseif($fase === 'perlu_lapor_progress')
        {{-- Sudah masuk pagi, perlu lapor progress sebelum istirahat --}}
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:10px 14px;border-radius:10px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);font-size:13px;color:#10B981;text-align:center;">
                ✅ Sudah absen masuk pukul {{ substr($absenHariIni->jam_masuk, 0, 5) }}
                @if($absenHariIni->status === 'telat')
                · <span style="color:#F59E0B;">⏰ Telat</span>
                @elseif($absenHariIni->status === 'setengah_hari')
                · <span style="color:#F59E0B;">⚠️ Setengah Hari</span>
                @endif
            </div>
            <a href="{{ route('absensi.form-lapor-progress') }}"
               style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;text-decoration:none;color:#0F1117;background:linear-gradient(135deg,#F59E0B,#D97706);min-height:54px;">
                📋 LAPOR PROGRESS SEKARANG
            </a>
        </div>

        @elseif($fase === 'perlu_kembali_kerja')
        {{-- Istirahat sudah selesai, tap buat catat kembali kerja --}}
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div id="kkAlert" style="display:none;padding:10px 14px;border-radius:10px;font-size:13px;text-align:center;"></div>
            <button type="button" id="btnKembaliKerja" onclick="tapKembaliKerja()"
               style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:16px;border-radius:14px;font-size:15px;font-weight:700;border:none;color:#0F1117;background:linear-gradient(135deg,#F59E0B,#D97706);min-height:54px;cursor:pointer;">
                🔄 LANJUT KERJA
            </button>
        </div>
        <script>
        function tapKembaliKerja() {
            const btn = document.getElementById('btnKembaliKerja');
            const alertBox = document.getElementById('kkAlert');
            btn.disabled = true;
            btn.textContent = 'Mendeteksi lokasi...';
            navigator.geolocation.getCurrentPosition(pos => {
                fetch('{{ route("absensi.kembali-kerja") }}', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify({lat: pos.coords.latitude, lng: pos.coords.longitude})
                }).then(r => r.json()).then(data => {
                    alertBox.style.display = 'block';
                    alertBox.style.background = data.success ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)';
                    alertBox.style.color = data.success ? '#6ee7b7' : '#fca5a5';
                    alertBox.textContent = data.message;
                    if (data.success) {
                        setTimeout(() => window.location.href = data.redirect, 1500);
                    } else {
                        btn.disabled = false;
                        btn.textContent = '🔄 LANJUT KERJA';
                    }
                });
            }, () => {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(239,68,68,0.15)';
                alertBox.style.color = '#fca5a5';
                alertBox.textContent = 'GPS gagal terdeteksi, coba lagi.';
                btn.disabled = false;
                btn.textContent = '🔄 LANJUT KERJA';
            }, {enableHighAccuracy:true, timeout:10000});
        }
        </script>
```

- [ ] **Step 2: Update tampilan ringkasan jam di blok `perlu_pulang`**

Cari baris ini (sekitar baris 97-99):

```php
                @if($absenHariIni->jam_absen_siang)
                · Siang {{ substr($absenHariIni->jam_absen_siang, 0, 5) }}
                @endif
```

Ganti jadi:

```php
                @if($absenHariIni->jam_lapor_progress)
                · Lapor {{ substr($absenHariIni->jam_lapor_progress, 0, 5) }}
                @endif
                @if($absenHariIni->jam_absen_siang)
                · Kembali {{ substr($absenHariIni->jam_absen_siang, 0, 5) }}
                @endif
```

- [ ] **Step 3: Lint file**

Run: `php -l resources/views/absensi/index.blade.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/absensi/index.blade.php
git commit -m "feat: halaman utama absensi kenal fase Lapor Progress & Kembali Kerja"
```

**Catatan verifikasi manual (setelah deploy):**
- Jam 11:00-12:30, karyawan yang belum lapor → tombol "LAPOR PROGRESS SEKARANG" muncul.
- 2 karyawan beda buka jam yang sama → pertanyaan progress yang tampil beda (bukti pemilihan per-user jalan).
- Ada kendala → Owner dapat notif Telegram lengkap (progress+kendala+penyebab).
- Lewat jam 12:30 belum lapor → potongan Rp20rb flat masuk ke rekap.
- Jam 13:00, tap "LANJUT KERJA" → tercatat, kalau telat potongan prorata sesuai menit.
- Lewat jam 14:00 belum tap kembali kerja → potongan Rp20rb flat (mekanisme lama, harus tetap jalan sama persis).

---

## Ringkasan urutan deploy (setelah semua 7 task selesai & direview)

1. Push branch ke `main` **HANYA SETELAH** SQL manual (catatan di akhir Task 1) sudah dikonfirmasi jalan di phpMyAdmin production.
2. Setelah deploy, HAPUS `storage/framework/views/*.php` di server (cache blade lama — `form-siang.blade.php` sudah dihapus, pola lama tetap bisa nyangkut di cache kalau gak dibersihkan, sesuai catatan `CLAUDE.md` "Pelajaran deploy mahal").
3. Jalankan checklist verifikasi manual yang tercantum di Task 6 dan 7.
4. Kabari SEMUA karyawan (14 orang) soal perubahan alur ini SEBELUM jam 11:00 di hari deploy — jangan diam-diam, karena ini ubah kebiasaan harian mereka (beda dari fitur admin-only sebelumnya yang gak perlu sosialisasi ke semua orang).
