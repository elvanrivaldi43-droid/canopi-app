<?php

declare(strict_types=1);

function vwGagal(string $pesan): never
{
    fwrite(STDERR, "FAIL: {$pesan}\n");
    exit(1);
}

function vwPastikan(bool $kondisi, string $pesan): void
{
    if (!$kondisi) {
        vwGagal($pesan);
    }
}

function vwPosisi(string $isi, string $jarum): int
{
    $posisi = strpos($isi, $jarum);
    if ($posisi === false) {
        vwGagal("workflow wajib memuat: {$jarum}");
    }

    return $posisi;
}

$root = dirname(__DIR__, 2);
$workflowPath = $root . '/.github/workflows/verify.yml';

if (!is_file($workflowPath)) {
    vwGagal('.github/workflows/verify.yml belum ada');
}

$workflow = file_get_contents($workflowPath);
if ($workflow === false) {
    vwGagal('verify.yml tidak dapat dibaca');
}

vwPastikan(str_contains($workflow, 'workflow_call:'), 'workflow harus reusable lewat workflow_call');
vwPastikan(str_contains($workflow, 'feature/verification-gate'), 'branch GREEN wajib menjadi trigger');
vwPastikan(str_contains($workflow, 'probe/verification-gate-negative'), 'branch probe NEGATIVE wajib menjadi trigger');
vwPastikan(str_contains($workflow, 'actions/checkout@v4'), 'checkout@v4 wajib dipakai');
vwPastikan(str_contains($workflow, 'shivammathur/setup-php@v2'), 'setup-php@v2 wajib dipakai');
vwPastikan(preg_match('/php-version:\s*[\'\"]?8\.3[\'\"]?/', $workflow) === 1, 'PHP 8.3 wajib dikunci');
vwPastikan(str_contains($workflow, 'coverage: none'), 'coverage harus dimatikan');
vwPastikan(str_contains($workflow, 'actions/setup-node@v4'), 'setup-node@v4 wajib dipakai');
vwPastikan(preg_match('/node-version:\s*[\'\"]?22[\'\"]?/', $workflow) === 1, 'Node 22 wajib dikunci');
vwPastikan(str_contains($workflow, 'npm ci --ignore-scripts'), 'npm ci deterministic wajib dipakai');
vwPastikan(str_contains($workflow, './scripts/canopi-check --full'), 'full guardrail wajib menjadi gate');

$posisiDirektori = vwPosisi($workflow, 'mkdir -p storage/framework/cache');
$posisiComposer = vwPosisi($workflow, 'composer install --no-interaction --prefer-dist --no-progress');
$posisiNpm = vwPosisi($workflow, 'npm ci --ignore-scripts');
$posisiGate = vwPosisi($workflow, './scripts/canopi-check --full');

vwPastikan($posisiDirektori < $posisiComposer, 'direktori Laravel wajib dibuat sebelum composer install');
vwPastikan($posisiComposer < $posisiNpm, 'composer install wajib sebelum npm ci');
vwPastikan($posisiNpm < $posisiGate, 'dependency install wajib selesai sebelum full guardrail');

$forbidden = [
    'FTP_SERVER',
    'FTP_USERNAME',
    'FTP_PASSWORD',
    'php artisan migrate',
    'migrate --force',
    'cp .env',
    'database.sqlite',
];

foreach ($forbidden as $larangan) {
    vwPastikan(!str_contains($workflow, $larangan), "workflow verification dilarang memuat: {$larangan}");
}

fwrite(STDOUT, "PASS: verify workflow contract valid dan terisolasi dari FTP/DB.\n");
