<?php
// Keamanan: harus ada ?key=canopi2026 di URL
if (!isset($_GET['key']) || $_GET['key'] !== 'canopi2026') {
    die('<h1 style="color:red">Access Denied</h1>');
}

$base = '/home/u8221523/public_html/app';
$php = 'php8.4';

echo '<html><head><style>
body{font-family:monospace;background:#0F1117;color:#E2E8F0;padding:20px;}
.ok{color:#10B981;} .err{color:#EF4444;} .warn{color:#F59E0B;}
pre{background:#1E2535;padding:15px;border-radius:8px;overflow:auto;}
h2{color:#C9A84C;}
</style></head><body>';

echo '<div style="background:#EF4444;color:white;padding:15px;border-radius:8px;margin-bottom:20px;font-size:16px;font-weight:bold;">
⚠️ HAPUS FILE INI SETELAH SETUP SELESAI!<br>
Delete: /home/u8221523/public_html/app/public/setup.php
</div>';

echo '<h2>🚀 Pusat Kanopi — Server Setup</h2>';

function run($label, $cmd) {
    echo "<h3>$label</h3>";
    $output = shell_exec($cmd . ' 2>&1');
    echo "<pre>" . htmlspecialchars($output ?? 'No output') . "</pre>";
}

// 1. Cek versi PHP
run('1. PHP Version', "$php --version");

// 2. Install Composer
run('2. Download Composer', "cd $base && curl -sS https://getcomposer.org/installer | $php -- --install-dir=$base --filename=composer.phar");

// 3. Install dependencies
run('3. Composer Install', "cd $base && $php composer.phar install --no-dev --optimize-autoloader --no-interaction");

// 4. Generate App Key
run('4. Generate App Key', "cd $base && $php artisan key:generate --force");

// 5. Run Migrations + Seed
run('5. Migrate & Seed', "cd $base && $php artisan migrate --seed --force");

// 6. Storage Link
run('6. Storage Link', "cd $base && $php artisan storage:link --force");

// 7. Cache
run('7. Config Cache', "cd $base && $php artisan config:cache");
run('8. Route Cache', "cd $base && $php artisan route:cache");
run('9. View Cache', "cd $base && $php artisan view:cache");

// 8. Permissions
run('10. Permissions', "chmod -R 775 $base/storage $base/bootstrap/cache");

echo '<div style="background:#10B981;color:white;padding:15px;border-radius:8px;margin-top:20px;font-size:16px;">
✅ Setup Selesai! Cek https://app.kanopibsd.co.id<br>
⚠️ JANGAN LUPA HAPUS FILE setup.php INI!
</div>';

echo '</body></html>';
?>
