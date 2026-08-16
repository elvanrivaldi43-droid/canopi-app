<?php

declare(strict_types=1);

/**
 * Guardrail untuk tests/guardrail/manifest.json.
 * Jalankan: php tests/guardrail/test_manifest.php
 */

$root = dirname(__DIR__, 2);
$testsRoot = $root . '/tests';
$manifestPath = __DIR__ . '/manifest.json';

function gagal(string $pesan): never
{
    fwrite(STDERR, "FAIL: {$pesan}\n");
    exit(1);
}

function pastikan(bool $kondisi, string $pesan): void
{
    if (!$kondisi) {
        gagal($pesan);
    }
}

function pathAman(string $path): bool
{
    return str_starts_with($path, 'tests/')
        && !str_contains($path, '..')
        && !str_starts_with($path, '/');
}

function daftarFileTes(string $root, string $testsRoot): array
{
    $hasil = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $nama = $file->getFilename();
        $ekstensi = strtolower($file->getExtension());
        $adalahTesPhp = $ekstensi === 'php' && str_starts_with($nama, 'test_');
        $adalahTesNode = $ekstensi === 'mjs';

        if ($adalahTesPhp || $adalahTesNode) {
            $hasil[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($hasil);
    return $hasil;
}

function daftarHelper(string $root, string $testsRoot): array
{
    $hasil = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $nama = $file->getFilename();
        $ekstensi = strtolower($file->getExtension());
        if (!in_array($ekstensi, ['php', 'mjs'], true)) {
            continue;
        }

        $adalahTesPhp = $ekstensi === 'php' && str_starts_with($nama, 'test_');
        $adalahTesNode = $ekstensi === 'mjs';
        if (!$adalahTesPhp && !$adalahTesNode) {
            $hasil[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($hasil);
    return $hasil;
}

pastikan(is_file($manifestPath), 'tests/guardrail/manifest.json belum ada');

try {
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    gagal('manifest.json bukan JSON valid: ' . $e->getMessage());
}

pastikan(is_array($manifest), 'root manifest harus object JSON');
pastikan(($manifest['version'] ?? null) === 1, 'version manifest harus integer 1');
pastikan(isset($manifest['tests']) && is_array($manifest['tests']), 'tests harus array');
pastikan(isset($manifest['excluded']) && is_array($manifest['excluded']), 'excluded harus array');

$pathTerdaftar = [];
$tesTerdaftar = [];

foreach ($manifest['tests'] as $index => $entry) {
    pastikan(is_array($entry), "tests[{$index}] harus object");
    $path = $entry['path'] ?? null;
    $runner = $entry['runner'] ?? null;

    pastikan(is_string($path) && $path !== '', "tests[{$index}].path wajib string");
    pastikan(pathAman($path), "path tidak aman/keluar tests/: {$path}");
    pastikan(!isset($pathTerdaftar[$path]), "path duplikat: {$path}");
    pastikan(in_array($runner, ['php', 'node'], true), "runner tidak valid untuk {$path}");
    pastikan(array_key_exists('requires_db', $entry) && is_bool($entry['requires_db']), "requires_db wajib boolean untuk {$path}");
    pastikan(array_key_exists('manual', $entry) && is_bool($entry['manual']), "manual wajib boolean untuk {$path}");
    pastikan($entry['manual'] === false, "tes otomatis tidak boleh ditandai manual: {$path}");
    pastikan(is_file($root . '/' . $path), "file manifest hilang: {$path}");

    $ekstensi = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    pastikan(
        ($runner === 'php' && $ekstensi === 'php') || ($runner === 'node' && $ekstensi === 'mjs'),
        "runner tidak cocok dengan ekstensi: {$path}"
    );

    $pathTerdaftar[$path] = true;
    $tesTerdaftar[] = $path;
}

$helperTerdaftar = [];
foreach ($manifest['excluded'] as $index => $entry) {
    pastikan(is_array($entry), "excluded[{$index}] harus object");
    $path = $entry['path'] ?? null;
    $reason = $entry['reason'] ?? null;

    pastikan(is_string($path) && $path !== '', "excluded[{$index}].path wajib string");
    pastikan(pathAman($path), "path excluded tidak aman/keluar tests/: {$path}");
    pastikan(!isset($pathTerdaftar[$path]), "path duplikat tests/excluded: {$path}");
    pastikan(is_string($reason) && trim($reason) !== '', "excluded {$path} wajib punya reason");
    pastikan(($entry['manual'] ?? null) === true, "excluded {$path} wajib manual=true");
    pastikan(is_file($root . '/' . $path), "file excluded hilang: {$path}");

    $pathTerdaftar[$path] = true;
    $helperTerdaftar[] = $path;
}

sort($tesTerdaftar);
sort($helperTerdaftar);
$tesAktual = daftarFileTes($root, $testsRoot);
$helperAktual = daftarHelper($root, $testsRoot);

pastikan(
    $tesTerdaftar === $tesAktual,
    "inventory tes drift. Terdaftar=" . json_encode($tesTerdaftar) . " Aktual=" . json_encode($tesAktual)
);
pastikan(
    $helperTerdaftar === $helperAktual,
    "inventory helper drift. Terdaftar=" . json_encode($helperTerdaftar) . " Aktual=" . json_encode($helperAktual)
);

printf(
    "PASS: manifest valid — %d tes otomatis, %d helper manual/excluded.\n",
    count($tesTerdaftar),
    count($helperTerdaftar)
);
