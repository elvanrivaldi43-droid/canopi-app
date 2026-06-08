<?php
$key = $_GET['key'] ?? '';
if ($key !== 'canopi2026') die('Forbidden');

// Load Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

// Cek semua route yang terdaftar
$routes = app('router')->getRoutes();
echo '<pre style="background:#1a1a2e;color:lime;padding:20px;">';
echo "=== ROUTE TERDAFTAR ===\n\n";
foreach ($routes as $route) {
    $uri = $route->uri();
    if (str_contains($uri, 'registrasi')) {
        echo "✅ FOUND: " . implode('|', $route->methods()) . " → /" . $uri . "\n";
        echo "   Action: " . $route->getActionName() . "\n\n";
    }
}
echo "\n=== SELESAI ===";
echo '</pre>';