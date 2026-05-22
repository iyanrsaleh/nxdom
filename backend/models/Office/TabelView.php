<?php
declare(strict_types=1);

namespace App\Models\Office;

use App\System\NexaModel;

class TabelView extends NexaModel
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
    public function buildTabelView(array $data): array {
        try {
            // Ambil view name dari createView atau label
            $viewName = $data['createView'] ?? $data['label'] ?? 'view_' . time();
            
            // Cek apakah ada query custom dari user
            $customQuery = null;
            if (isset($data['query']) && is_array($data['query'])) {
                $customQuery = $data['query']['view'] ?? null;
                $isCustom = $data['query']['custom'] ?? false;
                
                // Jika ada query custom dan flag custom = true, gunakan query tersebut
                if ($isCustom && !empty($customQuery)) {
                    // Validasi bahwa query adalah SELECT statement
                    $queryUpper = strtoupper(trim($customQuery));
                    if (strpos($queryUpper, 'SELECT') !== 0) {
                        throw new \InvalidArgumentException('Custom query must be a SELECT statement');
                    }
                    
                    // Buat CREATE VIEW dengan query custom
                    $createViewSql = "CREATE OR REPLACE VIEW `{$viewName}` AS\n{$customQuery}";
                    $this->raw($createViewSql);
                    
                    return [
                        'success' => true,
                        'results' => [
                            'viewName' => $viewName,
                            'sql' => $createViewSql,
                            'query' => $customQuery,
                            'data' => $data,
                            'custom' => true
                        ],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            // Jika tidak ada query custom, lanjutkan dengan build otomatis
            // Validasi data yang diperlukan
            if (!isset($data['tabelKey']) || !isset($data['tabelAlias']) || !isset($data['failedAS'])) {
                throw new \InvalidArgumentException('Missing required data: tabelKey, tabelAlias, or failedAS');
            }
            
            // Ambil data yang diperlukan
            $tabelKeys = $data['tabelKey'] ?? [];
            $tabelAliases = $data['tabelAlias'] ?? [];
            $failedAS = $data['failedAS'] ?? [];
            $oprasi = $data['oprasi'] ?? [];
            $groupBy = $data['groupBy'] ?? []; // Ambil GROUP BY untuk digunakan di SELECT clause
            
            // Validasi array tidak kosong
            if (empty($tabelKeys) || empty($tabelAliases) || empty($failedAS)) {
                throw new \InvalidArgumentException('tabelKey, tabelAlias, or failedAS cannot be empty');
            }
            
            // Build SELECT clause dari failedAS
            // Jika ada GROUP BY, kita perlu memodifikasi SELECT untuk memenuhi only_full_group_by
            $hasGroupBy = !empty($groupBy) && is_array($groupBy);
            
            if ($hasGroupBy) {
                // Buat array untuk GROUP BY fields (fullField)
                $groupByFields = [];
                foreach ($groupBy as $field) {
                    $fullField = trim($field);
                    if (!empty($fullField)) {
                        $groupByFields[] = $fullField;
                    }
                }
                
                // Modifikasi SELECT clause: kolom di GROUP BY tetap, yang lain pakai aggregate
                $selectFields = [];
                foreach ($failedAS as $selectField) {
                    $selectFieldTrimmed = trim($selectField);
                    $fullField = trim(explode(' AS ', $selectFieldTrimmed)[0]);
                    
                    // Jika field ada di GROUP BY, gunakan seperti biasa
                    if (in_array($fullField, $groupByFields)) {
                        $selectFields[] = $selectFieldTrimmed;
                    } else {
                        // Jika tidak ada di GROUP BY, gunakan aggregate function (MAX atau ANY_VALUE)
                        // ANY_VALUE tersedia di MySQL 5.7.5+, jika tidak gunakan MAX
                        // Format: MAX(demo.id) AS id
                        $parts = explode(' AS ', $selectFieldTrimmed);
                        if (count($parts) >= 2) {
                            $fieldPart = trim($parts[0]);
                            $aliasPart = trim($parts[1]);
                            // Gunakan ANY_VALUE jika tersedia, jika tidak gunakan MAX
                            $selectFields[] = "ANY_VALUE({$fieldPart}) AS {$aliasPart}";
                        } else {
                            // Fallback jika format tidak sesuai
                            $selectFields[] = "ANY_VALUE({$fullField}) AS " . (count($parts) > 1 ? trim($parts[1]) : $fullField);
                        }
                    }
                }
                $selectClause = 'SELECT ' . implode(', ', $selectFields);
            } else {
                // Tidak ada GROUP BY, gunakan SELECT biasa
                $selectClause = 'SELECT ' . implode(', ', array_map('trim', $failedAS));
            }
            
            // Get main table (first table)
            $mainTableKey = (int)$tabelKeys[0];
            $mainTableAlias = $tabelAliases[0];
            $mainTableReal = $this->tablesIndex($mainTableKey);
            
            if (empty($mainTableReal)) {
                throw new \InvalidArgumentException("Cannot find real table name for key: {$mainTableKey}");
            }
            
            // Build FROM clause
            $fromClause = "FROM {$mainTableReal} AS {$mainTableAlias}";
            
            // Build JOIN clauses dari oprasi
            $joinClauses = [];
            foreach ($oprasi as $operation) {
                // Oprasi format: [{"user": {"type": "inner", "index": "demo.userid", "target": "user.id"}}]
                foreach ($operation as $tableAlias => $joinData) {
                    // Skip jika ini main table
                    if ($tableAlias === $mainTableAlias) {
                        continue;
                    }
                    
                    // Cari key untuk tabel ini
                    $tableIndex = array_search($tableAlias, $tabelAliases);
                    if ($tableIndex === false) {
                        continue; // Skip jika alias tidak ditemukan
                    }
                    
                    $tableKey = (int)$tabelKeys[$tableIndex];
                    $realTableName = $this->tablesIndex($tableKey);
                    
                    if (empty($realTableName)) {
                        continue; // Skip jika tidak bisa mendapatkan nama tabel
                    }
                    
                    // Ambil join type, index, dan target
                    $joinType = strtoupper($joinData['type'] ?? 'INNER');
                    $indexField = $joinData['index'] ?? '';
                    $targetField = $joinData['target'] ?? '';
                    
                    if (empty($indexField) || empty($targetField)) {
                        continue; // Skip jika index atau target kosong
                    }
                    
                    // Build JOIN clause
                    $joinClause = "{$joinType} JOIN {$realTableName} AS {$tableAlias} ON {$indexField} = {$targetField}";
                    $joinClauses[] = $joinClause;
                }
            }
            
            // Combine all parts untuk CREATE VIEW
            $viewQuery = $selectClause . "\n" . $fromClause;
            if (!empty($joinClauses)) {
                $viewQuery .= "\n" . implode("\n", $joinClauses);
            }
            
            // Add WHERE clause jika ada
            $where = $data['where'] ?? [];
            if (!empty($where) && is_array($where)) {
                $whereConditions = [];
                
                foreach ($where as $whereItem) {
                    if (!is_array($whereItem) || empty($whereItem['field'])) {
                        continue;
                    }
                    
                    // Field sudah fullField dengan prefix tabel (misalnya "demo.row")
                    $field = trim($whereItem['field']);
                    $operator = strtoupper(trim($whereItem['operator'] ?? '='));
                    $value = $whereItem['value'] ?? '';
                    $value2 = $whereItem['value2'] ?? '';
                    $logical = strtoupper(trim($whereItem['logical'] ?? 'AND'));
                    
                    // Validasi logical operator
                    if (!in_array($logical, ['AND', 'OR']) && !empty($whereConditions)) {
                        $logical = 'AND';
                    }
                    
                    // Validasi bahwa field (fullField dengan prefix tabel) ada di SELECT clause
                    $fieldExists = false;
                    foreach ($failedAS as $selectField) {
                        $fullField = trim(explode(' AS ', $selectField)[0]);
                        if ($fullField === $field) {
                            $fieldExists = true;
                            break;
                        }
                    }
                    
                    // Jika field tidak ditemukan, skip condition ini
                    if (!$fieldExists) {
                        continue;
                    }
                    
                    // Gunakan field langsung dengan prefix tabel (misalnya "demo.row")
                    $fieldQuoted = $field;
                    
                    // Build condition berdasarkan operator
                    $condition = '';
                    
                    if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                        // IS NULL atau IS NOT NULL tidak perlu value
                        $condition = "{$fieldQuoted} {$operator}";
                    } elseif ($operator === 'BETWEEN') {
                        // BETWEEN memerlukan 2 value
                        $val1 = $this->escapeValue($value);
                        $val2 = $this->escapeValue($value2);
                        $condition = "{$fieldQuoted} BETWEEN {$val1} AND {$val2}";
                    } elseif (in_array($operator, ['IN', 'NOT IN'])) {
                        // IN atau NOT IN memerlukan multiple values (comma-separated)
                        if (!empty($value)) {
                            // Parse value jika comma-separated
                            $values = array_map('trim', explode(',', $value));
                            $escapedValues = array_map([$this, 'escapeValue'], $values);
                            $condition = "{$fieldQuoted} {$operator} (" . implode(', ', $escapedValues) . ")";
                        }
                    } elseif (in_array($operator, ['LIKE', 'NOT LIKE'])) {
                        // LIKE memerlukan value dengan wildcard
                        $escapedValue = $this->escapeValue($value);
                        $condition = "{$fieldQuoted} {$operator} {$escapedValue}";
                    } else {
                        // Operator standar (=, !=, >, <, >=, <=)
                        $escapedValue = $this->escapeValue($value);
                        $condition = "{$fieldQuoted} {$operator} {$escapedValue}";
                    }
                    
                    if (!empty($condition)) {
                        // Tambahkan logical operator jika bukan condition pertama
                        if (!empty($whereConditions)) {
                            $whereConditions[] = $logical;
                        }
                        $whereConditions[] = $condition;
                    }
                }
                
                if (!empty($whereConditions)) {
                    $whereClause = 'WHERE ' . implode(' ', $whereConditions);
                    $viewQuery .= "\n" . $whereClause;
                }
            }
            
            // Add GROUP BY clause jika ada (sudah didefinisikan di atas)
            if (!empty($groupBy) && is_array($groupBy)) {
                // Filter groupBy untuk menghilangkan nilai kosong dan validasi fullField
                $groupByFields = [];
                foreach ($groupBy as $field) {
                    $fullField = trim($field);
                    if (empty($fullField)) {
                        continue;
                    }
                    
                    // Validasi bahwa fullField ada di SELECT clause
                    $fieldExists = false;
                    foreach ($failedAS as $selectField) {
                        $selectFullField = trim(explode(' AS ', $selectField)[0]);
                        if ($selectFullField === $fullField) {
                            $fieldExists = true;
                            break;
                        }
                    }
                    
                    if ($fieldExists) {
                        // Gunakan fullField dengan prefix tabel (misalnya "demo.row")
                        $groupByFields[] = $fullField;
                    }
                }
                
                if (!empty($groupByFields)) {
                    $groupByClause = 'GROUP BY ' . implode(', ', $groupByFields);
                    $viewQuery .= "\n" . $groupByClause;
                }
            }
            
            // Add ORDER BY clause jika ada
            $orderBy = $data['orderBy'] ?? [];
            if (!empty($orderBy) && is_array($orderBy)) {
                // Filter orderBy untuk menghilangkan nilai kosong dan validasi fullField
                $orderByFields = [];
                foreach ($orderBy as $orderItem) {
                    if (is_array($orderItem) && !empty($orderItem['field'])) {
                        $fullField = trim($orderItem['field']);
                        $direction = strtoupper(trim($orderItem['direction'] ?? 'ASC'));
                        
                        // Validasi direction
                        if (!in_array($direction, ['ASC', 'DESC'])) {
                            $direction = 'ASC';
                        }
                        
                        // Validasi bahwa fullField ada di SELECT clause
                        $fieldExists = false;
                        foreach ($failedAS as $selectField) {
                            $selectFullField = trim(explode(' AS ', $selectField)[0]);
                            if ($selectFullField === $fullField) {
                                $fieldExists = true;
                                break;
                            }
                        }
                        
                        if ($fieldExists) {
                            // Gunakan fullField dengan prefix tabel (misalnya "demo.row")
                            $orderByFields[] = "{$fullField} {$direction}";
                        }
                    } elseif (is_string($orderItem) && !empty(trim($orderItem))) {
                        // Fallback untuk format string sederhana
                        $fullField = trim($orderItem);
                        // Validasi fullField
                        $fieldExists = false;
                        foreach ($failedAS as $selectField) {
                            $selectFullField = trim(explode(' AS ', $selectField)[0]);
                            if ($selectFullField === $fullField) {
                                $fieldExists = true;
                                break;
                            }
                        }
                        if ($fieldExists) {
                            $orderByFields[] = $fullField;
                        }
                    }
                }
                
                if (!empty($orderByFields)) {
                    $orderByClause = 'ORDER BY ' . implode(', ', $orderByFields);
                    $viewQuery .= "\n" . $orderByClause;
                }
            }
            
            // Build CREATE VIEW statement
            $createViewSql = "CREATE OR REPLACE VIEW `{$viewName}` AS\n{$viewQuery}";
            
            // Execute CREATE VIEW
            $this->raw($createViewSql);
            
            return [
                'success' => true,
                'results' => [
                    'viewName' => $viewName,
                    'sql' => $createViewSql,
                    'query' => $viewQuery,
                    'data' => $data
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
     * Delete view from database
     * 
     * @param array $data Data containing 'tabel' (view name) or 'createView' or 'label'
     * @return array Result with success status
     */
    public function buildTabelDelete(array $data): array {
        try {
            // Ambil view name dari data
            $viewName = $data['tabel'] ?? $data['createView'] ?? $data['label'] ?? null;
            
            if (empty($viewName)) {
                throw new \InvalidArgumentException('View name (tabel, createView, or label) is required');
            }
            
            // Build DROP VIEW statement
            $dropViewSql = "DROP VIEW IF EXISTS `{$viewName}`";
            
            // Execute DROP VIEW
            $this->raw($dropViewSql);
            
        return [
            'success' => true,
                'results' => [
                    'viewName' => $viewName,
                    'sql' => $dropViewSql,
                    'message' => "View '{$viewName}' has been deleted successfully"
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
     * Test query before creating view
     * Execute query with LIMIT 10 to preview results
     * 
     * @param array $data Same data structure as buildTabelView
     * @return array Result with sample data (max 10 rows) or error
     */
    public function testTabelView(array $data): array {
        try {
            // Cek apakah ada query custom
            $customQuery = null;
            if (isset($data['query']) && is_array($data['query'])) {
                $customQuery = $data['query']['view'] ?? null;
                $isCustom = $data['query']['custom'] ?? false;
                
                if ($isCustom && !empty($customQuery)) {
                    // Validasi bahwa query adalah SELECT statement
                    $queryUpper = strtoupper(trim($customQuery));
                    if (strpos($queryUpper, 'SELECT') !== 0) {
                        throw new \InvalidArgumentException('Custom query must be a SELECT statement');
                    }
                    
                    // Tambahkan LIMIT 10 untuk preview
                    $testQuery = trim($customQuery);
                    // Hapus LIMIT yang sudah ada jika ada, lalu tambahkan LIMIT 10
                    $testQuery = preg_replace('/\s+LIMIT\s+\d+/i', '', $testQuery);
                    $testQuery .= ' LIMIT 10';
                    
                    // Execute query
                    $results = $this->raw($testQuery);
                    
                    return [
                        'success' => true,
                        'results' => [
                            'data' => $results,
                            'rowCount' => is_array($results) ? count($results) : 0,
                            'query' => $testQuery,
                            'message' => 'Query executed successfully'
                        ],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            // Jika tidak ada query custom, build query seperti buildTabelView
            if (!isset($data['tabelKey']) || !isset($data['tabelAlias']) || !isset($data['failedAS'])) {
                throw new \InvalidArgumentException('Missing required data: tabelKey, tabelAlias, or failedAS');
            }
            
            // Build query (sama seperti buildTabelView tapi tanpa CREATE VIEW)
            $tabelKeys = $data['tabelKey'] ?? [];
            $tabelAliases = $data['tabelAlias'] ?? [];
            $failedAS = $data['failedAS'] ?? [];
            $oprasi = $data['oprasi'] ?? [];
            $groupBy = $data['groupBy'] ?? [];
            $orderBy = $data['orderBy'] ?? [];
            $where = $data['where'] ?? [];
            
            if (empty($tabelKeys) || empty($tabelAliases) || empty($failedAS)) {
                throw new \InvalidArgumentException('tabelKey, tabelAlias, or failedAS cannot be empty');
            }
            
            // Build SELECT clause (sama seperti buildTabelView)
            $hasGroupBy = !empty($groupBy) && is_array($groupBy);
            
            if ($hasGroupBy) {
                $groupByFields = [];
                foreach ($groupBy as $field) {
                    $fullField = trim($field);
                    if (!empty($fullField)) {
                        $groupByFields[] = $fullField;
                    }
                }
                
                $selectFields = [];
                foreach ($failedAS as $selectField) {
                    $selectFieldTrimmed = trim($selectField);
                    $fullField = trim(explode(' AS ', $selectFieldTrimmed)[0]);
                    
                    if (in_array($fullField, $groupByFields)) {
                        $selectFields[] = $selectFieldTrimmed;
                    } else {
                        $parts = explode(' AS ', $selectFieldTrimmed);
                        $originalField = $parts[0];
                        $alias = $parts[1] ?? $originalField;
                        $selectFields[] = "ANY_VALUE({$originalField}) AS {$alias}";
                    }
                }
                $selectClause = 'SELECT ' . implode(', ', $selectFields);
            } else {
                $selectClause = 'SELECT ' . implode(', ', array_map('trim', $failedAS));
            }
            
            $mainTableKey = (int)$tabelKeys[0];
            $mainTableAlias = $tabelAliases[0];
            $mainTableReal = $this->tablesIndex($mainTableKey);
            
            if (empty($mainTableReal)) {
                throw new \InvalidArgumentException("Cannot find real table name for key: {$mainTableKey}");
            }
            
            $fromClause = "FROM {$mainTableReal} AS {$mainTableAlias}";
            $joinClauses = [];
            foreach ($oprasi as $operation) {
                foreach ($operation as $tableAlias => $joinData) {
                    if ($tableAlias === $mainTableAlias) {
                        continue;
                    }
                    $tableIndex = array_search($tableAlias, $tabelAliases);
                    if ($tableIndex === false) {
                        continue;
                    }
                    $tableKey = (int)$tabelKeys[$tableIndex];
                    $realTableName = $this->tablesIndex($tableKey);
                    if (empty($realTableName)) {
                        continue;
                    }
                    $joinType = strtoupper($joinData['type'] ?? 'INNER');
                    $indexField = $joinData['index'] ?? '';
                    $targetField = $joinData['target'] ?? '';
                    if (empty($indexField) || empty($targetField)) {
                        continue;
                    }
                    $joinClause = "{$joinType} JOIN {$realTableName} AS {$tableAlias} ON {$indexField} = {$targetField}";
                    $joinClauses[] = $joinClause;
                }
            }
            
            $testQuery = $selectClause . "\n" . $fromClause;
            if (!empty($joinClauses)) {
                $testQuery .= "\n" . implode("\n", $joinClauses);
            }
            
            // Add WHERE clause
            if (!empty($where) && is_array($where)) {
                $whereConditions = [];
                foreach ($where as $whereItem) {
                    if (!is_array($whereItem) || empty($whereItem['field'])) {
                        continue;
                    }
                    $field = trim($whereItem['field']);
                    $operator = strtoupper(trim($whereItem['operator'] ?? '='));
                    $value = $whereItem['value'] ?? '';
                    $value2 = $whereItem['value2'] ?? '';
                    $logical = strtoupper(trim($whereItem['logical'] ?? 'AND'));
                    
                    if (!in_array($logical, ['AND', 'OR']) && !empty($whereConditions)) {
                        $logical = 'AND';
                    }
                    
                    $fieldExists = false;
                    foreach ($failedAS as $selectField) {
                        $selectFullField = trim(explode(' AS ', $selectField)[0]);
                        if ($selectFullField === $field) {
                            $fieldExists = true;
                            break;
                        }
                    }
                    if (!$fieldExists) {
                        continue;
                    }
                    
                    $fieldQuoted = $field;
                    $condition = '';
                    if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                        $condition = "{$fieldQuoted} {$operator}";
                    } elseif ($operator === 'BETWEEN') {
                        $val1 = $this->escapeValue($value);
                        $val2 = $this->escapeValue($value2);
                        $condition = "{$fieldQuoted} BETWEEN {$val1} AND {$val2}";
                    } elseif (in_array($operator, ['IN', 'NOT IN'])) {
                        if (!empty($value)) {
                            $values = array_map('trim', explode(',', $value));
                            $escapedValues = array_map([$this, 'escapeValue'], $values);
                            $condition = "{$fieldQuoted} {$operator} (" . implode(', ', $escapedValues) . ")";
                        }
                    } elseif (in_array($operator, ['LIKE', 'NOT LIKE'])) {
                        $escapedValue = $this->escapeValue($value);
                        $condition = "{$fieldQuoted} {$operator} {$escapedValue}";
                    } else {
                        $escapedValue = $this->escapeValue($value);
                        $condition = "{$fieldQuoted} {$operator} {$escapedValue}";
                    }
                    if (!empty($condition)) {
                        if (!empty($whereConditions)) {
                            $whereConditions[] = $logical;
                        }
                        $whereConditions[] = $condition;
                    }
                }
                if (!empty($whereConditions)) {
                    $whereClause = 'WHERE ' . implode(' ', $whereConditions);
                    $testQuery .= "\n" . $whereClause;
                }
            }
            
            // Add GROUP BY clause
            if (!empty($groupBy) && is_array($groupBy)) {
                $groupByFields = [];
                foreach ($groupBy as $field) {
                    $fullField = trim($field);
                    if (empty($fullField)) {
                        continue;
                    }
                    $fieldExists = false;
                    foreach ($failedAS as $selectField) {
                        $selectFullField = trim(explode(' AS ', $selectField)[0]);
                        if ($selectFullField === $fullField) {
                            $fieldExists = true;
                            break;
                        }
                    }
                    if ($fieldExists) {
                        $groupByFields[] = $fullField;
                    }
                }
                if (!empty($groupByFields)) {
                    $groupByClause = 'GROUP BY ' . implode(', ', $groupByFields);
                    $testQuery .= "\n" . $groupByClause;
                }
            }
            
            // Add ORDER BY clause
            if (!empty($orderBy) && is_array($orderBy)) {
                $orderByFields = [];
                foreach ($orderBy as $orderItem) {
                    if (is_array($orderItem) && !empty($orderItem['field'])) {
                        $fullField = trim($orderItem['field']);
                        $direction = strtoupper(trim($orderItem['direction'] ?? 'ASC'));
                        
                        if (!in_array($direction, ['ASC', 'DESC'])) {
                            $direction = 'ASC';
                        }
                        $fieldExists = false;
                        foreach ($failedAS as $selectField) {
                            $selectFullField = trim(explode(' AS ', $selectField)[0]);
                            if ($selectFullField === $fullField) {
                                $fieldExists = true;
                                break;
                            }
                        }
                        if ($fieldExists) {
                            $orderByFields[] = "{$fullField} {$direction}";
                        }
                    } elseif (is_string($orderItem) && !empty(trim($orderItem))) {
                        $fullField = trim($orderItem);
                        $fieldExists = false;
                        foreach ($failedAS as $selectField) {
                            $selectFullField = trim(explode(' AS ', $selectField)[0]);
                            if ($selectFullField === $fullField) {
                                $fieldExists = true;
                                break;
                            }
                        }
                        if ($fieldExists) {
                            $orderByFields[] = $fullField;
                        }
                    }
                }
                if (!empty($orderByFields)) {
                    $orderByClause = 'ORDER BY ' . implode(', ', $orderByFields);
                    $testQuery .= "\n" . $orderByClause;
                }
            }
            
            // Tambahkan LIMIT 10 untuk preview
            $testQuery .= "\nLIMIT 10";
            
            // Execute query
            $results = $this->raw($testQuery);
            
            return [
                'success' => true,
                'results' => [
                    'data' => $results,
                    'rowCount' => is_array($results) ? count($results) : 0,
                    'query' => $testQuery,
                    'message' => 'Query executed successfully'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => [
                    'data' => [],
                    'rowCount' => 0,
                    'query' => $data['query']['view'] ?? 'N/A',
                    'message' => 'Query execution failed'
                ],
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


