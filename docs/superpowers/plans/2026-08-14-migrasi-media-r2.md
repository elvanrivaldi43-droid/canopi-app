# Migrasi Media ke Cloudflare R2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pindahkan penyimpanan foto absen (dari disk lokal) dan foto profil lokasi survei (dari Cloudinary) ke Cloudflare R2, sekaligus menambah fitur baru: upload video profil lokasi survei.

**Architecture:** Satu class baru `App\Services\R2Service` (murni `curl_init` + tanda tangan AWS SigV4 manual, tanpa package Composer baru) menyediakan 2 kemampuan: upload langsung dari server (dipakai foto absen, alur base64 tidak berubah) dan bikin presigned URL upload (dipakai foto & video lokasi survei, upload langsung dari browser ke R2 tanpa lewat PHP).

**Tech Stack:** Laravel 13 / PHP 8.3, `curl_init` (bawaan PHP), JavaScript vanilla (tanpa library baru), MySQL (SQL manual via phpMyAdmin — tidak ada `php artisan migrate` di production).

**Spec:** `docs/superpowers/specs/2026-08-14-migrasi-media-r2-design.md`

## Global Constraints

- Tidak ada dependency Composer baru — cPanel Niagahoster tidak punya Terminal/Composer (dicek langsung, hanya ada WordPress Manager, PHP PEAR Packages, Perl Modules, Optimize Website, Application Manager, Softaculous Installer, Select PHP Version).
- Kredensial dibaca lewat `getenv()`, bukan `env()` (lebih andal di shared hosting, konvensi project ini).
- Semua panggilan API eksternal pakai `curl_init` (bukan `Http::` facade — tidak jalan di shared hosting project ini).
- Migrasi skema database dieksekusi manual lewat SQL idempotent (`ADD COLUMN IF NOT EXISTS`) di phpMyAdmin production — bukan `php artisan migrate` (tidak ada SSH ke server).
- Data lama (foto absen di disk lokal, foto lokasi di Cloudinary) TIDAK dimigrasi — hanya upload baru yang masuk R2.
- Video lokasi survei: maksimal 1 video per lokasi, maksimal ukuran 200MB, direkam lewat `<input type="file" accept="video/*" capture>` (kamera native HP, bukan recorder custom).
- Test standalone mengikuti pola project ini: file PHP polos dijalankan lewat `php tests/.../test_x.php`, TANPA PHPUnit/framework, pakai closure `$check()` bandingkan got vs expected, `exit(1)` kalau ada yang gagal.

---

### Task 1: Setup akun Cloudflare & bucket R2 (manual, non-kode)

Ini langkah operasional oleh Elvan (non-teknis), bukan kode. Task berikutnya (smoke test) butuh kredensial nyata dari sini.

**Files:** Tidak ada (murni langkah di dashboard Cloudflare + isi `.env` production).

- [ ] **Langkah 1: Buat akun Cloudflare**

  Buka `https://dash.cloudflare.com/sign-up`, daftar pakai email `bktgolden@gmail.com` (atau email kerja Elvan), verifikasi email.

- [ ] **Langkah 2: Buat bucket R2**

  Di dashboard Cloudflare, sidebar kiri → **R2 Object Storage** → **Create bucket**. Nama bucket: `canopi-media` (huruf kecil semua, tanpa spasi — aturan R2). Location: **Automatic**. Klik **Create bucket**.

- [ ] **Langkah 3: Aktifkan akses publik**

  Buka bucket `canopi-media` yang baru dibuat → tab **Settings** → bagian **Public access** → klik **Allow Access** (mode `r2.dev` subdomain — gratis, instan, tanpa perlu domain sendiri). Catat URL publik yang muncul, formatnya `https://pub-xxxxxxxxxxxxxxxxxxxxxxxx.r2.dev` — ini nilai untuk `R2_PUBLIC_URL`.

- [ ] **Langkah 4: Buat API Token**

  Kembali ke halaman **R2 Object Storage** (bukan di dalam bucket) → cari tombol **Manage API Tokens** (biasanya di kanan atas) → **Create API Token**. Isi:
  - Token name: `canopi-app-r2`
  - Permissions: **Object Read & Write**
  - Specify bucket(s): pilih hanya `canopi-media` (jangan "Apply to all buckets")

  Klik **Create API Token**. Halaman berikutnya menampilkan (CATAT SEMUA, hanya muncul SEKALI):
  - **Access Key ID** → ini `R2_ACCESS_KEY`
  - **Secret Access Key** → ini `R2_SECRET_KEY`
  - **Endpoint** (format `https://<account_id>.r2.cloudflarestorage.com`) → ini `R2_ENDPOINT`

- [ ] **Langkah 5: Isi ke `.env` production**

  Lewat File Manager cPanel Niagahoster, buka file `.env` di root Laravel (`public_html/app/.env` atau sesuai struktur folder project — cek lokasi `.env` yang sudah ada, taruh di situ). Tambahkan baris:

  ```env
  R2_ACCESS_KEY=isi_dari_langkah_4
  R2_SECRET_KEY=isi_dari_langkah_4
  R2_BUCKET=canopi-media
  R2_ENDPOINT=https://isi_account_id.r2.cloudflarestorage.com
  R2_PUBLIC_URL=https://pub-xxxxxxxxxxxxxxxxxxxxxxxx.r2.dev
  ```

  Simpan file. Tidak perlu restart apapun (dibaca ulang tiap request oleh PHP).

- [ ] **Langkah 6: Konfirmasi**

  Screenshot atau salin (redacted, jangan kirim secret key mentah-mentah ke chat) konfirmasi bahwa ke-5 baris di atas sudah ada di `.env` production. Lanjut ke Task 2.

---

### Task 2: `R2Service` — inti upload & presigned URL

**Files:**
- Create: `app/Services/R2Service.php`
- Test: `tests/r2/test_r2_service.php`

**Interfaces:**
- Produces: `R2Service::put(string $key, string $binaryData, string $contentType = 'application/octet-stream'): ?string` — upload langsung, return URL publik atau `null` kalau gagal.
- Produces: `R2Service::presignPutUrl(string $key, string $contentType = 'application/octet-stream', int $expiresSeconds = 900): ?array` — return `['uploadUrl' => ..., 'publicUrl' => ...]` atau `null` kalau kredensial belum lengkap.
- Produces (untuk testing, public method): `R2Service::sign(array $config, string $amzDate, string $dateStamp, string $canonicalRequest, string $signedHeaders): array` — return `[$authorizationHeader, $signatureHex]`.
- Produces (untuk testing, public method): `R2Service::uriEncodePath(string $path): string`.

- [ ] **Step 1: Tulis `R2Service`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class R2Service
{
    private const SERVICE = 's3';
    private const REGION  = 'auto';

    public function put(string $key, string $binaryData, string $contentType = 'application/octet-stream'): ?string
    {
        $config = $this->config();
        if (!$config) {
            return null;
        }

        $host = parse_url($config['endpoint'], PHP_URL_HOST);
        $uri  = '/' . $config['bucket'] . '/' . ltrim($key, '/');
        $amzDate     = gmdate('Ymd\THis\Z');
        $dateStamp   = gmdate('Ymd');
        $payloadHash = hash('sha256', $binaryData);

        $canonicalHeaders = "host:{$host}\n"
            . "x-amz-content-sha256:{$payloadHash}\n"
            . "x-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", [
            'PUT',
            $this->uriEncodePath($uri),
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        [$authorization] = $this->sign($config, $amzDate, $dateStamp, $canonicalRequest, $signedHeaders);

        $ch = curl_init($config['endpoint'] . $uri);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $binaryData,
            CURLOPT_HTTPHEADER     => [
                'Host: ' . $host,
                'x-amz-content-sha256: ' . $payloadHash,
                'x-amz-date: ' . $amzDate,
                'Authorization: ' . $authorization,
                'Content-Type: ' . $contentType,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            Log::error("R2Service::put gagal (HTTP {$status}) untuk key {$key}: {$error}");
            return null;
        }

        return rtrim($config['publicUrl'], '/') . '/' . ltrim($key, '/');
    }

    public function presignPutUrl(string $key, string $contentType = 'application/octet-stream', int $expiresSeconds = 900): ?array
    {
        $config = $this->config();
        if (!$config) {
            return null;
        }

        $host = parse_url($config['endpoint'], PHP_URL_HOST);
        $uri  = '/' . $config['bucket'] . '/' . ltrim($key, '/');
        $amzDate   = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = "{$dateStamp}/" . self::REGION . '/' . self::SERVICE . '/aws4_request';

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $config['accessKey'] . '/' . $credentialScope,
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($queryParams);
        $canonicalQuery = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders    = 'host';
        $payloadHash      = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = implode("\n", [
            'PUT',
            $this->uriEncodePath($uri),
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        [, $signature] = $this->sign($config, $amzDate, $dateStamp, $canonicalRequest, $signedHeaders);

        $presignedUrl = $config['endpoint'] . $uri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;

        return [
            'uploadUrl' => $presignedUrl,
            'publicUrl' => rtrim($config['publicUrl'], '/') . '/' . ltrim($key, '/'),
        ];
    }

    public function sign(array $config, string $amzDate, string $dateStamp, string $canonicalRequest, string $signedHeaders): array
    {
        $credentialScope = "{$dateStamp}/" . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kSecret  = 'AWS4' . $config['secretKey'];
        $kDate    = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion  = hash_hmac('sha256', self::REGION, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = 'AWS4-HMAC-SHA256 '
            . "Credential={$config['accessKey']}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, "
            . "Signature={$signature}";

        return [$authorization, $signature];
    }

    public function uriEncodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function config(): ?array
    {
        $accessKey = getenv('R2_ACCESS_KEY');
        $secretKey = getenv('R2_SECRET_KEY');
        $bucket    = getenv('R2_BUCKET');
        $endpoint  = getenv('R2_ENDPOINT');
        $publicUrl = getenv('R2_PUBLIC_URL');

        if (!$accessKey || !$secretKey || !$bucket || !$endpoint || !$publicUrl) {
            Log::error('R2Service: kredensial R2 belum lengkap di .env');
            return null;
        }

        return compact('accessKey', 'secretKey', 'bucket', 'endpoint', 'publicUrl');
    }
}
```

- [ ] **Step 2: Tulis test standalone**

Catatan penting: test ini TIDAK memanggil `put()` (butuh network nyata ke R2, diverifikasi di Task 3 lewat smoke test manual) dan TIDAK memanggil `config()` lewat jalur yang butuh Laravel bootstrap (`Log::error()` butuh service container). Yang diuji: `sign()`, `uriEncodePath()` (murni komputasi), dan `presignPutUrl()` dengan kredensial palsu lewat `putenv()` (murni membangun string URL, tanpa network).

```php
<?php
// Jalankan: php tests/r2/test_r2_service.php
require __DIR__ . '/../../app/Services/R2Service.php';

use App\Services\R2Service;

// Stub minimal biar Log::error() di R2Service::config() tidak butuh Laravel bootstrap penuh.
if (!class_exists('Illuminate\Support\Facades\Log')) {
    class LogStub { public static function error($msg) {} }
    class_alias('LogStub', 'Illuminate\Support\Facades\Log');
}

$svc = new R2Service();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};
$checkTrue = function (string $name, bool $cond) use (&$fail) {
    echo ($cond ? 'PASS' : 'FAIL') . " — $name\n";
    if (!$cond) $fail = true;
};

// ── uriEncodePath: slash dipertahankan, spasi di-encode, tilde TIDAK di-encode ──
$check('uriEncodePath encode spasi, pertahankan slash',
    $svc->uriEncodePath('/my bucket/folder/a file.jpg'),
    '/my%20bucket/folder/a%20file.jpg');
$check('uriEncodePath tidak encode tilde (unreserved char)',
    $svc->uriEncodePath('/b/foo~bar.jpg'),
    '/b/foo~bar.jpg');

// ── sign(): struktur & determinisme ──
$config = [
    'accessKey' => 'testAccessKey',
    'secretKey' => 'testSecretKey',
    'bucket'    => 'test-bucket',
    'endpoint'  => 'https://abc123.r2.cloudflarestorage.com',
    'publicUrl' => 'https://pub-abc123.r2.dev',
];
$canonicalRequest = "PUT\n/test-bucket/foo.jpg\n\nhost:abc123.r2.cloudflarestorage.com\nx-amz-content-sha256:abc\nx-amz-date:20260101T000000Z\n\nhost;x-amz-content-sha256;x-amz-date\nabc";
[$auth1, $sig1] = $svc->sign($config, '20260101T000000Z', '20260101', $canonicalRequest, 'host;x-amz-content-sha256;x-amz-date');
[$auth2, $sig2] = $svc->sign($config, '20260101T000000Z', '20260101', $canonicalRequest, 'host;x-amz-content-sha256;x-amz-date');

$checkTrue('signature 64 karakter hex lowercase', (bool) preg_match('/^[0-9a-f]{64}$/', $sig1));
$check('sign() deterministik — input sama, output sama', $sig2, $sig1);
$check('authorization header memuat credential scope yang benar',
    str_contains($auth1, 'Credential=testAccessKey/20260101/auto/s3/aws4_request'), true);
$check('authorization header memuat signed headers yang benar',
    str_contains($auth1, 'SignedHeaders=host;x-amz-content-sha256;x-amz-date'), true);

$configLainSecret = $config;
$configLainSecret['secretKey'] = 'secretYangBeda';
[, $sig3] = $svc->sign($configLainSecret, '20260101T000000Z', '20260101', $canonicalRequest, 'host;x-amz-content-sha256;x-amz-date');
$checkTrue('secret key beda -> signature beda (bukan diabaikan diam-diam)', $sig3 !== $sig1);

// ── presignPutUrl(): bangun URL lengkap tanpa network, pakai kredensial palsu ──
putenv('R2_ACCESS_KEY=fakeAccessKey');
putenv('R2_SECRET_KEY=fakeSecretKey');
putenv('R2_BUCKET=fake-bucket');
putenv('R2_ENDPOINT=https://fake123.r2.cloudflarestorage.com');
putenv('R2_PUBLIC_URL=https://pub-fake123.r2.dev');

$hasil = $svc->presignPutUrl('lokasi/1/foto/test.jpg', 'image/jpeg', 900);
$checkTrue('presignPutUrl return array (bukan null) saat kredensial lengkap', $hasil !== null);
$checkTrue('uploadUrl memuat X-Amz-Signature 64 hex', (bool) preg_match('/X-Amz-Signature=[0-9a-f]{64}$/', $hasil['uploadUrl'] ?? ''));
$checkTrue('uploadUrl memuat algoritma yang benar', str_contains($hasil['uploadUrl'] ?? '', 'X-Amz-Algorithm=AWS4-HMAC-SHA256'));
$check('publicUrl sesuai pola R2_PUBLIC_URL + key', $hasil['publicUrl'] ?? null, 'https://pub-fake123.r2.dev/lokasi/1/foto/test.jpg');

putenv('R2_ACCESS_KEY');
putenv('R2_SECRET_KEY');
putenv('R2_BUCKET');
putenv('R2_ENDPOINT');
putenv('R2_PUBLIC_URL');

$hasilTanpaKredensial = $svc->presignPutUrl('x/y.jpg');
$check('presignPutUrl return null kalau kredensial belum diset', $hasilTanpaKredensial, null);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
```

- [ ] **Step 3: Jalankan test, pastikan semua PASS**

Run: `php tests/r2/test_r2_service.php`
Expected: semua baris `PASS`, diakhiri `=== SEMUA TES LULUS ===`

- [ ] **Step 4: Commit**

```bash
git add app/Services/R2Service.php tests/r2/test_r2_service.php
git commit -m "feat: R2Service — upload & presigned URL ke Cloudflare R2 (curl murni, tanpa Composer baru)"
```

---

### Task 3: Smoke test koneksi R2 nyata (manual, sekali jalan)

Test di Task 2 murni struktural (tanpa network). Task ini membuktikan kredensial dari Task 1 beneran bisa upload ke R2 sungguhan — SEBELUM dipasang ke fitur asli.

**Files:**
- Create sementara: `public/r2-smoke-test.php` (dihapus lagi di Step 4)

**Interfaces:**
- Consumes: `R2Service::put()` dari Task 2.

- [ ] **Step 1: Tulis script smoke test**

```php
<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi_r2_smoke_2026') {
    http_response_code(403);
    die('Akses ditolak.');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = new \App\Services\R2Service();
$isiTes = 'tes koneksi R2 — ' . now();
$url = $svc->put('smoke-test/tes.txt', $isiTes, 'text/plain');

if (!$url) {
    echo "GAGAL upload. Cek laravel.log untuk detail error (kemungkinan kredensial .env salah).";
    exit;
}

echo "Upload berhasil. URL: {$url}\n\n";
echo "Buka URL di atas di tab baru — harus tampil tulisan: \"{$isiTes}\"\n\n";
echo "Kalau sudah dicek dan benar, HAPUS FILE INI (public/r2-smoke-test.php) dan hapus juga object 'smoke-test/tes.txt' dari bucket R2 (lewat dashboard Cloudflare).";
```

- [ ] **Step 2: Deploy sementara & jalankan**

```bash
git add public/r2-smoke-test.php
git commit -m "test: smoke test koneksi R2 (sementara, dihapus setelah dipakai)"
git push origin main
```

Tunggu ±1-2 menit auto-deploy, lalu buka `https://app.kanopibsd.co.id/r2-smoke-test.php?key=canopi_r2_smoke_2026`.

- [ ] **Step 3: Verifikasi**

Konfirmasi: (a) halaman menampilkan "Upload berhasil" + URL, (b) URL tersebut kalau dibuka nampilin isi teks tesnya, (c) file `tes.txt` muncul di bucket `canopi-media` lewat dashboard Cloudflare (folder `smoke-test/`).

Kalau GAGAL: cek `laravel.log` (`lihat-log.php`) — error paling mungkin: kredensial `.env` salah ketik (Task 1 Langkah 5), atau bucket belum public (Task 1 Langkah 3).

- [ ] **Step 4: Bersihkan**

Hapus file `public/r2-smoke-test.php` dari repo, dan hapus object `smoke-test/tes.txt` dari bucket lewat dashboard Cloudflare (R2 → bucket `canopi-media` → cari file → Delete).

```bash
git rm public/r2-smoke-test.php
git commit -m "chore: hapus smoke test R2 sementara, koneksi sudah terverifikasi"
git push origin main
```

---

### Task 4: Swap foto absen ke R2

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`

**Interfaces:**
- Consumes: `R2Service::put()` dari Task 2.

- [ ] **Step 1: Tambah import**

Di `app/Http/Controllers/AbsensiController.php`, tambahkan setelah baris `use App\Services\LiburService;` (baris 13):

```php
use App\Services\R2Service;
```

- [ ] **Step 2: Ubah `simpanFotoBase64()` — satu titik perubahan untuk semua 3 pemakai**

Cari method `simpanFotoBase64` (sekitar baris 667-673):

```php
    private function simpanFotoBase64(string $base64,string $folder): string
    {
        $imageData=preg_replace('/^data:image\/\w+;base64,/','',$base64);
        $filename=$folder.'/'.date('His').'_'.uniqid().'.jpg';
        Storage::disk('public')->put($filename,base64_decode($imageData));
        return $filename;
    }
```

Ganti jadi:

```php
    private function simpanFotoBase64(string $base64,string $folder): ?string
    {
        $imageData=preg_replace('/^data:image\/\w+;base64,/','',$base64);
        $filename=$folder.'/'.date('His').'_'.uniqid().'.jpg';
        return app(R2Service::class)->put($filename, base64_decode($imageData), 'image/jpeg');
    }
```

- [ ] **Step 3: Tambah guard "upload gagal" di `absenMasuk()`**

Cari baris (sekitar 179):

```php
        $fotoPath     = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        $jamSekarang  = now()->format('H:i');
```

Ganti jadi:

```php
        $fotoPath = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }
        $jamSekarang  = now()->format('H:i');
```

- [ ] **Step 4: Tambah guard "upload gagal" di `laporProgress()`**

Cari baris (sekitar 321-324):

```php
        $adaKendala = $request->ada_kendala == 1;
        $pertanyaan = self::pilihPertanyaanProgress($user->id, today());
        $folder     = 'absensi/'.$user->id.'/'.today()->format('Ymd');

        $absen->update([
            'foto_siang_1'        => $this->simpanFotoBase64($request->foto,$folder),
```

Ganti jadi:

```php
        $adaKendala = $request->ada_kendala == 1;
        $pertanyaan = self::pilihPertanyaanProgress($user->id, today());
        $folder     = 'absensi/'.$user->id.'/'.today()->format('Ymd');

        $fotoPath = $this->simpanFotoBase64($request->foto,$folder);
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }

        $absen->update([
            'foto_siang_1'        => $fotoPath,
```

- [ ] **Step 5: Tambah guard "upload gagal" di `absenPulang()`**

Cari baris (sekitar 427-428):

```php
        $fotoPath  = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        $jamPulang = now()->format('H:i:s');
```

Ganti jadi:

```php
        $fotoPath = $this->simpanFotoBase64($request->foto,'absensi/'.$user->id.'/'.today()->format('Ymd'));
        if (!$fotoPath) {
            return response()->json(['success'=>false,'message'=>'Gagal menyimpan foto, coba lagi.']);
        }
        $jamPulang = now()->format('H:i:s');
```

- [ ] **Step 6: Cek `Storage` facade masih dipakai di tempat lain file ini**

Run: `grep -n "Storage::" app/Http/Controllers/AbsensiController.php`

Kalau sudah tidak ada pemakaian `Storage::` lagi di file ini, hapus baris `use Illuminate\Support\Facades\Storage;` (baris 8). Kalau masih ada (mis. dipakai fitur lain di file yang sama), biarkan importnya.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "feat: foto absen (masuk/lapor-progress/kembali-kerja) sekarang disimpan ke R2, bukan disk lokal"
```

- [ ] **Step 8: Checklist verifikasi manual (setelah deploy, Task 8)**

- [ ] Absen masuk dengan foto → `foto_masuk` di DB berisi URL `https://pub-....r2.dev/absensi/...` (bukan path lokal).
- [ ] Lapor Progress dengan foto → `foto_siang_1` juga URL R2.
- [ ] Kalau `.env` R2 sengaja dikosongkan sementara (simulasi gagal) → absen menampilkan pesan "Gagal menyimpan foto, coba lagi." (bukan 500 error kayak insiden kolom hilang kemarin).

---

### Task 5: Migrasi foto profil lokasi survei — Cloudinary ke R2

**Files:**
- Modify: `app/Http/Controllers/LokasiController.php`
- Modify: `resources/views/lokasi/index.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `R2Service::presignPutUrl()` dari Task 2.

- [ ] **Step 1: Tambah route presign**

Di `routes/web.php`, cari baris (sekitar 429-430):

```php
    Route::get('/lokasi/{id}', [\App\Http\Controllers\LokasiController::class, 'index']);
    Route::post('/lokasi/{id}', [\App\Http\Controllers\LokasiController::class, 'simpan']);
```

Tambahkan setelahnya:

```php
    Route::post('/lokasi/{id}/presign', [\App\Http\Controllers\LokasiController::class, 'presign']);
```

- [ ] **Step 2: Tambah method `presign()` di `LokasiController`**

Di `app/Http/Controllers/LokasiController.php`, tambahkan `use App\Services\R2Service;` setelah `use Illuminate\Support\Facades\DB;`, lalu tambahkan method baru sebelum `simpan()`:

```php
    private function extensionDariContentType(string $contentType): string
    {
        return match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'video/mp4'  => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            default => 'bin',
        };
    }

    public function presign(Request $request, $id)
    {
        abort_if(!$this->bolehAkses(), 403);
        $lead = DB::table('pipeline_leads')->where('id', $id)->first();
        abort_if(!$lead, 404);

        $request->validate([
            'tipe'         => 'required|in:foto,video',
            'content_type' => 'required|string',
        ]);

        $ext = $this->extensionDariContentType($request->content_type);
        $key = 'lokasi/'.$id.'/'.$request->tipe.'/'.now()->format('Ymd_His').'_'.uniqid().'.'.$ext;

        $hasil = app(R2Service::class)->presignPutUrl($key, $request->content_type, 900);
        if (!$hasil) {
            return response()->json(['success'=>false,'message'=>'Gagal menyiapkan upload, coba lagi.'], 500);
        }

        return response()->json(['success'=>true,'uploadUrl'=>$hasil['uploadUrl'],'publicUrl'=>$hasil['publicUrl']]);
    }
```

- [ ] **Step 3: Ganti JS upload foto — dari Cloudinary ke R2**

Di `resources/views/lokasi/index.blade.php`, cari blok (baris 186-243, dari komentar `// ================= FOTO -> CLOUDINARY =================` sampai penutup fungsi `uploadCloudinary`):

```javascript
// ================= FOTO -> CLOUDINARY =================
var CLOUD_NAME='rnvp56qs';
var UPLOAD_PRESET='canopi_lokasi';
var LEAD_ID={{ $lead->id }};
var MAKS_FOTO=8;
```

Ganti jadi (hapus `CLOUD_NAME`/`UPLOAD_PRESET`, tambah `PRESIGN_URL`):

```javascript
// ================= FOTO -> R2 =================
var LEAD_ID={{ $lead->id }};
var MAKS_FOTO=8;
var PRESIGN_URL='{{ url("/lokasi/".$lead->id."/presign") }}';
```

Lalu cari fungsi `uploadCloudinary` (baris 232-243):

```javascript
function uploadCloudinary(blob){
    var fd=new FormData();
    fd.append('file', blob);
    fd.append('upload_preset', UPLOAD_PRESET);
    fd.append('folder', 'canopi/lokasi/lead_'+LEAD_ID);
    return fetch('https://api.cloudinary.com/v1_1/'+CLOUD_NAME+'/image/upload', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d && d.secure_url) return d.secure_url;
            throw new Error((d && d.error && d.error.message) ? d.error.message : 'upload gagal');
        });
}
```

Ganti jadi:

```javascript
function uploadR2(blob, tipe){
    var contentType = blob.type || 'application/octet-stream';
    return fetch(PRESIGN_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({tipe: tipe, content_type: contentType})
    }).then(function(r){ return r.json(); }).then(function(d){
        if(!d.success) throw new Error(d.message||'gagal menyiapkan upload');
        return fetch(d.uploadUrl, {method:'PUT', headers:{'Content-Type':contentType}, body:blob})
            .then(function(putRes){
                if(!putRes.ok) throw new Error('upload ke R2 gagal (status '+putRes.status+')');
                return d.publicUrl;
            });
    });
}
```

Lalu di dalam listener `fotoInput.addEventListener('change', ...)` (sekitar baris 256-260), ganti pemanggilan:

```javascript
            kompresFoto(files[idx])
                .then(uploadCloudinary)
                .then(function(url){ fotoList.push(url); renderFoto(); idx++; next(); })
                .catch(function(e){ fotoStatus('Gagal: '+e.message); idx++; next(); });
```

Jadi:

```javascript
            kompresFoto(files[idx])
                .then(function(blob){ return uploadR2(blob, 'foto'); })
                .then(function(url){ fotoList.push(url); renderFoto(); idx++; next(); })
                .catch(function(e){ fotoStatus('Gagal: '+e.message); idx++; next(); });
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LokasiController.php resources/views/lokasi/index.blade.php routes/web.php
git commit -m "feat: foto profil lokasi survei pindah dari Cloudinary ke R2 (presigned URL, upload langsung dari browser)"
```

---

### Task 6: Fitur baru — video profil lokasi survei

**Files:**
- Modify: `resources/views/lokasi/index.blade.php`
- Modify: `app/Http/Controllers/LokasiController.php`
- Create: migration `database/migrations/2026_08_14_000001_add_lokasi_video_to_pipeline_leads_table.php`

**Interfaces:**
- Consumes: `uploadR2(blob, tipe)` dari Task 5 (dipakai ulang dengan `tipe='video'`).

- [ ] **Step 1: Migration (untuk kelengkapan repo — deploy sebenarnya via SQL manual di Step 5)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_leads', function (Blueprint $table) {
            $table->text('lokasi_video')->nullable()->after('lokasi_foto');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_leads', function (Blueprint $table) {
            $table->dropColumn('lokasi_video');
        });
    }
};
```

- [ ] **Step 2: Tambah UI video di `lokasi/index.blade.php`**

Cari blok "Foto Lokasi" (baris 125-134):

```html
        {{-- Foto Lokasi (upload ke Cloudinary) --}}
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:6px;">Foto Lokasi</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:10px;">Foto otomatis dikompres sebelum upload (hemat kuota). Maks 8 foto. Jangan lupa tekan Simpan setelah upload.</div>
            <input type="file" id="fotoInput" accept="image/*" capture="environment" multiple style="display:none">
            <button type="button" id="btnFoto" class="btn" style="background:#334155;color:#e2e8f0;">📷 Tambah Foto</button>
            <div id="fotoStatus" style="font-size:12px;color:#cbd5e1;margin-top:8px;"></div>
            <div id="fotoGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px;"></div>
            <input type="hidden" name="lokasi_foto" id="fotoJson" value="{{ $lead->lokasi_foto ?? '[]' }}">
        </div>
```

Tambahkan blok baru setelahnya (sebelum tombol Simpan):

```html
        {{-- Video Lokasi (baru, ke R2) --}}
        <div class="lk-card">
            <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:6px;">Video Lokasi</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:10px;">1 video singkat keliling lokasi (maks 200MB). Jangan lupa tekan Simpan setelah upload.</div>
            <input type="file" id="videoInput" accept="video/*" capture="environment" style="display:none">
            <button type="button" id="btnVideo" class="btn" style="background:#334155;color:#e2e8f0;">🎥 Tambah Video</button>
            <div id="videoStatus" style="font-size:12px;color:#cbd5e1;margin-top:8px;"></div>
            <button type="button" id="btnRetryVideo" onclick="cobaUploadVideo()" class="btn" style="background:#b45309;color:#fff;margin-top:6px;display:none;">🔁 Coba Upload Lagi</button>
            <div id="videoGrid" style="margin-top:10px;"></div>
            <input type="hidden" name="lokasi_video" id="videoJson" value="{{ $lead->lokasi_video ?? '[]' }}">
        </div>
```

- [ ] **Step 3: Tambah JS video** (setelah `renderFoto();` baris 265, sebelum blok validasi `LV_USER`)

```javascript
// ================= VIDEO -> R2 =================
var MAKS_VIDEO=1;
var MAKS_VIDEO_BYTES=200*1024*1024;

var videoList=[];
try{ videoList=JSON.parse(document.getElementById('videoJson').value||'[]'); }catch(e){ videoList=[]; }
if(!videoList || typeof videoList.length==='undefined') videoList=[];

function videoStatusMsg(t){ var e=document.getElementById('videoStatus'); if(e) e.textContent=t; }
function renderVideo(){
    var g=document.getElementById('videoGrid'); if(!g) return;
    var html='';
    for(var i=0;i<videoList.length;i++){
        html+='<div style="position:relative;margin-bottom:6px;">'+
            '<video src="'+videoList[i]+'" controls style="width:100%;border-radius:6px;border:1px solid #334155;"></video>'+
            '<button type="button" onclick="hapusVideo('+i+')" style="position:absolute;top:6px;right:6px;background:#7f1d1d;color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:13px;cursor:pointer;line-height:1;">×</button>'+
            '</div>';
    }
    g.innerHTML=html;
    document.getElementById('videoJson').value=JSON.stringify(videoList);
}
function hapusVideo(i){ videoList.splice(i,1); renderVideo(); }

// File yang lagi diproses TETAP disimpan di variabel ini sampai upload SUKSES —
// kalau gagal (sinyal lemah dsb), tombol retry pakai file yang sama, tidak perlu rekam ulang.
var pendingVideoFile=null;

function cobaUploadVideo(){
    if(!pendingVideoFile) return;
    document.getElementById('btnRetryVideo').style.display='none';
    videoStatusMsg('Mengupload video...');
    uploadR2(pendingVideoFile, 'video')
        .then(function(url){
            videoList.push(url); renderVideo();
            videoStatusMsg('Video siap. Tekan Simpan untuk menyimpan.');
            pendingVideoFile=null;
        })
        .catch(function(e){
            videoStatusMsg('Gagal: '+e.message+' — file tetap tersimpan, coba lagi tanpa rekam ulang.');
            document.getElementById('btnRetryVideo').style.display='block';
        });
}

var btnVideo=document.getElementById('btnVideo');
var videoInput=document.getElementById('videoInput');
if(btnVideo && videoInput){
    btnVideo.addEventListener('click', function(){ videoInput.click(); });
    videoInput.addEventListener('change', function(){
        var file=this.files[0];
        this.value='';
        if(!file){ return; }
        if(videoList.length>=MAKS_VIDEO){ videoStatusMsg('Maksimal '+MAKS_VIDEO+' video. Hapus dulu yang lama kalau mau ganti.'); return; }
        if(file.size>MAKS_VIDEO_BYTES){ videoStatusMsg('Video terlalu besar (maks 200MB).'); return; }
        pendingVideoFile=file;
        cobaUploadVideo();
    });
}
renderVideo();
```

- [ ] **Step 4: Terima `lokasi_video` di `LokasiController::simpan()`**

Di `app/Http/Controllers/LokasiController.php`, method `simpan()`, tambahkan ke `$request->validate([...])` (setelah baris `'lokasi_foto' => 'nullable|string',`):

```php
            'lokasi_video'          => 'nullable|string',
```

Dan tambahkan ke array `$data` (setelah baris `'lokasi_foto' => $request->lokasi_foto,`):

```php
            'lokasi_video'           => $request->lokasi_video,
```

- [ ] **Step 5: SQL manual production (dijalankan Elvan sebelum push)**

```sql
ALTER TABLE pipeline_leads ADD COLUMN IF NOT EXISTS lokasi_video TEXT NULL AFTER lokasi_foto;
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/lokasi/index.blade.php app/Http/Controllers/LokasiController.php database/migrations/2026_08_14_000001_add_lokasi_video_to_pipeline_leads_table.php
git commit -m "feat: fitur baru upload video profil lokasi survei (maks 1 video, 200MB, ke R2)"
```

---

### Task 7: Retensi — R2 Lifecycle Rule (manual, non-kode)

**Files:** Tidak ada (murni konfigurasi dashboard Cloudflare).

- [ ] **Step 1: Buat lifecycle rule untuk foto absen**

Dashboard Cloudflare → R2 → bucket `canopi-media` → tab **Settings** → bagian **Object lifecycle rules** → **Add rule**. Isi:
- Rule name: `hapus-foto-absen-60-hari`
- Prefix: `absensi/` (hanya kena folder foto absen, TIDAK kena folder `lokasi/`)
- Action: **Delete object**
- Condition: **Age of object** → **60 days**

Simpan.

- [ ] **Step 2: Konfirmasi TIDAK ada rule untuk folder `lokasi/`**

Pastikan tidak ada lifecycle rule dengan prefix `lokasi/` — foto & video lokasi survei harus permanen (sesuai spec).

- [ ] **Step 3: Catatan — jangan hapus `foto-absen-bersih.php` dulu**

Script `public/foto-absen-bersih.php` (dibuat sebelum migrasi ini) masih dibutuhkan untuk membersihkan foto absen LAMA yang masih ada di disk lokal server (data lama tidak ikut dimigrasi ke R2, lihat spec bagian Cakupan). Baru hapus script itu setelah Elvan yakin semua foto lokal lama sudah dibersihkan manual dan lifecycle rule R2 di atas sudah terbukti jalan (butuh ditunggu ≥60 hari untuk lihat hasil pertama).

---

### Task 8: Deploy & verifikasi production

**Files:** Tidak ada (deploy + checklist manual).

- [ ] **Step 1: Push semua task ke `main`**

```bash
git push origin main
```

Tunggu ±1-2 menit auto-deploy GitHub Actions.

- [ ] **Step 2: Konfirmasi SQL Task 6 Step 5 sudah dijalankan Elvan di phpMyAdmin production SEBELUM push di atas** (kalau belum, jalankan dulu sebelum lanjut — kolom `lokasi_video` harus ada sebelum kode yang menulis ke situ live).

- [ ] **Step 3: Checklist verifikasi manual di production**

- [ ] Absen masuk dengan foto (HP asli) → sukses, cek `foto_masuk` di DB berisi URL `pub-....r2.dev`.
- [ ] Lapor Progress dengan foto → sukses, `foto_siang_1` juga URL R2.
- [ ] Buka `/lokasi/{id}` (lead pipeline yang sudah ada) sebagai level 1/2/3 → upload 1 foto → foto tampil di grid, tersimpan setelah klik Simpan.
- [ ] Upload 1 video (durasi wajar, <200MB) → video tampil dengan player, tersimpan setelah klik Simpan, `lokasi_video` di DB berisi URL R2.
- [ ] Coba upload video ke-2 → ditolak dengan pesan "Maksimal 1 video".
- [ ] Coba upload video >200MB (kalau ada file tes) → ditolak SEBELUM upload dimulai, pesan jelas.
- [ ] Matikan WiFi/data di tengah upload video → file tidak hilang, tombol upload ulang bisa dipakai tanpa rekam ulang.
- [ ] Buka salah satu URL foto/video R2 langsung di browser baru (tanpa login) → tetap bisa dibuka (bucket publik, sesuai desain).
- [ ] Cek dashboard Cloudflare R2 → file-file di atas beneran muncul di bucket `canopi-media`.

- [ ] **Step 4: Update `CLAUDE.md`**

Tambahkan ringkasan sesi ini ke bagian "STATUS TERKINI" — migrasi R2 selesai, sumber kebenaran baru untuk foto absen & lokasi survei, roadmap #4 "Sesi Media R2" bisa ditandai selesai (kecuali fitur tampilan riwayat foto 7-hari yang sengaja ditunda jadi spec terpisah).
