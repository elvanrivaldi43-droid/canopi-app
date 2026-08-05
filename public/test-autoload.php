<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') die('Forbidden');

require __DIR__.'/../vendor/autoload.php';

header('Content-Type: text/plain');

try {
    if (!class_exists(\App\Services\PingTestService::class)) {
        echo "GAGAL: class_exists() = false — class baru TIDAK terdeteksi autoloader.\n";
        exit;
    }
    $svc = new \App\Services\PingTestService();
    echo "BERHASIL: " . $svc->ping() . "\n";
    echo "Class baru di app/Services/ berhasil di-autoload tanpa masalah.\n";
} catch (\Throwable $e) {
    echo "GAGAL (exception): " . get_class($e) . " — " . $e->getMessage() . "\n";
}
