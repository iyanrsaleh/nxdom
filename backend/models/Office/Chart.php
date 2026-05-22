<?php
declare(strict_types=1);

namespace App\Models\Office;
use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class Chart extends NexaModel
{
    private $formula;

    public function __construct() {
        parent::__construct();
        $this->formula = new Formula();
    }
    
    public function NestedTabel(array $bulder): array {
      try {
        if (empty($bulder['tableKey']) || empty($bulder['variables'])) {
            throw new \Exception("tableKey dan variables wajib diisi");
        }

        $table = $this->tablesIndex($bulder['tableKey']);
        if (!$table) throw new \Exception("Tabel tidak ditemukan dari tableKey");

        $allowedAgg = ['GROUP_CONCAT', 'MAX', 'MIN', 'COUNT', 'SUM', 'AVG', 'STDDEV', 'VARIANCE', 'FIRST', 'LAST'];
        $selectFields = [];
        
        // Auto-generate groupByFields from all variables
        // All variables will automatically get COUNT() wrapper and be added to GROUP BY
        $groupByFields = [];
        if (!empty($bulder['variables'])) {
            foreach ($bulder['variables'] as $v) {
                if (is_array($v)) {
                    $field = $v['field'] ?? '';
                    if ($field) $groupByFields[] = $field;
                } else {
                    $field = trim($v);
                    if ($field) $groupByFields[] = $field;
                }
            }
        }
        
        // Note: We ignore the separate groupBy input to avoid conflicts
        // All variables are automatically included in GROUP BY

        // Process variables array (basic fields) - Show original field + count
        foreach ($bulder['variables'] as $v) {
            if (is_array($v)) {
                $field = $v['field'] ?? '';
                $alias = $v['alias'] ?? $field;
                $agg   = isset($v['aggregateType']) ? trim($v['aggregateType']) : '';
                $arithmetic = $v['arithmetic'] ?? null;

                if (!$field) continue;

                // For GROUP BY queries, show both original field and count
                if (!empty($groupByFields)) {
                    if ($arithmetic) {
                        $formula = $this->formula->buildArithmeticFormula($field, $agg, $arithmetic);
                        $selectFields[] = "$formula AS `$alias`";
                    } elseif (in_array($agg, $allowedAgg)) {
                        $selectFields[] = "$agg($field) AS `$alias`";
                    } else {
                        // Show original field + count
                        $selectFields[] = "$field";
                        $selectFields[] = "COUNT($field) AS `{$field}_count`";
                    }
                } else {
                    // For non-GROUP BY queries, use original logic
                if ($arithmetic) {
                    $formula = $this->formula->buildArithmeticFormula($field, $agg, $arithmetic);
                    $selectFields[] = "$formula AS `$alias`";
                } elseif (in_array($agg, $allowedAgg)) {
                    $selectFields[] = "$agg($field) AS `$alias`";
                } else {
                    $selectFields[] = "$field AS `$alias`";
                }
                }
            } else {
                // Handle string variables
                $field = trim($v);
                if (!empty($groupByFields)) {
                    // Show original field + count
                    $selectFields[] = "$field";
                    $selectFields[] = "COUNT($field) AS `{$field}_count`";
            } else {
                    $selectFields[] = "$field";
                }
            }
        }
        
        // Process aggregate array (separate aggregate operations)
        if (!empty($bulder['aggregate'])) {
            foreach ($bulder['aggregate'] as $agg) {
                $field = $agg['field'] ?? '';
                $type = strtoupper(trim($agg['type'] ?? ''));
                $alias = $agg['alias'] ?? $field;
                
                if (!$field || !in_array($type, $allowedAgg)) continue;
                
                $selectFields[] = "$type($field) AS `$alias`";
            }
        }
        
        // Process arithmetic array (separate arithmetic operations) - following executeOperation pattern
        if (!empty($bulder['arithmetic'])) {
            foreach ($bulder['arithmetic'] as $arith) {
                $field = $arith['field'] ?? '';
                $operation = $arith['operation'] ?? '';
                $alias = $arith['alias'] ?? $field;
                $nested = $arith['nested'] ?? [];
                
                if (!$field) continue;
                
                // Handle COMPLEX operation with nested operations
                if ($operation === 'COMPLEX' && !empty($nested)) {
                    $formula = $this->formula->buildNestedFormula($field, '', $nested);
                    $selectFields[] = "$formula AS `$alias`";
                } else {
                    // Handle simple arithmetic operations - following executeOperation pattern
                    $arithmeticData = [
                        'operation' => $operation,
                        'field2' => $arith['field2'] ?? '',
                        'value' => $arith['value'] ?? '',
                        'nested' => $nested
                    ];
                    $formula = $this->formula->buildArithmeticFormula($field, '', $arithmeticData);
                    $selectFields[] = "$formula AS `$alias`";
                }
            }
        }

        $select = "SELECT " . implode(", ", $selectFields);
        $from   = "FROM `$table`";

        // WHERE
        $where = "";
        if (!empty($bulder['where'])) {
            $conditions = [];
            foreach ($bulder['where'] as $i => $cond) {
                $field = $cond['field'] ?? '';
                $op    = strtoupper($cond['operator'] ?? '=');
                $value = $cond['value'] ?? null;
                $logic = strtoupper($cond['logic'] ?? "AND");

                if (!$field) continue;

                if ($op === "IN" && is_array($value)) {
                    $safe = array_map(fn($v) => "'" . addslashes($v) . "'", $value);
                    $expr = "$field IN (" . implode(", ", $safe) . ")";
                } else {
                    $val = is_numeric($value) ? $value : "'" . addslashes($value) . "'";
                    $expr = "$field $op $val";
                }

                $conditions[] = ($i > 0 ? "$logic " : "") . $expr;
            }
            if ($conditions) {
                $where = "WHERE " . implode(" ", $conditions);
            }
        }

        // GROUP BY - automatically use all variables
        $groupBy = "";
        if (!empty($groupByFields)) {
            $groupBy = "GROUP BY " . implode(", ", $groupByFields);
        }

        // ORDER BY - Use automatic ordering only, ignore JavaScript input
        $orderBy = "";
        if (!empty($groupByFields)) {
            // Automatic ordering for analysis: order by count descending
            $firstField = $groupByFields[0];
            $orderBy = "ORDER BY COUNT($firstField) DESC";
        }

        // LIMIT OFFSET - Ignore LIMIT for GROUP BY queries to show all data
        $limit = "";
        if (isset($bulder['limit']) && empty($groupByFields)) {
            // Only apply LIMIT if we're not using GROUP BY
            $limit = "LIMIT " . (int)$bulder['limit'];
            if (isset($bulder['offset'])) {
                $limit .= " OFFSET " . (int)$bulder['offset'];
            }
        }

        // FINAL SQL
        $sql = implode(" ", array_filter([$select, $from, $where, $groupBy, $orderBy, $limit]));
               
                $result = $this->raw($sql);
                
                // Optimize output: if all counts are same, use simplified format
                if (!empty($result) && !empty($groupByFields)) {
                    $result = $this->optimizeCountOutput($result, $groupByFields);
                }

                return [
            'success' => true,
            'response' => $result ?: [],
            'query' => $sql,
            'debug' => [
                'selectFields' => $selectFields,
                'table' => $table,
                'groupByFields' => $groupByFields,
                'variables' => $bulder['variables'] ?? []
            ]
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'query' => '',
            'response' => []
        ];
    }
    }

 
 /**
 * Method khusus untuk operasi SUM dan COUNT dengan JOIN
 * Optimized untuk analisis data relasional
 */  
    public function CrossJoin(array $data): array {
        try {
            if (!$data || !isset($data['joins']) || !isset($data['fields'])) {
                throw new \Exception("Format JSON tidak valid");
            }
            // --- IDENTIFIKASI BASE TABLE ---
            $firstJoin   = $data['joins'][0];
            $indexTable  = (int)$firstJoin['sourceKeyIndex'];
            $baseTable   = $this->tablesIndex($indexTable);
            $aliasBase   = $firstJoin['sourceTable'];

            if (empty($baseTable) || empty($aliasBase)) {
                throw new \Exception("Base table atau alias tidak valid");
            }
            // --- AUTO COUNT DETECTION FOR ANALYSIS ---
            $allowedAgg = ['GROUP_CONCAT', 'MAX', 'MIN', 'COUNT', 'SUM', 'AVG', 'STDDEV', 'VARIANCE', 'FIRST', 'LAST'];
            $hasGroupBy = !empty($data['groupBy']);
            
            // Auto-generate groupByFields from all fields (konsisten dengan NestedTabel)
            $groupByFields = [];
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $f) {
                    $fieldName = $f['fieldName'];
                    if ($fieldName) $groupByFields[] = $fieldName;
                }
            }
            
            // Jika ada fields, otomatis aktifkan GROUP BY dan COUNT (konsisten dengan NestedTabel)
            if (!empty($groupByFields)) {
                $hasGroupBy = true;
                // Set groupBy jika belum ada
                if (empty($data['groupBy'])) {
                    $data['groupBy'] = $groupByFields;
                }
            }

            // --- SELECT FIELDS DENGAN AGGREGASI DINAMIS DAN ARITMATIKA ---
            $selectFields = [];
            foreach ($data['fields'] as $f) {
                $alias     = $f['alias'] ?? $f['fieldKey'];
                $fieldName = $f['fieldName'];
                $table     = $f['table'] ?? '';
                $agg       = strtoupper(trim($f['aggregateType'] ?? ''));
                $arithmetic = $f['arithmetic'] ?? null;

                // Jika ada formula aritmatika - berlaku untuk semua tabel
                if ($arithmetic) {
                    $formula = $this->formula->buildArithmeticFormula($fieldName, $agg, $arithmetic);
                    $selectFields[] = "$formula AS $alias";
                } else {
                    // Untuk GROUP BY queries, tampilkan field asli + count (konsisten dengan NestedTabel)
                    if ($hasGroupBy) {
                        if (in_array($agg, $allowedAgg)) {
                            // Jika ada agregasi eksplisit
                            $selectFields[] = "$agg($fieldName) AS $alias";
                        } else {
                            // Tampilkan field asli + count
                            $selectFields[] = "$fieldName";
                            // Clean alias: remove table prefix for count
                            $cleanFieldName = strpos($fieldName, '.') !== false ? substr($fieldName, strpos($fieldName, '.') + 1) : $fieldName;
                            $selectFields[] = "COUNT($fieldName) AS `{$cleanFieldName}_count`";
                        }
                    } else {
                        // Field biasa tanpa GROUP BY
                        $selectFields[] = "$fieldName AS $alias";
                    }
                }
            }
            $selectClause = "SELECT " . implode(", ", $selectFields);

            // --- FROM CLAUSE ---
            $fromClause = "FROM $baseTable AS $aliasBase";

            // --- JOIN CLAUSES ---
            $joinClauses = [];
            foreach ($data['joins'] as $join) {
                $targetIndex = (int)$join['targetKeyIndex'];
                $targetTable = $this->tablesIndex($targetIndex) ?? $join['targetTable'];
                $aliasTarget = $join['targetTable'];
                $joinType    = strtoupper(trim($join['joinType'] ?? 'INNER'));
                $sourceField = $join['sourceField'] ?? '';
                $targetField = $join['targetField'] ?? '';
                $condition   = $join['joinCondition'] ?? '=';

                if (!$targetTable || !$aliasTarget || !$sourceField || !$targetField) {
                    throw new \Exception("JOIN clause tidak lengkap");
                }
                $joinClauses[] = "$joinType JOIN $targetTable AS $aliasTarget ON $sourceField $condition $targetField";
            }

            // --- WHERE CLAUSES ---
            $whereClause = "";
            if (!empty($data['where'])) {
                $conditions = [];
                foreach ($data['where'] as $i => $cond) {
                    $field =$cond['field'];
                    $op    = strtoupper(trim($cond['operator']));
                    $value = $cond['value'];
                    $logic = strtoupper($cond['logic'] ?? "AND");

                    if ($op === "IS NULL" || $op === "IS NOT NULL") {
                        $expr = "$field $op";
                    } elseif (is_null($value)) {
                        $expr = ($op === "=") ? "$field IS NULL" : "$field IS NOT NULL";
                    } elseif ($op === "BETWEEN" && is_array($value) && count($value) === 2) {
                        $expr = "$field BETWEEN '" . addslashes($value[0]) . "' AND '" . addslashes($value[1]) . "'";
                    } elseif (in_array($op, ["IN", "NOT IN", "NOT_IN"])) {
                        if (!is_array($value)) throw new \Exception("Value untuk IN/NOT IN harus array");
                        $safeValues = array_map(fn($v) => "'" . addslashes($v) . "'", $value);
                        $normalizedOp = ($op === "NOT_IN") ? "NOT IN" : $op;
                        $expr = "$field $normalizedOp (" . implode(", ", $safeValues) . ")";
                    } else {
                        $expr = "$field $op '" . addslashes($value) . "'";
                    }

                    $conditions[] = ($i > 0 ? "$logic " : "") . $expr;
                }
                $whereClause = "WHERE " . implode(" ", $conditions);
            }

            // --- GROUP BY ---
            $groupByClause = "";
            if (!empty($groupByFields)) {
                $groupByClause = "GROUP BY " . implode(", ", $groupByFields);
            }

            // --- ORDER BY - Use automatic ordering only, ignore JavaScript input ---
            $orderByClause = "";
            if ($hasGroupBy && !empty($groupByFields)) {
                // Automatic ordering for analysis: order by count descending
                $firstField = $groupByFields[0];
                $orderByClause = "ORDER BY COUNT($firstField) DESC";
            }

            // --- LIMIT OFFSET - Ignore LIMIT for GROUP BY queries to show all data ---
            $limitClause = "";
            if (isset($data['limit']) && empty($groupByFields)) {
                // Only apply LIMIT if we're not using GROUP BY
                $limitClause = "LIMIT " . (int)$data['limit'];
                if (isset($data['offset'])) {
                    $limitClause .= " OFFSET " . (int)$data['offset'];
                }
            }

            // --- FINAL SQL ---
            $sql = implode(" ", array_filter([
                $selectClause,
                $fromClause,
                implode(" ", $joinClauses),
                $whereClause,
                $groupByClause,
                $orderByClause,
                $limitClause
            ]));

            $result = $this->raw($sql);
            
            // Optimize output: if all counts are same, use simplified format
            if (!empty($result) && $hasGroupBy) {
                $result = $this->optimizeCountOutput($result, $groupByFields);
            }
         
            return [
                'success' => true,
                'response' => $result ?: [],
                'query' => $sql ?: [],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'data'    => []
            ];
        }
    }

    /**
     * Optimize COUNT output: group by field type and separate each field into individual rows for chart visualization
     */
    private function optimizeCountOutput(array $result, array $groupByFields): array {
        if (empty($result)) {
            return $result;
        }
        
        $fieldGroups = [];
        
        foreach ($result as $row) {
            $fieldData = [];
            $countData = [];
            
            // Extract field values and their corresponding counts
            foreach ($row as $key => $value) {
                if (strpos($key, '_count') !== false) {
                    // This is a count field, extract the original field name
                    $originalField = str_replace('_count', '', $key);
                    $countData[$originalField] = (int)$value;
                } else {
                    // This is an original field
                    $fieldData[$key] = $value;
                }
            }
            
            // Group by field type
            foreach ($fieldData as $fieldName => $fieldValue) {
                $count = $countData[$fieldName] ?? 0;
                
                if (!isset($fieldGroups[$fieldName])) {
                    $fieldGroups[$fieldName] = [];
                }
                
                $fieldGroups[$fieldName][] = [
                    'label' => $fieldValue,
                    'value' => $count,
                    'total' => $count
                ];
            }
        }
        
        // Flatten the grouped data
        $optimized = [];
        foreach ($fieldGroups as $fieldName => $fieldData) {
            $optimized = array_merge($optimized, $fieldData);
        }
        
        return $optimized;
    }

}
