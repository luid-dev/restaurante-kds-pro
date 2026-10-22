<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Base de datos aislada de la suite
|--------------------------------------------------------------------------
|
| RefreshDatabase ejecuta migrate:fresh sobre la conexión por defecto. Antes de
| arrancar PHPUnit se verifica que el destino sea una base *_test y se crea si
| no existe (PostgreSQL: JSONB, CHECK, índices parciales y triggers no corren en
| SQLite). Así ninguna corrida vuelve a vaciar la base de desarrollo.
|
*/

(static function (): void {
    $root = dirname(__DIR__);
    $file = is_file($root.'/.env.testing') ? '.env.testing' : '.env';
    $values = is_file($root.'/'.$file) ? Dotenv::parse((string) file_get_contents($root.'/'.$file)) : [];

    $read = static function (string $key, string $default) use ($values): string {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : ($values[$key] ?? $default);
    };

    $database = $read('DB_DATABASE', '');

    if (preg_match('/^[a-z0-9_]+_test$/', $database) !== 1) {
        fwrite(STDERR, "Suite detenida: DB_DATABASE debe terminar en _test (actual: \"{$database}\").\n");
        exit(1);
    }

    if ($read('DB_CONNECTION', 'pgsql') !== 'pgsql') {
        return;
    }

    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=postgres', $read('DB_HOST', '127.0.0.1'), $read('DB_PORT', '5432')),
            $read('DB_USERNAME', 'postgres'),
            $read('DB_PASSWORD', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $exists->execute([$database]);

        if ($exists->fetchColumn() === false) {
            $pdo->exec(sprintf('CREATE DATABASE "%s"', $database));
            fwrite(STDERR, "Base de pruebas {$database} creada.\n");
        }
    } catch (PDOException $exception) {
        // Sin servidor las pruebas fallan con el error de conexión real de Laravel.
        fwrite(STDERR, "No se pudo verificar la base {$database}: {$exception->getMessage()}\n");
    }
})();
