<?php
namespace App\System\Storage;
use App\System\Storage\NexaDb;
use App\System\Helpers\NexaRaw;

class NexaBrif {
    private $db;
    private $table;
    private $select = '*';
    private $where = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    private $groupBy = [];
    private $having = [];
    private $unions = [];

    public function __construct() {
        try {
            //error_log("NexaBrif - Initializing...");
            $this->db = NexaDb::getInstance()->getConnection();
            if (!$this->db) {
                throw new \Exception("Failed to get database instance");
            }
            //error_log("NexaBrif - NexaDb instance created successfully");
        } catch (\Exception $e) {
            error_log("NexaBrif initialization error: " . $e->getMessage());
            throw new \Exception("Failed to initialize database connection: " . $e->getMessage());
        }
    }

    private function ensureConnection() {
        try {
            // //error_log("NexaBrif - Ensuring connection...");
            if (!$this->db) {
                ////error_log("NexaBrif - Not connected, attempting connection...");
                $this->db = NexaDb::getInstance()->getConnection();
                
                if (!$this->db) {
                    throw new \Exception("Database connection test failed");
                }
                // //error_log("NexaBrif - Connection established and tested successfully");
            } else {
                // //error_log("NexaBrif - Already connected");
            }
        } catch (\Exception $e) {
            //error_log("NexaBrif connection error: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Pilih tabel yang akan digunakan
     */
    public function table($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Pilih kolom yang akan diambil
     */
    public function select($columns) {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE
     */
    public function where($column, $operator = null, $value = null, $boolean = 'AND') {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where[] = [$key, '=', $val, 'AND'];
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            
            // Special handling for LIKE queries
            if (strtoupper($operator) === 'LIKE') {
                // If the value already contains wildcards, use it as is
                if (strpos($value, '%') !== false) {
                    $this->where[] = [$column, $operator, $value, $boolean];
                } else {
                    // Otherwise, wrap the value in wildcards
                    $this->where[] = [$column, $operator, "%$value%", $boolean];
                }
            } else {
                $this->where[] = [$column, $operator, $value, $boolean];
            }
        }
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE
     */
    public function orWhere($column, $operator = null, $value = null) {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE IN
     */
    public function whereIn($column, array $values, $boolean = 'AND') {
        if (empty($values)) {
            return $this;
        }
        $this->where[] = [$column, 'IN', $values, $boolean, 'IN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT IN
     */
    public function whereNotIn($column, array $values, $boolean = 'AND') {
        if (empty($values)) {
            return $this;
        }
        $this->where[] = [$column, 'NOT IN', $values, $boolean, 'NOT_IN'];
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE IN
     */
    public function orWhereIn($column, array $values) {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Tambahkan kondisi OR WHERE NOT IN
     */
    public function orWhereNotIn($column, array $values) {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE BETWEEN
     */
    public function whereBetween($column, array $values, $boolean = 'AND') {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween requires exactly 2 values');
        }
        $this->where[] = [$column, 'BETWEEN', $values, $boolean, 'BETWEEN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT BETWEEN
     */
    public function whereNotBetween($column, array $values, $boolean = 'AND') {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween requires exactly 2 values');
        }
        $this->where[] = [$column, 'NOT BETWEEN', $values, $boolean, 'NOT_BETWEEN'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NULL
     */
    public function whereNull($column, $boolean = 'AND') {
        $this->where[] = [$column, 'IS NULL', null, $boolean, 'NULL'];
        return $this;
    }

    /**
     * Tambahkan kondisi WHERE NOT NULL
     */
    public function whereNotNull($column, $boolean = 'AND') {
        $this->where[] = [$column, 'IS NOT NULL', null, $boolean, 'NOT_NULL'];
        return $this;
    }

    /**
     * Tambahkan kondisi OR WHERE NULL
     */
    public function orWhereNull($column) {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Tambahkan kondisi OR WHERE NOT NULL
     */
    public function orWhereNotNull($column) {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * Tambahkan kondisi WHERE DATE
     */
    public function whereDate($column, $operator, $value, $boolean = 'AND') {
        return $this->where("DATE($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE YEAR
     */
    public function whereYear($column, $operator, $value, $boolean = 'AND') {
        return $this->where("YEAR($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE MONTH
     */
    public function whereMonth($column, $operator, $value, $boolean = 'AND') {
        return $this->where("MONTH($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan kondisi WHERE DAY
     */
    public function whereDay($column, $operator, $value, $boolean = 'AND') {
        return $this->where("DAY($column)", $operator, $value, $boolean);
    }

    /**
     * Tambahkan JOIN
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER') {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type
        ];
        return $this;
    }

    /**
     * Tambahkan LEFT JOIN
     */
    public function leftJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Tambahkan ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        // Handle array of columns
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->orderBy[] = [$col, strtoupper($direction)];
            }
        } 
        // Handle comma-separated string
        else if (strpos($column, ',') !== false) {
            $columns = array_map('trim', explode(',', $column));
            foreach ($columns as $col) {
                $this->orderBy[] = [$col, strtoupper($direction)];
            }
        } 
        // Handle single column
        else {
            $this->orderBy[] = [$column, strtoupper($direction)];
        }
        return $this;
    }

    /**
     * Tambahkan LIMIT
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Tambahkan OFFSET
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Tambahkan GROUP BY
     */
    public function groupBy($columns) {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * Tambahkan HAVING
     */
    public function having($column, $operator = null, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->having[] = [$column, $operator, $value];
        return $this;
    }

    /**
     * Eksekusi query SELECT
     */
    public function get() {
        $this->ensureConnection();
        $query = $this->buildSelectQuery();
        $stmt = $this->db->prepare($query);
        
        // Bind parameters
        $this->bindWhereValues($stmt);
        
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Handle JSON fields
        foreach ($results as &$row) {
            foreach ($row as $key => $value) {
                if (is_string($value) && $this->isJson($value)) {
                    $row[$key] = json_decode($value, true);
                }
            }
        }
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $results;
    }

    /**
     * Check if a string is valid JSON
     */
    private function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Reset query state for fresh query
     */
    private function resetQueryState() {
        $this->select = '*';
        $this->where = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->groupBy = [];
        $this->having = [];
        $this->unions = [];
        // Keep table name as it might be needed for chaining
    }

    /**
     * Ambil satu baris hasil
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        // Note: get() already calls resetQueryState()
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Ambil satu baris data terakhir
     */
    public function last() {
        $this->orderBy('id', 'DESC');
        return $this->first();
    }

    /**
     * Insert data
     */
    public function insert(array $data) {
        // Encode any array/object values as JSON
        $data = $this->encodeJsonFields($data);
        
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
        $stmt = $this->db->prepare($query);
        
        $result = $stmt->execute(array_values($data));
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Insert multiple records at once
     * @param array $data Array of associative arrays
     * @return bool
     */
    public function insertMany(array $data) {
        if (empty($data)) {
            return false;
        }
        
        // Ensure all rows have the same structure
        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        
        foreach ($data as &$row) {
            $row = $this->encodeJsonFields($row);
            // Ensure all rows have the same columns
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    $row[$column] = null;
                }
            }
        }
        
        $columnList = implode(', ', $columns);
        $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
        $values = str_repeat($placeholders . ',', count($data) - 1) . $placeholders;
        
        $query = "INSERT INTO {$this->table} ($columnList) VALUES $values";
        $stmt = $this->db->prepare($query);
        
        // Flatten the data array
        $flatValues = [];
        foreach ($data as $row) {
            foreach ($columns as $column) {
                $flatValues[] = $row[$column];
            }
        }
        
        $result = $stmt->execute($flatValues);
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Insert or update on duplicate key
     * @param array $data Data to insert
     * @param array $updateColumns Columns to update on duplicate (if empty, update all)
     * @return bool
     */
    public function upsert(array $data, array $updateColumns = []) {
        // Encode any array/object values as JSON
        $data = $this->encodeJsonFields($data);
        
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
        
        if (!empty($updateColumns)) {
            $updates = [];
            foreach ($updateColumns as $column) {
                $updates[] = "$column = VALUES($column)";
            }
            $query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
        } else {
            // Update all columns except primary key
            $updates = [];
            foreach (array_keys($data) as $column) {
                if ($column !== 'id') { // Assuming 'id' is primary key
                    $updates[] = "$column = VALUES($column)";
                }
            }
            if (!empty($updates)) {
                $query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
            }
        }
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute(array_values($data));
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Update data
     */
    public function update(array $data) {
        // Encode any array/object values as JSON
        $data = $this->encodeJsonFields($data);
        
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $set);
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($query);
        $values = array_merge(array_values($data), $this->getWhereValues());
        
        $result = $stmt->execute($values);
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Encode array/object values as JSON
     */
    private function encodeJsonFields(array $data) {
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$key] = json_encode($value);
            }
        }
        return $data;
    }

    /**
     * Delete data
     */
    public function delete() {
        $query = "DELETE FROM {$this->table}";
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($this->getWhereValues());
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Build query SELECT
     */
    private function buildSelectQuery() {
        $query = "SELECT {$this->select} FROM {$this->table}";

        // Add joins
        foreach ($this->joins as $join) {
            $query .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add where conditions
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
        }

        // Add group by
        if (!empty($this->groupBy)) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Add having
        if (!empty($this->having)) {
            $query .= ' HAVING ' . $this->buildHavingClause();
        }

        // Add order by
        if (!empty($this->orderBy)) {
            $parts = [];
            foreach ($this->orderBy as $order) {
                $parts[] = "{$order[0]} {$order[1]}";
            }
            $query .= ' ORDER BY ' . implode(', ', $parts);
        }

        // Add limit and offset
        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $query .= " OFFSET {$this->offset}";
            }
        }

        return $query;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause() {
        $conditions = [];
        $isFirst = true;
        
        foreach ($this->where as $where) {
            $column = $where[0];
            $operator = $where[1];
            $value = $where[2];
            $boolean = $where[3] ?? 'AND';
            $type = $where[4] ?? 'BASIC';
            
            $condition = '';
            
            // Add boolean operator (AND/OR) except for first condition
            if (!$isFirst) {
                $condition .= " $boolean ";
            }
            
            switch ($type) {
                case 'IN':
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $condition .= "$column IN ($placeholders)";
                    break;
                    
                case 'NOT_IN':
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $condition .= "$column NOT IN ($placeholders)";
                    break;
                    
                case 'BETWEEN':
                    $condition .= "$column BETWEEN ? AND ?";
                    break;
                    
                case 'NOT_BETWEEN':
                    $condition .= "$column NOT BETWEEN ? AND ?";
                    break;
                    
                case 'NULL':
                    $condition .= "$column IS NULL";
                    break;
                    
                case 'NOT_NULL':
                    $condition .= "$column IS NOT NULL";
                    break;
                    
                default: // BASIC
                    $condition .= "$column $operator ?";
                    break;
            }
            
            $conditions[] = $condition;
            $isFirst = false;
        }
        
        return implode('', $conditions);
    }

    /**
     * Get WHERE values for binding
     */
    private function getWhereValues() {
        $values = [];
        
        foreach ($this->where as $where) {
            $value = $where[2];
            $type = $where[4] ?? 'BASIC';
            
            switch ($type) {
                case 'IN':
                case 'NOT_IN':
                    // Flatten array values for IN/NOT IN
                    foreach ($value as $v) {
                        $values[] = $v;
                    }
                    break;
                    
                case 'BETWEEN':
                case 'NOT_BETWEEN':
                    // Add both values for BETWEEN
                    $values[] = $value[0];
                    $values[] = $value[1];
                    break;
                    
                case 'NULL':
                case 'NOT_NULL':
                    // No values needed for NULL checks
                    break;
                    
                default: // BASIC
                    if ($value !== null) {
                        $values[] = $value;
                    }
                    break;
            }
        }
        
        return $values;
    }

    /**
     * Bind WHERE values to statement
     */
    private function bindWhereValues($stmt) {
        $values = $this->getWhereValues();
        foreach ($values as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
    }

    /**
     * Build HAVING clause
     */
    private function buildHavingClause() {
        $conditions = [];
        foreach ($this->having as $having) {
            $conditions[] = "{$having[0]} {$having[1]} {$having[2]}";
        }
        return implode(' AND ', $conditions);
    }
    /**
     * Count records
     * @param string $column Column to count (default: *)
     * @return int
     */
    public function count($column = '*') {
        $originalSelect = $this->select;
        $this->select = "COUNT($column) as count";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Count records by multiple conditions
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param string $countColumn Column to count (default: '*') or 'percentage' for percentage calculation
     * @return array Associative array with condition as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countByConditions('status', ['on', 'off', 'pending']);
     * // Returns: ['on' => 75, 'off' => 25, 'pending' => 10]
     * 
     * $counts = $db->table('orders')->countByConditions('status', ['completed', 'pending'], 'id');
     * // Returns: ['completed' => 150, 'pending' => 45]
     * 
     * $counts = $db->table('orders')->countByConditions('status', ['completed', 'pending'], 'percentage');
     * // Returns: ['completed' => 150, 'pending' => 45, 'percompleted' => '77%', 'perpending' => '23%']
     */
    public function countByConditions($column, array $conditions, $countColumn = '*') {
        if (empty($conditions)) {
            return [];
        }

        $includePercentage = ($countColumn === 'percentage');
        if ($includePercentage) {
            $countColumn = '*'; // Use default count for percentage calculation
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        foreach ($conditions as $condition) {
            $caseStatements[] = "SUM(CASE WHEN $column = ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
        }

        // Add total count for percentage calculation
        if ($includePercentage) {
            $caseStatements[] = "COUNT($countColumn) as total_count";
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($conditions as $condition) {
            $stmt->bindValue($bindIndex++, $condition);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $counts = [];
        if ($result) {
            $totalCount = $includePercentage ? (int)($result['total_count'] ?? 0) : 0;
            
            foreach ($conditions as $condition) {
                $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
                $count = (int)($result[$key] ?? 0);
                $counts[$condition] = $count;
                
                // Add percentage if requested
                if ($includePercentage && $totalCount > 0) {
                    $percentage = round(($count / $totalCount) * 100, 1);
                    $counts['per' . $condition] = $percentage . '%';
                }
            }
        }
        
        return $counts;
    }

    /**
     * Count records by conditions with percentage calculation
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @return array Associative array with counts and percentages
     * 
     * Example:
     * $result = $db->table('orders')->countWithPercentage('status', ['completed', 'pending']);
     * // Returns: [
     * //   'completed' => 150, 
     * //   'pending' => 45, 
     * //   'percompleted' => '77%', 
     * //   'perpending' => '23%',
     * //   'total' => 195
     * // ]
     */
    public function countWithPercentage($column, array $conditions) {
        return $this->countByConditions($column, $conditions, 'percentage');
    }

    /**
     * Get only percentage values for conditions
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param int $decimals Number of decimal places (default: 1)
     * @return array Associative array with condition as key and percentage as value
     * 
     * Example:
     * $percentages = $db->table('orders')->getPercentages('status', ['completed', 'pending']);
     * // Returns: ['completed' => '77%', 'pending' => '23%']
     * 
     * $percentages = $db->table('orders')->getPercentages('status', ['completed', 'pending'], 2);
     * // Returns: ['completed' => '76.92%', 'pending' => '23.08%']
     */
    public function getPercentages($column, array $conditions, $decimals = 1) {
        if (empty($conditions)) {
            return [];
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        foreach ($conditions as $condition) {
            $caseStatements[] = "SUM(CASE WHEN $column = ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
        }
        
        // Add total count
        $caseStatements[] = "COUNT(*) as total_count";

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($conditions as $condition) {
            $stmt->bindValue($bindIndex++, $condition);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result - only percentages
        $percentages = [];
        if ($result) {
            $totalCount = (int)($result['total_count'] ?? 0);
            
            if ($totalCount > 0) {
                foreach ($conditions as $condition) {
                    $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $condition);
                    $count = (int)($result[$key] ?? 0);
                    $percentage = round(($count / $totalCount) * 100, $decimals);
                    $percentages[$condition] = $percentage . '%';
                }
            }
        }
        
        return $percentages;
    }

    /**
     * Count records grouped by a specific column
     * @param string $groupColumn Column to group by
     * @param string $countColumn Column to count (default: *)
     * @param array $filterValues Optional array to filter specific values only
     * @return array Associative array with group value as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countByGroup('status');
     * // Returns: ['active' => 100, 'inactive' => 50, 'pending' => 25]
     * 
     * $counts = $db->table('users')->countByGroup('status', '*', ['active', 'inactive']);
     * // Returns: ['active' => 100, 'inactive' => 50]
     */
    public function countByGroup($groupColumn, $countColumn = '*', array $filterValues = []) {
        $originalSelect = $this->select;
        $originalGroupBy = $this->groupBy;
        
        $this->select = "$groupColumn, COUNT($countColumn) as count";
        $this->groupBy = [$groupColumn];
        
        // Add filter for specific values if provided
        if (!empty($filterValues)) {
            $this->whereIn($groupColumn, $filterValues);
        }
        
        $results = $this->get();
        
        // Restore original state
        $this->select = $originalSelect;
        $this->groupBy = $originalGroupBy;
        
        // Format result
        $counts = [];
        foreach ($results as $row) {
            $counts[$row[$groupColumn]] = (int)$row['count'];
        }
        
        return $counts;
    }

    /**
     * Count records with multiple WHERE conditions in one query
     * @param array $whereConditions Array of where conditions
     * @param string $countColumn Column to count (default: *)
     * @return array Associative array with condition label as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->countMultipleWhere([
     *     'active_users' => ['status', '=', 'active'],
     *     'premium_users' => ['plan', '=', 'premium'],
     *     'recent_users' => ['created_at', '>=', '2024-01-01']
     * ]);
     * // Returns: ['active_users' => 150, 'premium_users' => 75, 'recent_users' => 200]
     */
    public function countMultipleWhere(array $whereConditions, $countColumn = '*') {
        if (empty($whereConditions)) {
            return [];
        }

        // Build CASE WHEN statements for each condition
        $caseStatements = [];
        $bindings = [];
        
        foreach ($whereConditions as $label => $condition) {
            $column = $condition[0];
            $operator = $condition[1];
            $value = $condition[2];
            
            $caseStatements[] = "SUM(CASE WHEN $column $operator ? THEN 1 ELSE 0 END) as count_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $label);
            $bindings[] = $value;
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $caseStatements);

        // Build query
        $query = $this->buildSelectQuery();
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind condition values
        $bindIndex = 1;
        foreach ($bindings as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        // Bind existing where values
        $whereValues = $this->getWhereValues();
        foreach ($whereValues as $value) {
            $stmt->bindValue($bindIndex++, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $counts = [];
        if ($result) {
            foreach ($whereConditions as $label => $condition) {
                $key = 'count_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $label);
                $counts[$label] = (int)($result[$key] ?? 0);
            }
        }
        
        return $counts;
    }

    /**
     * Quick count for common status-based conditions
     * @param string $statusColumn Column name for status (default: 'status')
     * @param array $statusValues Array of status values to count
     * @return array Associative array with status as key and count as value
     * 
     * Example:
     * $counts = $db->table('users')->quickStatusCount();
     * // Returns: ['active' => 100, 'inactive' => 50] (default status values)
     * 
     * $counts = $db->table('orders')->quickStatusCount('order_status', ['pending', 'completed', 'cancelled']);
     * // Returns: ['pending' => 25, 'completed' => 150, 'cancelled' => 10]
     */
    public function quickStatusCount($statusColumn = 'status', array $statusValues = ['active', 'inactive']) {
        return $this->countByConditions($statusColumn, $statusValues);
    }

    /**
     * Count records by multiple columns (aggregate functions)
     * @param array $columns Array of columns to count/sum
     * @param string $operation Operation to perform: 'count', 'sum', 'avg', 'max', 'min', 'percentage' (default: 'count')
     * @param bool $includePercentage Whether to include percentage calculation for sum operations
     * @return array Associative array with column name as key and result as value
     * 
     * Example:
     * $counts = $db->table('posts')->countByColumn(['views', 'shares', 'likes']);
     * // Returns: ['views' => 1500, 'shares' => 250, 'likes' => 890] (COUNT of non-null values)
     * 
     * $sums = $db->table('posts')->countByColumn(['views', 'shares'], 'sum');
     * // Returns: ['views' => 15000, 'shares' => 2500] (SUM of all values)
     * 
     * $averages = $db->table('products')->countByColumn(['price', 'rating'], 'avg');
     * // Returns: ['price' => 25.50, 'rating' => 4.2] (AVERAGE values)
     * 
     * $sumsWithPercentage = $db->table('posts')->countByColumn(['views', 'shares'], 'sum', true);
     * // Returns: ['views' => 15000, 'shares' => 2500, 'perviews' => '85.7%', 'pershares' => '14.3%']
     * 
     * $percentageOnly = $db->table('posts')->countByColumn(['views', 'shares'], 'percentage');
     * // Returns: ['views' => '85.7%', 'shares' => '14.3%'] (percentage of total sum)
     */
    public function countByColumn(array $columns, $operation = 'count', $includePercentage = false) {
        if (empty($columns)) {
            return [];
        }

        $validOperations = ['count', 'sum', 'avg', 'max', 'min', 'percentage'];
        $operation = strtolower($operation);
        
        if (!in_array($operation, $validOperations)) {
            throw new \InvalidArgumentException("Invalid operation. Allowed: " . implode(', ', $validOperations));
        }

        // Handle percentage operation
        $isPercentageOnly = ($operation === 'percentage');
        if ($isPercentageOnly) {
            $operation = 'sum'; // Use sum for percentage calculation
            $includePercentage = true;
        }

        // Build aggregate statements for each column
        $aggregateStatements = [];
        foreach ($columns as $column) {
            $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
            
            switch ($operation) {
                case 'count':
                    // Count non-null values
                    $aggregateStatements[] = "COUNT($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'sum':
                    $aggregateStatements[] = "SUM($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'avg':
                    $aggregateStatements[] = "AVG($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'max':
                    $aggregateStatements[] = "MAX($column) as {$operation}_{$cleanColumn}";
                    break;
                case 'min':
                    $aggregateStatements[] = "MIN($column) as {$operation}_{$cleanColumn}";
                    break;
            }
        }

        // Add total sum for percentage calculation
        if ($includePercentage && $operation === 'sum') {
            $totalSumColumns = [];
            foreach ($columns as $column) {
                $totalSumColumns[] = "COALESCE($column, 0)";
            }
            $aggregateStatements[] = "SUM(" . implode(' + ', $totalSumColumns) . ") as total_sum";
        }

        $originalSelect = $this->select;
        $this->select = implode(', ', $aggregateStatements);

        // Execute query
        $result = $this->first();
        
        // Restore original select
        $this->select = $originalSelect;
        
        // Format result
        $results = [];
        if ($result) {
            $totalSum = 0;
            if ($includePercentage && $operation === 'sum') {
                $totalSum = (float)($result['total_sum'] ?? 0);
            }

            foreach ($columns as $column) {
                $cleanColumn = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
                $key = "{$operation}_{$cleanColumn}";
                
                $value = $result[$key] ?? 0;
                
                // Convert to appropriate type based on operation
                switch ($operation) {
                    case 'count':
                        $columnValue = (int)$value;
                        break;
                    case 'sum':
                        $columnValue = is_numeric($value) ? (float)$value : 0;
                        break;
                    case 'avg':
                        $columnValue = is_numeric($value) ? round((float)$value, 2) : 0;
                        break;
                    case 'max':
                    case 'min':
                        $columnValue = $value;
                        break;
                }

                // Add main value (unless percentage only)
                if (!$isPercentageOnly) {
                    $results[$column] = $columnValue;
                }
                
                // Add percentage if requested
                if ($includePercentage && $operation === 'sum' && $totalSum > 0) {
                    $percentage = round(($columnValue / $totalSum) * 100, 1);
                    $results[$isPercentageOnly ? $column : 'per' . $column] = $percentage . '%';
                }
            }
        }
        
        return $results;
    }

    /**
     * Count non-null values for multiple columns (shortcut for countByColumn with 'count')
     * @param array $columns Array of columns to count non-null values
     * @return array Associative array with column name as key and count as value
     * 
     * Example:
     * $counts = $db->table('posts')->countColumns(['views', 'shares', 'likes']);
     * // Returns: ['views' => 150, 'shares' => 120, 'likes' => 180] (count of non-null values)
     */
    public function countColumns(array $columns) {
        return $this->countByColumn($columns, 'count');
    }

    /**
     * Sum values for multiple columns (shortcut for countByColumn with 'sum')
     * @param array $columns Array of columns to sum
     * @return array Associative array with column name as key and sum as value
     * 
     * Example:
     * $sums = $db->table('posts')->sumColumns(['views', 'shares', 'likes']);
     * // Returns: ['views' => 15000, 'shares' => 2500, 'likes' => 8900] (sum of all values)
     */
    public function sumColumns(array $columns) {
        return $this->countByColumn($columns, 'sum');
    }

    /**
     * Get average values for multiple columns (shortcut for countByColumn with 'avg')
     * @param array $columns Array of columns to average
     * @return array Associative array with column name as key and average as value
     * 
     * Example:
     * $averages = $db->table('products')->avgColumns(['price', 'rating', 'discount']);
     * // Returns: ['price' => 25.50, 'rating' => 4.2, 'discount' => 15.75] (average values)
     */
    public function avgColumns(array $columns) {
        return $this->countByColumn($columns, 'avg');
    }

    /**
     * Sum values of a column
     * @param string $column
     * @return float|int
     */
    public function sum($column) {
        $originalSelect = $this->select;
        $this->select = "SUM($column) as sum";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? ($result['sum'] ?? 0) : 0;
    }

    /**
     * Average value of a column
     * @param string $column
     * @return float
     */
    public function avg($column) {
        $originalSelect = $this->select;
        $this->select = "AVG($column) as avg";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? (float)($result['avg'] ?? 0) : 0;
    }

    /**
     * Maximum value of a column
     * @param string $column
     * @return mixed
     */
    public function max($column) {
        $originalSelect = $this->select;
        $this->select = "MAX($column) as max";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? $result['max'] : null;
    }

    /**
     * Minimum value of a column
     * @param string $column
     * @return mixed
     */
    public function min($column) {
        $originalSelect = $this->select;
        $this->select = "MIN($column) as min";
        
        $result = $this->first();
        $this->select = $originalSelect; // Restore original select
        
        return $result ? $result['min'] : null;
    }

    /**
     * Fungsi untuk mendapatkan data dengan pagination (legacy method)
     * @param int $page Halaman saat ini
     * @param int $perPage Jumlah item per halaman
     * @return array ['data' => array, 'total' => int, 'last_page' => int, 'current_page' => int]
     */
    public function countPaginate($page = 1, $perPage = 10) {
        // Hitung total data
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($this->where)) {
            $countQuery .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($countQuery);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Hitung last page
        $lastPage = ceil($total / $perPage);
        
        // Set limit dan offset untuk pagination
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        
        // Ambil data
        $data = $this->get();
        
        return [
            'data' => $data,
            'total' => (int) $total,
            'last_page' => $lastPage,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage
        ];
    }
    /**
     * Fungsi untuk mendapatkan data dengan pagination
     * @param int $page Halaman saat ini
     * @param int $perPage Jumlah item per halaman
     * @return array ['data' => array, 'total' => int, 'last_page' => int, 'current_page' => int]
     */
    public function paginate($page = 1, $perPage = 10) {
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($this->where)) {
            $countQuery .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->db->prepare($countQuery);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

        $lastPage = ceil($total / $perPage);
        
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        
        $data = $this->get();
        
        return [
            'data' => $data,
            'total' => (int) $total,
            'last_page' => $lastPage,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage
        ];
    }

    /**
     * Execute raw SQL query
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function raw($sql, array $bindings = []) {
        $this->ensureConnection();
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the SQL query string (for debugging)
     * @return string
     */
    public function toSql() {
        return $this->buildSelectQuery();
    }

    /**
     * Get the bindings array (for debugging)
     * @return array
     */
    public function getBindings() {
        return $this->getWhereValues();
    }

    /**
     * Execute transaction
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function transaction($callback) {
        $this->ensureConnection();
        $this->db->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Check if record exists
     * @return bool
     */
    public function exists() {
        return $this->count() > 0;
    }

    /**
     * Get first record or throw exception if not found
     * @return array
     * @throws Exception
     */
    public function firstOrFail() {
        $result = $this->first();
        if (!$result) {
            throw new \Exception("No records found");
        }
        return $result;
    }

    /**
     * Get value of a single column
     * @param string $column
     * @return mixed
     */
    public function value($column) {
        $result = $this->first([$column]);
        return $result ? $result[$column] : null;
    }

    /**
     * Get array of values from a single column
     * @param string $column
     * @param string|null $key Optional key column
     * @return array
     */
    public function pluck($column, $key = null) {
        $columns = [$column];
        if ($key) {
            $columns[] = $key;
        }
        
        $results = $this->select($columns)->get();
        
        if ($key) {
            $plucked = [];
            foreach ($results as $result) {
                $plucked[$result[$key]] = $result[$column];
            }
            return $plucked;
        }
        
        return array_column($results, $column);
    }

    /**
     * Add ORDER BY RAND() for random ordering
     * @return $this
     */
    public function inRandomOrder() {
        return $this->orderBy('RAND()');
    }

    /**
     * Order by latest (descending by created_at or specified column)
     * @param string $column
     * @return $this
     */
    public function latest($column = 'created_at') {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by oldest (ascending by created_at or specified column)
     * @param string $column
     * @return $this
     */
    public function oldest($column = 'created_at') {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Alias for limit()
     * @param int $value
     * @return $this
     */
    public function take($value) {
        return $this->limit($value);
    }

    /**
     * Alias for offset()
     * @param int $value
     * @return $this
     */
    public function skip($value) {
        return $this->offset($value);
    }

    /**
     * Get distinct values
     * @param string|array $columns
     * @return $this
     */
    public function distinct($columns = null) {
        if ($columns) {
            $columns = is_array($columns) ? implode(', ', $columns) : $columns;
            $this->select = "DISTINCT $columns";
        } else {
            $this->select = "DISTINCT " . $this->select;
        }
        return $this;
    }

    /**
     * Add UNION query
     * @param NexaBrif $query
     * @param bool $all
     * @return $this
     */
    public function union($query, $all = false) {
        // This is a simplified implementation
        // In a full implementation, you'd need to handle this in buildSelectQuery()
        $unionType = $all ? 'UNION ALL' : 'UNION';
        $this->unions[] = ['query' => $query, 'type' => $unionType];
        return $this;
    }

    /**
     * Add UNION ALL query
     * @param NexaBrif $query
     * @return $this
     */
    public function unionAll($query) {
        return $this->union($query, true);
    }

    /**
     * Increment a column value
     * @param string $column
     * @param int $amount
     * @param array $extra Additional columns to update
     * @return bool
     */
    public function increment($column, $amount = 1, array $extra = []) {
        $updates = ["$column = $column + ?"];
        $bindings = [$amount];
        
        foreach ($extra as $key => $value) {
            $updates[] = "$key = ?";
            $bindings[] = $value;
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $updates);
        if (!empty($this->where)) {
            $query .= ' WHERE ' . $this->buildWhereClause();
            $bindings = array_merge($bindings, $this->getWhereValues());
        }
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute($bindings);
        
        // Reset query state after execution
        $this->resetQueryState();
        
        return $result;
    }

    /**
     * Decrement a column value
     * @param string $column
     * @param int $amount
     * @param array $extra Additional columns to update
     * @return bool
     */
    public function decrement($column, $amount = 1, array $extra = []) {
        return $this->increment($column, -$amount, $extra);
    }

    /**
     * Debug: Print the SQL query and bindings
     * @return $this
     */
    public function dd() {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        die();
    }

    /**
     * Debug: Dump the SQL query and bindings without stopping execution
     * @return $this
     */
    public function dump() {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindings()) . "\n";
        return $this;
    }

    /**
     * Sum values for multiple columns with percentage calculation
     * @param array $columns Array of columns to sum with percentage
     * @return array Associative array with column sums and percentages
     * 
     * Example:
     * $result = $db->table('posts')->sumColumnsWithPercentage(['views', 'shares', 'likes']);
     * // Returns: [
     * //   'views' => 15000, 'shares' => 2500, 'likes' => 8900,
     * //   'perviews' => '57.7%', 'pershares' => '9.6%', 'perlikes' => '32.7%'
     * // ]
     */
    public function sumColumnsWithPercentage(array $columns) {
        return $this->countByColumn($columns, 'sum', true);
    }

    /**
     * Get percentage distribution for multiple columns (based on sum)
     * @param array $columns Array of columns to calculate percentage
     * @return array Associative array with column name as key and percentage as value
     * 
     * Example:
     * $percentages = $db->table('posts')->getColumnPercentages(['views', 'shares', 'likes']);
     * // Returns: ['views' => '57.7%', 'shares' => '9.6%', 'likes' => '32.7%']
     */
    public function getColumnPercentages(array $columns) {
        return $this->countByColumn($columns, 'percentage');
    }

    /**
     * Get list of all tables in the database
     * @return array
     */
    public function showTables() {
        $this->ensureConnection();
        $stmt = $this->db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_NUM);
        
        $tableNames = [];
        foreach ($tables as $table) {
            $tableNames[] = $table[0];
        }
        
        return $tableNames;
    }

    /**
     * Get detailed information about all tables
     * @return array
     */
    public function getTablesInfo() {
        $this->ensureConnection();
        $query = "
            SELECT 
                TABLE_NAME as name,
                TABLE_ROWS as rows,
                DATA_LENGTH as data_length,
                INDEX_LENGTH as index_length,
                TABLE_COMMENT as comment,
                ENGINE as engine,
                TABLE_COLLATION as collation
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY TABLE_NAME
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get columns information for a specific table
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName) {
        $this->ensureConnection();
        $query = "
            SELECT 
                COLUMN_NAME as name,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                COLUMN_KEY as key_type,
                EXTRA as extra,
                COLUMN_COMMENT as comment
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$tableName]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a table exists
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        $this->ensureConnection();
        $query = "
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$tableName]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int)$result['count'] > 0;
    }

    /**
     * Get database information
     * @return array
     */
    public function getDatabaseInfo() {
        $this->ensureConnection();
        $info = [
            'database_name' => '',
            'total_tables' => 0,
            'database_size' => 0,
            'charset' => '',
            'collation' => ''
        ];
        
        // Get database name
        $stmt = $this->db->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $info['database_name'] = $result['db_name'];
        
        // Get database info
        $query = "
            SELECT 
                COUNT(*) as table_count,
                SUM(DATA_LENGTH + INDEX_LENGTH) as total_size,
                DEFAULT_CHARACTER_SET_NAME as charset,
                DEFAULT_COLLATION_NAME as collation
            FROM INFORMATION_SCHEMA.TABLES t
            JOIN INFORMATION_SCHEMA.SCHEMATA s ON t.TABLE_SCHEMA = s.SCHEMA_NAME
            WHERE t.TABLE_SCHEMA = DATABASE()
        ";
        
        $stmt = $this->db->query($query);
        $dbInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $info['total_tables'] = (int)$dbInfo['table_count'];
        $info['database_size'] = (int)$dbInfo['total_size'];
        $info['charset'] = $dbInfo['charset'];
        $info['collation'] = $dbInfo['collation'];
        
        return $info;
    }

    /**
     * Format bytes into human readable format
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $base = log($bytes, 1024);
        $index = floor($base);
        
        if ($index >= count($units)) {
            $index = count($units) - 1;
        }
        
        $size = round(pow(1024, $base - $index), $precision);
        return $size . ' ' . $units[$index];
    }

    /**
     * Display query results in formatted table using NexaRaw
     * @param bool $withPre Whether to include HTML pre tags
     * @return $this
     */
    public function render($withPre = false) {
        $results = $this->get();
        $renderer = new NexaRaw($results);
        $renderer->render($withPre);
        return $this;
    }

    /**
     * Display tables list in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTablesFormatted($withPre = false) {
        $tablesInfo = $this->getTablesInfo();
        $renderer = new NexaRaw($tablesInfo);
        $renderer->render($withPre);
    }

    /**
     * Display simple tables list in formatted table with data count and size
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTablesListFormatted($withPre = false) {
        $tablesInfo = $this->getTablesInfo();
        $formattedTables = [];
        foreach ($tablesInfo as $index => $tableInfo) {
            $sizeInBytes = ($tableInfo['data_length'] ?? 0) + ($tableInfo['index_length'] ?? 0);
            $formattedTables[] = [
                'No' => $index + 1,
                'Table Name' => $tableInfo['name'],
                'Rows' => number_format($tableInfo['rows'] ?? 0),
                'Size' => $this->formatBytes($sizeInBytes),
                'Engine' => $tableInfo['engine'] ?? 'Unknown'
            ];
        }
        $renderer = new NexaRaw($formattedTables);
        $renderer->render($withPre);
    }

    /**
     * Display table columns in formatted table
     * @param string $tableName
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showTableColumnsFormatted($tableName, $withPre = false) {
        $columns = $this->getTableColumns($tableName);
        $renderer = new NexaRaw($columns);
        $renderer->render($withPre);
    }

    /**
     * Display database information in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return void
     */
    public function showDatabaseInfoFormatted($withPre = false) {
        $info = $this->getDatabaseInfo();
        $formattedInfo = [
            [
                'Property' => 'Database Name',
                'Value' => $info['database_name']
            ],
            [
                'Property' => 'Total Tables',
                'Value' => $info['total_tables']
            ],
            [
                'Property' => 'Database Size',
                'Value' => $this->formatBytes($info['database_size'])
            ],
            [
                'Property' => 'Charset',
                'Value' => $info['charset']
            ],
            [
                'Property' => 'Collation',
                'Value' => $info['collation']
            ]
        ];
        $renderer = new NexaRaw($formattedInfo);
        $renderer->render($withPre);
    }

    /**
     * Execute query and display results in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The query results
     */
    public function getAndDisplay($withPre = false) {
        $results = $this->get();
        $renderer = new NexaRaw($results);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Get first record and display in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @return array|null The first record
     */
    public function firstAndDisplay($withPre = false) {
        $result = $this->first();
        if ($result) {
            $renderer = new NexaRaw([$result]);
            $renderer->render($withPre);
        } else {
            echo $withPre ? "<pre>(no data found)</pre>" : "(no data found)\n";
        }
        return $result;
    }

    /**
     * Count records and display result in formatted table
     * @param bool $withPre Whether to include HTML pre tags
     * @param string $column Column to count
     * @return int The count result
     */
    public function countAndDisplay($withPre = false, $column = '*') {
        $count = $this->count($column);
        $result = [['Count' => $count]];
        $renderer = new NexaRaw($result);
        $renderer->render($withPre);
        return $count;
    }

    /**
     * Display count by conditions in formatted table
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The count results
     */
    public function countByConditionsAndDisplay($column, array $conditions, $withPre = false) {
        $counts = $this->countByConditions($column, $conditions);
        $formattedCounts = [];
        foreach ($counts as $condition => $count) {
            $formattedCounts[] = [
                'Condition' => $condition,
                'Count' => $count
            ];
        }
        $renderer = new NexaRaw($formattedCounts);
        $renderer->render($withPre);
        return $counts;
    }

    /**
     * Display count with percentage in formatted table
     * @param string $column Column to check conditions against
     * @param array $conditions Array of conditions to count
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The count and percentage results
     */
    public function countWithPercentageAndDisplay($column, array $conditions, $withPre = false) {
        $results = $this->countWithPercentage($column, $conditions);
        $formattedResults = [];
        
        foreach ($conditions as $condition) {
            $count = $results[$condition] ?? 0;
            $percentage = $results['per' . $condition] ?? '0%';
            $formattedResults[] = [
                'Condition' => $condition,
                'Count' => $count,
                'Percentage' => $percentage
            ];
        }
        
        $renderer = new NexaRaw($formattedResults);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Display aggregate results (sum, avg, etc.) in formatted table
     * @param array $columns Array of columns to aggregate
     * @param string $operation Operation to perform
     * @param bool $withPre Whether to include HTML pre tags
     * @return array The aggregate results
     */
    public function aggregateAndDisplay(array $columns, $operation = 'sum', $withPre = false) {
        $results = $this->countByColumn($columns, $operation);
        $formattedResults = [];
        
        foreach ($results as $column => $value) {
            $formattedResults[] = [
                'Column' => $column,
                ucfirst($operation) => $value
            ];
        }
        
        $renderer = new NexaRaw($formattedResults);
        $renderer->render($withPre);
        return $results;
    }

    /**
     * Full database explorer with formatted display
     * @param bool $withPre Whether to include HTML pre tags
     * @param bool $showColumns Whether to show columns for each table
     * @return void
     */
    public function exploreDatabase($withPre = false, $showColumns = false) {
        if ($withPre) echo "<h3>Database Explorer</h3>";
        else echo "=== DATABASE EXPLORER ===\n\n";
        
        // Show database info
        if ($withPre) echo "<h4>Database Information:</h4>";
        else echo "Database Information:\n";
        $this->showDatabaseInfoFormatted($withPre);
        
        if ($withPre) echo "<br><h4>Tables Overview:</h4>";
        else echo "\nTables Overview:\n";
        $this->showTablesFormatted($withPre);
        
        if ($showColumns) {
            $tables = $this->showTables();
            foreach ($tables as $tableName) {
                if ($withPre) echo "<br><h4>Columns in '$tableName':</h4>";
                else echo "\nColumns in '$tableName':\n";
                $this->showTableColumnsFormatted($tableName, $withPre);
            }
        }
    }
}