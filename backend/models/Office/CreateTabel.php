<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\System\NexaModel;

class CreateTabel extends NexaModel
{
    /**
     * Batch buildtabelViewe records with foreign-aware behavior similar to Insert/Update.
     *
     * Params items support:
     * - key: int (required)
     * - id: int (preferred for direct buildtabelViewe of that row)
     * - buildtabelViewe: array optional payload (ignored for now)
     *
     * Foreign items:
     * - key: int (target table key)
     * - failed: string[] where the first element is treated as FK field
     */
    public function buildCreateTabel(array $data): array {
        try {
            // Validasi data yang diperlukan
            if (!isset($data['tabelName']) || !isset($data['tableStructure'])) {
                throw new \InvalidArgumentException('Missing required data: tabelName or tableStructure');
            }
            
            $tabelName = $data['tabelName'];
            $tableStructure = $data['tableStructure'];
            
            // Validasi array tidak kosong
            if (empty($tabelName) || empty($tableStructure) || !is_array($tableStructure)) {
                throw new \InvalidArgumentException('tabelName cannot be empty and tableStructure must be a valid array');
            }
            
            // Build CREATE TABLE statement
            $columns = [];
            $primaryKey = 'id'; // Default primary key adalah id
            
            // Tambahkan field default yang wajib ada di setiap tabel
            $defaultColumns = [
                "`id` INT(11) NOT NULL AUTO_INCREMENT",
                "`userid` VARCHAR(11) DEFAULT NULL",
                "`row` ENUM('1') NOT NULL",
                "`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
                "`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
            ];
            
            // Masukkan default columns ke array columns
            $columns = $defaultColumns;
            
            // Lanjutkan dengan field dari user
            foreach ($tableStructure as $field) {
                // Setiap field adalah object dengan key = nama field
                // Format: {"namafile": {"type": "VARCHAR", "length": 25, "default": "NULL"}}
                foreach ($field as $fieldName => $fieldProps) {
                    $type = strtoupper($fieldProps['type'] ?? 'VARCHAR');
                    $length = $fieldProps['length'] ?? null;
                    
                    // Untuk tipe TEXT/BLOB dan sejenisnya, set length ke null
                    $typesWithoutLength = ['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'JSON'];
                    if (in_array($type, $typesWithoutLength)) {
                        $length = null;
                    }
                    $default = $fieldProps['default'] ?? 'NULL';
                    $nullable = $fieldProps['nullable'] ?? true; // Default nullable
                    $autoIncrement = $fieldProps['autoIncrement'] ?? false;
                    $isPrimary = $fieldProps['primary'] ?? false;
                    $isUnique = $fieldProps['unique'] ?? false;
                    
                    // Build column definition
                    $columnDef = "`{$fieldName}` {$type}";
                    
                    // Tambahkan length untuk tipe yang membutuhkan
                    // TEXT, BLOB, dan sejenisnya tidak boleh memiliki length
                    $typesWithLength = ['VARCHAR', 'CHAR', 'VARBINARY', 'BINARY', 'DECIMAL', 'NUMERIC', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'FLOAT', 'DOUBLE'];
                    $typesWithoutLength = ['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'JSON', 'DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'YEAR'];
                    
                    if ($length && in_array($type, $typesWithLength) && !in_array($type, $typesWithoutLength)) {
                        $columnDef .= "({$length})";
                    }
                    
                    // Nullable
                    if (!$nullable || $isPrimary || $autoIncrement) {
                        $columnDef .= " NOT NULL";
                    } else {
                        $columnDef .= " NULL";
                    }
                    
                    // Auto increment
                    if ($autoIncrement) {
                        $columnDef .= " AUTO_INCREMENT";
                        $primaryKey = $fieldName; // Auto increment biasanya primary key
                    }
                    
                    // Default value
                    if (!$autoIncrement && $default !== 'NONE' && $default !== '') {
                        if ($default === 'NULL') {
                            $columnDef .= " DEFAULT NULL";
                        } elseif ($default === 'CURRENT_TIMESTAMP') {
                            $columnDef .= " DEFAULT CURRENT_TIMESTAMP";
                        } else {
                            // Escape default value
                            $escapedDefault = $this->escapeValue($default);
                            $columnDef .= " DEFAULT {$escapedDefault}";
                        }
                    }
                    
                    // Unique constraint
                    if ($isUnique && !$isPrimary) {
                        $columnDef .= " UNIQUE";
                    }
                    
                    $columns[] = $columnDef;
                    
                    // Set primary key jika ditandai
                    if ($isPrimary) {
                        $primaryKey = $fieldName;
                    }
                }
            }
            
            // Build primary key constraint (id sudah pasti primary key)
            $columns[] = "PRIMARY KEY (`id`)";
            
            // Build CREATE TABLE statement
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tabelName}` (\n  ";
            $createTableSql .= implode(",\n  ", $columns);
            $createTableSql .= "\n)";
            
            // Tambahkan engine dan charset
            $engine = $data['engine'] ?? 'InnoDB';
            $charset = $data['charset'] ?? 'utf8mb4';
            
            $createTableSql .= " ENGINE={$engine} DEFAULT CHARSET={$charset}";
            
            // Cek apakah tabel sudah ada
            $checkTableSql = "SHOW TABLES LIKE '{$tabelName}'";
            $tableExists = $this->raw($checkTableSql);
            
            if (!empty($tableExists)) {
                // Tabel sudah ada, jangan execute CREATE TABLE
                // Ambil struktur tabel yang ada
                $describeTableSql = "DESCRIBE `{$tabelName}`";
                $tableInfo = $this->raw($describeTableSql);
                
                return [
                    'success' => true,
                    'results' => [
                        'tabelName' => $tabelName,
                        'sql' => 'TABLE ALREADY EXISTS',
                        'tableInfo' => $tableInfo,
                        'message' => "Table '{$tabelName}' already exists. Use ALTER TABLE to modify structure."
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            // Execute CREATE TABLE jika tabel belum ada
            $this->raw($createTableSql);
            
            // Ambil struktur tabel yang baru dibuat untuk konfirmasi
            $describeTableSql = "DESCRIBE `{$tabelName}`";
            $tableInfo = $this->raw($describeTableSql);
            
            return [
                'success' => true,
                'results' => [
                    'tabelName' => $tabelName,
                    'sql' => $createTableSql,
                    'tableInfo' => $tableInfo,
                    'message' => "Table '{$tabelName}' has been created successfully"
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Alter existing table structure
     * 
     * @param array $data Data containing table name and modifications
     * @return array Result with success status
     */
    public function alterCreateTabel(array $data): array {
        try {
            // Validasi data
            if (!isset($data['tabelName'])) {
                throw new \InvalidArgumentException('Missing required data: tabelName');
            }
            
            $tabelName = $data['tabelName'];
            $addColumns = $data['addColumns'] ?? [];
            $dropColumns = $data['dropColumns'] ?? [];
            $modifyColumns = $data['modifyColumns'] ?? [];
            
            $alterStatements = [];
            
            // Add new columns
            foreach ($addColumns as $field) {
                foreach ($field as $fieldName => $fieldProps) {
                    $type = strtoupper($fieldProps['type'] ?? 'VARCHAR');
                    $length = $fieldProps['length'] ?? null;
                    $default = $fieldProps['default'] ?? 'NULL';
                    $nullable = $fieldProps['nullable'] ?? true;
                    
                    $columnDef = "`{$fieldName}` {$type}";
                    
                    if ($length && in_array($type, ['VARCHAR', 'CHAR', 'VARBINARY', 'BINARY', 'DECIMAL', 'NUMERIC'])) {
                        $columnDef .= "({$length})";
                    }
                    
                    $columnDef .= $nullable ? " NULL" : " NOT NULL";
                    
                    if ($default !== 'NONE' && $default !== '') {
                        if ($default === 'NULL') {
                            $columnDef .= " DEFAULT NULL";
                        } elseif ($default === 'CURRENT_TIMESTAMP') {
                            $columnDef .= " DEFAULT CURRENT_TIMESTAMP";
                        } else {
                            $escapedDefault = $this->escapeValue($default);
                            $columnDef .= " DEFAULT {$escapedDefault}";
                        }
                    }
                    
                    $alterStatements[] = "ADD COLUMN {$columnDef}";
                }
            }
            
            // Drop columns
            foreach ($dropColumns as $fieldName) {
                $alterStatements[] = "DROP COLUMN `{$fieldName}`";
            }
            
            // Modify columns
            foreach ($modifyColumns as $field) {
                foreach ($field as $fieldName => $fieldProps) {
                    $type = strtoupper($fieldProps['type'] ?? 'VARCHAR');
                    $length = $fieldProps['length'] ?? null;
                    $default = $fieldProps['default'] ?? 'NULL';
                    $nullable = $fieldProps['nullable'] ?? true;
                    
                    $columnDef = "`{$fieldName}` {$type}";
                    
                    if ($length && in_array($type, ['VARCHAR', 'CHAR', 'VARBINARY', 'BINARY', 'DECIMAL', 'NUMERIC'])) {
                        $columnDef .= "({$length})";
                    }
                    
                    $columnDef .= $nullable ? " NULL" : " NOT NULL";
                    
                    if ($default !== 'NONE' && $default !== '') {
                        if ($default === 'NULL') {
                            $columnDef .= " DEFAULT NULL";
                        } elseif ($default === 'CURRENT_TIMESTAMP') {
                            $columnDef .= " DEFAULT CURRENT_TIMESTAMP";
                        } else {
                            $escapedDefault = $this->escapeValue($default);
                            $columnDef .= " DEFAULT {$escapedDefault}";
                        }
                    }
                    
                    $alterStatements[] = "MODIFY COLUMN {$columnDef}";
                }
            }
            
            if (empty($alterStatements)) {
                throw new \InvalidArgumentException('No alterations specified');
            }
            
            // Build ALTER TABLE statement
            $alterTableSql = "ALTER TABLE `{$tabelName}`\n  " . implode(",\n  ", $alterStatements);
            
            // Execute ALTER TABLE
            $this->raw($alterTableSql);
            
            // Ambil struktur tabel setelah diubah
            $describeTableSql = "DESCRIBE `{$tabelName}`";
            $tableInfo = $this->raw($describeTableSql);
            
            return [
                'success' => true,
                'results' => [
                    'tabelName' => $tabelName,
                    'sql' => $alterTableSql,
                    'tableInfo' => $tableInfo,
                    'message' => "Table '{$tabelName}' has been altered successfully"
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Drop table
     * 
     * @param array $data Data containing table name
     * @return array Result with success status
     */
    public function dropCreateTabel(array $data): array {
        try {
            if (!isset($data['tabelName'])) {
                throw new \InvalidArgumentException('Missing required data: tabelName');
            }
            
            $tabelName = $data['tabelName'];
            $dropTableSql = "DROP TABLE IF EXISTS `{$tabelName}`";
            
            // Execute DROP TABLE
            $this->raw($dropTableSql);
            
            return [
                'success' => true,
                'results' => [
                    'tabelName' => $tabelName,
                    'sql' => $dropTableSql,
                    'message' => "Table '{$tabelName}' has been dropped successfully"
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
                'results' => $data,
                'timestamp' => date('Y-m-d H:i:s')
        ];
      }
   }
    /**
     * Escape value for SQL injection prevention
     */
    private function escapeValue($value): string {
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        return "'" . addslashes((string)$value) . "'";
    }
}


