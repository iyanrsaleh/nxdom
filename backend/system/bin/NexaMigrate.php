<?php
declare(strict_types=1);
/**
 * NexaMigrate - NexaUI Migration Runner
 * Dipanggil oleh migrate.bat
 */
$basePath = dirname(__DIR__, 2);

// Load .env
$envFile = $basePath . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        $_ENV[$key] = $value;
    }
}

$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'nexa_db';
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'root';
$_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
$_ENV['DB_CHARSET'] = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$command = $argv[1] ?? 'run';

// Harus sebelum NexaMigrator (koneksi ke DB yang mungkin belum ada)
if ($command === 'createdb' || $command === '5') {
    $dbName = $argv[2] ?? null;
    if ($dbName === null || $dbName === '') {
        echo "\n  Nama database: ";
        $line = fgets(STDIN);
        $dbName = $line !== false ? trim($line) : '';
    }
    try {
        nexaMigrateCreateDatabaseAndEnv($basePath, $envFile, $dbName);
        echo "  [OK] Database `{$dbName}` siap (CREATE DATABASE IF NOT EXISTS).\n";
        echo "  [OK] DB_DATABASE di .env diatur ke: {$dbName}\n";
    } catch (Throwable $e) {
        echo '  [ERROR] ' . $e->getMessage() . "\n";
        exit(1);
    }
    exit(0);
}

require_once $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\System\Database\NexaMigrator;

$migrator = new NexaMigrator();

try {
    switch ($command) {
        case 'run':
        case 'migrate':
        case '1':
            echo "  Running migrations...\n";
            $executed = $migrator->run();
            if (empty($executed)) {
                echo "  [OK] Database is up to date.\n";
            } else {
                foreach ($executed as $m) {
                    echo "  [OK] {$m}\n";
                }
                echo "  Migrated " . count($executed) . " migration(s).\n";
            }
            break;

        case 'rollback':
        case '2':
            echo "  Rolling back last batch...\n";
            $rolled = $migrator->rollback();
            if (empty($rolled)) {
                echo "  [OK] Nothing to rollback.\n";
            } else {
                foreach ($rolled as $m) {
                    echo "  [OK] Rolled back: {$m}\n";
                }
                echo "  Rolled back " . count($rolled) . " migration(s).\n";
            }
            break;

        case 'status':
        case '3':
            echo "  Migration status:\n";
            $status = $migrator->status();
            if (empty($status)) {
                echo "  No migrations in database/migrations/\n";
            } else {
                foreach ($status as $s) {
                    $icon = $s['ran'] ? '[OK]' : '[--]';
                    $state = $s['ran'] ? 'Ran' : 'Pending';
                    echo "    {$icon} {$s['migration']} ({$state})\n";
                }
            }
            break;

        case 'create':
        case '4':
            $name = $argv[2] ?? null;
            if (!$name) {
                echo "  [ERROR] Usage: create MigrationName\n";
                echo "  Contoh: create CreateUsersTable\n";
                exit(1);
            }
            $path = $migrator->create($name);
            echo "  [OK] Created: " . basename($path) . "\n";
            echo "       Edit: {$path}\n";
            break;

        default:
            echo "  [ERROR] Perintah tidak dikenal: {$command}\n";
            echo "  Gunakan: run, rollback, status, create, createdb\n";
            exit(1);
    }
} catch (Throwable $e) {
    echo "  [ERROR] " . $e->getMessage() . "\n";
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}

/**
 * Buat database MySQL (tanpa memilih schema) dan set DB_DATABASE di .env
 */
function nexaMigrateCreateDatabaseAndEnv(string $basePath, string $envFile, string $dbName): void
{
    $dbName = trim($dbName);
    if ($dbName === '') {
        throw new InvalidArgumentException('Nama database wajib diisi. Contoh: nexa migrate createdb nexa_production');
    }
    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $dbName)) {
        throw new InvalidArgumentException('Nama database tidak valid. Gunakan huruf, angka, dan underscore (1–64 karakter).');
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = (int)($_ENV['DB_PORT'] ?? 3306);
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $safe = '`' . str_replace('`', '``', $dbName) . '`';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$safe} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if (!is_file($envFile)) {
        throw new RuntimeException('File .env tidak ditemukan: ' . $envFile);
    }
    if (!is_writable($envFile)) {
        throw new RuntimeException('File .env tidak dapat ditulis. Periksa izin file.');
    }

    $content = file_get_contents($envFile);
    if ($content === false) {
        throw new RuntimeException('Tidak dapat membaca .env');
    }

    $pattern = '/^DB_DATABASE=.*$/m';
    $line = 'DB_DATABASE=' . $dbName;
    if (preg_match($pattern, $content)) {
        $newContent = preg_replace($pattern, $line, $content, 1);
    } else {
        $newContent = rtrim($content) . "\n" . $line . "\n";
    }

    if (file_put_contents($envFile, $newContent) === false) {
        throw new RuntimeException('Tidak dapat menulis .env');
    }
}
