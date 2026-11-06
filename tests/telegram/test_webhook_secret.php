<?php
// FILE: tests/telegram/test_webhook_secret.php
// Jalankan: php tests/telegram/test_webhook_secret.php
require __DIR__.'/../bootstrap.php';

use App\Http\Controllers\TelegramWebhookController as T;

$fail = false;
$check = function (string $name, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    echo ($ok ? 'PASS' : 'FAIL') . " — $name (got " . var_export($got, true) . ", exp " . var_export($exp, true) . ")\n";
    if (!$ok) $fail = true;
};

$s = 'abcdefghijklmnop1234';
$check('cocok -> true', T::secretValid($s, $s), true);
$check('salah -> false', T::secretValid('salah', $s), false);
$check('header null -> false', T::secretValid(null, $s), false);
$check('kosong vs kosong -> false', T::secretValid('', ''), false);
$check('server pendek -> false', T::secretValid('pendek', 'pendek'), false);

echo $fail ? "\nADA YANG GAGAL\n" : "\nSEMUA PASS\n";
exit($fail ? 1 : 0);
