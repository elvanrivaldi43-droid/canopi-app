<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') {
    http_response_code(403);
    die('Akses ditolak.');
}

$folder = __DIR__ . '/../storage/framework/views/';
$dihapus = 0;

if (is_dir($folder)) {
    foreach (glob($folder . '*.php') as $file) {
        if (unlink($file)) {
            $dihapus++;
        }
    }
}

echo "Cache dibersihkan. Total file dihapus: $dihapus";