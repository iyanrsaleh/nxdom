<?php

/**
 * NexaTabelTrigger - Database Schema Builder DINAMIS
 * 
 * Class ini menyediakan builder pattern untuk:
 * - Modify kolom tabel secara dinamis
 * - Mengelola indexes dengan mudah
 * - Membuat triggers untuk tabel apapun
 * 
 * @author Nexa Framework
 * @version 2.0 - DINAMIS
 */
class NexaTabelTrigger
{
    private $connection;
    private $table;
    private $queries = [];
    private $errors = [];
    private $config = [];
    
    public function __construct($connection = null)
    {
        $this->connection = $connection ?: $this->getDefaultConnection();
        $this->config = $this->getDefaultConfig();
    }
    
    /**
     * Set tabel yang akan dimodifikasi
     */
    public function table($tableName)
    {
        $this->table = $tableName;
        return $this;
    }
    
    // ========================================
    // COLUMN METHODS - DINAMIS
    // ========================================
    
    /**
     * Modify kolom dengan parameter dinamis
     */
    public function modify($columnName, $options = [])
    {
        $definition = $this->buildColumnDefinition($options);
        $sql = "ALTER TABLE `{$this->table}` MODIFY COLUMN `{$columnName}` {$definition}";
        $this->queries[] = $sql;
        return $this;
    }
    
    /**
     * Tambah kolom baru dengan parameter dinamis
     */
    public function add($columnName, $options = [], $after = null)
    {
        $definition = $this->buildColumnDefinition($options);
        $sql = "ALTER TABLE `{$this->table}` ADD COLUMN `{$columnName}` {$definition}";
        if ($after) {
            $sql .= " AFTER `{$after}`";
        }
        $this->queries[] = $sql;
        return $this;
    }
    
    /**
     * Drop kolom dengan konfirmasi
     */
    public function drop($columnName, $confirm = false)
    {
        if (!$confirm) {
            throw new Exception("Untuk drop kolom, set parameter confirm = true");
        }
        $sql = "ALTER TABLE `{$this->table}` DROP COLUMN `{$columnName}`";
        $this->queries[] = $sql;
        return $this;
    }
    
    /**
     * Rename kolom dengan parameter dinamis
     */
    public function rename($oldName, $newName, $options = [])
    {
        $definition = $this->buildColumnDefinition($options);
        $sql = "ALTER TABLE `{$this->table}` CHANGE COLUMN `{$oldName}` `{$newName}` {$definition}";
        $this->queries[] = $sql;
        return $this;
    }
    
    /**
     * Build column definition dari options
     */
    private function buildColumnDefinition($options)
    {
        $type = $options['type'] ?? 'VARCHAR(255)';
        $definition = strtoupper($type);
        
        // Null/Not Null
        if (isset($options['nullable']) && $options['nullable'] === false) {
            $definition .= ' NOT NULL';
        } else {
            $definition .= ' NULL';
        }
        
        // Default value
        if (isset($options['default'])) {
            $default = $options['default'];
            if (is_string($default) && $default !== 'NULL' && $default !== 'CURRENT_TIMESTAMP') {
                $default = "'{$default}'";
            }
            $definition .= " DEFAULT {$default}";
        }
        
        // Auto increment
        if (isset($options['auto_increment']) && $options['auto_increment']) {
            $definition .= ' AUTO_INCREMENT';
        }
        
        // Character set
        if (isset($options['charset'])) {
            $definition .= " CHARACTER SET {$options['charset']}";
        }
        
        // Collation
        if (isset($options['collation'])) {
            $definition .= " COLLATE {$options['collation']}";
        }
        
        // Comment
        if (isset($options['comment'])) {
            $definition .= " COMMENT '{$options['comment']}'";
        }
        
        return $definition;
    }
    
    // ========================================
    // INDEX METHODS - DINAMIS & MUDAH
    // ========================================
    
    /**
     * Method universal untuk index - SANGAT DINAMIS
     */
    public function index($name, $columns = null, $type = 'INDEX')
    {
        // Jika name adalah array, anggap sebagai konfigurasi lengkap
        if (is_array($name)) {
            return $this->createIndexFromConfig($name);
        }
        
        // Jika columns null, gunakan name sebagai column
        if ($columns === null) {
            $columns = $name;
            $name = "idx_{$this->table}_{$name}";
        }
        
        return $this->addIndex($name, $columns, $type);
    }
    
    /**
     * Shortcut methods untuk berbagai jenis index
     */
    public function primary($columns)
    {
        return $this->addPrimaryKey($columns);
    }
    
    public function unique($name, $columns = null)
    {
        return $this->index($name, $columns, 'UNIQUE INDEX');
    }
    
    public function foreign($name, $column, $refTable, $refColumn = 'id', $options = [])
    {
        $onDelete = $options['on_delete'] ?? 'CASCADE';
        $onUpdate = $options['on_update'] ?? 'CASCADE';
        
        return $this->addForeignKey($name, $column, $refTable, $refColumn, $onDelete, $onUpdate);
    }
    
    /**
     * Buat index dari konfigurasi array
     */
    private function createIndexFromConfig($config)
    {
        foreach ($config as $indexConfig) {
            $name = $indexConfig['name'];
            $columns = $indexConfig['columns'];
            $type = $indexConfig['type'] ?? 'INDEX';
            
            $this->addIndex($name, $columns, $type);
        }
        return $this;
    }
    
    /**
     * Auto create index berdasarkan pattern kolom
     */
    public function autoIndex($patterns = [])
    {
        $defaultPatterns = [
            'id' => 'PRIMARY',
            '*_id' => 'INDEX',
            'email' => 'UNIQUE',
            'slug' => 'UNIQUE',
            'status' => 'INDEX',
            'created_at' => 'INDEX',
            'updated_at' => 'INDEX'
        ];
        
        $patterns = array_merge($defaultPatterns, $patterns);
        $columns = $this->getTableColumns();
        
        foreach ($columns as $column) {
            foreach ($patterns as $pattern => $indexType) {
                if ($this->matchPattern($column, $pattern)) {
                    $indexName = "idx_{$this->table}_{$column}";
                    
                    if ($indexType === 'PRIMARY') {
                        $this->primary($column);
                    } elseif ($indexType === 'UNIQUE') {
                        $this->unique($indexName, $column);
                    } else {
                        $this->index($indexName, $column);
                    }
                    break;
                }
            }
        }
        
        return $this;
    }
    
    // ========================================
    // TRIGGER METHODS - SUPER DINAMIS
    // ========================================
    
    /**
     * Buat trigger dengan konfigurasi dinamis
     */
    public function trigger($config)
    {
        $name = $config['name'];
        $timing = $config['timing'] ?? 'BEFORE';
        $event = $config['event'] ?? 'INSERT';
        $table = $config['table'] ?? $this->table;
        $body = $config['body'];
        
        return $this->createTrigger($name, $timing, $event, $table, $body);
    }
    
    /**
     * Auto sync antar tabel - DINAMIS untuk tabel apapun
     */
    public function autoSync($sourceTable, $sourceColumn, $targetColumn, $relationColumn = null)
    {
        // Jika relation column tidak disebutkan, cari otomatis
        if ($relationColumn === null) {
            $relationColumn = $sourceTable . '_id';
        }
        
        return $this->createSyncTrigger($sourceTable, $this->table, $sourceColumn, $targetColumn, $relationColumn);
    }
    
    /**
     * Buat audit log trigger - DINAMIS
     */
    public function auditLog($auditTable = null, $columns = ['*'])
    {
        if ($auditTable === null) {
            $auditTable = $this->table . '_audit';
        }
        
        $body = $this->buildAuditTriggerBody($auditTable, $columns);
        
        return $this->createTrigger(
            "tr_{$this->table}_audit",
            'AFTER',
            'UPDATE',
            $this->table,
            $body
        );
    }
    
    /**
     * Buat timestamps trigger - DINAMIS
     */
    public function timestamps($createdColumn = 'created_at', $updatedColumn = 'updated_at')
    {
        $insertBody = "SET NEW.{$createdColumn} = NOW(), NEW.{$updatedColumn} = NOW();";
        $updateBody = "SET NEW.{$updatedColumn} = NOW();";
        
        $this->createTrigger("tr_{$this->table}_timestamps_insert", 'BEFORE', 'INSERT', $this->table, $insertBody);
        $this->createTrigger("tr_{$this->table}_timestamps_update", 'BEFORE', 'UPDATE', $this->table, $updateBody);
        
        return $this;
    }
    
    // ========================================
    // ADVANCED METHODS - SUPER DINAMIS
    // ========================================
    
    /**
     * Setup lengkap dengan konfigurasi array
     */
    public function setup($config)
    {
        // Modify columns
        if (isset($config['modify'])) {
            foreach ($config['modify'] as $column => $options) {
                $this->modify($column, $options);
            }
        }
        
        // Add columns
        if (isset($config['add'])) {
            foreach ($config['add'] as $column => $options) {
                $after = $options['after'] ?? null;
                unset($options['after']);
                $this->add($column, $options, $after);
            }
        }
        
        // Drop columns
        if (isset($config['drop'])) {
            foreach ($config['drop'] as $column) {
                $this->drop($column, true);
            }
        }
        
        // Indexes
        if (isset($config['indexes'])) {
            foreach ($config['indexes'] as $indexConfig) {
                $this->index($indexConfig);
            }
        }
        
        // Triggers
        if (isset($config['triggers'])) {
            foreach ($config['triggers'] as $triggerConfig) {
                $this->trigger($triggerConfig);
            }
        }
        
        // Auto sync
        if (isset($config['sync'])) {
            foreach ($config['sync'] as $syncConfig) {
                $this->autoSync(
                    $syncConfig['source_table'],
                    $syncConfig['source_column'],
                    $syncConfig['target_column'],
                    $syncConfig['relation_column'] ?? null
                );
            }
        }
        
        return $this;
    }
    
    /**
     * Batch operations
     */
    public function batch($operations)
    {
        foreach ($operations as $operation) {
            $method = $operation['method'];
            $params = $operation['params'] ?? [];
            
            call_user_func_array([$this, $method], $params);
        }
        
        return $this;
    }
    
    /**
     * Conditional operations
     */
    public function when($condition, $callback)
    {
        if ($condition) {
            $callback($this);
        }
        
        return $this;
    }
    
    // ========================================
    // HELPER METHODS
    // ========================================
    
    private function getTableColumns()
    {
        $sql = "SHOW COLUMNS FROM `{$this->table}`";
        $result = $this->connection->query($sql);
        $columns = [];
        
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        return $columns;
    }
    
    private function matchPattern($column, $pattern)
    {
        if ($pattern === $column) {
            return true;
        }
        
        if (strpos($pattern, '*') !== false) {
            $regex = str_replace('*', '.*', $pattern);
            return preg_match("/^{$regex}$/", $column);
        }
        
        return false;
    }
    
    private function buildAuditTriggerBody($auditTable, $columns)
    {
        $columnList = '';
        $valueList = '';
        
        if ($columns === ['*']) {
            $tableColumns = $this->getTableColumns();
            foreach ($tableColumns as $col) {
                $columnList .= "`old_{$col}`, `new_{$col}`, ";
                $valueList .= "OLD.{$col}, NEW.{$col}, ";
            }
        } else {
            foreach ($columns as $col) {
                $columnList .= "`old_{$col}`, `new_{$col}`, ";
                $valueList .= "OLD.{$col}, NEW.{$col}, ";
            }
        }
        
        $columnList = rtrim($columnList, ', ');
        $valueList = rtrim($valueList, ', ');
        
        return "
            INSERT INTO `{$auditTable}` 
            (table_name, action, record_id, {$columnList}, changed_at)
            VALUES 
            ('{$this->table}', 'UPDATE', NEW.id, {$valueList}, NOW());
        ";
    }
    
    private function getDefaultConfig()
    {
        return [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB'
        ];
    }
    
    // ========================================
    // CORE SQL METHODS (PRIVATE)
    // ========================================
    
    private function modifyColumn($columnName, $definition)
    {
        $sql = "ALTER TABLE `{$this->table}` MODIFY COLUMN `{$columnName}` {$definition}";
        $this->queries[] = $sql;
        return $this;
    }
    
    private function addColumn($columnName, $definition, $after = null)
    {
        $sql = "ALTER TABLE `{$this->table}` ADD COLUMN `{$columnName}` {$definition}";
        if ($after) {
            $sql .= " AFTER `{$after}`";
        }
        $this->queries[] = $sql;
        return $this;
    }
    
    private function dropColumn($columnName)
    {
        $sql = "ALTER TABLE `{$this->table}` DROP COLUMN `{$columnName}`";
        $this->queries[] = $sql;
        return $this;
    }
    
    private function renameColumn($oldName, $newName, $definition)
    {
        $sql = "ALTER TABLE `{$this->table}` CHANGE COLUMN `{$oldName}` `{$newName}` {$definition}";
        $this->queries[] = $sql;
        return $this;
    }
    
    private function addPrimaryKey($columns)
    {
        $columns = is_array($columns) ? implode('`, `', $columns) : $columns;
        $sql = "ALTER TABLE `{$this->table}` ADD PRIMARY KEY (`{$columns}`)";
        $this->queries[] = $sql;
        return $this;
    }
    
    private function addIndex($indexName, $columns, $type = 'INDEX')
    {
        $columns = is_array($columns) ? implode('`, `', $columns) : $columns;
        $sql = "ALTER TABLE `{$this->table}` ADD {$type} `{$indexName}` (`{$columns}`)";
        $this->queries[] = $sql;
        return $this;
    }
    
    private function addUniqueIndex($indexName, $columns)
    {
        return $this->addIndex($indexName, $columns, 'UNIQUE INDEX');
    }
    
    private function addForeignKey($constraintName, $column, $refTable, $refColumn, $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
    {
        $sql = "ALTER TABLE `{$this->table}` ADD CONSTRAINT `{$constraintName}` 
                FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`) 
                ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
        $this->queries[] = $sql;
        return $this;
    }
    
    public function dropIndex($indexName)
    {
        $sql = "ALTER TABLE `{$this->table}` DROP INDEX `{$indexName}`";
        $this->queries[] = $sql;
        return $this;
    }
    
    public function dropForeignKey($constraintName)
    {
        $sql = "ALTER TABLE `{$this->table}` DROP FOREIGN KEY `{$constraintName}`";
        $this->queries[] = $sql;
        return $this;
    }
    
    /**
     * Buat trigger sinkronisasi DINAMIS untuk tabel apapun
     */
    private function createSyncTrigger($sourceTable, $targetTable, $sourceColumn, $targetColumn, $relationColumn, $sourceKeyColumn = 'id')
    {
        // Trigger untuk INSERT
        $insertTrigger = "
        DROP TRIGGER IF EXISTS tr_{$targetTable}_insert_sync_{$targetColumn};
        DELIMITER $$
        CREATE TRIGGER tr_{$targetTable}_insert_sync_{$targetColumn}
        BEFORE INSERT ON `{$targetTable}`
        FOR EACH ROW
        BEGIN
            DECLARE sync_value VARCHAR(500);
            
            IF NEW.{$relationColumn} IS NOT NULL THEN
                SELECT {$sourceColumn} INTO sync_value 
                FROM `{$sourceTable}` 
                WHERE {$sourceKeyColumn} = NEW.{$relationColumn};
                
                SET NEW.{$targetColumn} = sync_value;
            END IF;
        END$$
        DELIMITER ;";
        
        // Trigger untuk UPDATE
        $updateTrigger = "
        DROP TRIGGER IF EXISTS tr_{$targetTable}_update_sync_{$targetColumn};
        DELIMITER $$
        CREATE TRIGGER tr_{$targetTable}_update_sync_{$targetColumn}
        BEFORE UPDATE ON `{$targetTable}`
        FOR EACH ROW
        BEGIN
            DECLARE sync_value VARCHAR(500);
            
            IF NEW.{$relationColumn} IS NOT NULL AND (OLD.{$relationColumn} IS NULL OR NEW.{$relationColumn} != OLD.{$relationColumn}) THEN
                SELECT {$sourceColumn} INTO sync_value 
                FROM `{$sourceTable}` 
                WHERE {$sourceKeyColumn} = NEW.{$relationColumn};
                
                SET NEW.{$targetColumn} = sync_value;
            END IF;
        END$$
        DELIMITER ;";
        
        // Trigger untuk UPDATE source table
        $sourceTrigger = "
        DROP TRIGGER IF EXISTS tr_{$sourceTable}_update_sync_{$sourceColumn};
        DELIMITER $$
        CREATE TRIGGER tr_{$sourceTable}_update_sync_{$sourceColumn}
        AFTER UPDATE ON `{$sourceTable}`
        FOR EACH ROW
        BEGIN
            IF NEW.{$sourceColumn} != OLD.{$sourceColumn} THEN
                UPDATE `{$targetTable}` 
                SET {$targetColumn} = NEW.{$sourceColumn} 
                WHERE {$relationColumn} = NEW.{$sourceKeyColumn};
            END IF;
        END$$
        DELIMITER ;";
        
        $this->queries[] = $insertTrigger;
        $this->queries[] = $updateTrigger;
        $this->queries[] = $sourceTrigger;
        
        return $this;
    }
    
    private function createTrigger($triggerName, $timing, $event, $table, $body)
    {
        $sql = "
        DROP TRIGGER IF EXISTS {$triggerName};
        DELIMITER $$
        CREATE TRIGGER {$triggerName}
        {$timing} {$event} ON `{$table}`
        FOR EACH ROW
        BEGIN
            {$body}
        END$$
        DELIMITER ;";
        
        $this->queries[] = $sql;
        return $this;
    }
    
    public function dropTrigger($triggerName)
    {
        $sql = "DROP TRIGGER IF EXISTS {$triggerName}";
        $this->queries[] = $sql;
        return $this;
    }
    
    public function createSyncProcedure($procedureName, $targetTable, $sourceTable, $targetColumn, $sourceColumn, $relationColumn)
    {
        $sql = "
        DROP PROCEDURE IF EXISTS {$procedureName};
        DELIMITER $$
        CREATE PROCEDURE {$procedureName}()
        BEGIN
            UPDATE `{$targetTable}` t
            INNER JOIN `{$sourceTable}` s ON t.{$relationColumn} = s.id
            SET t.{$targetColumn} = s.{$sourceColumn}
            WHERE t.{$targetColumn} IS NULL OR t.{$targetColumn} != s.{$sourceColumn};
        END$$
        DELIMITER ;";
        
        $this->queries[] = $sql;
        return $this;
    }
    
    // ========================================
    // EXECUTION METHODS
    // ========================================
    
    public function execute()
    {
        $results = [];
        
        foreach ($this->queries as $query) {
            try {
                $result = $this->connection->query($query);
                $results[] = [
                    'query' => $query,
                    'status' => 'success',
                    'result' => $result
                ];
            } catch (Exception $e) {
                $this->errors[] = [
                    'query' => $query,
                    'error' => $e->getMessage()
                ];
                $results[] = [
                    'query' => $query,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    public function toSql()
    {
        return $this->queries;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
    
    public function reset()
    {
        $this->queries = [];
        $this->errors = [];
        $this->table = null;
        return $this;
    }
    
    private function getDefaultConnection()
    {
        global $db;
        return $db;
    }
    
    // ========================================
    // STATIC HELPER METHODS
    // ========================================
    
    /**
     * Quick setup untuk relasi tabel apapun
     */
    public static function quickSync($sourceTable, $targetTable, $sourceColumn, $targetColumn, $relationColumn = null)
    {
        $trigger = new self();
        
        if ($relationColumn === null) {
            $relationColumn = $sourceTable . '_id';
        }
        
        return $trigger
            ->table($targetTable)
            ->modify($targetColumn, ['type' => 'VARCHAR(500)', 'nullable' => true])
            ->index("idx_{$targetTable}_{$relationColumn}", $relationColumn)
            ->autoSync($sourceTable, $sourceColumn, $targetColumn, $relationColumn)
            ->createSyncProcedure(
                "sp_sync_{$targetTable}_{$targetColumn}",
                $targetTable,
                $sourceTable,
                $targetColumn,
                $sourceColumn,
                $relationColumn
            );
    }
    
    public function validateTable($tableName)
    {
        $sql = "SHOW TABLES LIKE '{$tableName}'";
        $result = $this->connection->query($sql);
        return $result->num_rows > 0;
    }
}
