<?php
// Proteksi sederhana — ganti token ini sebelum deploy
define('SECRET', 'canopi2026');

if (($_GET['token'] ?? '') !== SECRET) {
    http_response_code(403);
    die('403 Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

$baseDir = dirname(__DIR__);

echo "=== Git Reset & Pull ===\n";
echo "Dir  : {$baseDir}\n";
echo "Time : " . date('Y-m-d H:i:s') . "\n\n";

// git reset --hard HEAD
echo "--- git reset --hard HEAD ---\n";
echo shell_exec("cd {$baseDir} && git reset --hard HEAD 2>&1");
echo "\n";

// git pull origin main
echo "--- git pull origin main ---\n";
echo shell_exec("cd {$baseDir} && git pull origin main 2>&1");
echo "\n";

echo "=== Selesai ===\n";
