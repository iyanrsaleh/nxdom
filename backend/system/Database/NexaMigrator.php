<?php
declare(strict_types=1);

namespace App\System\Database;

use App\System\Storage\NexaDb;
use PDO;
use Exception;

/**
 * NexaMigrator - Migration runner untuk NexaUI Framework
 *
 * Menjalankan migrations dari folder database/migrations/
 * Menyimpan riwayat di tabel nexa_migrations
 *
 * Usage:
 *   php migrate.php           - Jalankan semua migration yang belum dijalankan
 *   php migrate.php rollback  - Rollback migration terakhir
 *   php migrate.php status    - Lihat status migrations
 *   php migrate.php create NamaMigration - Buat file migration baru
 *
 * @package App\System\Database
 */
class NexaMigrator
{
    private PDO $db;
    private string $migrationsPath;
    private string $table = 'nexa_migrations';

    public function __construct()
    {
        $this->db = NexaDb::getInstance()->getConnection();
        $this->migrationsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Pastikan tabel migrations ada
     */
    public function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT UNSIGNED NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `migration_unique` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
    }

    /**
     * Dapatkan daftar migration yang sudah dijalankan
     */
    public function getRanMigrations(): array
    {
        $this->ensureMigrationsTable();
        $stmt = $this->db->query("SELECT migration FROM `{$this->table}` ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Dapatkan batch number berikutnya
     */
    public function getNextBatch(): int
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `{$this->table}`");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Dapatkan daftar file migration yang tersedia
     */
    public function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php');
        $migrations = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+$/', $name)) {
                $migrations[$name] = $file;
            }
        }
        ksort($migrations);
        return $migrations;
    }

    /**
     * Jalankan migrations yang belum dijalankan
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $batch = $this->getNextBatch();
        $executed = [];

        foreach ($files as $name => $path) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $this->runMigration($name, $path, $batch);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Jalankan satu migration
     */
    private function runMigration(string $name, string $path, int $batch): void
    {
        $instance = $this->resolveMigration($name, $path);
        $instance->up();
        $this->recordMigration($name, $batch);
    }

    /**
     * Rollback migration terakhir (satu batch)
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();
        $stmt = $this->db->query("SELECT MAX(batch) FROM `{$this->table}`");
        $batch = (int) $stmt->fetchColumn();
        if ($batch === 0) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT migration FROM `{$this->table}` WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$batch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $rolledBack = [];

        foreach ($migrations as $name) {
            $path = $this->migrationsPath . DIRECTORY_SEPARATOR . $name . '.php';
            if (file_exists($path)) {
                $instance = $this->resolveMigration($name, $path);
                $instance->down();
                $this->deleteMigration($name);
                $rolledBack[] = $name;
            }
        }

        return $rolledBack;
    }

    /**
     * Resolve migration class dari file
     */
    private function resolveMigration(string $name, string $path): NexaMigration
    {
        require_once $path;
        $className = $this->getMigrationClass($name);
        if (!class_exists($className)) {
            throw new Exception("Migration class {$className} not found in {$path}");
        }
        return new $className();
    }

    /**
     * Convert nama file ke nama class (CamelCase)
     * Format: 2024_03_20_120000_create_users_table -> CreateUsersTable
     */
    private function getMigrationClass(string $name): string
    {
        $parts = explode('_', $name);
        $parts = array_slice($parts, 4); // skip YYYY_MM_DD_HHMMSS
        $className = implode('', array_map('ucfirst', $parts));
        return $className;
    }

    /**
     * Catat migration yang sudah dijalankan
     */
    private function recordMigration(string $name, int $batch): void
    {
        $stmt = $this->db->prepare("INSERT INTO `{$this->table}` (migration, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);
    }

    /**
     * Hapus record migration (untuk rollback)
     */
    private function deleteMigration(string $name): void
    {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE migration = ?");
        $stmt->execute([$name]);
    }

    /**
     * Status semua migrations
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $status = [];

        foreach ($files as $name => $path) {
            $status[] = [
                'migration' => $name,
                'ran' => in_array($name, $ran, true),
            ];
        }

        return $status;
    }

    /**
     * Buat file migration baru
     */
    public function create(string $name): string
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $fileName = "{$timestamp}_{$safeName}.php";
        $filePath = $this->migrationsPath . DIRECTORY_SEPARATOR . $fileName;

        $className = implode('', array_map('ucfirst', explode('_', $safeName)));
        $content = $this->getMigrationStub($className);

        file_put_contents($filePath, $content);
        return $filePath;
    }

    /**
     * Stub mengikuti standar database/beasiswa.sql
     */
    private function getMigrationStub(string $className): string
    {
        $tableName = strtolower(preg_replace('/^Create|Table$/', '', $className)) ?: 'example_table';
        return <<<PHP
<?php
declare(strict_types=1);

use App\System\Database\NexaMigration;
use App\System\Database\NexaSchema;

/**
 * Standar Beasiswa: Referensi database/beasiswa.sql
 */
class {$className} extends NexaMigration
{
    public function up(): void
    {
        \$this->createTable('{$tableName}', function (NexaSchema \$table) {
            \$table->column('id', 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
            \$table->column('userid', 'VARCHAR(11) DEFAULT NULL');
            \$table->column('nama', 'VARCHAR(250) DEFAULT NULL');
            \$table->column('title', 'VARCHAR(100) DEFAULT NULL');
            \$table->column('status', 'VARCHAR(25) DEFAULT NULL');
            \$table->column('row', "ENUM('1') NOT NULL");
            \$table->column('updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            \$table->column('created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        });
    }

    public function down(): void
    {
        \$this->dropTable('{$tableName}');
    }
}

PHP;
    }
}
