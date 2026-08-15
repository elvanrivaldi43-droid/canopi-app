<?php

declare(strict_types=1);

function dgGagal(string $pesan): never
{
    fwrite(STDERR, "FAIL: {$pesan}\n");
    exit(1);
}

function dgPastikan(bool $kondisi, string $pesan): void
{
    if (!$kondisi) {
        dgGagal($pesan);
    }
}

$root = dirname(__DIR__, 2);
$deployPath = $root . '/.github/workflows/deploy.yml';

if (!is_file($deployPath)) {
    dgGagal('.github/workflows/deploy.yml tidak ada');
}

$deploy = file_get_contents($deployPath);
if ($deploy === false) {
    dgGagal('deploy.yml tidak dapat dibaca');
}

$runtimeMarker = "    runs-on: ubuntu-latest\n";
$runtimePosisi = strpos($deploy, $runtimeMarker);
if ($runtimePosisi === false) {
    dgGagal('marker runtime deploy lama tidak ditemukan');
}

$runtimeLama = substr($deploy, $runtimePosisi);
$expectedRuntimeHash = 'a11c7864f04f4bc1e6c3475f211aa051f24bef5f4e9ababa5076e941f55ffde2';
dgPastikan(
    hash('sha256', $runtimeLama) === $expectedRuntimeHash,
    'blok FTP/cache lama berubah; Task 5 hanya boleh menambah verification dependency'
);

dgPastikan(
    preg_match(
        '/jobs:\s*\n  verify:\s*\n    uses: \.\/\.github\/workflows\/verify\.yml\s*\n\s*deploy:/',
        $deploy
    ) === 1,
    'job verify reusable belum dipasang sebelum deploy'
);

dgPastikan(
    preg_match(
        '/\n  deploy:\s*\n    name: Deploy via FTP\s*\n    needs: verify\s*\n    runs-on: ubuntu-latest/',
        $deploy
    ) === 1,
    'job deploy belum dikunci dengan needs: verify'
);

dgPastikan(substr_count($deploy, 'SamKirkland/FTP-Deploy-Action@v4.3.5') === 1, 'FTP action lama wajib tetap tepat satu');
dgPastikan(str_contains($deploy, 'server: ${{ secrets.FTP_SERVER }}'), 'FTP server secret lama hilang');
dgPastikan(str_contains($deploy, 'username: ${{ secrets.FTP_USERNAME }}'), 'FTP username secret lama hilang');
dgPastikan(str_contains($deploy, 'password: ${{ secrets.FTP_PASSWORD }}'), 'FTP password secret lama hilang');
dgPastikan(str_contains($deploy, 'protocol: ftp'), 'protocol FTP lama berubah');
dgPastikan(str_contains($deploy, 'port: 21'), 'port FTP lama berubah');
dgPastikan(str_contains($deploy, "branches:\n      - main"), 'deploy wajib tetap hanya dipicu main');

fwrite(STDOUT, "PASS: deploy gate memakai reusable verify dan blok FTP/cache tetap byte-identik.\n");
