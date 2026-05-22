<?php
declare(strict_types=1);
namespace App\Models\Office;
use App\System\NexaModel;

/**
 * Formula class untuk operasi aritmatika dan nested formula
 */
class Analysis extends NexaModel{
    private $formula;
    private $showSql = true; // Default: tampilkan SQL

    public function __construct() {
        parent::__construct();
        $this->formula = new Formula();
    }
    
    /**
     * Set apakah SQL query ditampilkan di response (default untuk semua request)
     * @param bool $show true untuk tampilkan SQL, false untuk sembunyikan
     * @return self
     */
    public function setShowSql(bool $show): self {
        $this->showSql = $show;
        return $this;
    }
    
public function index(array $data) {
    try {
    // Check if showSql is overridden in data (per-request setting)
    $showSql = isset($data['showSql']) ? (bool)$data['showSql'] : $this->showSql;
    
    // Validate required data structure
    if (!isset($data['alias']) || !isset($data['operasi'])) {
        throw new \InvalidArgumentException('Missing required data: alias or operasi');
    }
    
    // ✅ Validate operasi is array
    if (!is_array($data['operasi']) || empty($data['operasi'])) {
        throw new \InvalidArgumentException('operasi must be a non-empty array');
    }
    
    // ✅ HANYA untuk type "operasi", "petir", atau "layar": Check if any operasi has type dengan direct query
    // Jika type bukan "operasi", "petir", atau "layar", akan menggunakan build SQL normal di bawah
    $rawQueryData = $this->extractRawQuery($data['operasi']);
    if ($rawQueryData !== null) {
        // Dieksekusi jika type adalah "operasi" atau "petir"
        return $this->executeRawQuery($rawQueryData['query'], $data, $rawQueryData['type']);
    }
    
    // Untuk type selain "operasi", gunakan build SQL normal
    // For single table queries, generate tabelName from operasi if not provided
    if (!isset($data['tabelName']) || empty($data['tabelName'])) {
        $data['tabelName'] = array_keys($data['operasi']);
    }

    // ✅ Jika ada GROUP BY, sesuaikan alias untuk kompatibel dengan GROUP BY
    if (!empty($data['group']) && $data['group'] !== false) {
        // Get custom count alias name (default: 'total')
        $countAlias = $data['countAlias'] ?? 'total';
        $data['alias'] = $this->adjustAliasForGroupBy($data['alias'], $data['group'], $countAlias);
    }

        // Build the SQL query
        $sql = $this->buildJoinSQL($data);
        
        // Build total count query (without LIMIT/OFFSET)
        $totalCountSql = $this->buildTotalCountSQL($data);
        
        // Execute the queries
        $results = $this->raw($sql);
        $totalCountResult = $this->raw($totalCountSql);
        $totalCount = isset($totalCountResult[0]['total_count']) ? (int)$totalCountResult[0]['total_count'] : 0;
        
        // Process subquery if exists
        if (isset($data['subquery']) && !empty($data['subquery'])) {
            $results = $this->processSubquery($results, $data['subquery'], $data);
        }

        if (isset($data['subnested']) && !empty($data['subnested'])) {
            $results = $this->processSubNested($results, $data['subnested'], $data);
        }

        // ✅ Process percent calculation if percent is true
        if (isset($data['percent']) && $data['percent'] === true) {
            $results = $this->calculatePercent($results, $data);
        }
        
        // ✅ Process progres calculation if progres is true (untuk progress bar)
        if (isset($data['progres']) && $data['progres'] === true) {
            $results = $this->calculateProgres($results, $data);
        }

        
        return [
            'success' => true,
            'sql' => $showSql ? $sql : false,
            'response' => $results,
            'count' => count($results),
            'totalCount' => $totalCount
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error'   => $e->getMessage(),
            'response'    => [],
            'sql'     => null
        ];
    }
}

/**
 * Build JOIN SQL query from the provided data structure
 */
private function buildJoinSQL(array $data): string {
    $aliases = $data['alias'] ?? [];
    $tableNames = $data['tabelName'] ?? [];
    $operations = $data['operasi'] ?? [];
    $where = $data['where'] ?? null;
    $group = $data['group'] ?? null;
    $order = $data['order'] ?? null;
    $limit = $data['limit'] ?? null;
    $offset = $data['offset'] ?? null;





    
    if (empty($aliases) || empty($tableNames) || empty($operations)) {
        throw new \InvalidArgumentException('Empty aliases, table names, or operations');
    }
    
    // Build SELECT clause with aliases
    $selectClause = 'SELECT ' . implode(', ', $aliases);
    
    // Get the main table (first table in the list)
    $mainTable = $tableNames[0];
    $mainTableReal = $this->getTableNameFromKey($mainTable, $operations);
    
    // Build FROM clause
    $fromClause = "FROM {$mainTableReal} AS {$mainTable}";
    
    // Build JOIN clauses
    $joinClauses = [];
    foreach ($operations as $tableName => $operation) {
        if ($tableName === $mainTable) {
            continue; // Skip main table
        }
        
        $joinType = strtoupper($operation['type'] ?? 'INNER');
        $condition = $operation['condition'] ?? '=';
        $indexField = $operation['index'] ?? '';
        $targetField = $operation['target'] ?? '';
        
        // Get real table name using keyTarget
        $realTableName = $this->getTableNameFromKey($tableName, $operations, $operation['keyTarget'] ?? '');
        
        // Build JOIN clause
        $joinClause = "{$joinType} JOIN {$realTableName} AS {$tableName} ON {$indexField} {$condition} {$targetField}";
        $joinClauses[] = $joinClause;
    }
    
    // Combine all parts
    $sql = $selectClause . "\n" . $fromClause;
    if (!empty($joinClauses)) {
        $sql .= "\n" . implode("\n", $joinClauses);
    }
    
    // Access Control: Add userid filter if access is private
    $conditions = [];
    if (isset($data['access']) && strtolower($data['access']) === 'private') {
        // Gunakan userid dari $data jika ada, jika tidak gunakan $this->userid()
        $userId = isset($data['userid']) ? $data['userid'] : $this->userid();
        if (!empty($userId)) {
            $conditions[] = $tableNames[0] . ".userid = '" . addslashes((string)$userId) . "'";
        }
    }
    
    // Add WHERE clause if provided
    // Check if where is a valid string (not false, null, or empty string)
    if ($where !== null && $where !== false && $where !== '' && !empty(trim((string)$where))) {
        // If WHERE clause doesn't start with "WHERE", add it
        $whereClause = (stripos(trim($where), 'WHERE') === 0) ? $where : "WHERE {$where}";
        $sql .= "\n" . $whereClause;
        
        // Add access control conditions to existing WHERE clause
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
    } else {
        // Add access control conditions as new WHERE clause
        if (!empty($conditions)) {
            $sql .= "\nWHERE " . implode(" AND ", $conditions);
        }
    }
    
    // Add GROUP BY clause if provided
    if (!empty($group)) {
        // If GROUP BY clause doesn't start with "GROUP BY", add it
        $groupClause = (stripos(trim($group), 'GROUP BY') === 0) ? $group : "GROUP BY {$group}";
        $sql .= "\n" . $groupClause;
    }
    
    // Add ORDER BY clause if provided
    if (!empty($order)) {
        // If ORDER BY clause doesn't start with "ORDER BY", add it
        $orderClause = (stripos(trim($order), 'ORDER BY') === 0) ? $order : "ORDER BY {$order}";
        $sql .= "\n" . $orderClause;
    }
    
    // Add LIMIT and OFFSET if provided
    // Check if limit is false or null to skip LIMIT clause
    if ($limit !== null && $limit !== false && is_numeric($limit) && $limit > 0) {
        $sql .= "\nLIMIT " . (int)$limit;
        
        if ($offset !== null && is_numeric($offset) && $offset >= 0) {
            $sql .= " OFFSET " . (int)$offset;
        }
    }
    
    return $sql;
}

/**
 * Build total count SQL query (without LIMIT/OFFSET for getting total records)
 */
private function buildTotalCountSQL(array $data): string {
    $tableNames = $data['tabelName'] ?? [];
    $operations = $data['operasi'] ?? [];
    $where = $data['where'] ?? null;
    $group = $data['group'] ?? null;
    
    if (empty($tableNames) || empty($operations)) {
        throw new \InvalidArgumentException('Empty table names or operations');
    }
    
    // Build SELECT COUNT clause
    $selectClause = 'SELECT COUNT(*) as total_count';
    
    // Get the main table (first table in the list)
    $mainTable = $tableNames[0];
    $mainTableReal = $this->getTableNameFromKey($mainTable, $operations);
    
    // Build FROM clause
    $fromClause = "FROM {$mainTableReal} AS {$mainTable}";
    
    // Build JOIN clauses (same as main query)
    $joinClauses = [];
    foreach ($operations as $tableName => $operation) {
        if ($tableName === $mainTable) {
            continue; // Skip main table
        }
        
        $joinType = strtoupper($operation['type'] ?? 'INNER');
        $condition = $operation['condition'] ?? '=';
        $indexField = $operation['index'] ?? '';
        $targetField = $operation['target'] ?? '';
        
        // Get real table name using keyTarget
        $realTableName = $this->getTableNameFromKey($tableName, $operations, $operation['keyTarget'] ?? '');
        
        // Build JOIN clause
        $joinClause = "{$joinType} JOIN {$realTableName} AS {$tableName} ON {$indexField} {$condition} {$targetField}";
        $joinClauses[] = $joinClause;
    }
    
    // Combine all parts
    $sql = $selectClause . "\n" . $fromClause;
    if (!empty($joinClauses)) {
        $sql .= "\n" . implode("\n", $joinClauses);
    }
    
    // Access Control: Add userid filter if access is private
    $conditions = [];
    if (isset($data['access']) && strtolower($data['access']) === 'private') {
        // Gunakan userid dari $data jika ada, jika tidak gunakan $this->userid()
        $userId = isset($data['userid']) ? $data['userid'] : $this->userid();
        if (!empty($userId)) {
            $conditions[] = $tableNames[0] . ".userid = '" . addslashes((string)$userId) . "'";
        }
    }
    
    // Add WHERE clause if provided
    // Check if where is a valid string (not false, null, or empty string)
    if ($where !== null && $where !== false && $where !== '' && !empty(trim((string)$where))) {
        $whereClause = (stripos(trim($where), 'WHERE') === 0) ? $where : "WHERE {$where}";
        $sql .= "\n" . $whereClause;
        
        // Add access control conditions to existing WHERE clause
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
    } else {
        // Add access control conditions as new WHERE clause
        if (!empty($conditions)) {
            $sql .= "\nWHERE " . implode(" AND ", $conditions);
        }
    }
    
    // Add GROUP BY clause if provided (affects COUNT behavior)
    if (!empty($group)) {
        $groupClause = (stripos(trim($group), 'GROUP BY') === 0) ? $group : "GROUP BY {$group}";
        $sql .= "\n" . $groupClause;
        
        // If GROUP BY is used, we need to wrap the query to count groups
        $sql = "SELECT COUNT(*) as total_count FROM ({$sql}) as grouped_query";
    }
    
    // Note: No LIMIT/OFFSET for total count
    
    return $sql;
}
private function processSubNested(array $mainResults, array $subqueryData, array $mainData): array {
    if (empty($mainResults) || empty($subqueryData)) {
        return $mainResults;
    }
    
    // Extract the field name that will be used for subquery WHERE condition
    $subqueryWhere = $subqueryData['where'] ?? '';
    $mainTableAlias = $mainData['tabelName'][0] ?? '';
    
    // Parse the WHERE condition to find the field reference
    // Example: "WHERE controllers.userid = Member.id AS userid"
    $whereField = $this->extractWhereField($subqueryWhere, $mainTableAlias);
    
    foreach ($mainResults as &$result) {
        try {
            // Build subquery data with dynamic WHERE condition
            // Pass mainTableAlias untuk filter pattern yang perlu di-replace (hanya pattern dari main table)
            $dynamicSubqueryData = $this->buildDynamicSubquery($subqueryData, $whereField, $result, $mainTableAlias);
            
            // Execute subquery
            $subqueryResult = $this->executeSubquery($dynamicSubqueryData);
            
            // Flatten subnested data directly into main result instead of nesting under "subdata"
            if (!empty($subqueryResult) && is_array($subqueryResult)) {
                // Get the first result from subquery (assuming single result for subnested)
                $subnestedData = $subqueryResult[0] ?? [];
                // Merge subnested fields directly into main result
                if (!empty($subnestedData) && is_array($subnestedData)) {
                    foreach ($subnestedData as $key => $value) {
                        $result[$key] = $value;
                    }
                }
            }
            
        } catch (\Exception $e) {
            // If subquery fails, log error but don't add subdata
            error_log("Subnested error: " . $e->getMessage());
        }
    }
    
    return $mainResults;
}

/**
 * Process subquery for each main query result
 */
private function processSubquery(array $mainResults, array $subqueryData, array $mainData): array {
    if (empty($mainResults) || empty($subqueryData)) {
        return $mainResults;
    }
    
    // Extract the field name that will be used for subquery WHERE condition
    $subqueryWhere = $subqueryData['where'] ?? '';
    $mainTableAlias = $mainData['tabelName'][0] ?? '';
    
    // Parse the WHERE condition to find the field reference
    // Example: "WHERE controllers.userid = Member.id AS userid"
    $whereField = $this->extractWhereField($subqueryWhere, $mainTableAlias);
    
    foreach ($mainResults as &$result) {
        try {
            // Build subquery data with dynamic WHERE condition
            $dynamicSubqueryData = $this->buildDynamicSubquery($subqueryData, $whereField, $result, $mainTableAlias);
            
            // Execute subquery
            $subqueryResult = $this->executeSubquery($dynamicSubqueryData);
            
            // Add subquery result to main result
            $result['subdata'] = $subqueryResult;
            
        } catch (\Exception $e) {
            // If subquery fails, add empty subdata
            $result['subdata'] = [];
            error_log("Subquery error: " . $e->getMessage());
        }
    }
    
    return $mainResults;
}

/**
 * Extract field name from WHERE condition for subquery
 */
private function extractWhereField(string $whereClause, string $mainTableAlias): string {
    // Remove WHERE keyword if present
    $whereClause = preg_replace('/^WHERE\s+/i', '', trim($whereClause));
    
    // Look for pattern like "controllers.userid = Member.id AS userid"
    // Extract the field after "AS" or the last part
    if (preg_match('/AS\s+(\w+)/i', $whereClause, $matches)) {
        return $matches[1];
    }
    
    // If no AS found, try to extract field from the right side of =
    if (preg_match('/=\s*' . preg_quote($mainTableAlias) . '\.(\w+)/i', $whereClause, $matches)) {
        return $matches[1];
    }
    
    // Default fallback
    return 'id';
}

/**
 * Build dynamic subquery data with WHERE condition based on main result
 * Supports multiple field references in WHERE clause
 */
private function buildDynamicSubquery(array $subqueryData, string $whereField, array $mainResult, string $mainTableAlias = ''): array {
    $dynamicData = $subqueryData;
    
    // Get the value from main result
    $fieldValue = $mainResult[$whereField] ?? null;
    
    // ✅ Selalu build dynamic WHERE, bahkan jika fieldValue null
    // Ini untuk memastikan replacement table.field pattern dengan nilai (atau NULL)
    $originalWhere = $subqueryData['where'] ?? '';
    
    // ✅ Support multiple field references: extract all table.field patterns
    // Hanya extract pattern dari main table alias (misalnya "demo.userid", bukan "user.id")
    $fieldPatterns = $this->extractAllFieldPatterns($originalWhere, $mainTableAlias);
    
    if (!empty($fieldPatterns)) {
        // Build dynamic WHERE dengan multiple replacements
        $dynamicWhere = $this->buildDynamicWhereClauseMultiple($originalWhere, $fieldPatterns, $mainResult);
    } else {
        // Fallback ke method lama jika hanya satu field
        $dynamicWhere = $this->buildDynamicWhereClause($originalWhere, $fieldValue);
    }
    
    if (!empty($dynamicWhere)) {
        $dynamicData['where'] = $dynamicWhere;
    }
    
    return $dynamicData;
}

/**
 * Build dynamic WHERE clause by replacing field references with actual values
 */
private function buildDynamicWhereClause(string $originalWhere, $fieldValue): string {
    // Remove WHERE keyword if present
    $whereClause = preg_replace('/^WHERE\s+/i', '', trim($originalWhere));
    
    // Extract table.field pattern from WHERE clause (e.g., "demo.userid")
    $fieldPattern = $this->extractFieldPattern($whereClause);
    
    if (!empty($fieldPattern)) {
        // ✅ Replace the table.field pattern with actual value
        // Pattern: match "demo.userid" or "demo.userid AS something"
        // Gunakan lookbehind dan lookahead untuk memastikan tidak partial match
        $escapedPattern = preg_quote($fieldPattern, '/');
        $pattern = '/(?<![A-Za-z0-9_\.])' . $escapedPattern . '(?![A-Za-z0-9_\.])/i';
        
        if ($fieldValue !== null) {
            // Replace with actual value
            $replacement = $this->escapeValue($fieldValue);
        } else {
            // ✅ Jika field tidak ada, replace dengan NULL (tidak akan match)
            $replacement = 'NULL';
        }
        
        $dynamicWhere = preg_replace($pattern, $replacement, $whereClause);
    } else {
        // Jika tidak ada pattern, biarkan seperti semula
        $dynamicWhere = $whereClause;
    }
    
    // Add WHERE keyword back
    return "WHERE " . $dynamicWhere;
}

/**
 * Extract table.field pattern from WHERE clause (single pattern)
 */
private function extractFieldPattern(string $whereClause): string {
    // Look for pattern like "controllers.userid = Member.id AS userid"
    // Extract "Member.id" from the right side of =
    if (preg_match('/=\s*([A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*)/i', $whereClause, $matches)) {
        return $matches[1];
    }
    
    // Fallback: look for any table.field pattern
    if (preg_match('/([A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*)/i', $whereClause, $matches)) {
        return $matches[1];
    }
    
    return '';
}

/**
 * Extract ALL table.field patterns from WHERE clause (for complex conditions)
 * Returns array of patterns with their corresponding field names
 * Hanya extract pattern dari main table alias jika provided
 */
private function extractAllFieldPatterns(string $whereClause, string $mainTableAlias = ''): array {
    $patterns = [];
    
    // Remove WHERE keyword if present
    $clause = preg_replace('/^WHERE\s+/i', '', trim($whereClause));
    
    // Find all table.field patterns (e.g., "demo.userid", "demo.status")
    // Pattern: table.field (bukan di dalam string literal)
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*\.([A-Za-z_][A-Za-z0-9_]*))/i', $clause, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $index => $match) {
            $fullPattern = $match[0]; // e.g., "demo.userid" or "user.id"
            $tableName = explode('.', $fullPattern)[0]; // e.g., "demo" or "user"
            $fieldName = $matches[2][$index][0]; // e.g., "userid" or "id"
            
            // ✅ Hanya extract pattern dari main table alias (jika provided)
            // Ini untuk avoid replace pattern dari subquery table (misalnya "user.id")
            if (!empty($mainTableAlias) && $tableName !== $mainTableAlias) {
                continue; // Skip pattern yang bukan dari main table
            }
            
            // Skip jika sudah ada (avoid duplicates)
            if (!isset($patterns[$fullPattern])) {
                $patterns[$fullPattern] = $fieldName;
            }
        }
    }
    
    return $patterns;
}

/**
 * Build dynamic WHERE clause with multiple field replacements
 * Supports complex conditions with AND/OR, multiple fields, etc.
 */
private function buildDynamicWhereClauseMultiple(string $originalWhere, array $fieldPatterns, array $mainResult): string {
    // Remove WHERE keyword if present
    $whereClause = preg_replace('/^WHERE\s+/i', '', trim($originalWhere));
    
    // Replace each pattern with actual value from main result
    foreach ($fieldPatterns as $pattern => $fieldName) {
        $fieldValue = $mainResult[$fieldName] ?? null;
        
        if ($fieldValue !== null) {
            $replacement = $this->escapeValue($fieldValue);
        } else {
            $replacement = 'NULL';
        }
        
        // Replace pattern dengan pattern yang lebih tepat untuk table.field
        // Gunakan lookbehind dan lookahead untuk memastikan tidak partial match
        // Pattern: (start of string, whitespace, atau operator) table.field (whitespace, operator, atau end)
        $escapedPattern = preg_quote($pattern, '/');
        $whereClause = preg_replace('/(?<![A-Za-z0-9_\.])' . $escapedPattern . '(?![A-Za-z0-9_\.])/i', $replacement, $whereClause);
    }
    
    // Add WHERE keyword back
    return "WHERE " . $whereClause;
}

/**
 * Extract subquery field from WHERE condition
 */
private function extractSubqueryField(string $whereClause): string {
    // Look for pattern like "controllers.userid = Member.id AS userid"
    // Extract the field before the first =
    if (preg_match('/^(\w+\.\w+)\s*=/i', trim($whereClause), $matches)) {
        return explode('.', $matches[1])[1] ?? '';
    }
    
    return 'userid'; // Default fallback
}

/**
 * Execute subquery - supports both normal build and type "petir"/"operasi"/"layar" with raw query
 */
private function executeSubquery(array $subqueryData): array {
    try {
        // ✅ Check if subquery has type "petir", "operasi", or "layar" with raw query
        if (isset($subqueryData['operasi']) && is_array($subqueryData['operasi'])) {
            $rawQueryData = $this->extractRawQuery($subqueryData['operasi']);
            
            if ($rawQueryData !== null) {
                // Use raw query execution for type "petir", "operasi", or "layar"
                // For subnested, we need to handle dynamic WHERE from main result
                $rawQuery = $rawQueryData['query'];
                $type = $rawQueryData['type'];
                
                // ✅ For type "layar": Gunakan query apa adanya, limit/offset dari query sendiri
                // Hanya handle dynamic WHERE jika ada, tapi tetap pertahankan limit/offset dari query
                if ($type === 'layar') {
                    $queryBase = trim($rawQuery);
                    
                    // ✅ Handle dynamic WHERE from subqueryData (dari buildDynamicSubquery)
                    // Jika ada WHERE dari subqueryData, replace WHERE di query dengan yang dinamis
                    $dynamicWhere = $subqueryData['where'] ?? null;
                    if ($dynamicWhere !== null && $dynamicWhere !== false && !empty(trim((string)$dynamicWhere))) {
                        // Hapus WHERE lama dari query jika ada
                        if (preg_match('/\s+WHERE\s+/i', $queryBase)) {
                            $parts = preg_split('/\s+WHERE\s+/i', $queryBase, 2);
                            if (count($parts) === 2) {
                                $beforeWhere = trim($parts[0]);
                                $afterWhere = $parts[1];
                                
                                // Cari keyword yang mengakhiri WHERE clause (GROUP BY, ORDER BY, LIMIT)
                                $endPattern = '/\s+(GROUP\s+BY|ORDER\s+BY|LIMIT)/i';
                                if (preg_match($endPattern, $afterWhere, $matches, PREG_OFFSET_CAPTURE)) {
                                    $endPos = $matches[0][1];
                                    $queryBase = $beforeWhere . ' ' . substr($afterWhere, $endPos);
                                } else {
                                    $queryBase = $beforeWhere;
                                }
                            }
                        }
                        
                        $queryBase = trim($queryBase);
                        
                        // Tambahkan WHERE baru yang dinamis
                        $whereClause = trim((string)$dynamicWhere);
                        $whereClause = preg_replace('/^WHERE\s+/i', '', $whereClause);
                        $queryBase .= "\nWHERE " . $whereClause;
                    }
                    
                    // Execute query dengan limit/offset dari query sendiri (tidak dihapus)
                    return $this->raw($queryBase);
                } elseif ($type === 'petir') {
                    // ✅ For type "petir": Use query as-is, replace WHERE dengan dinamis, hapus LIMIT/OFFSET
                    // Support GROUP BY dan ORDER BY dari subqueryData jika ada
                    // Hapus LIMIT dan OFFSET dari raw query (untuk subnested tidak perlu LIMIT)
                    $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $rawQuery);
                    $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $rawQuery);
                    $queryBase = trim($queryBase);
                    
                    // ✅ Handle dynamic WHERE from subqueryData (dari buildDynamicSubquery)
                    // Jika ada WHERE dari subqueryData, replace WHERE di query dengan yang dinamis
                    $dynamicWhere = $subqueryData['where'] ?? null;
                    if ($dynamicWhere !== null && $dynamicWhere !== false && !empty(trim((string)$dynamicWhere))) {
                        // ✅ Hapus WHERE lama dari query dengan lebih tepat (support JOIN/multi-table)
                        // Gunakan regex yang lebih tepat untuk menghapus WHERE clause
                        // Pattern: WHERE ... (hingga GROUP BY, ORDER BY, LIMIT, atau akhir string)
                        // Cari WHERE dan hapus sampai keyword berikutnya atau akhir string
                        if (preg_match('/\s+WHERE\s+/i', $queryBase)) {
                            // Split query menjadi bagian sebelum dan sesudah WHERE
                            $parts = preg_split('/\s+WHERE\s+/i', $queryBase, 2);
                            if (count($parts) === 2) {
                                $beforeWhere = trim($parts[0]);
                                $afterWhere = $parts[1];
                                
                                // Cari keyword yang mengakhiri WHERE clause
                                $endPattern = '/\s+(GROUP\s+BY|ORDER\s+BY|LIMIT)/i';
                                if (preg_match($endPattern, $afterWhere, $matches, PREG_OFFSET_CAPTURE)) {
                                    // Ada keyword setelah WHERE, ambil bagian setelah keyword
                                    $endPos = $matches[0][1];
                                    $queryBase = $beforeWhere . ' ' . substr($afterWhere, $endPos);
                                } else {
                                    // Tidak ada keyword, ambil hanya bagian sebelum WHERE
                                    $queryBase = $beforeWhere;
                                }
                            }
                        }
                        
                        $queryBase = trim($queryBase);
                        
                        // ✅ Pastikan LIMIT dihapus lagi (jika masih ada setelah WHERE dihapus)
                        $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $queryBase);
                        $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
                        $queryBase = trim($queryBase);
                        
                        // Tambahkan WHERE baru yang dinamis
                        $whereClause = trim((string)$dynamicWhere);
                        // Hapus "WHERE" dari whereClause jika ada
                        $whereClause = preg_replace('/^WHERE\s+/i', '', $whereClause);
                        $queryBase .= "\nWHERE " . $whereClause;
                    }
                    
                    // ✅ Support GROUP BY dari subqueryData (jika ada, override GROUP BY di query)
                    $dynamicGroup = $subqueryData['group'] ?? null;
                    if ($dynamicGroup !== null && $dynamicGroup !== false && !empty(trim((string)$dynamicGroup))) {
                        // Hapus GROUP BY lama dari query (jika ada)
                        $queryBase = preg_replace('/\s+GROUP\s+BY\s+[^\s]+(?:\s*,\s*[^\s]+)*/i', '', $queryBase);
                        $queryBase = trim($queryBase);
                        
                        // Tambahkan GROUP BY baru dari subqueryData
                        $groupClause = trim((string)$dynamicGroup);
                        $groupClause = preg_replace('/^GROUP\s+BY\s+/i', '', $groupClause);
                        $queryBase .= "\nGROUP BY " . $groupClause;
                    }
                    
                    // ✅ Support ORDER BY dari subqueryData (jika ada, override ORDER BY di query)
                    $dynamicOrder = $subqueryData['order'] ?? null;
                    if ($dynamicOrder !== null && $dynamicOrder !== false && !empty(trim((string)$dynamicOrder))) {
                        // Hapus ORDER BY lama dari query (jika ada)
                        $queryBase = preg_replace('/\s+ORDER\s+BY\s+[^\s]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[^\s]+(?:\s+(?:ASC|DESC))?)*/i', '', $queryBase);
                        $queryBase = trim($queryBase);
                        
                        // Tambahkan ORDER BY baru dari subqueryData
                        $orderClause = trim((string)$dynamicOrder);
                        $orderClause = preg_replace('/^ORDER\s+BY\s+/i', '', $orderClause);
                        $queryBase .= "\nORDER BY " . $orderClause;
                    }
                    
                    // ✅ Pastikan tidak ada LIMIT di query final (untuk subnested tidak perlu LIMIT)
                    $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $queryBase);
                    $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
                    $queryBase = trim($queryBase);
                    
                    // Execute query tanpa LIMIT
                    return $this->raw($queryBase);
                } else {
                    // Type "operasi": Use executeRawQuery logic but simplified for subquery
                    // Hapus WHERE, GROUP BY, ORDER BY, LIMIT, OFFSET dari raw query
                    // (Type "layar" sudah di-handle di atas, jadi ini hanya untuk "operasi")
                    $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $rawQuery);
                    $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $rawQuery);
                    $queryBase = preg_replace('/\s+ORDER\s+BY\s+[^\s]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[^\s]+(?:\s+(?:ASC|DESC))?)*/i', '', $queryBase);
                    $queryBase = preg_replace('/\s+GROUP\s+BY\s+[^\s]+(?:\s*,\s*[^\s]+)*/i', '', $queryBase);
                    $queryBase = preg_replace('/\s+WHERE\s+.*$/is', '', $queryBase);
                    $queryBase = trim($queryBase);
                    
                    // ✅ Gunakan WHERE, GROUP BY, ORDER BY dari subqueryData (yang sudah dinamis)
                    $dynamicWhere = $subqueryData['where'] ?? null;
                    $dynamicGroup = $subqueryData['group'] ?? null;
                    $dynamicOrder = $subqueryData['order'] ?? null;
                    
                    // Tambahkan WHERE jika ada
                    if ($dynamicWhere !== null && $dynamicWhere !== false && !empty(trim((string)$dynamicWhere))) {
                        $whereClause = trim((string)$dynamicWhere);
                        $whereClause = preg_replace('/^WHERE\s+/i', '', $whereClause);
                        $queryBase .= "\nWHERE " . $whereClause;
                    }
                    
                    // Tambahkan GROUP BY jika ada
                    if ($dynamicGroup !== null && $dynamicGroup !== false && !empty(trim((string)$dynamicGroup))) {
                        $groupClause = trim((string)$dynamicGroup);
                        $groupClause = preg_replace('/^GROUP\s+BY\s+/i', '', $groupClause);
                        $queryBase .= "\nGROUP BY " . $groupClause;
                    }
                    
                    // Tambahkan ORDER BY jika ada
                    if ($dynamicOrder !== null && $dynamicOrder !== false && !empty(trim((string)$dynamicOrder))) {
                        $orderClause = trim((string)$dynamicOrder);
                        $orderClause = preg_replace('/^ORDER\s+BY\s+/i', '', $orderClause);
                        $queryBase .= "\nORDER BY " . $orderClause;
                    }
                    
                    // Execute query
                    return $this->raw($queryBase);
                }
            }
        }
        
        // Default: Build subquery SQL using buildJoinSQL (untuk type "single" atau type normal lainnya)
        $subquerySql = $this->buildJoinSQL($subqueryData);
        
        // Execute subquery
        return $this->raw($subquerySql);
        
    } catch (\Exception $e) {
        error_log("Subquery execution error: " . $e->getMessage());
        return [];
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

/**
 * Get real table name from key using tablesIndex method
 */
private function getTableNameFromKey(string $aliasName, array $operations, string $keyOverride = ''): string {
    // Try to find the key from operations
    $key = null;
    
    if (!empty($keyOverride)) {
        $key = $keyOverride;
    } elseif (isset($operations[$aliasName])) {
        $key = $operations[$aliasName]['keyTarget'] ?? $operations[$aliasName]['keyIndex'] ?? null;
    }
    
    // If no key found, try to extract from the first operation
    if (empty($key)) {
        foreach ($operations as $op) {
            if (isset($op['aliasTarget']) && $op['aliasTarget'] === $aliasName) {
                $key = $op['keyTarget'];
                break;
            }
            if (isset($op['aliasIndex']) && $op['aliasIndex'] === $aliasName) {
                $key = $op['keyIndex'];
                break;
            }
        }
    }
    
    if (empty($key)) {
        throw new \InvalidArgumentException("Cannot find key for table alias: {$aliasName}");
    }
    
    // Convert key to integer and get real table name
    $keyInt = (int)$key;
    $realTableName = $this->tablesIndex($keyInt);
    
    if (empty($realTableName)) {
        throw new \InvalidArgumentException("Cannot find real table name for key: {$keyInt}");
    }
    
    return $realTableName;
}

/**
 * Extract raw query from operasi HANYA jika type adalah "operasi", "petir", atau "layar"
 * Returns array dengan query dan type jika found, null otherwise
 * 
 * IMPORTANT: Method ini HANYA berlaku untuk type "operasi", "petir", atau "layar"
 * Type lainnya akan diabaikan dan menggunakan build SQL normal
 */
private function extractRawQuery(array $operations): ?array {
    if (empty($operations) || !is_array($operations)) {
        return null;
    }
    
    foreach ($operations as $operation) {
        if (!is_array($operation)) {
            continue;
        }
        
        // ✅ HANYA cek jika type adalah "operasi", "petir", atau "layar" (strict check)
        // Type lainnya (INNER, LEFT, RIGHT, dll) akan diabaikan
        if (isset($operation['type']) && ($operation['type'] === 'operasi' || $operation['type'] === 'petir' || $operation['type'] === 'layar')) {
            if (isset($operation['query']) && !empty($operation['query'])) {
                return [
                    'query' => $operation['query'],
                    'type' => $operation['type']
                ];
            }
        }
        // Jika type bukan "operasi", "petir", atau "layar", skip dan lanjut ke operasi berikutnya
    }
    
    return null; // Tidak ada operasi dengan type "operasi", "petir", atau "layar", return null untuk build SQL normal
}

/**
 * Execute raw SQL query directly
 * Handles subquery and subnested processing if needed
 * - Type "operasi": Uses where, group, order, limit, offset from $data if provided
 * - Type "petir": Uses ONLY limit and offset from $data (query used as-is)
 * - Type "layar": Uses query as-is, limit/offset dari query sendiri (tidak dari data)
 */
private function executeRawQuery(string $rawQuery, array $data, string $type = 'operasi'): array {
    try {
        // Check if showSql is overridden in data (per-request setting)
        $showSql = isset($data['showSql']) ? (bool)$data['showSql'] : $this->showSql;
        
        // ✅ Untuk type "layar": Gunakan query apa adanya, limit/offset dari query sendiri
        if ($type === 'layar') {
            // Hanya hapus semicolon di akhir query (jika ada)
            $finalQuery = rtrim($rawQuery, " \t\n\r\0\x0B;");
            $finalQuery = trim($finalQuery);
            
            // Execute query langsung tanpa modifikasi (limit/offset sudah ada di query)
            $results = $this->raw($finalQuery);
            
            // Build total count query (hapus limit/offset untuk count)
            $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $finalQuery);
            $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
            $queryBase = trim($queryBase);
            
            $totalCountSql = $this->buildTotalCountFromRawQuery($queryBase, null, null);
            $totalCountResult = $this->raw($totalCountSql);
            
            // ✅ Pastikan totalCount dihitung dengan benar
            if (isset($totalCountResult[0]['total_count'])) {
                $totalCount = (int)$totalCountResult[0]['total_count'];
            } else {
                // Jika query count gagal, eksekusi query tanpa LIMIT untuk hitung manual
                $allResults = $this->raw($queryBase);
                $totalCount = is_array($allResults) ? count($allResults) : 0;
            }
        } elseif ($type === 'petir') {
            // ✅ Untuk type "petir": Hanya hapus LIMIT/OFFSET, gunakan query langsung
            // Hapus LIMIT dan OFFSET dari raw query
            $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $rawQuery);
            $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
            // ✅ Hapus semicolon di akhir query (jika ada) - hapus semua whitespace dan semicolon
            $queryBase = rtrim($queryBase, " \t\n\r\0\x0B;");
            $queryBase = trim($queryBase);
            
            // Hanya gunakan limit dan offset dari data
            $limit = $data['limit'] ?? null;
            $offset = $data['offset'] ?? null;
            
            $finalQuery = $queryBase;
            
            // Tambahkan LIMIT dan OFFSET dari data
            if ($limit !== null && is_numeric($limit) && $limit > 0) {
                $finalQuery .= "\nLIMIT " . (int)$limit;
                
                if ($offset !== null && is_numeric($offset) && $offset >= 0) {
                    $finalQuery .= " OFFSET " . (int)$offset;
                }
            }
            
            // Execute the final query (query as-is, hanya limit/offset dari data)
            $results = $this->raw($finalQuery);
            
            // Build total count query (tanpa limit/offset, query sudah punya WHERE di dalamnya)
            // Untuk type "petir", query sudah lengkap dengan WHERE, jadi langsung convert ke COUNT
            $totalCountSql = $this->buildTotalCountFromRawQuery($queryBase, null, null);
            $totalCountResult = $this->raw($totalCountSql);
            
            // ✅ Pastikan totalCount dihitung dengan benar, bukan dari count($results)
            if (isset($totalCountResult[0]['total_count'])) {
                $totalCount = (int)$totalCountResult[0]['total_count'];
            } else {
                // Jika query count gagal, eksekusi query tanpa LIMIT untuk hitung manual
                $countQuery = $queryBase; // Query sudah tanpa LIMIT/OFFSET
                $allResults = $this->raw($countQuery);
                $totalCount = is_array($allResults) ? count($allResults) : 0;
            }
        } else {
            // ✅ Untuk type "operasi": Hapus WHERE, GROUP BY, ORDER BY, LIMIT, OFFSET dari raw query
            $queryBase = $rawQuery;
            
            // Hapus LIMIT dan OFFSET terlebih dahulu
            $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $queryBase);
            $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
            // ✅ Hapus semicolon di akhir query (jika ada)
            $queryBase = rtrim($queryBase, ';');
            $queryBase = trim($queryBase);
            
            // Hapus ORDER BY (biasanya sebelum LIMIT)
            $queryBase = preg_replace('/\s+ORDER\s+BY\s+[^\s]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[^\s]+(?:\s+(?:ASC|DESC))?)*/i', '', $queryBase);
            
            // Hapus GROUP BY (biasanya sebelum ORDER BY)
            $queryBase = preg_replace('/\s+GROUP\s+BY\s+[^\s]+(?:\s*,\s*[^\s]+)*/i', '', $queryBase);
            
            // Hapus WHERE (split berdasarkan WHERE, ambil bagian sebelum WHERE)
            $queryBase = preg_replace('/\s+WHERE\s+.*$/is', '', $queryBase);
            
            $queryBase = trim($queryBase);
            
            // ✅ Gunakan kondisi dari $data jika tersedia (selain false)
            $where = $data['where'] ?? null;
            $group = $data['group'] ?? null;
            $order = $data['order'] ?? null;
            $limit = $data['limit'] ?? null;
            $offset = $data['offset'] ?? null;
            
            // Build final query dengan urutan: WHERE -> GROUP BY -> ORDER BY -> LIMIT -> OFFSET
            $finalQuery = $queryBase;
            
            // Tambahkan WHERE jika ada (selain false)
            if ($where !== null && $where !== false && !empty(trim((string)$where))) {
                $whereClause = trim((string)$where);
                // Jika tidak dimulai dengan WHERE, tambahkan
                if (stripos($whereClause, 'WHERE') !== 0) {
                    $finalQuery .= "\nWHERE " . $whereClause;
                } else {
                    $finalQuery .= "\n" . $whereClause;
                }
            }
            
            // Tambahkan GROUP BY jika ada (selain false)
            if ($group !== null && $group !== false && !empty(trim((string)$group))) {
                $groupClause = trim((string)$group);
                // Jika tidak dimulai dengan GROUP BY, tambahkan
                if (stripos($groupClause, 'GROUP BY') !== 0) {
                    $finalQuery .= "\nGROUP BY " . $groupClause;
                } else {
                    $finalQuery .= "\n" . $groupClause;
                }
            }
            
            // Tambahkan ORDER BY jika ada (selain false)
            if ($order !== null && $order !== false && !empty(trim((string)$order))) {
                $orderClause = trim((string)$order);
                // Jika tidak dimulai dengan ORDER BY, tambahkan
                if (stripos($orderClause, 'ORDER BY') !== 0) {
                    $finalQuery .= "\nORDER BY " . $orderClause;
                } else {
                    $finalQuery .= "\n" . $orderClause;
                }
            }
            
            // Tambahkan LIMIT dan OFFSET dari data
            if ($limit !== null && is_numeric($limit) && $limit > 0) {
                $finalQuery .= "\nLIMIT " . (int)$limit;
                
                if ($offset !== null && is_numeric($offset) && $offset >= 0) {
                    $finalQuery .= " OFFSET " . (int)$offset;
                }
            }
            
            // Execute the final query with conditions from data
            $results = $this->raw($finalQuery);
            
            // Build total count query dengan where dan group dari data (tanpa limit/offset)
            $totalCountSql = $this->buildTotalCountFromRawQuery($queryBase, $where, $group);
            $totalCountResult = $this->raw($totalCountSql);
            $totalCount = isset($totalCountResult[0]['total_count']) ? (int)$totalCountResult[0]['total_count'] : count($results);
        }
        
        // Process subquery if exists
        if (isset($data['subquery']) && !empty($data['subquery'])) {
            $results = $this->processSubquery($results, $data['subquery'], $data);
        }
        
        // Process subnested if exists
        if (isset($data['subnested']) && !empty($data['subnested'])) {
            $results = $this->processSubNested($results, $data['subnested'], $data);
        }
        
        return [
            'success' => true,
            'sql' => $showSql ? $finalQuery : false,
            'response' => $results,
            'count' => count($results),
            'totalCount' => $totalCount
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response' => [],
            'sql' => null
        ];
    }
}

/**
 * Build total count query from raw query
 * Uses where and group from data if provided
 * Removes LIMIT/OFFSET, ORDER BY and wraps with COUNT(*)
 */
private function buildTotalCountFromRawQuery(string $rawQuery, $where = null, $group = null): string {
    $queryBase = trim($rawQuery);
    
    // ✅ Hapus ORDER BY untuk efisiensi (tidak mempengaruhi COUNT)
    $queryBase = preg_replace('/\s+ORDER\s+BY\s+[^\s]+(?:\s+(?:ASC|DESC))?(?:\s*,\s*[^\s]+(?:\s+(?:ASC|DESC))?)*/i', '', $queryBase);
    
    // Tambahkan WHERE jika ada (selain false)
    if ($where !== null && $where !== false && !empty(trim((string)$where))) {
        $whereClause = trim((string)$where);
        // Jika tidak dimulai dengan WHERE, tambahkan
        if (stripos($whereClause, 'WHERE') !== 0) {
            $queryBase .= "\nWHERE " . $whereClause;
        } else {
            $queryBase .= "\n" . $whereClause;
        }
    }
    
    // Tambahkan GROUP BY jika ada (selain false)
    if ($group !== null && $group !== false && !empty(trim((string)$group))) {
        $groupClause = trim((string)$group);
        // Jika tidak dimulai dengan GROUP BY, tambahkan
        if (stripos($groupClause, 'GROUP BY') !== 0) {
            $queryBase .= "\nGROUP BY " . $groupClause;
        } else {
            $queryBase .= "\n" . $groupClause;
        }
    }
    
    // Check if query has GROUP BY (dari data atau dari query)
    if (preg_match('/\s+GROUP\s+BY\s+/i', $queryBase)) {
        // If GROUP BY exists, wrap the query to count groups
        return "SELECT COUNT(*) as total_count FROM ({$queryBase}) as grouped_query";
    }
    
    // Otherwise, replace SELECT clause with COUNT(*)
    // Find the SELECT part and replace it (handle multiline SELECT)
    $countQuery = preg_replace(
        '/^SELECT\s+.*?\s+FROM/is',
        'SELECT COUNT(*) as total_count FROM',
        $queryBase,
        1
    );
    
    return $countQuery;
}

/**
 * Process nestedAnalysis result and perform analysis
 * This method accepts the result from nestedAnalysis and performs additional analysis
 * 
 * @param array $nestedResult Result from nestedAnalysis method
 * @param array $originalQueryData Original query data used for nestedAnalysis (optional, but recommended)
 * @param array $analysisConfig Additional analysis configuration (where, group, order, etc.)
 * @return array Analysis result
 */
public function fromNestedAnalysis(array $nestedResult, array $originalQueryData = [], array $analysisConfig = []): array {
    try {
        // Extract data from nestedAnalysis result
        $nestedData = $nestedResult['data'] ?? $nestedResult;
        
        if (!isset($nestedData['success']) || !$nestedData['success']) {
            throw new \InvalidArgumentException('Invalid nestedAnalysis result: success is false');
        }
        
        // Check if analysisConfig has any meaningful changes
        $hasConfigChanges = false;
        $configKeys = ['where', 'group', 'order', 'limit', 'offset', 'access', 'showSql'];
        foreach ($configKeys as $key) {
            if (isset($analysisConfig[$key]) && $analysisConfig[$key] !== false && $analysisConfig[$key] !== null) {
                $hasConfigChanges = true;
                break;
            }
        }
        
        // If no config changes and original query data matches, return nestedAnalysis result directly
        if (!$hasConfigChanges && !empty($originalQueryData)) {
            // Check if original query data matches nestedAnalysis result
            $originalWhere = $originalQueryData['where'] ?? false;
            $originalGroup = $originalQueryData['group'] ?? false;
            $originalOrder = $originalQueryData['order'] ?? false;
            $originalLimit = $originalQueryData['limit'] ?? null;
            $originalOffset = $originalQueryData['offset'] ?? null;
            
            // If all match, return nestedAnalysis result (optimization)
            if (($originalWhere === false || $originalWhere === null) &&
                ($originalGroup === false || $originalGroup === null) &&
                ($originalOrder === false || $originalOrder === null)) {
                // Return nestedAnalysis result directly (no need to re-query)
                return $nestedData;
            }
        }
        
        // If original query data is provided, use it directly (more efficient)
        if (!empty($originalQueryData) && isset($originalQueryData['operasi']) && isset($originalQueryData['alias'])) {
            // Start with original query data
            $analysisData = $originalQueryData;
            
            // Override with analysis config if provided (only non-false/non-null values)
            if (isset($analysisConfig['where']) && $analysisConfig['where'] !== false && $analysisConfig['where'] !== null) {
                $analysisData['where'] = $analysisConfig['where'];
            }
            if (isset($analysisConfig['group']) && $analysisConfig['group'] !== false && $analysisConfig['group'] !== null) {
                $analysisData['group'] = $analysisConfig['group'];
            }
            if (isset($analysisConfig['order']) && $analysisConfig['order'] !== false && $analysisConfig['order'] !== null) {
                $analysisData['order'] = $analysisConfig['order'];
            }
            if (isset($analysisConfig['limit']) && $analysisConfig['limit'] !== null) {
                $analysisData['limit'] = $analysisConfig['limit'];
            }
            if (isset($analysisConfig['offset']) && $analysisConfig['offset'] !== null) {
                $analysisData['offset'] = $analysisConfig['offset'];
            }
            if (isset($analysisConfig['access'])) {
                $analysisData['access'] = $analysisConfig['access'];
            }
            if (isset($analysisConfig['showSql'])) {
                $analysisData['showSql'] = $analysisConfig['showSql'];
            }
            
            // Execute analysis using existing index method
            return $this->index($analysisData);
        }
        
        // Fallback: Extract SQL from nestedAnalysis result and parse it
        $sql = $nestedData['sql'] ?? null;
        
        if (empty($sql)) {
            throw new \InvalidArgumentException('SQL query not found in nestedAnalysis result and original query data not provided');
        }
        
        // Build analysis data from SQL and config
        // Extract table name and operations from SQL
        $analysisData = $this->buildAnalysisDataFromSql($sql, $analysisConfig);
        
        // Execute analysis using existing index method
        return $this->index($analysisData);
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response' => [],
            'sql' => null
        ];
    }
}

/**
 * Build analysis data structure from SQL query
 * Extracts table names, aliases, and operations from SQL
 * 
 * @param string $sql SQL query string
 * @param array $config Additional configuration (where, group, order, limit, offset, keyIndex)
 * @return array Analysis data structure
 */
private function buildAnalysisDataFromSql(string $sql, array $config = []): array {
    // Parse SQL to extract components
    $sql = trim($sql);
    
    // Extract SELECT fields (aliases)
    $aliases = [];
    if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $sql, $matches)) {
        $selectPart = trim($matches[1]);
        // Split by comma, but handle AS clauses
        $fields = preg_split('/,\s*(?=(?:[^\'"]*(?:\'|")[^\'"]*(?:\'|"))*[^\'"]*$)/', $selectPart);
        foreach ($fields as $field) {
            $field = trim($field);
            if (!empty($field)) {
                $aliases[] = $field;
            }
        }
    }
    
    // Extract table name from FROM clause
    $tableName = '';
    $tableAlias = '';
    if (preg_match('/FROM\s+(\w+)\s+AS\s+(\w+)/i', $sql, $matches)) {
        $tableName = $matches[1];
        $tableAlias = $matches[2];
    } elseif (preg_match('/FROM\s+(\w+)/i', $sql, $matches)) {
        $tableName = $matches[1];
        $tableAlias = $matches[1];
    }
    
    // Get keyIndex from config or try to find it
    $keyIndex = $config['keyIndex'] ?? null;
    if ($keyIndex === null && !empty($tableName)) {
        $keyIndex = $this->findKeyIndexFromTableName($tableName);
    }
    
    // Build operations structure
    $operations = [];
    if (!empty($tableAlias) && $keyIndex !== null) {
        $operations[$tableAlias] = [
            'type' => 'single',
            'index' => '',
            'target' => '',
            'keyIndex' => $keyIndex,
            'condition' => '',
            'keyTarget' => '',
            'aliasIndex' => $tableAlias,
            'aliasTarget' => ''
        ];
    }
    
    // Build analysis data
    $analysisData = [
        'alias' => $aliases,
        'operasi' => $operations,
        'tabelName' => [$tableAlias],
        'access' => $config['access'] ?? 'public',
        'showSql' => $config['showSql'] ?? $this->showSql
    ];
    
    // Add optional parameters from config
    if (isset($config['where']) && $config['where'] !== false) {
        $analysisData['where'] = $config['where'];
    }
    if (isset($config['group']) && $config['group'] !== false) {
        $analysisData['group'] = $config['group'];
    }
    if (isset($config['order']) && $config['order'] !== false) {
        $analysisData['order'] = $config['order'];
    }
    if (isset($config['limit']) && $config['limit'] !== null) {
        $analysisData['limit'] = $config['limit'];
    }
    if (isset($config['offset']) && $config['offset'] !== null) {
        $analysisData['offset'] = $config['offset'];
    }
    
    return $analysisData;
}

/**
 * Find keyIndex from table name using tablesIndex method
 * Uses a more efficient approach by checking common key ranges
 * 
 * @param string $tableName Real table name
 * @return int|string Key index
 */
private function findKeyIndexFromTableName(string $tableName): int {
    // Try common key ranges first (more efficient)
    $ranges = [
        [1, 1000],
        [1000, 10000],
        [10000, 100000],
        [100000, 1000000]
    ];
    
    foreach ($ranges as $range) {
        for ($i = $range[0]; $i < $range[1]; $i++) {
            try {
                $foundTable = $this->tablesIndex($i);
                if ($foundTable === $tableName) {
                    return $i;
                }
            } catch (\Exception $e) {
                // Continue searching
                continue;
            }
        }
    }
    
    // If not found, throw exception
    throw new \InvalidArgumentException("Cannot find keyIndex for table: {$tableName}. Please provide keyIndex in config.");
}

/**
 * Adjust alias for GROUP BY compatibility
 * When GROUP BY is used, only columns in GROUP BY or aggregate functions are allowed
 * 
 * @param array $aliases Original aliases
 * @param string $groupBy GROUP BY clause
 * @param string $countAlias Custom alias name for COUNT(*) (default: 'total')
 * @return array Adjusted aliases
 */
private function adjustAliasForGroupBy(array $aliases, string $groupBy, string $countAlias = 'total'): array {
    // Extract columns from GROUP BY clause
    $groupByColumns = [];
    $groupByClean = preg_replace('/^GROUP\s+BY\s+/i', '', trim($groupBy));
    $groupByParts = preg_split('/\s*,\s*/', $groupByClean);
    
    foreach ($groupByParts as $part) {
        $part = trim($part);
        if (!empty($part)) {
            $groupByColumns[] = $part;
        }
    }
    
    // Check if aliases already contain aggregate functions
    $hasAggregate = false;
    foreach ($aliases as $alias) {
        if (preg_match('/\b(COUNT|SUM|AVG|MAX|MIN|GROUP_CONCAT)\s*\(/i', $alias)) {
            $hasAggregate = true;
            break;
        }
    }
    
    // Filter aliases to only include those in GROUP BY or aggregate functions
    $adjustedAliases = [];
    $hasCountInAliases = false;
    
    // First, add all aggregate functions
    foreach ($aliases as $alias) {
        $aliasClean = trim($alias);
        if (preg_match('/\b(COUNT|SUM|AVG|MAX|MIN|GROUP_CONCAT)\s*\(/i', $aliasClean)) {
            $adjustedAliases[] = $aliasClean;
            // Check if it's COUNT(*)
            if (preg_match('/\bCOUNT\s*\(\s*\*\s*\)/i', $aliasClean)) {
                $hasCountInAliases = true;
            }
        }
    }
    
    // Then, add aliases that match GROUP BY columns
    foreach ($aliases as $alias) {
        $aliasClean = trim($alias);
        
        // Skip if already added (aggregate)
        if (preg_match('/\b(COUNT|SUM|AVG|MAX|MIN|GROUP_CONCAT)\s*\(/i', $aliasClean)) {
            continue;
        }
        
        // Extract the column part (before AS if exists)
        $columnPart = preg_replace('/\s+AS\s+.*$/i', '', $aliasClean);
        $columnPart = trim($columnPart);
        
        // Check if this column is in GROUP BY
        foreach ($groupByColumns as $gbCol) {
            $gbColClean = trim($gbCol);
            // Compare: exact match or column name match
            if ($columnPart === $gbColClean || 
                stripos($gbColClean, $columnPart) !== false || 
                stripos($columnPart, $gbColClean) !== false) {
                $adjustedAliases[] = $aliasClean;
                break;
            }
        }
    }
    
    // If no aliases matched, use GROUP BY columns directly and add COUNT(*)
    if (empty($adjustedAliases)) {
        foreach ($groupByColumns as $gbCol) {
            $adjustedAliases[] = $gbCol;
        }
        if (!$hasAggregate || !$hasCountInAliases) {
            $adjustedAliases[] = "COUNT(*) AS {$countAlias}";
        }
    } elseif (!$hasAggregate || !$hasCountInAliases) {
        // If we have GROUP BY columns but no COUNT(*), add COUNT(*) with custom alias
        $adjustedAliases[] = "COUNT(*) AS {$countAlias}";
    }
    
    return $adjustedAliases;
}

/**
 * Calculate percentage for each result item
 * Adds 'percent' field to each item based on count/total value
 * 
 * @param array $results Query results
 * @param array $data Data configuration (to get countAlias)
 * @return array Results with percent field added
 */
private function calculatePercent(array $results, array $data): array {
    if (empty($results)) {
        return $results;
    }
    
    // Get count alias name (default: 'total')
    $countAlias = $data['countAlias'] ?? 'total';
    
    // Calculate total sum from all count values
    $totalSum = 0;
    foreach ($results as $result) {
        if (isset($result[$countAlias])) {
            $totalSum += (float)$result[$countAlias];
        }
    }
    
    // If total sum is 0, return results without percent
    if ($totalSum == 0) {
        return $results;
    }
    
    // Add percent field to each result
    foreach ($results as &$result) {
        if (isset($result[$countAlias])) {
            $countValue = (float)$result[$countAlias];
            $percent = ($countValue / $totalSum) * 100;
            // Round to 2 decimal places
            $result['percent'] = round($percent, 2);
        } else {
            $result['percent'] = 0;
        }
    }
    unset($result); // Unset reference
    
    return $results;
}

/**
 * Calculate progress value for each result item (untuk progress bar)
 * Adds 'progres' field to each item based on count/total value
 * Value is in percentage format (0-100) without rounding
 * 
 * @param array $results Query results
 * @param array $data Data configuration (to get countAlias)
 * @return array Results with progres field added
 */
private function calculateProgres(array $results, array $data): array {
    if (empty($results)) {
        return $results;
    }
    
    // Get count alias name (default: 'total')
    $countAlias = $data['countAlias'] ?? 'total';
    
    // Calculate total sum from all count values
    $totalSum = 0;
    foreach ($results as $result) {
        if (isset($result[$countAlias])) {
            $totalSum += (float)$result[$countAlias];
        }
    }
    
    // If total sum is 0, return results without progres
    if ($totalSum == 0) {
        return $results;
    }
    
    // Add progres field to each result (percentage 0-100, dibulatkan ke integer)
    foreach ($results as &$result) {
        if (isset($result[$countAlias])) {
            $countValue = (float)$result[$countAlias];
            $progres = ($countValue / $totalSum) * 100;
            // Dibulatkan ke integer untuk progress bar
            $result['progres'] = (int)round($progres);
        } else {
            $result['progres'] = 0;
        }
    }
    unset($result); // Unset reference
    
    return $results;
}

/**
 * Direct analysis method - accepts the same format as nestedAnalysis
 * This allows you to use Analysis method directly with the same query structure
 * 
 * @param array $data Query data (same format as nestedAnalysis)
 * @param array $analysisConfig Optional analysis configuration (where, group, order, limit, offset, etc.)
 * @return array Analysis result
 */
public function directAnalysis(array $data, array $analysisConfig = []): array {
    // If analysisConfig is provided, merge it with data
    if (!empty($analysisConfig)) {
        // Start with original data (keep all original data including alias)
        $mergedData = $data;
        
        // Override with analysis config - only override specific keys, NEVER override alias
        if (array_key_exists('where', $analysisConfig)) {
            $mergedData['where'] = $analysisConfig['where'];
        }
        if (array_key_exists('group', $analysisConfig)) {
            $mergedData['group'] = $analysisConfig['group'];
        }
        if (array_key_exists('order', $analysisConfig)) {
            $mergedData['order'] = $analysisConfig['order'];
        }
        // ✅ Support limit override (termasuk false untuk menghapus limit)
        if (array_key_exists('limit', $analysisConfig)) {
            $mergedData['limit'] = $analysisConfig['limit'];
        }
        if (array_key_exists('offset', $analysisConfig)) {
            $mergedData['offset'] = $analysisConfig['offset'];
        }
        if (array_key_exists('access', $analysisConfig)) {
            $mergedData['access'] = $analysisConfig['access'];
        }
        if (array_key_exists('showSql', $analysisConfig)) {
            $mergedData['showSql'] = $analysisConfig['showSql'];
        }
        
        // ✅ Support override alias from analysisConfig (untuk analisis khusus)
        // Jika alias ada di analysisConfig, gunakan untuk override alias yang ada
        if (array_key_exists('alias', $analysisConfig)) {
            // Jika alias adalah string, convert ke array
            if (is_string($analysisConfig['alias'])) {
                $mergedData['alias'] = [$analysisConfig['alias']];
            } elseif (is_array($analysisConfig['alias'])) {
                $mergedData['alias'] = $analysisConfig['alias'];
            }
        }
        
        // ✅ Support custom count alias name (default: 'total')
        if (array_key_exists('countAlias', $analysisConfig)) {
            $mergedData['countAlias'] = $analysisConfig['countAlias'];
        }
        
        // ✅ Support percent calculation
        if (array_key_exists('percent', $analysisConfig)) {
            $mergedData['percent'] = $analysisConfig['percent'];
        }
        
        // ✅ Support progres calculation (untuk progress bar)
        if (array_key_exists('progres', $analysisConfig)) {
            $mergedData['progres'] = $analysisConfig['progres'];
        }
        
        // Use merged data
        return $this->index($mergedData);
    }
    
    // If no analysisConfig, use data as-is
    return $this->index($data);
}


}
