<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') {
    die('Forbidden');
}

$output = [];

// Clear berbagai cache Laravel
exec('cd /home/u8221523/public_html/app && php artisan config:clear 2>&1', $output);
exec('cd /home/u8221523/public_html/app && php artisan route:clear 2>&1', $output);
exec('cd /home/u8221523/public_html/app && php artisan view:clear 2>&1', $output);
exec('cd /home/u8221523/public_html/app && php artisan cache:clear 2>&1', $output);
exec('cd /home/u8221523/public_html/app && php artisan optimize:clear 2>&1', $output);

echo '<pre style="background:#1a1a2e;color:lime;padding:20px;font-size:14px;">';
echo "=== CLEAR CACHE RESULT ===\n\n";
foreach ($output as $line) {
    echo $line . "\n";
}
echo "\n=== SELESAI ===";
echo '</pre>';