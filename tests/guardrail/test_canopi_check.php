<?php

declare(strict_types=1);

/**
 * Contract test untuk scripts/canopi-check.
 * Tes ini sengaja tidak memanggil --fast/--full agar tidak rekursif saat
 * dijalankan oleh canopi-check sendiri.
 */

$root = dirname(__DIR__, 2);
$runner = $root . '/scripts/canopi-check';
$manifestPath = __DIR__ . '/manifest.json';

function ccGagal(string $pesan): never
{
    fwrite(STDERR, "FAIL: {$pesan}\n");
    exit(1);
}

function ccPastikan(bool $kondisi, string $pesan): void
{
    if (!$kondisi) {
        ccGagal($pesan);
    }
}

/** @return array{exit_code:int,output:string} */
function ccJalankan(array $command, string $cwd): array
{
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $cwd
    );

    ccPastikan(is_resource($process), 'gagal memulai child process');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['exit_code' => $exitCode, 'output' => $stdout . $stderr];
}

ccPastikan(is_file($runner), 'scripts/canopi-check belum ada');
ccPastikan(is_executable($runner), 'scripts/canopi-check belum executable');
ccPastikan(is_file($manifestPath), 'manifest.json belum ada');

$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$expectedPaths = array_column($manifest['tests'], 'path');

$list = ccJalankan([$runner, '--list'], $root);
ccPastikan($list['exit_code'] === 0, '--list harus exit 0: ' . $list['output']);
ccPastikan(str_contains($list['output'], 'MODE: list'), '--list harus menampilkan mode');
ccPastikan(
    str_contains($list['output'], 'TOTAL: ' . count($expectedPaths)),
    '--list harus menampilkan jumlah tes dari manifest'
);
foreach ($expectedPaths as $path) {
    ccPastikan(str_contains($list['output'], $path), "--list melewatkan {$path}");
}

$invalid = ccJalankan([$runner, '--mode-tidak-ada'], $root);
ccPastikan($invalid['exit_code'] === 2, 'mode tidak dikenal harus exit 2');

if (!defined('CANOPI_CHECK_LIBRARY_MODE')) {
    define('CANOPI_CHECK_LIBRARY_MODE', true);
}
require $runner;

ccPastikan(function_exists('canopiRunCommand'), 'fungsi canopiRunCommand tidak tersedia');
ccPastikan(function_exists('canopiForbiddenStagedPath'), 'fungsi scanner path tidak tersedia');
ccPastikan(function_exists('canopiAddedLineViolation'), 'fungsi scanner added-line tidak tersedia');

$commandPass = canopiRunCommand([PHP_BINARY, '-r', 'fwrite(STDOUT, "child-ok"); exit(0);'], $root, false);
ccPastikan($commandPass['exit_code'] === 0, 'command sukses harus diteruskan sebagai 0');
ccPastikan(str_contains($commandPass['output'], 'child-ok'), 'stdout child command harus ditangkap');

$commandFail = canopiRunCommand([PHP_BINARY, '-r', 'fwrite(STDERR, "child-fail"); exit(7);'], $root, false);
ccPastikan($commandFail['exit_code'] === 7, 'exit code child gagal harus dipertahankan');
ccPastikan(str_contains($commandFail['output'], 'child-fail'), 'stderr child command harus ditangkap');

ccPastikan(canopiForbiddenStagedPath('.env') !== null, '.env harus ditolak');
ccPastikan(canopiForbiddenStagedPath('.hermes/plans/internal.md') !== null, '.hermes harus ditolak');
ccPastikan(canopiForbiddenStagedPath('storage/logs/laravel.log') !== null, 'storage artifact harus ditolak');
ccPastikan(canopiForbiddenStagedPath('app/Services/GajiService.php') === null, 'source normal tidak boleh ditolak');

$fakeDebugPertama = '+ d' . 'd($data);';
$fakeDebugKedua = '+ du' . 'mp($data);';
ccPastikan(canopiAddedLineViolation($fakeDebugPertama) !== null, 'debug helper pertama harus terdeteksi');
ccPastikan(canopiAddedLineViolation($fakeDebugKedua) !== null, 'debug helper kedua harus terdeteksi');
$fakeVariableName = '$to' . 'ken';
$fakeCredentialLine = '+ ' . $fakeVariableName . ' = "' . '1234567890-secret' . '";';
ccPastikan(canopiAddedLineViolation($fakeCredentialLine) !== null, 'token hardcode harus terdeteksi');
ccPastikan(canopiAddedLineViolation('+ $token = getenv("TELEGRAM_TOKEN");') === null, 'getenv token tidak boleh false positive');
ccPastikan(canopiAddedLineViolation('+ // token dibaca dari environment') === null, 'komentar aman tidak boleh false positive');

printf("PASS: canopi-check contract — list, exit propagation, dan safety scanner valid.\n");
