<?php
// FILE: tests/bootstrap.php
// Autoload untuk semua tes standalone di worktree ini.
//
// PENTING: vendor/ di-share dari repo utama (/root/projects/canopi-app) dan classmap
// composer di sana SUDAH berisi kelas App\* versi branch lain. Classmap MENANG atas
// PSR-4, jadi `$loader->setPsr4('App\\', [...])` saja TIDAK cukup: kelas yang sudah
// ada di repo utama (mis. App\Models\KodeAbsen, App\Services\LiburService) tetap
// dimuat dari sana, dan tes hijau bisa berarti "kode branch lain lulus", bukan kode ini.
// Makanya classmap-nya ditimpa file-per-file dari app/ milik worktree ini.
$loader = require __DIR__ . '/../vendor/autoload.php';
$appDir = realpath(__DIR__ . '/../app');

$loader->setPsr4('App\\', [$appDir]);

$peta = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $relatif = substr($file->getPathname(), strlen($appDir) + 1, -4);
    $peta['App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relatif)] = $file->getPathname();
}
$loader->addClassMap($peta);

return $loader;
