<?php
declare(strict_types=1);

namespace App\System\Database;

use App\System\Storage\NexaDb;
use PDO;

/**
 * NexaMigration - Base class untuk database migrations
 *
 * Setiap migration harus extend class ini dan implementasi method up() dan down().
 * Format nama file: YYYY_MM_DD_HHMMSS_nama_migration.php
 *
 * @package App\System\Database
 */
abstract class NexaMigration
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = NexaDb::getInstance()->getConnection();
    }

    /**
     * Jalankan migration (perubahan ke depan)
     */
    abstract public function up(): void;

    /**
     * Rollback migration (membatalkan perubahan)
     */
    abstract public function down(): void;

    /**
     * Eksekusi SQL query
     */
    protected function execute(string $sql, array $params = []): void
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Buat tabel dengan schema builder
     */
    protected function createTable(string $table, callable $callback): void
    {
        $schema = new NexaSchema($table, true);
        $callback($schema);
        $this->execute($schema->toSql());
    }

    /**
     * Hapus tabel
     */
    protected function dropTable(string $table): void
    {
        $table = $this->quoteIdentifier($table);
        $this->execute("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Tambah kolom ke tabel
     */
    protected function addColumn(string $table, string $column, string $definition): void
    {
        $table = $this->quoteIdentifier($table);
        $column = $this->quoteIdentifier($column);
        $this->execute("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    /**
     * Hapus kolom dari tabel
     */
    protected function dropColumn(string $table, string $column): void
    {
        $table = $this->quoteIdentifier($table);
        $column = $this->quoteIdentifier($column);
        $this->execute("ALTER TABLE {$table} DROP COLUMN {$column}");
    }

    /**
     * Quote identifier untuk MySQL
     */
    protected function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Cek apakah tabel sudah ada (mis. dibuat manual / impor SQL sebelum migrasi).
     */
    protected function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
        return $stmt !== false && (bool) $stmt->fetch();
    }

    /**
     * Cek apakah kolom ada pada tabel (MySQL / MariaDB).
     */
    protected function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = ' . $this->db->quote($table)
            . ' AND COLUMN_NAME = ' . $this->db->quote($column);
        $stmt = $this->db->query($sql);
        return $stmt !== false && (bool) $stmt->fetch();
    }
}
