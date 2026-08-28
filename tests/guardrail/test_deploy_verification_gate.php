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
// Diperbarui 28 Ags 2026: upload FTP kini diulang sampai 3x (Niagahoster suka
// memutus koneksi di tengah -- 2 kegagalan dalam sehari, guardrail hijau keduanya).
// Hash tetap dikunci supaya blok ini tak bisa diubah diam-diam; kalau memang perlu
// diubah lagi, ubah SADAR berikut hash-nya.
$expectedRuntimeHash = '448cbb644e01bbbf96f2946f23ebc1d69d65cc745b742d55fcd2fd55281530ef';
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

dgPastikan(substr_count($deploy, 'SamKirkland/FTP-Deploy-Action@v4.3.5') === 3, 'FTP action wajib tepat 3 (percobaan 1-3), versi sama semua');
dgPastikan(substr_count($deploy, 'uses: SamKirkland/FTP-Deploy-Action@') === 3, 'tak boleh ada FTP action versi lain menyelinap');

// Retry TIDAK BOLEH jadi hijau-palsu: hanya 2 percobaan pertama yang boleh dimaafkan,
// percobaan terakhir wajib membuat deploy MERAH kalau gagal. "Hijau tapi file tak naik"
// adalah kegagalan diam yang paling mahal di project ini.
dgPastikan(substr_count($deploy, 'continue-on-error: true') === 2, 'continue-on-error wajib tepat 2 -- percobaan terakhir harus bisa memerahkan deploy');
dgPastikan(
    preg_match('/percobaan 3, terakhir\)\s*\n        if: [^\n]+\n        uses: SamKirkland/', $deploy) === 1,
    'percobaan terakhir tak boleh punya continue-on-error'
);
dgPastikan(str_contains($deploy, 'server: ${{ secrets.FTP_SERVER }}'), 'FTP server secret lama hilang');
dgPastikan(str_contains($deploy, 'username: ${{ secrets.FTP_USERNAME }}'), 'FTP username secret lama hilang');
dgPastikan(str_contains($deploy, 'password: ${{ secrets.FTP_PASSWORD }}'), 'FTP password secret lama hilang');
dgPastikan(str_contains($deploy, 'protocol: ftp'), 'protocol FTP lama berubah');
dgPastikan(str_contains($deploy, 'port: 21'), 'port FTP lama berubah');
dgPastikan(str_contains($deploy, "branches:\n      - main"), 'deploy wajib tetap hanya dipicu main');

fwrite(STDOUT, "PASS: deploy gate memakai reusable verify dan blok FTP/cache tetap byte-identik.\n");
