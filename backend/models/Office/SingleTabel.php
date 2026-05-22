<?php
declare(strict_types=1);
namespace App\Models\Office;
use App\System\NexaModel;

/**
 * Formula class untuk operasi aritmatika dan nested formula
 */
class SingleTabel extends NexaModel{
    private $formula;

    public function __construct() {
        parent::__construct();
        $this->formula = new Formula();
    }
    
public function singleQuery(array $bulder) {
    try {
        if (empty($bulder['tableKey']) || empty($bulder['variables'])) {
            throw new \Exception("tableKey dan variables wajib diisi");
        }

        $table = $this->tablesIndex($bulder['tableKey']);
        if (!$table) {
            throw new \Exception("Tabel tidak ditemukan dari tableKey");
        }

        $allowedAgg = ['MAX', 'MIN', 'COUNT', 'SUM', 'AVG', 'STDDEV', 'VARIANCE', 'FIRST', 'LAST'];
        $selectFields = [];
        
        // Process variables array (basic fields)
        foreach ($bulder['variables'] as $v) {
            if (is_array($v)) {
                $field = $v['field'] ?? '';
                $alias = $v['alias'] ?? $field;
                $agg   = isset($v['aggregateType']) ? strtoupper(trim($v['aggregateType'])) : '';
                $arithmetic = $v['arithmetic'] ?? null;

                if (!$field) {
                    continue;
                }

                if ($arithmetic) {
                    $formula = $this->formula->buildArithmeticFormula($field, $agg, $arithmetic);
                    $selectFields[] = "$formula AS `$alias`";
                } elseif (in_array($agg, $allowedAgg)) {
                    $selectFields[] = "$agg($field) AS `$alias`";
                } else {
                    $selectFields[] = "$field AS `$alias`";
                }
            } else {
                // Handle string variables like ['nama', 'title', 'deskripsi', 'images', 'row', 'id']
                $selectFields[] = trim($v);
            }
        }
        
        // Process aggregate array (separate aggregate operations)
        if (!empty($bulder['aggregate'])) {
            foreach ($bulder['aggregate'] as $agg) {
                $field = $agg['field'] ?? '';
                $type = strtoupper(trim($agg['type'] ?? ''));
                $alias = $agg['alias'] ?? $field;
                
                if (!$field || !in_array($type, $allowedAgg)) {
                    continue;
                }
                
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
                
                if (!$field) {
                    continue;
                }
                
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
        $conditions = [];
        
        // Access Control: Add userid filter if access is private
        if (isset($bulder['access']) && strtolower($bulder['access']) === 'private') {
            $userId = $this->userid();
            if (!empty($userId)) {
                $conditions[] = "userid = '" . addslashes((string)$userId) . "'";
            }
        }
        
        // Process custom WHERE conditions
        if (!empty($bulder['where'])) {
            foreach ($bulder['where'] as $cond) {
                $field = $cond['field'] ?? '';
                $op    = strtoupper($cond['operator'] ?? '=');
                $value = $cond['value'] ?? null;
                $logic = strtoupper($cond['logic'] ?? "AND");

                if (!$field) {
                    continue;
                }

                if ($op === "IN" && is_array($value)) {
                    $safe = array_map(fn($v) => "'" . addslashes($v) . "'", $value);
                    $expr = "$field IN (" . implode(", ", $safe) . ")";
                } else {
                    $val = is_numeric($value) ? $value : "'" . addslashes($value) . "'";
                    $expr = "$field $op $val";
                }

                $conditions[] = (!empty($conditions) ? "$logic " : "") . $expr;
            }
        }
        
        // Build final WHERE clause
        if ($conditions) {
            $where = "WHERE " . implode(" ", $conditions);
        }

        // GROUP BY
        $groupBy = "";
        if (!empty($bulder['groupBy'])) {
            $groupFields = array_filter($bulder['groupBy']);
            if ($groupFields) {
                $groupBy = "GROUP BY " . implode(", ", $groupFields);
            }
        }

        // ORDER BY
        $orderBy = "";
        if (!empty($bulder['orderBy'])) {
            $orders = array_map(function($o) {
                $field = $o['field'] ?? '';
                $dir   = strtoupper($o['direction'] ?? 'ASC');
                return $field ? "$field $dir" : '';
            }, $bulder['orderBy']);
            $orders = array_filter($orders);
            if ($orders) {
                $orderBy = "ORDER BY " . implode(", ", $orders);
            }
        }

        // LIMIT OFFSET
        $limit = "";
        if (isset($bulder['limit'])) {
            $limit = "LIMIT " . (int)$bulder['limit'];
            if (isset($bulder['offset'])) {
                $limit .= " OFFSET " . (int)$bulder['offset'];
            }
        }

        // FINAL SQL
        $sql = implode(" ", array_filter([$select, $from, $where, $groupBy, $orderBy, $limit]));
        
        // Debug logging
        $accessType = $bulder['access'] ?? 'public';
        $userId = $this->userid();
        
        $result = $this->raw($sql);

        return [
            'success' => true,
            'response' => $result ?: [],
            'query' => $sql,
            'debug' => [
                'userid' => $userId,
                'access_type' => $accessType,
                'table_key' => $bulder['tableKey'],
                'table_name' => $bulder['tableName'] ?? 'N/A'
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
}
