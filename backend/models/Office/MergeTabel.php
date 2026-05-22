<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\System\NexaModel;

class MergeTabel extends NexaModel
{
    /**
     * Build ALTER TABLE statement based on variables from metadata
     *
     * @param array $data Data containing tableName and variables
     * @return array Result with success status
     */
    public function buildCreateTabel(array $data): array {
        try {
            // Validasi data yang diperlukan
            if (!isset($data['tableName']) || !isset($data['variables'])) {
                throw new \InvalidArgumentException('Missing required data: tableName or variables');
            }
            
            $tableName = $data['tableName'];
            $variables = $data['variables'];
            
            // Validasi
            if (empty($tableName) || empty($variables) || !is_array($variables)) {
                throw new \InvalidArgumentException('tableName cannot be empty and variables must be a valid array');
            }
            
            // Cek apakah tabel ada
            $checkTableSql = "SHOW TABLES LIKE '{$tableName}'";
            $tableExists = $this->raw($checkTableSql);
            
            if (empty($tableExists)) {
                throw new \InvalidArgumentException("Table '{$tableName}' does not exist");
            }
            
            // Ambil struktur tabel saat ini
            $describeTableSql = "DESCRIBE `{$tableName}`";
            $currentStructure = $this->raw($describeTableSql);
            
            // Buat mapping kolom yang sudah ada
            $existingColumns = [];
            foreach ($currentStructure as $column) {
                $existingColumns[$column['Field']] = $column;
            }
            
            $alterStatements = [];
            
            // Loop through variables dan bandingkan dengan struktur yang ada
            foreach ($variables as $fieldName => $fieldProps) {
                $type = strtoupper($fieldProps['type']);
                $length = $fieldProps['length'] ?? null;
                $default = $fieldProps['default'] ?? null;
                $nullable = $fieldProps['nullable'] ?? 'YES';
                $keyType = $fieldProps['key_type'] ?? '';
                $extra = $fieldProps['extra'] ?? '';
                
                // Build column definition
                $columnDef = "`{$fieldName}` {$type}";
                
                // Handle ENUM dan SET khusus
                if ($type === 'ENUM' || $type === 'SET') {
                    // Untuk ENUM/SET, ambil dari existing structure jika ada
                    if (isset($existingColumns[$fieldName])) {
                        $existingType = $existingColumns[$fieldName]['Type'];
                        // Extract values dari existing type, misal: enum('1','2') atau set('a','b')
                        if (preg_match('/^(enum|set)\((.*)\)$/i', $existingType, $matches)) {
                            $columnDef = "`{$fieldName}` {$type}({$matches[2]})";
                        } else {
                            // Default ENUM('1') jika tidak bisa extract
                            $columnDef = "`{$fieldName}` {$type}('1')";
                        }
                    } else {
                        // Default untuk kolom baru
                        $columnDef = "`{$fieldName}` {$type}('1')";
                    }
                } else {
                    // Tipe yang membutuhkan length
                    $typesWithLength = ['VARCHAR', 'CHAR', 'VARBINARY', 'BINARY', 'DECIMAL', 'NUMERIC', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT'];
                    $typesWithoutLength = ['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'JSON', 'DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'YEAR'];
                    
                    if ($length && in_array($type, $typesWithLength) && !in_array($type, $typesWithoutLength)) {
                        $columnDef .= "({$length})";
                    }
                }
                
                // Nullable
                if ($nullable === 'NO' || $keyType === 'PRI' || strpos($extra, 'auto_increment') !== false) {
                    $columnDef .= " NOT NULL";
                } else {
                    $columnDef .= " NULL";
                }
                
                // Auto increment
                if (strpos($extra, 'auto_increment') !== false) {
                    $columnDef .= " AUTO_INCREMENT";
                }
                
                // Default value
                if (strpos($extra, 'auto_increment') === false) {
                    if ($default === 'CURRENT_TIMESTAMP' || strpos($extra, 'CURRENT_TIMESTAMP') !== false) {
                        $columnDef .= " DEFAULT CURRENT_TIMESTAMP";
                    } elseif ($default === null && $nullable === 'YES') {
                        // Hanya tambahkan DEFAULT NULL jika nullable
                        $columnDef .= " DEFAULT NULL";
                    } elseif ($default !== null && $default !== '' && $default !== 'NONE') {
                        $escapedDefault = $this->escapeValue($default);
                        $columnDef .= " DEFAULT {$escapedDefault}";
                    }
                    // Jika NOT NULL dan default null, jangan tambahkan DEFAULT (MySQL akan error)
                }
                
                // On update CURRENT_TIMESTAMP
                if (strpos($extra, 'on update CURRENT_TIMESTAMP') !== false) {
                    $columnDef .= " ON UPDATE CURRENT_TIMESTAMP";
                }
                
                // Cek apakah kolom sudah ada
                if (isset($existingColumns[$fieldName])) {
                    // Kolom sudah ada, gunakan MODIFY
                    $alterStatements[] = "MODIFY COLUMN {$columnDef}";
                } else {
                    // Kolom belum ada, gunakan ADD
                    $alterStatements[] = "ADD COLUMN {$columnDef}";
                }
            }
            
            // Cari kolom yang ada di database tapi tidak ada di variables (untuk di-DROP)
            foreach ($existingColumns as $existingFieldName => $existingColumn) {
                // Skip kolom yang masih ada di variables
                if (isset($variables[$existingFieldName])) {
                    continue;
                }
                
                // DROP kolom yang tidak ada lagi di variables
                $alterStatements[] = "DROP COLUMN `{$existingFieldName}`";
            }
            
            if (empty($alterStatements)) {
                return [
                    'success' => true,
                    'results' => [
                        'tableName' => $tableName,
                        'message' => 'No changes needed'
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            // Build ALTER TABLE statement
            $alterTableSql = "ALTER TABLE `{$tableName}`\n  " . implode(",\n  ", $alterStatements);
            
            // Execute ALTER TABLE
            $this->raw($alterTableSql);
            
            // Ambil struktur tabel setelah diubah
            $tableInfo = $this->raw($describeTableSql);
            
            return [
                'success' => true,
                'results' => [
                    'tableName' => $tableName,
                    'sql' => $alterTableSql,
                    'tableInfo' => $tableInfo,
                    'alterations' => count($alterStatements),
                    'message' => "Table '{$tableName}' has been altered successfully"
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


