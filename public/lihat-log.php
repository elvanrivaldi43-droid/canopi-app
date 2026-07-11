<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') {
    http_response_code(403);
    die('Akses ditolak.');
}
header('Content-Type: text/plain');

$path = __DIR__ . '/../storage/logs/laravel.log';

if (isset($_GET['kosongkan'])) {
    file_put_contents($path, '');
    echo "Log dikosongkan.";
    exit;
}

if (!file_exists($path)) {
    echo "File log tidak ada.";
    exit;
}

$isi = file_get_contents($path);
$baris = explode("\n", $isi);
$ambil = array_slice($baris, -300);
echo implode("\n", $ambil);