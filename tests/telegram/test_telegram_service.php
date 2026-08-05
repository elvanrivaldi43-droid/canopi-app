<?php
// Jalankan: php tests/telegram/test_telegram_service.php
require __DIR__ . '/../../app/Services/TelegramService.php';

use App\Services\TelegramService;

$svc = new TelegramService();
$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$check('chat_id null -> skip, return false', $svc->kirim(null, 'tes'), false);
$check('chat_id string kosong -> skip, return false', $svc->kirim('', 'tes'), false);

if ($fail) { echo "\n=== ADA YANG GAGAL ===\n"; exit(1); }
echo "\n=== SEMUA TES LULUS ===\n";
