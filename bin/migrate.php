<?php

declare(strict_types=1);

use App\Config\Database;
use PDO;
use Throwable;

require dirname(__DIR__) . '/bootstrap/app.php';

$database = Database::connection();
$database->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(190) NOT NULL,
        executed_at DATETIME NOT NULL,
        UNIQUE KEY uq_migrations_name (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executed = $database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$executedLookup = array_fill_keys(array_map('strval', $executed), true);
$files = glob(dirname(__DIR__) . '/database/migrations/*.php') ?: [];
sort($files, SORT_STRING);

$ran = 0;

foreach ($files as $file) {
    $migration = require $file;

    if (!is_array($migration) || !isset($migration['name'], $migration['up'])) {
        fwrite(STDERR, 'Invalid migration file: ' . basename($file) . PHP_EOL);
        exit(1);
    }

    $name = (string) $migration['name'];
    if (isset($executedLookup[$name])) {
        fwrite(STDOUT, "SKIP {$name}" . PHP_EOL);
        continue;
    }

    fwrite(STDOUT, "RUN  {$name}" . PHP_EOL);

    try {
        // MySQL commits DDL statements implicitly. Migrations are therefore
        // written to be safely repeatable instead of wrapping DDL in a transaction.
        $migration['up']($database);
        $statement = $database->prepare(
            'INSERT INTO migrations (migration, executed_at) VALUES (:migration, UTC_TIMESTAMP())'
        );
        $statement->execute(['migration' => $name]);
        $ran++;
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAILED {$name}: {$exception->getMessage()}" . PHP_EOL);
        exit(1);
    }
}

fwrite(STDOUT, $ran === 0
    ? 'Database is already up to date.' . PHP_EOL
    : "Completed {$ran} migration(s)." . PHP_EOL);
