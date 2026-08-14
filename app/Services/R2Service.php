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
