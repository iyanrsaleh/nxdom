<?php
declare(strict_types=1);

namespace App\System\Database;

/**
 * NexaSchema - Fluent schema builder untuk migrations
 *
 * Mirip Laravel Schema Builder. Digunakan dalam callback createTable().
 *
 * @package App\System\Database
 */
class NexaSchema
{
    private string $table;
    private bool $create;
    private array $columns = [];
    private string $engine = 'InnoDB';
    private string $charset = 'utf8mb4';
    private string $collation = 'utf8mb4_unicode_ci';

    public function __construct(string $table, bool $create = true)
    {
        $this->table = $table;
        $this->create = $create;
    }

    /**
     * Kolom bigint auto increment (primary key)
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = "`{$name}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Kolom string/varchar
     */
    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "`{$name}` VARCHAR({$length}) NOT NULL";
        return $this;
    }

    /**
     * Kolom string nullable
     */
    public function stringNullable(string $name, int $length = 255): self
    {
        $this->columns[] = "`{$name}` VARCHAR({$length}) NULL DEFAULT NULL";
        return $this;
    }

    /**
     * Kolom text
     */
    public function text(string $name): self
    {
        $this->columns[] = "`{$name}` TEXT NULL";
        return $this;
    }

    /**
     * Kolom integer
     */
    public function integer(string $name, bool $unsigned = false): self
    {
        $unsigned = $unsigned ? ' UNSIGNED' : '';
        $this->columns[] = "`{$name}` INT{$unsigned} NOT NULL";
        return $this;
    }

    /**
     * Kolom integer nullable
     */
    public function integerNullable(string $name, bool $unsigned = false): self
    {
        $unsigned = $unsigned ? ' UNSIGNED' : '';
        $this->columns[] = "`{$name}` INT{$unsigned} NULL";
        return $this;
    }

    /**
     * Kolom bigint
     */
    public function bigInteger(string $name, bool $unsigned = false): self
    {
        $unsigned = $unsigned ? ' UNSIGNED' : '';
        $this->columns[] = "`{$name}` BIGINT{$unsigned} NOT NULL";
        return $this;
    }

    /**
     * Kolom boolean/tinyint
     */
    public function boolean(string $name): self
    {
        $this->columns[] = "`{$name}` TINYINT(1) NOT NULL DEFAULT 0";
        return $this;
    }

    /**
     * Kolom decimal
     */
    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        $this->columns[] = "`{$name}` DECIMAL({$precision},{$scale}) NOT NULL";
        return $this;
    }

    /**
     * Kolom timestamp (created_at, updated_at)
     */
    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP NULL DEFAULT NULL";
        $this->columns[] = "`updated_at` TIMESTAMP NULL DEFAULT NULL";
        return $this;
    }

    /**
     * Kolom timestamp nullable
     */
    public function timestamp(string $name, bool $nullable = true): self
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`{$name}` TIMESTAMP {$null} DEFAULT NULL";
        return $this;
    }

    /**
     * Kolom dengan definisi custom
     */
    public function column(string $name, string $definition): self
    {
        $this->columns[] = "`{$name}` {$definition}";
        return $this;
    }

    /**
     * Index unique
     */
    public function unique(string $column): self
    {
        $this->columns[] = "UNIQUE KEY `{$this->table}_{$column}_unique` (`{$column}`)";
        return $this;
    }

    /**
     * Index
     */
    public function index(string $column): self
    {
        $this->columns[] = "KEY `{$this->table}_{$column}_index` (`{$column}`)";
        return $this;
    }

    /**
     * Foreign key (simplified)
     */
    public function foreign(string $column, string $references, string $on = 'id'): self
    {
        $fkName = "{$this->table}_{$column}_foreign";
        $this->columns[] = "CONSTRAINT `{$fkName}` FOREIGN KEY (`{$column}`) REFERENCES `{$references}` (`{$on}`) ON DELETE CASCADE";
        return $this;
    }

    /**
     * Generate SQL CREATE TABLE
     */
    public function toSql(): string
    {
        $table = '`' . str_replace('`', '``', $this->table) . '`';
        $columns = implode(",\n  ", $this->columns);
        return sprintf(
            "CREATE TABLE %s (\n  %s\n) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s",
            $table,
            $columns,
            $this->engine,
            $this->charset,
            $this->collation
        );
    }
}
