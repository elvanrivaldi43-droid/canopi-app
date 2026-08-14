<?php
// Jalankan: php tests/r2/test_r2_service.php
require __DIR__ . '/../../app/Services/R2Service.php';

use App\Services\R2Service;

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

$check('uriEncodePath encode spasi, pertahankan slash',
    $svc->uriEncodePath('/my bucket/folder/a file.jpg'),
    '/my%20bucket/folder/a%20file.jpg');
$check('uriEncodePath tidak encode tilde (unreserved char)',
    $svc->uriEncodePath('/b/foo~bar.jpg'),
    '/b/foo~bar.jpg');

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
