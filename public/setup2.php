<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'canopi2026') die('Access Denied');

echo '<pre style="background:#0F1117;color:#E2E8F0;padding:20px;font-family:monospace;">';
echo "=== DISABLED FUNCTIONS ===\n";
echo ini_get('disable_functions') . "\n\n";

echo "=== PHP VERSION ===\n";
echo PHP_VERSION . "\n\n";

echo "=== AVAILABLE SHELL FUNCTIONS ===\n";
$funcs = ['shell_exec','exec','passthru','system','proc_open','popen'];
foreach ($funcs as $f) {
    echo "$f: " . (function_exists($f) ? "✅ AVAILABLE" : "❌ disabled") . "\n";
}

echo "\n=== TEST EXEC php8.4 ===\n";
if (function_exists('exec')) {
    exec('php8.4 --version 2>&1', $out);
    echo implode("\n", $out);
} elseif (function_exists('passthru')) {
    passthru('php8.4 --version 2>&1');
} elseif (function_exists('shell_exec')) {
    echo shell_exec('php8.4 --version 2>&1');
} else {
    echo "Semua fungsi shell dinonaktifkan.";
}

echo "\n\n=== TEST EXEC whoami ===\n";
if (function_exists('exec')) {
    exec('whoami 2>&1', $out2);
    echo implode("\n", $out2);
} elseif (function_exists('passthru')) {
    passthru('whoami 2>&1');
}

echo "\n\n=== BASE PATH ===\n";
echo __DIR__ . "\n";
echo $_SERVER['DOCUMENT_ROOT'] . "\n";

echo '</pre>';
