<?php
declare(strict_types=1);
namespace App\Models\Office;
use App\System\NexaModel;
use Phpfastcache\Helper\Psr16Adapter;

/**
 * Formula class untuk operasi aritmatika dan nested formula
 */
class JoinTabel extends NexaModel{
    private $formula;
    private $showSql = false; // Default: tampilkan SQL
    private static ?Psr16Adapter $cache = null;
    private const CACHE_TTL = 60; // detik

    private static function getCache(): Psr16Adapter {
        if (self::$cache === null) {
            $cacheConfig = new \Phpfastcache\Drivers\Files\Config([
                'path' => sys_get_temp_dir() . '/nexacache',
            ]);
            self::$cache = new Psr16Adapter('files', $cacheConfig);
        }
        return self::$cache;
    }

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
    
public function joinQuery(array $data) {
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

    $this->normalizeClientPagination($data);
    
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

        // Build the SQL query
        $sql = $this->buildJoinSQL($data);
        
        // ── Cache layer (skip for showSql/preview requests) ───────────────
        $useCache = !$showSql && empty($data['subquery']) && empty($data['subnested']);
        if ($useCache) {
            $cacheKey = 'jq_' . md5(serialize($data));
            $cache    = self::getCache();
            $cached   = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Execute the main (paginated) query
        $results = $this->raw($sql);

        // Skip COUNT(*) when caller already knows the total (e.g. page > 0).
        // This avoids a full-table scan on every page navigation.
        $skipCount = isset($data['skipCount']) && $data['skipCount'] === true;
        if ($skipCount) {
            $totalCount = -1; // Signal to frontend: ignore this value, keep existing total
        } else {
            $totalCountSql    = $this->buildTotalCountSQL($data);
            $totalCountResult = $this->raw($totalCountSql);
            $totalCount       = isset($totalCountResult[0]['total_count']) ? (int)$totalCountResult[0]['total_count'] : 0;
        }
        
        // Process subquery if exists
        if (isset($data['subquery']) && !empty($data['subquery'])) {
            $results = $this->processSubquery($results, $data['subquery'], $data);
        }

        if (isset($data['subnested']) && !empty($data['subnested'])) {
            $results = $this->processSubNested($results, $data['subnested'], $data);
        }

        $result = [
            'success'    => true,
            'sql'        => $showSql ? $sql : false,
            'response'   => $results,
            'count'      => count($results),
            'totalCount' => $totalCount,
        ];

        if ($useCache) {
            self::getCache()->set($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
        
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
 * Sinkronkan limit/offset dari alias klien (pageSize, per_page, dll.) agar sqlview bertipe "layar"
 * bisa mengganti LIMIT bila hanya alias yang terkirim atau limit tidak sampai PHP.
 */
private function normalizeClientPagination(array &$data): void {
    $lim = $data['limit'] ?? null;
    if ($lim === null || $lim === '' || !is_numeric($lim) || (int)$lim <= 0) {
        foreach (['pageSize', 'pagesize', 'page_size', 'perPage', 'per_page'] as $alias) {
            if (!isset($data[$alias])) {
                continue;
            }
            $v = $data[$alias];
            if ($v !== null && $v !== '' && is_numeric($v) && (int)$v > 0) {
                $data['limit'] = (int)$v;
                break;
            }
        }
    }

    $hasOffsetKey = array_key_exists('offset', $data);
    $off = $hasOffsetKey ? $data['offset'] : null;
    if ((!$hasOffsetKey || $off === null || $off === '')
        && isset($data['page'], $data['limit'])
        && is_numeric($data['page'])
        && is_numeric($data['limit'])
        && (int)$data['limit'] > 0
        && (int)$data['page'] >= 0
    ) {
        $data['offset'] = (int)$data['page'] * (int)$data['limit'];
    }
}

/**
 * Gabung fragment WHERE ke SELECT sqlview biasa — sisipkan sebelum GROUP BY / ORDER BY / LIMIT luar.
 */
private function mergeWhereIntoSelectSql(string $sql, string $whereFragment): string {
    $clause = preg_replace('/^\s*WHERE\s+/i', '', trim($whereFragment));
    if ($clause === '') {
        return $sql;
    }
    $s = trim(rtrim($sql, " \t\n\r\0\x0B;"));
    foreach (["/\s+GROUP\s+BY\s+/i", "/\s+ORDER\s+BY\s+/i", "/\s+LIMIT\s+\d+/i"] as $re) {
        if (preg_match($re, $s, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $before = rtrim(substr($s, 0, $pos));
            $after = substr($s, $pos);
            return trim($this->appendWherePredicateBeforeTail($before, $after, $clause));
        }
    }
    return trim($this->appendWherePredicateBeforeTail($s, '', $clause));
}

private function appendWherePredicateBeforeTail(string $beforeSql, string $afterSql, string $predicate): string {
    $beforeSql = rtrim($beforeSql);
    $afterSql = ltrim((string)$afterSql);
    if (preg_match("/\bWHERE\b/i", $beforeSql)) {
        $glue = "\nAND ({$predicate}) ";
    } else {
        $glue = "\nWHERE ({$predicate}) ";
    }
    if ($afterSql === '') {
        return $beforeSql . $glue;
    }
    return $beforeSql . $glue . $afterSql;
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
    $search = isset($data['search']) ? trim((string)$data['search']) : '';

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
    if (!empty($where)) {
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
    
    // Add server-side search: OR LIKE across all selected columns
    if (!empty($search)) {
        $escapedSearch = addslashes($search);
        $likeParts = [];
        foreach ($aliases as $alias) {
            // alias format: "table.field AS name" or "table.field"
            if (preg_match('/^\s*([\w.]+)(?:\s+AS\s+\w+)?\s*$/i', $alias, $m)) {
                $col = $m[1];
                $likeParts[] = "{$col} LIKE '%{$escapedSearch}%'";
            }
        }
        if (!empty($likeParts)) {
            $searchClause = '(' . implode(' OR ', $likeParts) . ')';
            // Check if WHERE already exists in SQL
            if (stripos($sql, 'WHERE') !== false) {
                $sql .= " AND " . $searchClause;
            } else {
                $sql .= "\nWHERE " . $searchClause;
            }
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
    if ($limit !== null && is_numeric($limit) && $limit > 0) {
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
    $aliases    = $data['alias']  ?? [];
    $where = $data['where'] ?? null;
    $group = $data['group'] ?? null;
    $search = isset($data['search']) ? trim((string)$data['search']) : '';
    
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
    if (!empty($where)) {
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

    // Add server-side search: same LIKE conditions as in buildJoinSQL
    if (!empty($search)) {
        $escapedSearch = addslashes($search);
        $likeParts = [];
        foreach ($aliases as $alias) {
            if (preg_match('/^\s*([\w.]+)(?:\s+AS\s+\w+)?\s*$/i', $alias, $m)) {
                $likeParts[] = $m[1] . " LIKE '%{$escapedSearch}%'";
            }
        }
        if (!empty($likeParts)) {
            $searchClause = '(' . implode(' OR ', $likeParts) . ')';
            if (stripos($sql, 'WHERE') !== false) {
                $sql .= " AND " . $searchClause;
            } else {
                $sql .= "\nWHERE " . $searchClause;
            }
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
                    $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
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
 * - Type "layar": `$data['where']` (kalau ada) digabung ke SQL sebelum ORDER/LIMIT;
 *   LIMIT/OFFSET dari query bisa diganti bila `$data['limit']` (>0).
 */
private function executeRawQuery(string $rawQuery, array $data, string $type = 'operasi'): array {
    try {
        // Check if showSql is overridden in data (per-request setting)
        $showSql = isset($data['showSql']) ? (bool)$data['showSql'] : $this->showSql;
        
        // ✅ Type "layar": By default pakai query sqlview apa adanya; bila klien kirim limit (NexaPayload pageSize), override paginasi
        if ($type === 'layar') {
            $finalQuery = rtrim($rawQuery, " \t\n\r\0\x0B;");
            $finalQuery = trim($finalQuery);

            $whereLayer = $data['where'] ?? null;
            if ($whereLayer !== null && $whereLayer !== false && trim((string)$whereLayer) !== '') {
                $finalQuery = $this->mergeWhereIntoSelectSql($finalQuery, (string)$whereLayer);
            }

            $limit = $data['limit'] ?? null;
            $offset = $data['offset'] ?? null;

            if ($limit !== null && is_numeric($limit) && (int)$limit > 0) {
                $finalQuery = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $finalQuery);
                $finalQuery = preg_replace('/\s+OFFSET\s+\d+/i', '', $finalQuery);
                $finalQuery = trim($finalQuery);
                $finalQuery .= "\nLIMIT " . (int)$limit;
                if ($offset !== null && is_numeric($offset) && (int)$offset >= 0) {
                    $finalQuery .= ' OFFSET ' . (int)$offset;
                }
            }

            $results = $this->raw($finalQuery);

            $queryBase = preg_replace('/\s+LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $finalQuery);
            $queryBase = preg_replace('/\s+OFFSET\s+\d+/i', '', $queryBase);
            $queryBase = trim($queryBase);

            $totalCountSql = $this->buildTotalCountFromRawQuery($queryBase, null, null);
            $totalCountResult = $this->raw($totalCountSql);
            $totalCount = (int)($totalCountResult[0]['total_count'] ?? 0);
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
            $totalCount = (int)($totalCountResult[0]['total_count'] ?? 0);
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
    
    // Always wrap in subquery — handles complex SELECT (expressions, subqueries, CTEs, DISTINCT)
    // and avoids loading all rows into memory when regex rewrite fails on complex SELECTs
    return "SELECT COUNT(*) as total_count FROM ({$queryBase}) as count_wrapper";
}


}
