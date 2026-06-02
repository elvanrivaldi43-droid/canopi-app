<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'canopi2026') die('Access Denied');

// Cari PHP binary
$phpPaths = [
    '/usr/local/php82/bin/php',
    '/usr/local/php81/bin/php',
    '/usr/bin/php82',
    '/usr/bin/php8.2',
    '/opt/php82/bin/php',
    '/usr/local/bin/php',
];

$phpBin = 'php';
foreach ($phpPaths as $path) {
    if (file_exists($path)) {
        $phpBin = $path;
        break;
    }
}

$base = '/home/u8221523/public_html/app';

function run($label, $cmd) {
    echo "<h3 style='color:#C9A84C'>$label</h3><pre style='background:#1E2535;padding:10px;border-radius:6px;color:#E2E8F0;overflow:auto;'>";

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, null, ['PATH' => '/usr/local/bin:/usr/bin:/bin']);

    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error  = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        echo htmlspecialchars($output ?: $error ?: 'No output');
    } else {
        echo "proc_open gagal!";
    }
    echo "</pre>";
}

echo '<html><head><style>
body{font-family:monospace;background:#0F1117;color:#E2E8F0;padding:20px;}
h2{color:#C9A84C;}
</style></head><body>';

echo '<div style="background:#EF4444;color:white;padding:15px;border-radius:8px;margin-bottom:20px;font-weight:bold;">
⚠️ HAPUS FILE INI SETELAH SETUP SELESAI!</div>';

echo '<h2>🚀 Pusat Kanopi — Server Setup (proc_open)</h2>';
echo "<p style='color:#10B981'>PHP Binary: $phpBin</p>";

// 1. Cek PHP
run('1. PHP Version', "$phpBin --version");

// 2. Download composer
run('2. Download Composer', "cd $base && curl -sS https://getcomposer.org/installer | $phpBin -- --install-dir=$base --filename=composer.phar");

// 3. Install dependencies
run('3. Composer Install', "cd $base && $phpBin composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1");

// 4. Generate key
run('4. Generate App Key', "cd $base && $phpBin artisan key:generate --force 2>&1");

// 5. Migrate + seed
run('5. Migrate & Seed', "cd $base && $phpBin artisan migrate --seed --force 2>&1");

// 6. Storage link
run('6. Storage Link', "cd $base && $phpBin artisan storage:link --force 2>&1");

// 7. Cache
run('7. Config Cache', "cd $base && $phpBin artisan config:cache 2>&1");
run('8. Route Cache',  "cd $base && $phpBin artisan route:cache 2>&1");
run('9. View Cache',   "cd $base && $phpBin artisan view:cache 2>&1");

// 8. Permissions
run('10. Permissions', "chmod -R 775 $base/storage $base/bootstrap/cache");

echo '<div style="background:#10B981;color:white;padding:15px;border-radius:8px;margin-top:20px;font-weight:bold;">
✅ Setup Selesai! Cek https://app.kanopibsd.co.id<br>
⚠️ HAPUS setup3.php setelah ini!</div>';

echo '</body></html>';
