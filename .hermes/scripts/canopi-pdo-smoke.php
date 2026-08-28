<?php

declare(strict_types=1);

$required = [
    'APP_ENV' => 'testing',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3307',
    'DB_DATABASE' => 'canopi_test',
    'DB_USERNAME' => 'canopi_test',
];

foreach ($required as $name => $expected) {
    $actual = getenv($name);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$name} bukan target test yang diizinkan\n");
        exit(1);
    }
}

$password = getenv('DB_PASSWORD');
if (!is_string($password) || $password === '') {
    fwrite(STDERR, "FAIL: DB_PASSWORD test kosong\n");
    exit(1);
}

$table = 'canopi_smoke_guard';
$dsn = 'mysql:host=127.0.0.1;port=3307;dbname=canopi_test;charset=utf8mb4';

try {
    $pdo = new PDO($dsn, 'canopi_test', $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("DROP TABLE IF EXISTS {$table}");
    $pdo->exec("CREATE TABLE {$table} (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, nama VARCHAR(50) NOT NULL) ENGINE=InnoDB");
    $statement = $pdo->prepare("INSERT INTO {$table} (nama) VALUES (?)");
    $statement->execute(['DATA PALSU']);
    $value = $pdo->query("SELECT nama FROM {$table} WHERE id = 1")->fetchColumn();
    if ($value !== 'DATA PALSU') {
        throw new RuntimeException('read-back data palsu tidak cocok');
    }
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $pdo->exec("DROP TABLE {$table}");
    fwrite(STDOUT, "PASS: PDO MariaDB disposable; server={$version}\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        } catch (Throwable) {
        }
    }
    fwrite(STDERR, 'FAIL: PDO smoke — ' . $error->getMessage() . "\n");
    exit(1);
}
