<?php
// Jalankan: php tests/telegram/test_webhook_parser.php
require __DIR__ . '/../../app/Http/Controllers/Controller.php';
require __DIR__ . '/../../app/Http/Controllers/TelegramWebhookController.php';

use App\Http\Controllers\TelegramWebhookController;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$check('/start dengan token valid', TelegramWebhookController::parseStartToken('/start abc123XYZ'), 'abc123XYZ');
$check('/start tanpa token', TelegramWebhookController::parseStartToken('/start'), null);
$check('pesan bukan /start', TelegramWebhookController::parseStartToken('halo bot'), null);
$check('/start dengan spasi ekstra', TelegramWebhookController::parseStartToken('  /start   abc123XYZ  '), 'abc123XYZ');

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
